<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Opportunity;
use App\Models\ProposalFile;
use App\Models\ProposalSubmission;
use App\Services\BidSources\OpportunityDocumentService;
use App\Services\Documents\DocumentTextExtractionService;
use App\Services\Proposals\ProposalIntakeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
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

        // Multi-file drag-and-drop: store every dropped file as a plain
        // attachment (no AI auto-fill — that's the single-file "read" path below).
        if ($request->hasFile('files')) {
            $request->validate([
                'files' => 'required|array|max:25',
                'files.*' => 'file|max:102400|mimetypes:application/pdf,image/jpeg,image/png|mimes:pdf,jpg,jpeg,png',
                'document_type' => 'nullable|string|max:100',
            ]);

            $count = 0;
            foreach ($request->file('files') as $dropped) {
                $this->persistProposalFile($proposalSubmission, $dropped, $request->input('document_type'), $request->user()->id);
                $count++;
            }

            return back()->with('success', $count . ' file' . ($count === 1 ? '' : 's') . ' uploaded.');
        }

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

        $this->persistProposalFile($proposalSubmission, $file, $request->input('document_type'), $request->user()->id);

        return back()->with('success', 'File uploaded successfully.');
    }

    /** Store one uploaded file on the private disk and record it as a ProposalFile. */
    private function persistProposalFile(ProposalSubmission $proposalSubmission, \Illuminate\Http\UploadedFile $file, ?string $documentType, int $userId): ProposalFile
    {
        $storedName = Str::ulid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs("proposals/{$proposalSubmission->id}", $storedName, 'local');

        return ProposalFile::create([
            'ulid' => (string) Str::ulid(),
            'proposal_submission_id' => $proposalSubmission->id,
            'uploaded_by' => $userId,
            'display_name' => $file->getClientOriginalName(),
            'original_filename' => $file->getClientOriginalName(),
            'stored_filename' => $storedName,
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
            'document_type' => $documentType,
            'status' => 'uploaded',
            'version' => 1,
            'is_current_version' => true,
        ]);
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

    /**
     * Proxy a solicitation document pulled from the linked SAM.gov opportunity.
     * The file lives on SAM's servers — we stream it through so the API key
     * stays server-side and access is gated by the proposal's view policy.
     * Append ?dl=1 to force a download instead of an inline preview.
     */
    public function samDocument(Request $request, ProposalSubmission $proposalSubmission, int $index, OpportunityDocumentService $docs): mixed
    {
        $this->authorize('view', $proposalSubmission);

        $opportunity = $this->linkedOpportunity($proposalSubmission);
        abort_if($opportunity === null, 404, 'This proposal is not linked to a SAM.gov opportunity.');

        $url = $docs->urlAt($opportunity, $index);
        abort_if($url === null, 404, 'Document not found.');

        $fetched = $docs->fetch($url);
        abort_if($fetched === null, 502, 'Could not retrieve the document from SAM.gov.');

        $disposition = $request->boolean('dl') ? 'attachment' : 'inline';

        return response($fetched['body'], 200, [
            'Content-Type' => $fetched['mime'],
            'Content-Disposition' => $disposition . '; filename="' . addslashes($fetched['filename']) . '"',
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);
    }

    /**
     * Read a SAM.gov solicitation document and use it to fill in any blank
     * proposal fields (same QuakeAI extraction used for uploads).
     */
    public function extractSamDocument(Request $request, ProposalSubmission $proposalSubmission, int $index, OpportunityDocumentService $docs): RedirectResponse
    {
        $this->authorize('update', $proposalSubmission);

        $opportunity = $this->linkedOpportunity($proposalSubmission);
        abort_if($opportunity === null, 404, 'This proposal is not linked to a SAM.gov opportunity.');

        $url = $docs->urlAt($opportunity, $index);
        abort_if($url === null, 404, 'Document not found.');

        $fetched = $docs->fetch($url);
        if ($fetched === null) {
            return back()->with('error', 'Could not retrieve the document from SAM.gov.');
        }

        // Only PDFs carry extractable text; anything else just isn't useful here.
        if (!str_contains($fetched['mime'], 'pdf')) {
            return back()->with('warning', 'That document is not a PDF, so there was nothing to extract.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'sam_');
        try {
            file_put_contents($tmp, $fetched['body']);
            $name = Str::endsWith(strtolower($fetched['filename']), '.pdf') ? $fetched['filename'] : $fetched['filename'] . '.pdf';
            $uploaded = new UploadedFile($tmp, $name, 'application/pdf', null, true);

            $analysis = $this->intake->extract($proposalSubmission, $uploaded, $request->user());
            if (!$analysis) {
                return back()->with('warning', 'No readable text was found in that document.');
            }
            $summary = $this->intake->autoApply($proposalSubmission->fresh(), $analysis, $request->user(), fillBlanksOnly: true);
            $records = count($summary['created'] ?? []) + count($summary['linked'] ?? []);

            return back()->with('success', 'Read by QuakeAI — ' . count($summary['fields'] ?? []) . ' field(s) filled, ' . $records . ' record(s) created/linked.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not read that document.');
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }

    private function linkedOpportunity(ProposalSubmission $proposal): ?Opportunity
    {
        if (!$proposal->opportunity_id) {
            return null;
        }
        return Opportunity::select('id', 'title', 'source', 'raw_source_data')->find($proposal->opportunity_id);
    }
}
