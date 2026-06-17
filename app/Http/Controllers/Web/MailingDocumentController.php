<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MailingDocument;
use App\Models\ProposalMailing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Documents attached to a mailing (the UPS label, customs forms, receipts, …).
 * Mirrors the proposals file convention: private local disk, streamed via this
 * controller only, PDF/JPEG/PNG accepted (validated by sniffed type + extension).
 */
class MailingDocumentController extends Controller
{
    public function store(Request $request, string $ulid): RedirectResponse
    {
        $mailing = $this->mailing($request, $ulid);
        $this->authorize('update', $mailing);

        $request->validate([
            'file' => 'required|file|max:51200|mimetypes:application/pdf,image/jpeg,image/png|mimes:pdf,jpg,jpeg,png',
            'document_type' => 'nullable|string|max:50',
        ]);

        $file = $request->file('file');
        $storedName = (string) Str::ulid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs("mailing-documents/{$mailing->id}", $storedName, 'local');

        $mailing->documents()->create([
            'uploaded_by' => $request->user()->id,
            'display_name' => $file->getClientOriginalName(),
            'original_filename' => $file->getClientOriginalName(),
            'stored_filename' => $storedName,
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'document_type' => $request->input('document_type'),
        ]);

        return back()->with('success', 'Document uploaded.');
    }

    public function download(Request $request, string $ulid, MailingDocument $document): mixed
    {
        $mailing = $this->mailing($request, $ulid);
        $this->authorize('view', $mailing);
        abort_unless($document->proposal_mailing_id === $mailing->id, 404);
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);

        return Storage::disk($document->disk)->download($document->path, $document->display_name);
    }

    public function preview(Request $request, string $ulid, MailingDocument $document): mixed
    {
        $mailing = $this->mailing($request, $ulid);
        $this->authorize('view', $mailing);
        abort_unless($document->proposal_mailing_id === $mailing->id, 404);
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);

        return Storage::disk($document->disk)->response($document->path, $document->display_name, [
            'Content-Type' => $document->mime_type,
            'Content-Disposition' => 'inline; filename="'.addslashes($document->display_name).'"',
        ]);
    }

    public function destroy(Request $request, string $ulid, MailingDocument $document): RedirectResponse
    {
        $mailing = $this->mailing($request, $ulid);
        $this->authorize('update', $mailing);
        abort_unless($document->proposal_mailing_id === $mailing->id, 404);

        Storage::disk($document->disk)->delete($document->path);
        $document->delete();

        return back()->with('success', 'Document removed.');
    }

    private function mailing(Request $request, string $ulid): ProposalMailing
    {
        return ProposalMailing::query()
            ->forOrganization($request->user()->organization_id)
            ->where('ulid', $ulid)
            ->firstOrFail();
    }
}
