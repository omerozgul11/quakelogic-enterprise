<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Library\LibraryDocument;
use App\Models\Library\LibraryDocumentLink;
use App\Models\Library\LibraryFolder;
use App\Models\User;
use App\Services\Documents\DocumentTextExtractionService;
use App\Support\Library\LinkTargets;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Document Library — a Google-Drive-style, org-shared file store under the
 * Proposals app. Users organise files into nested folders, keep documents
 * shared or private, attach them to other records (proposals / POs / projects /
 * …), and every shared file is fed to QuakeBot's knowledge base. Files live on
 * the private `local` disk; they're only ever served through the authorized
 * preview/download actions here.
 */
class LibraryController extends Controller
{
    /** Drive-style broad upload allowance (validated by extension). 100 MB cap. */
    private const UPLOAD_RULES = 'file|max:102400|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,md,rtf,odt,ods,odp,jpg,jpeg,png,gif,webp,svg,heic,zip';

    public function index(Request $request): Response
    {
        $user = $request->user();
        $orgId = (int) $user->organization_id;

        $folderId = $request->integer('folder') ?: null;
        $current = null;
        if ($folderId) {
            $current = LibraryFolder::forOrganization($orgId)->visibleTo($user)->find($folderId);
            abort_if($current === null, 404);
        }

        $search = trim((string) $request->string('q'));

        $foldersQuery = LibraryFolder::forOrganization($orgId)->visibleTo($user)
            ->withCount([
                'children as children_count',
                'documents as documents_count' => fn ($q) => $q->where('is_current_version', true),
            ])
            ->orderBy('name');

        $documentsQuery = LibraryDocument::forOrganization($orgId)->visibleTo($user)
            ->where('is_current_version', true)
            ->with('uploader:id,name')
            ->withCount('links')
            ->orderByDesc('updated_at');

        if ($search !== '') {
            // Search spans the whole library, ignoring the current folder.
            $foldersQuery->where('name', 'like', "%{$search}%");
            $documentsQuery->where(function ($q) use ($search) {
                $q->where('display_name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('original_filename', 'like', "%{$search}%");
            });
        } else {
            $foldersQuery->where('parent_id', $folderId);
            $documentsQuery->where('library_folder_id', $folderId);
        }

        $folders = $foldersQuery->get()->map(fn (LibraryFolder $f) => [
            'id' => $f->id,
            'name' => $f->name,
            'visibility' => $f->visibility,
            'documents_count' => (int) $f->documents_count,
            'children_count' => (int) $f->children_count,
        ]);

        $documents = $documentsQuery->limit(500)->get()->map(fn (LibraryDocument $d) => $this->docCard($d));

        return Inertia::render('Library/Index', [
            'folders' => $folders,
            'documents' => $documents,
            'current' => $current ? [
                'id' => $current->id,
                'name' => $current->name,
                'visibility' => $current->visibility,
            ] : null,
            'breadcrumbs' => $this->breadcrumbs($current),
            'search' => $search,
            'can' => ['manage' => $user->can('manage library')],
        ]);
    }

    public function show(Request $request, LibraryDocument $document): Response
    {
        $user = $request->user();
        $this->authorizeView($document, $user);

        $document->load(['uploader:id,name', 'owner:id,name', 'folder:id,name']);

        $rootId = $document->rootId();
        $versions = LibraryDocument::withTrashed()
            ->forOrganization($user->organization_id)
            ->where(fn ($q) => $q->where('id', $rootId)->orWhere('parent_document_id', $rootId))
            ->with('uploader:id,name')
            ->orderByDesc('version')
            ->get()
            ->map(fn (LibraryDocument $v) => [
                'id' => $v->id,
                'version' => $v->version,
                'is_current_version' => (bool) $v->is_current_version,
                'size_label' => $v->sizeLabel(),
                'original_filename' => $v->original_filename,
                'uploaded_by' => $v->uploader?->name,
                'created_at' => $v->created_at?->toIso8601String(),
                'trashed' => $v->trashed(),
                'download_url' => route('library.download', $v),
            ]);

        $links = LibraryDocumentLink::where('library_document_id', $document->id)
            ->orderBy('linkable_type')
            ->orderByDesc('id')
            ->get()
            ->map(fn (LibraryDocumentLink $l) => $this->linkRow($l));

        return Inertia::render('Library/Show', [
            'document' => $this->docDetail($document),
            'versions' => $versions,
            'links' => $links,
            'linkTargets' => LinkTargets::options(),
            'previewUrl' => route('library.preview', $document),
            'downloadUrl' => route('library.download', $document),
            'can' => ['manage' => $user->can('manage library')],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $orgId = (int) $user->organization_id;

        $data = $request->validate([
            'files' => 'required|array|max:25',
            'files.*' => self::UPLOAD_RULES,
            'folder_id' => ['nullable', Rule::exists('library_folders', 'id')->where('organization_id', $orgId)->whereNull('deleted_at')],
            'visibility' => 'nullable|in:shared,private',
            'description' => 'nullable|string|max:2000',
        ]);

        $folder = ! empty($data['folder_id'])
            ? LibraryFolder::forOrganization($orgId)->find($data['folder_id'])
            : null;
        if ($folder && ! $folder->isVisibleTo($user)) {
            abort(403);
        }

        $visibility = ($data['visibility'] ?? 'shared') === 'private' ? 'private' : 'shared';
        // A file dropped into a private folder is private too.
        if ($folder && $folder->visibility === 'private') {
            $visibility = 'private';
        }

        $count = 0;
        foreach ($request->file('files') as $file) {
            $this->persist($orgId, $file, [
                'library_folder_id' => $data['folder_id'] ?? null,
                'uploaded_by' => $user->id,
                'description' => $data['description'] ?? null,
                'visibility' => $visibility,
                'owner_id' => $visibility === 'private' ? $user->id : null,
            ]);
            $count++;
        }

        return back()->with('success', $count . ' file' . ($count === 1 ? '' : 's') . ' uploaded to the library.');
    }

    public function update(Request $request, LibraryDocument $document): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeManage($document, $user);

        $data = $request->validate([
            'display_name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'visibility' => 'nullable|in:shared,private',
            'ai_indexed' => 'nullable|boolean',
            'folder_id' => ['nullable', Rule::exists('library_folders', 'id')->where('organization_id', $user->organization_id)->whereNull('deleted_at')],
        ]);

        if (array_key_exists('display_name', $data)) {
            $document->display_name = $data['display_name'];
        }
        if ($request->has('description')) {
            $document->description = $data['description'] ?? null;
        }
        if ($request->has('folder_id')) {
            $document->library_folder_id = $data['folder_id'] ?? null;
        }
        if ($request->has('ai_indexed')) {
            $document->ai_indexed = $request->boolean('ai_indexed');
        }
        if ($request->filled('visibility')) {
            $document->visibility = $data['visibility'];
            $document->owner_id = $data['visibility'] === 'private' ? ($document->owner_id ?: $user->id) : null;
        }

        $document->save();

        return back()->with('success', 'Document updated.');
    }

    public function destroy(Request $request, LibraryDocument $document): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeManage($document, $user);

        // Soft-delete only; the file stays on disk so a restore is possible.
        // The embedding observer clears its knowledge-base chunks on delete.
        $document->delete();

        return redirect()->route('library.index', ['folder' => $document->library_folder_id])
            ->with('success', 'Document moved to trash.');
    }

    public function download(Request $request, LibraryDocument $document): mixed
    {
        $this->authorizeView($document, $request->user());

        if (! Storage::disk($document->disk)->exists($document->path)) {
            abort(404, 'File not found in storage.');
        }

        return Storage::disk($document->disk)->download($document->path, $document->original_filename ?: $document->display_name);
    }

    public function preview(Request $request, LibraryDocument $document): mixed
    {
        $this->authorizeView($document, $request->user());

        if (! Storage::disk($document->disk)->exists($document->path)) {
            abort(404, 'File not found in storage.');
        }

        // PDFs, images and plain text render natively inside the iframe.
        if ($document->isNativelyPreviewable()) {
            return Storage::disk($document->disk)->response($document->path, $document->display_name, [
                'Content-Type' => $document->mime_type,
                'Content-Disposition' => 'inline; filename="' . addslashes($document->display_name) . '"',
            ]);
        }

        // Office / other formats: show the extracted text as a clean HTML page.
        $text = '';
        try {
            $text = app(DocumentTextExtractionService::class)->extract($document->path, (string) $document->mime_type);
        } catch (\Throwable) {
            $text = '';
        }

        return response($this->textPreviewHtml($document->display_name, $text), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'inline',
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);
    }

    public function storeVersion(Request $request, LibraryDocument $document): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeManage($document, $user);

        $request->validate(['file' => 'required|' . self::UPLOAD_RULES]);

        $rootId = $document->rootId();
        $family = fn ($q) => $q->where('id', $rootId)->orWhere('parent_document_id', $rootId);
        $maxVersion = (int) LibraryDocument::withTrashed()->where($family)->max('version');

        LibraryDocument::where($family)->update(['is_current_version' => false]);

        $new = $this->persist($user->organization_id, $request->file('file'), [
            'library_folder_id' => $document->library_folder_id,
            'uploaded_by' => $user->id,
            'display_name' => $document->display_name,
            'description' => $document->description,
            'visibility' => $document->visibility,
            'owner_id' => $document->owner_id,
            'ai_indexed' => $document->ai_indexed,
            'version' => $maxVersion + 1,
            'is_current_version' => true,
            'parent_document_id' => $rootId,
        ]);

        return redirect()->route('library.show', $new)
            ->with('success', "New version uploaded (v{$new->version}).");
    }

