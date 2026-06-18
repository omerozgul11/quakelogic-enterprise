<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Datasheet;
use App\Services\Ai\AiProviderInterface;
use App\Services\Datasheets\DatasheetDocumentService;
use App\Services\Datasheets\DatasheetWriterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Datasheet Writer — lives under the Proposal Writer in the proposals platform.
 * Users dump spec sheets, technical notes and product photos; the AI assembles a
 * branded, fully-written product datasheet they can edit and export to PDF.
 */
class DatasheetController extends Controller
{
    public function __construct(
        private readonly DatasheetWriterService $writer,
        private readonly DatasheetDocumentService $documents,
        private readonly AiProviderInterface $ai,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('use ai assistant');
        $user = $request->user();

        $datasheets = Datasheet::forOrganization($user->organization_id)
            ->with('creator:id,name')
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get()
            ->map(fn (Datasheet $d) => $this->summary($d))
            ->values();

        return Inertia::render('AI/Datasheet/Index', [
            'datasheets' => $datasheets,
            'aiProvider' => $this->ai->getName(),
            'aiAvailable' => $this->ai->isAvailable(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('use ai assistant');
        $user = $request->user();

        $validated = $request->validate([
            'product_name' => ['required', 'string', 'max:200'],
            'model_number' => ['nullable', 'string', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:200'],
            'input_notes' => ['nullable', 'string', 'max:20000'],
            'specs' => ['nullable', 'array', 'max:10'],
            'specs.*' => ['file', 'mimes:pdf,doc,docx,txt,csv,xlsx', 'max:25600'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:25600'],
        ]);

        if (blank($validated['input_notes'] ?? null) && ! $request->hasFile('specs') && ! $request->hasFile('images')) {
            return back()->withErrors(['input_notes' => 'Add some technical notes, a spec sheet, or a product photo so there is material to work from.'])->withInput();
        }

        $datasheet = Datasheet::create([
            'organization_id' => $user->organization_id,
            'created_by' => $user->id,
            'product_name' => $validated['product_name'],
            'model_number' => $validated['model_number'] ?? null,
            'tagline' => $validated['tagline'] ?? null,
            'input_notes' => $validated['input_notes'] ?? null,
            'status' => 'draft',
        ]);

        $media = [];
        foreach ((array) $request->file('specs', []) as $file) {
            $media[] = $this->storeFile($datasheet, $file, 'spec');
        }
        foreach ((array) $request->file('images', []) as $file) {
            $media[] = $this->storeFile($datasheet, $file, 'image');
        }
        $datasheet->update(['media' => $media]);

        $this->writer->generate($datasheet->refresh());

        return redirect()->route('ai.datasheets.show', $datasheet)->with('success', 'Datasheet generated.');
    }

    public function show(Request $request, Datasheet $datasheet): Response
    {
        $this->authorize('use ai assistant');
        $this->guard($request, $datasheet);

        return Inertia::render('AI/Datasheet/Show', [
            'datasheet' => $this->detail($datasheet),
            'aiProvider' => $this->ai->getName(),
            'aiAvailable' => $this->ai->isAvailable(),
        ]);
    }

    /** Save user edits to the generated content. */
    public function update(Request $request, Datasheet $datasheet): RedirectResponse
    {
        $this->authorize('use ai assistant');
        $this->guard($request, $datasheet);

        $validated = $request->validate([
            'product_name' => ['required', 'string', 'max:200'],
            'model_number' => ['nullable', 'string', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:200'],
            'sections' => ['nullable', 'array'],
            'sections.overview' => ['nullable', 'string'],
            'sections.key_features' => ['nullable', 'array'],
            'sections.applications' => ['nullable', 'array'],
            'sections.specifications' => ['nullable', 'array'],
        ]);

        $datasheet->update([
            'product_name' => $validated['product_name'],
            'model_number' => $validated['model_number'] ?? null,
            'tagline' => $validated['tagline'] ?? null,
            'sections' => $validated['sections'] ?? $datasheet->sections,
        ]);

        return back()->with('success', 'Saved.');
    }

    /** Re-run the AI generation from the stored inputs. */
    public function regenerate(Request $request, Datasheet $datasheet): RedirectResponse
    {
        $this->authorize('use ai assistant');
        $this->guard($request, $datasheet);

        $this->writer->generate($datasheet);

        return back()->with('success', 'Datasheet regenerated.');
    }

    public function download(Request $request, Datasheet $datasheet): HttpResponse
    {
        $this->authorize('use ai assistant');
        $this->guard($request, $datasheet);

        return $this->documents->pdf($datasheet);
    }

    public function destroy(Request $request, Datasheet $datasheet): RedirectResponse
    {
        $this->authorize('use ai assistant');
        $this->guard($request, $datasheet);

        foreach ((array) $datasheet->media as $m) {
            try {
                Storage::disk($m['disk'] ?? 'local')->delete($m['path']);
            } catch (\Throwable) {
                // best effort
            }
        }
        $datasheet->delete();

        return redirect()->route('ai.datasheets.index')->with('success', 'Datasheet deleted.');
    }

    private function guard(Request $request, Datasheet $datasheet): void
    {
        abort_unless((int) $datasheet->organization_id === (int) $request->user()->organization_id, 404);
    }

    private function storeFile(Datasheet $d, UploadedFile $file, string $kind): array
    {
        $ext = $file->getClientOriginalExtension() ?: ($file->guessExtension() ?: 'bin');
        $path = $file->storeAs("datasheets/{$d->id}", (string) Str::ulid() . ".{$ext}", 'local');

        return [
            'path' => $path,
            'disk' => 'local',
            'mime' => $file->getClientMimeType(),
            'kind' => $kind,
            'name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
        ];
    }

    private function summary(Datasheet $d): array
    {
        return [
            'id' => $d->id,
            'product_name' => $d->product_name,
            'model_number' => $d->model_number,
            'status' => $d->status,
            'creator' => $d->creator?->name,
            'spec_count' => count($d->mediaOfKind('spec')),
            'image_count' => count($d->mediaOfKind('image')),
            'generated_at' => $d->generated_at?->toIso8601String(),
            'updated_at' => $d->updated_at?->toIso8601String(),
        ];
    }

    private function detail(Datasheet $d): array
    {
        $sections = $d->sections ?? [];

        return [
            'id' => $d->id,
            'ulid' => $d->ulid,
            'product_name' => $d->product_name,
            'model_number' => $d->model_number,
            'tagline' => $d->tagline,
            'status' => $d->status,
            'input_notes' => $d->input_notes,
            'sections' => [
                'tagline' => $sections['tagline'] ?? null,
                'overview' => $sections['overview'] ?? '',
                'key_features' => array_values($sections['key_features'] ?? []),
                'specifications' => array_values($sections['specifications'] ?? []),
                'applications' => array_values($sections['applications'] ?? []),
            ],
            'media' => array_map(fn ($m) => [
                'name' => $m['name'] ?? 'file',
                'kind' => $m['kind'] ?? 'spec',
                'mime' => $m['mime'] ?? null,
            ], (array) $d->media),
            'generated_at' => $d->generated_at?->toIso8601String(),
        ];
    }
}
