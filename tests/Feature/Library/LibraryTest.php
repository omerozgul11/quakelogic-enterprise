<?php

namespace Tests\Feature\Library;

use App\Jobs\ReindexEmbeddingJob;
use App\Models\Company;
use App\Models\Library\LibraryDocument;
use App\Models\Library\LibraryDocumentLink;
use App\Models\Library\LibraryFolder;
use App\Models\Organization;
use App\Models\User;
use App\Services\Ai\KnowledgeBaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * The Document Library: Drive-style upload/organise, shared vs private
 * visibility, polymorphic links to other records, versioning, AI-index scoping
 * and org isolation. Files land on the private `local` disk.
 */
class LibraryTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake(); // don't run the embedding re-index jobs in tests

        $this->org = Organization::factory()->create();
        $this->user = $this->member($this->org);
    }

    private function member(Organization $org): User
    {
        $u = User::factory()->create(['organization_id' => $org->id, 'is_active' => true, 'email_verified_at' => now()]);
        foreach (['view library', 'manage library'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $u->givePermissionTo(['view library', 'manage library']);

        return $u;
    }

    private function upload(array $overrides = []): LibraryDocument
    {
        $this->actingAs($this->user)->post('/library/upload', array_merge([
            'files' => [UploadedFile::fake()->create('spec.pdf', 40, 'application/pdf')],
        ], $overrides))->assertRedirect();

        return LibraryDocument::latest('id')->firstOrFail();
    }

    public function test_index_renders(): void
    {
        $this->actingAs($this->user)->get('/library')->assertOk();
    }

    public function test_upload_stores_a_document_on_the_private_disk(): void
    {
        $doc = $this->upload();

        $this->assertSame($this->org->id, $doc->organization_id);
        $this->assertSame($this->user->id, $doc->uploaded_by);
        $this->assertSame('local', $doc->disk);
        $this->assertSame('shared', $doc->visibility);
        $this->assertTrue((bool) $doc->is_current_version);
        $this->assertSame('spec.pdf', $doc->original_filename);
        Storage::disk('local')->assertExists($doc->path);

        // The knowledge-base re-index is queued for the new shared document.
        Queue::assertPushed(ReindexEmbeddingJob::class);
    }

    public function test_upload_rejects_a_disallowed_type(): void
    {
        $this->actingAs($this->user)->post('/library/upload', [
            'files' => [UploadedFile::fake()->create('evil.exe', 10, 'application/x-msdownload')],
        ])->assertSessionHasErrors('files.0');

        $this->assertSame(0, LibraryDocument::count());
    }

    public function test_folder_creation_and_upload_into_folder(): void
    {
        $this->actingAs($this->user)->post('/library/folders', ['name' => 'Datasheets'])->assertRedirect();
        $folder = LibraryFolder::firstOrFail();
        $this->assertSame('shared', $folder->visibility);

        $doc = $this->upload(['folder_id' => $folder->id]);
        $this->assertSame($folder->id, $doc->library_folder_id);
    }

    public function test_private_folder_forces_private_uploads(): void
    {
        $this->actingAs($this->user)->post('/library/folders', ['name' => 'My stuff', 'visibility' => 'private'])->assertRedirect();
        $folder = LibraryFolder::firstOrFail();
        $this->assertSame('private', $folder->visibility);
        $this->assertSame($this->user->id, $folder->owner_id);

        $doc = $this->upload(['folder_id' => $folder->id, 'visibility' => 'shared']);
        $this->assertSame('private', $doc->visibility);
        $this->assertSame($this->user->id, $doc->owner_id);
    }

    public function test_private_documents_are_hidden_from_other_users(): void
    {
        $shared = $this->upload(['visibility' => 'shared']);
        $private = $this->upload(['visibility' => 'private']);

        $other = $this->member($this->org);

        $visibleIds = LibraryDocument::forOrganization($this->org->id)->visibleTo($other)->pluck('id');
        $this->assertTrue($visibleIds->contains($shared->id));
        $this->assertFalse($visibleIds->contains($private->id));

        // And a direct hit on someone else's private doc is forbidden.
        $this->actingAs($other)->get("/library/documents/{$private->id}")->assertForbidden();
        $this->actingAs($other)->get("/library/documents/{$shared->id}")->assertOk();
    }

    public function test_ai_index_scope_excludes_private_and_ai_off_documents(): void
    {
        $sharedOn = $this->upload(['visibility' => 'shared']);
        $private = $this->upload(['visibility' => 'private']);
        $sharedOff = $this->upload(['visibility' => 'shared']);
        $sharedOff->update(['ai_indexed' => false]);

        $ids = collect(app(KnowledgeBaseService::class)->recordsFor($this->org->id, 'library_document'))->pluck('id');

        $this->assertTrue($ids->contains($sharedOn->id));
        $this->assertFalse($ids->contains($private->id));
        $this->assertFalse($ids->contains($sharedOff->id));
    }

    public function test_link_search_unlink_flow(): void
    {
        $doc = $this->upload();
        $company = Company::factory()->create(['organization_id' => $this->org->id, 'name' => 'Globex Corp']);

        // Typeahead finds it.
        $this->actingAs($this->user)->getJson('/library/link-search?type=company&q=Globex')
            ->assertOk()
            ->assertJsonFragment(['id' => $company->id]);

        // Attach.
        $this->actingAs($this->user)->post("/library/documents/{$doc->id}/links", [
            'linkable_type' => 'company',
            'linkable_id' => $company->id,
            'note' => 'Master agreement',
        ])->assertRedirect();

        $link = LibraryDocumentLink::firstOrFail();
        $this->assertSame('company', $link->linkable_type);
        $this->assertSame($company->id, (int) $link->linkable_id);

        // Duplicate attach is a no-op (unique).
        $this->actingAs($this->user)->post("/library/documents/{$doc->id}/links", [
            'linkable_type' => 'company', 'linkable_id' => $company->id,
        ])->assertRedirect();
        $this->assertSame(1, LibraryDocumentLink::count());

        // Detach.
        $this->actingAs($this->user)->delete("/library/links/{$link->id}")->assertRedirect();
        $this->assertSame(0, LibraryDocumentLink::count());
    }

    public function test_link_rejects_a_cross_org_target(): void
    {
        $doc = $this->upload();
        $otherOrg = Organization::factory()->create();
        $foreign = Company::factory()->create(['organization_id' => $otherOrg->id, 'name' => 'Foreign Inc']);

        $this->actingAs($this->user)->post("/library/documents/{$doc->id}/links", [
            'linkable_type' => 'company', 'linkable_id' => $foreign->id,
        ])->assertSessionHas('error');

        $this->assertSame(0, LibraryDocumentLink::count());
    }

    public function test_new_version_supersedes_the_previous_one(): void
    {
        $v1 = $this->upload();

        $this->actingAs($this->user)->post("/library/documents/{$v1->id}/versions", [
            'file' => UploadedFile::fake()->create('spec-v2.pdf', 50, 'application/pdf'),
        ])->assertRedirect();

        $v1->refresh();
        $this->assertFalse((bool) $v1->is_current_version);

        $v2 = LibraryDocument::where('parent_document_id', $v1->id)->firstOrFail();
        $this->assertSame(2, $v2->version);
        $this->assertTrue((bool) $v2->is_current_version);
        $this->assertSame($v1->id, $v2->rootId());
    }

    public function test_download_and_org_isolation(): void
    {
        $doc = $this->upload();
        $this->actingAs($this->user)->get("/library/documents/{$doc->id}/download")->assertOk();

        $otherOrg = Organization::factory()->create();
        $intruder = $this->member($otherOrg);

        $this->actingAs($intruder)->get("/library/documents/{$doc->id}")->assertNotFound();
        $this->actingAs($intruder)->get("/library/documents/{$doc->id}/download")->assertNotFound();
    }
}