    public function restoreVersion(Request $request, LibraryDocument $document, LibraryDocument $version): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeManage($document, $user);
        abort_unless(
            $version->organization_id === $document->organization_id && $version->rootId() === $document->rootId(),
            404
        );

        $rootId = $document->rootId();
        LibraryDocument::where(fn ($q) => $q->where('id', $rootId)->orWhere('parent_document_id', $rootId))
            ->update(['is_current_version' => false]);

        $version->is_current_version = true;
        $version->save();

        return redirect()->route('library.show', $version)
            ->with('success', "Restored v{$version->version} as the current version.");
    }

    public function link(Request $request, LibraryDocument $document): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeManage($document, $user);

        $data = $request->validate([
            'linkable_type' => ['required', 'string', Rule::in(array_keys(LinkTargets::map()))],
            'linkable_id' => 'required|integer',
            'note' => 'nullable|string|max:255',
        ]);

        $target = LinkTargets::resolve($data['linkable_type'], (int) $data['linkable_id'], (int) $user->organization_id);
        if ($target === null) {
            return back()->with('error', 'That record was not found in your organization.');
        }

        LibraryDocumentLink::firstOrCreate([
            'library_document_id' => $document->id,
            'linkable_type' => $data['linkable_type'],
            'linkable_id' => (int) $data['linkable_id'],
        ], [
            'organization_id' => $user->organization_id,
            'note' => $data['note'] ?? null,
            'created_by' => $user->id,
        ]);

        return back()->with('success', 'Linked to ' . LinkTargets::label($data['linkable_type'], $target) . '.');
    }

    public function unlink(Request $request, LibraryDocumentLink $link): RedirectResponse
    {
        $user = $request->user();
        abort_unless((int) $link->organization_id === (int) $user->organization_id, 404);

        $document = LibraryDocument::find($link->library_document_id);
        if ($document) {
            $this->authorizeManage($document, $user);
        }

        $link->delete();

        return back()->with('success', 'Link removed.');
    }

    /** Typeahead JSON for the "attach to a record" picker. */
    public function linkSearch(Request $request): JsonResponse
    {
        $user = $request->user();
        $type = (string) $request->string('type');
        abort_unless(LinkTargets::has($type), 422);

        return response()->json([
            'results' => LinkTargets::search($type, (string) $request->string('q'), (int) $user->organization_id),
        ]);
    }

    public function storeFolder(Request $request): RedirectResponse
    {
        $user = $request->user();
        $orgId = (int) $user->organization_id;

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'parent_id' => ['nullable', Rule::exists('library_folders', 'id')->where('organization_id', $orgId)->whereNull('deleted_at')],
            'visibility' => 'nullable|in:shared,private',
        ]);

        $parent = ! empty($data['parent_id']) ? LibraryFolder::forOrganization($orgId)->find($data['parent_id']) : null;
        if ($parent && ! $parent->isVisibleTo($user)) {
            abort(403);
        }

        $visibility = ($data['visibility'] ?? 'shared') === 'private' ? 'private' : 'shared';
        if ($parent && $parent->visibility === 'private') {
            $visibility = 'private';
        }

        LibraryFolder::create([
            'organization_id' => $orgId,
            'parent_id' => $data['parent_id'] ?? null,
            'name' => $data['name'],
            'visibility' => $visibility,
            'owner_id' => $visibility === 'private' ? $user->id : null,
            'created_by' => $user->id,
        ]);

        return back()->with('success', 'Folder created.');
    }

    public function updateFolder(Request $request, LibraryFolder $folder): RedirectResponse
    {
        $user = $request->user();
        $orgId = (int) $user->organization_id;
        abort_unless((int) $folder->organization_id === $orgId, 404);
        abort_unless($folder->isVisibleTo($user), 403);

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:120',
            'parent_id' => ['nullable', Rule::exists('library_folders', 'id')->where('organization_id', $orgId)->whereNull('deleted_at')],
            'visibility' => 'nullable|in:shared,private',
        ]);

        if (array_key_exists('name', $data)) {
            $folder->name = $data['name'];
        }
        if ($request->has('parent_id')) {
            $newParent = $data['parent_id'] ?? null;
            if ($newParent !== null) {
                abort_if(
                    (int) $newParent === $folder->id || $this->isDescendant($folder->id, (int) $newParent),
                    422,
                    'You cannot move a folder into itself or one of its sub-folders.'
                );
            }
            $folder->parent_id = $newParent;
        }
        if ($request->filled('visibility')) {
            $folder->visibility = $data['visibility'];
            $folder->owner_id = $data['visibility'] === 'private' ? ($folder->owner_id ?: $user->id) : null;
        }

        $folder->save();

        return back()->with('success', 'Folder updated.');
    }

    public function destroyFolder(Request $request, LibraryFolder $folder): RedirectResponse
    {
        $user = $request->user();
        abort_unless((int) $folder->organization_id === (int) $user->organization_id, 404);
        abort_unless($folder->isVisibleTo($user), 403);

        // Non-destructive: lift the folder's contents up one level, then remove it.
        LibraryDocument::where('library_folder_id', $folder->id)->update(['library_folder_id' => $folder->parent_id]);
        LibraryFolder::where('parent_id', $folder->id)->update(['parent_id' => $folder->parent_id]);
        $folder->delete();

        return redirect()->route('library.index', ['folder' => $folder->parent_id])
            ->with('success', 'Folder deleted; its contents were moved up a level.');
    }

    // ---- helpers ---------------------------------------------------------

    private function persist(int $orgId, UploadedFile $file, array $attrs): LibraryDocument
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: ($file->guessExtension() ?: 'bin'));
        $storedName = Str::ulid() . '.' . $ext;
        $path = $file->storeAs("library/{$orgId}", $storedName, 'local');

        return LibraryDocument::create(array_merge([
            'organization_id' => $orgId,
            'display_name' => $file->getClientOriginalName(),
            'original_filename' => $file->getClientOriginalName(),
            'stored_filename' => $storedName,
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'extension' => $ext,
            'size' => $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
            'version' => 1,
            'is_current_version' => true,
        ], $attrs));
    }

    private function authorizeView(LibraryDocument $document, User $user): void
    {
        abort_unless((int) $document->organization_id === (int) $user->organization_id, 404);
        abort_unless($document->isVisibleTo($user), 403);
    }

    /** Write access. The `manage library` permission is enforced by route middleware. */
    private function authorizeManage(LibraryDocument $document, User $user): void
    {
        $this->authorizeView($document, $user);
    }

    /** @return array<string,mixed> */
    private function docCard(LibraryDocument $d): array
    {
        return [
            'id' => $d->id,
            'ulid' => $d->ulid,
            'display_name' => $d->display_name,
            'mime_type' => $d->mime_type,
            'extension' => $d->extension,
            'size_label' => $d->sizeLabel(),
            'visibility' => $d->visibility,
            'ai_indexed' => (bool) $d->ai_indexed,
            'version' => $d->version,
            'links_count' => (int) ($d->links_count ?? 0),
            'updated_at' => $d->updated_at?->toIso8601String(),
            'uploader' => $d->uploader ? ['name' => $d->uploader->name] : null,
            'show_url' => route('library.show', $d),
        ];
    }

    /** @return array<string,mixed> */
    private function docDetail(LibraryDocument $d): array
    {
        return array_merge($this->docCard($d), [
            'description' => $d->description,
            'original_filename' => $d->original_filename,
            'checksum' => $d->checksum,
            'created_at' => $d->created_at?->toIso8601String(),
            'is_previewable_natively' => $d->isNativelyPreviewable(),
            'owner' => $d->owner ? ['name' => $d->owner->name] : null,
            'folder' => $d->folder ? ['id' => $d->folder->id, 'name' => $d->folder->name] : null,
        ]);
    }

    /** @return array<string,mixed> */
    private function linkRow(LibraryDocumentLink $l): array
    {
        $target = $l->resolveTarget();

        return [
            'id' => $l->id,
            'type' => $l->linkable_type,
            'type_label' => LinkTargets::labelForType($l->linkable_type),
            'label' => $target ? LinkTargets::label($l->linkable_type, $target) : '(record removed)',
            'url' => $target ? LinkTargets::url($l->linkable_type, (int) $l->linkable_id) : null,
            'note' => $l->note,
            'exists' => $target !== null,
        ];
    }

    /** @return array<int,array{id:int,name:string}> root → current folder */
    private function breadcrumbs(?LibraryFolder $current): array
    {
        $chain = [];
        $node = $current;
        $guard = 0;
        while ($node && $guard++ < 50) {
            array_unshift($chain, ['id' => $node->id, 'name' => $node->name]);
            $node = $node->parent_id ? LibraryFolder::find($node->parent_id) : null;
        }

        return $chain;
    }

    private function isDescendant(int $folderId, int $candidateId): bool
    {
        $seen = [];
        $cur = $candidateId;
        while ($cur !== null) {
            if ($cur === $folderId) {
                return true;
            }
            if (isset($seen[$cur])) {
                break;
            }
            $seen[$cur] = true;
            $parent = LibraryFolder::where('id', $cur)->value('parent_id');
            $cur = $parent !== null ? (int) $parent : null;
        }

        return false;
    }

    private function textPreviewHtml(string $name, string $text): string
    {
        $safeName = e($name);

        if (trim($text) === '') {
            $body = '<div class="empty"><p>No inline preview is available for this file type.</p>'
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
}
