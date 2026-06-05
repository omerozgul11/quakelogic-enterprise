<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ProposalFile;
use App\Models\ProposalSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DocumentController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $files = ProposalFile::whereHas('proposal', fn($q) => $q->where('organization_id', $user->organization_id))
            ->with(['proposal:id,proposal_number,project_name', 'uploadedBy:id,name'])
            ->orderByDesc('created_at')
            ->paginate(25);

        return Inertia::render('Documents/Index', ['files' => $files]);
    }

    public function storeProposalFile(Request $request, ProposalSubmission $proposalSubmission): RedirectResponse
    {
        $this->authorize('update', $proposalSubmission);

        $validated = $request->validate([
            'file' => 'required|file|max:102400|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,zip,png,jpg,jpeg',
            'document_type' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $file = $validated['file'];
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
            'document_type' => $validated['document_type'] ?? null,
            'status' => 'uploaded',
            'version' => 1,
            'is_current_version' => true,
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('success', 'File uploaded successfully.');
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
