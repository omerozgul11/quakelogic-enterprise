<?php

namespace Tests\Feature\Crm;

use App\Enums\ProjectStatus;
use App\Models\Crm\Project;
use App\Models\Crm\ProjectFile;
use App\Models\Crm\ProjectFolder;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase 5 of the Project Field Information System: document folders + version
 * history (no overwriting). Uses text uploads (allowed mimetype) on a fake disk.
 */
class ProjectDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $admin;
    private User $lead;
    private User $outsider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        Storage::fake('local');
        $this->org = Organization::factory()->create();
        $this->admin = $this->user('Super Admin');
        $this->lead = $this->user('Sales Representative');
        $this->outsider = $this->user('Read Only');
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['organization_id' => $this->org->id, 'is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function project(): Project
    {
        return Project::create([
            'organization_id' => $this->org->id,
            'created_by' => $this->admin->id,
            'owner_id' => $this->lead->id,
            'project_manager_id' => $this->lead->id,
            'name' => 'Field Install',
            'project_number' => 'QL-PROJ-TEST-'.random_int(1000, 9999),
            'status' => ProjectStatus::New->value,
            'created_via' => 'manual',
        ]);
    }

    private function txt(string $name, string $body = 'content'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $body);
    }

    /** A folder can be created and a file uploaded straight into it. */
    public function test_create_folder_and_upload_into_it(): void
    {
        $project = $this->project();
        $this->actingAs($this->lead)->post("/projects/{$project->id}/folders", ['name' => 'Contracts'])->assertRedirect();
        $folder = ProjectFolder::where('crm_project_id', $project->id)->firstOrFail();

        $this->actingAs($this->lead)->post("/projects/{$project->id}/files", [
            'file' => $this->txt('contract.txt'), 'crm_project_folder_id' => $folder->id,
        ])->assertRedirect();

        $file = ProjectFile::where('crm_project_id', $project->id)->firstOrFail();
        $this->assertSame($folder->id, $file->crm_project_folder_id);
        $this->assertSame(1, $file->version);
        $this->assertTrue($file->is_current_version);
    }

    /** Uploading a new version supersedes the current one and keeps the old. */
    public function test_new_version_supersedes_and_keeps_history(): void
    {
        $project = $this->project();
        $this->actingAs($this->lead)->post("/projects/{$project->id}/files", ['file' => $this->txt('contract.txt', 'v1')]);
        $root = ProjectFile::where('crm_project_id', $project->id)->firstOrFail();

        $this->actingAs($this->lead)->post("/projects/{$project->id}/files", [
            'file' => $this->txt('contract-rev.txt', 'v2 body'), 'parent_file_id' => $root->id,
        ])->assertRedirect();

        $family = ProjectFile::where('crm_project_id', $project->id)
            ->where(fn ($q) => $q->where('id', $root->id)->orWhere('parent_file_id', $root->id))->get();
        $this->assertCount(2, $family);
        $v2 = $family->firstWhere('version', 2);
        $this->assertTrue($v2->is_current_version);
        $this->assertFalse($root->fresh()->is_current_version);
        $this->assertSame('contract.txt', $v2->display_name, 'New version keeps the document name.');
        $this->assertSame($root->id, $v2->parent_file_id);
    }

    /** An older version can be restored as current. */
    public function test_restore_old_version(): void
    {
        $project = $this->project();
        $this->actingAs($this->lead)->post("/projects/{$project->id}/files", ['file' => $this->txt('doc.txt', 'v1')]);
        $root = ProjectFile::where('crm_project_id', $project->id)->firstOrFail();
        $this->actingAs($this->lead)->post("/projects/{$project->id}/files", ['file' => $this->txt('doc.txt', 'v2'), 'parent_file_id' => $root->id]);

        $this->actingAs($this->lead)->patch("/projects/{$project->id}/files/{$root->id}/restore-version")->assertRedirect();

        $this->assertTrue($root->fresh()->is_current_version);
        $this->assertSame(1, ProjectFile::where('crm_project_id', $project->id)->where('is_current_version', true)->count());
    }

    /** The show payload groups versions into one document entry. */
    public function test_payload_groups_versions(): void
    {
        $project = $this->project();
        $this->actingAs($this->lead)->post("/projects/{$project->id}/files", ['file' => $this->txt('spec.txt', 'v1')]);
        $root = ProjectFile::where('crm_project_id', $project->id)->firstOrFail();
        $this->actingAs($this->lead)->post("/projects/{$project->id}/files", ['file' => $this->txt('spec.txt', 'v2'), 'parent_file_id' => $root->id]);

        $this->actingAs($this->admin)->get("/projects/{$project->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('files', 1)
                ->where('files.0.version', 2)
                ->has('files.0.versions', 2)
                ->has('folders'));
    }

    /** Deleting the current version promotes the previous one. */
    public function test_delete_current_promotes_previous(): void
    {
        $project = $this->project();
        $this->actingAs($this->lead)->post("/projects/{$project->id}/files", ['file' => $this->txt('d.txt', 'v1')]);
        $root = ProjectFile::where('crm_project_id', $project->id)->firstOrFail();
        $this->actingAs($this->lead)->post("/projects/{$project->id}/files", ['file' => $this->txt('d.txt', 'v2'), 'parent_file_id' => $root->id]);
        $v2 = ProjectFile::where('crm_project_id', $project->id)->where('version', 2)->firstOrFail();

        $this->actingAs($this->lead)->delete("/projects/{$project->id}/files/{$v2->id}")->assertRedirect();

        $this->assertTrue($root->fresh()->is_current_version);
    }

    /** Deleting a folder keeps its files but unfiles them. */
    public function test_delete_folder_unfiles_files(): void
    {
        $project = $this->project();
        $this->actingAs($this->lead)->post("/projects/{$project->id}/folders", ['name' => 'Drawings']);
        $folder = ProjectFolder::where('crm_project_id', $project->id)->firstOrFail();
        $this->actingAs($this->lead)->post("/projects/{$project->id}/files", ['file' => $this->txt('cad.txt'), 'crm_project_folder_id' => $folder->id]);
        $file = ProjectFile::where('crm_project_id', $project->id)->firstOrFail();

        $this->actingAs($this->lead)->delete("/projects/{$project->id}/folders/{$folder->id}")->assertRedirect();

        $this->assertNull($file->fresh()->crm_project_folder_id);
        $this->assertSoftDeleted('crm_project_folders', ['id' => $folder->id]);
    }

    /** An unassigned read-only user cannot upload or create folders. */
    public function test_outsider_is_blocked(): void
    {
        $project = $this->project();
        $this->actingAs($this->outsider)->post("/projects/{$project->id}/files", ['file' => $this->txt('x.txt')])->assertForbidden();
        $this->actingAs($this->outsider)->post("/projects/{$project->id}/folders", ['name' => 'X'])->assertForbidden();
    }
}
