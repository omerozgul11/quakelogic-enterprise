<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ProposalFile;
use App\Models\ProposalSubmission;
use App\Services\Documents\DocumentTextExtractionService;
use App\Services\Proposals\ProposalIntakeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DocumentController extends Controller
{
    public function __construct(private readonly ProposalIntakeService $intake) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $files = ProposalFile::whereHas('proposal', fn($q) => $q->where('organization_id', $user->organization_id))
            ->with(['proposal:id,proposal_number,project_name', 'uploadedBy:id,name'])
            ->orderByDesc('created_at')
            ->paginate(25);

        $files->getCollection()->transform(fn ($f) => [
            'id' => $f->id,
            'display_name' => $f->display_name,
            'document_type' => $f->document_type,
            'file_size_formatted' => $f->file_size_formatted,
            'mime_type' => $f->mime_type,
            'version' => $f->version,
            'created_at' => $f->created_at?->toIso8601String(),
            'uploaded_by_user' => $f->uploadedBy ? ['id' => $f->uploadedBy->id, 'name' => $f->uploadedBy->name] : null,
            'proposal' => $f->proposal ? ['id' => $f->proposal->id, 'proposal_number' => $f->proposal->proposal_number, 'project_name' => $f->proposal->project_name] : null,
        ]);

        return Inertia::render('Documents/Index', ['files' => $files]);
    }

    public function storeProposalFile(Request $request, ProposalSubmission $proposalSubmission): RedirectResponse
    {
        $this->authorize('update', $proposalSubmission);

        $request->validate([
            // Security: only PDF and image files are accepted. Office/text formats
            // and archives are rejected. Validate by sniffed content type
            // (mimetypes) and extension (mimes) for defense in depth.
            'file' => 'required|file|max:102400|mimetypes:application/pdf,image/jpeg,image/png|mimes:pdf,jpg,jpeg,png',
            'document_type' => 'nullable|string|max:100',
            'extract' => 'nullable|boolean',
        ]);

        $file = $request->file('file');

        // PDFs carry extractable text; images don't (no OCR), so only PDFs are
        // sent to QuakeAI. Everything else is stored as a plain attachment.
        $parseable = strtolower($file->getClientOriginalExtension()) === 'pdf';
        $wantsExtract = $request->boolean('extract', true);

        if ($parseable && $wantsExtract) {
            $analysis = $this->intake->extract($proposalSubmission, $file, $request->user());
            if ($analysis) {
                $summary = $this->intake->autoApply($proposalSubmission->fresh(), $analysis, $request->user(), fillBlanksOnly: true);
                $records = count($summary['created'] ?? []) + count($summary['linked'] ?? []);
                return back()->with('success', 'Document read by QuakeAI — ' . count($summary['fields'] ?? []) . ' field(s) filled, ' . $records . ' record(s) created/linked.');
            }
            return back()->with('warning', 'Document uploaded, but no readable text was found.');
        }

        $storedName = Str::ulid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs("proposals/{$proposalSubmission->id}", $storedName, 'local');

        ProposalFile::create([
            'ulid' => (string) Str::ulid(),
            'proposal_submission_id' => $proposalSubmission->id,
            'uploaded_by' => $request->user()->id,
            'display_name' => $file->getClientOriginalName(),
            'original_filename' => $file->getClientOriginalName(),
            'stored_filename' => $storedName,
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
            'document_type' => $request->input('document_type'),
            'status' => 'uploaded',
            'version' => 1,
            'is_current_version' => true,
        ]);

        return back()->with('success', 'File uploaded successfully.');
    }

    public function previewProposalFile(Request $request, ProposalSubmission $proposalSubmission, ProposalFile $file): mixed
    {
        $this->authorize('view', $proposalSubmission);
        abort_unless($file->proposal_submission_id === $proposalSubmission->id, 404);

        if (!Storage::disk($file->disk)->exists($file->path)) {
            abort(404, 'File not found in storage.');
        }

        $mime = (string) $file->mime_type;
        $nativelyRenderable = str_contains($mime, 'pdf')
            || str_starts_with($mime, 'image/')
            || str_starts_with($mime, 'text/');

        // PDFs, images and plain text render natively in the browser/iframe.
        if ($nativelyRenderable) {
            return Storage::disk($file->disk)->response($file->path, $file->display_name, [
                'Content-Type' => $file->mime_type,
                'Content-Disposition' => 'inline; filename="' . addslashes($file->display_name) . '"',
            ]);
        }

        // Word and other office documents can't be shown inline by browsers, so
        // render the extracted text as a clean, readable HTML preview instead.
        $text = '';
        try {
            $text = app(DocumentTextExtractionService::class)->extract($file->path, $file->mime_type);
        } catch (\Throwable) {
            $text = '';
        }

        return response($this->textPreviewHtml($file->display_name, $text), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'inline',
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);
    }

    private function textPreviewHtml(string $name, string $text): string
    {
        $safeName = e($name);
        $download = url()->current() . '/../download';

        if (trim($text) === '') {
            $body = '<div class="empty"><p>No preview is available for this file type.</p>'
                . '<p class="muted">Download it to view the original.</p></div>';
        } else {
            $body = '<pre>' . e($text) . '</pre>';
        }

        return <<<HTML
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$safeName}</title>
<style>
  :root { color-scheme: light dark; }
  * { box-sizing: border-box; }
  body { margin: 0; background: #f8fafc; color: #0f172a; font: 14px/1.6 ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, sans-serif; }
  .bar { position: sticky; top: 0; display: flex; align-items: center; gap: 8px; padding: 10px 16px; background: #fff; border-bottom: 1px solid #e2e8f0; }
  .bar .badge { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: #b45309; background: #fff7ed; border: 1px solid #fed7aa; border-radius: 999px; padding: 2px 8px; }
  .bar .name { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .wrap { max-width: 820px; margin: 0 auto; padding: 24px 28px; }
  .sheet { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 28px 32px; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
  pre { white-space: pre-wrap; word-wrap: break-word; font: 13.5px/1.7 ui-monospace, SFMono-Regular, Menlo, monospace; margin: 0; }
  .empty { text-align: center; padding: 48px 0; }
  .muted { color: #64748b; font-size: 13px; }
  @media (prefers-color-scheme: dark) {
    body { background: #0b1220; color: #e2e8f0; }
    .bar { background: #0f172a; border-color: #1e293b; }
    .sheet { background: #0f172a; border-color: #1e293b; }
  }
</style></head>
<body>
  <div class="bar"><span class="badge">Text preview</span><span class="name">{$safeName}</span></div>
  <div class="wrap"><div class="sheet">{$body}</div></div>
</body></html>
HTML;
    }

    public function downloadProposalFile(Request $request, ProposalSubmission $proposalSubmission, ProposalFile $file): mixed
    {
        $this->authorize('view', $proposalSubmission);
        abort_unless($file->proposal_submission_id === $proposalSubmission->id, 404);

        if (!Storage::disk($file->disk)->exists($file->path)) {
            abort(404, 'File not found in storage.');
        }

        return Storage::disk($file->disk)->download($file->path, $file->display_name);
    }

    public function destroyProposalFile(Request $request, ProposalSubmission $proposalSubmission, ProposalFile $file): RedirectResponse
    {
        $this->authorize('update', $proposalSubmission);
        abort_unless($file->proposal_submission_id === $proposalSubmission->id, 403);

        Storage::disk($file->disk)->delete($file->path);
        $file->delete();

        return back()->with('success', 'File deleted.');
    }
}
