<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * One-off disaster recovery. The main DB was wiped (no usable backup, binlog
 * off). Meilisearch survived and held a mirror of the searchable models, so this
 * reconstructs as much as possible into the current schema:
 *   - roles + permissions (standard seeder + the custom runtime roles)
 *   - the organization
 *   - every real user account (temp password — must be reset)
 *   - agencies / companies / contacts / proposal_submissions / opportunities
 *     from the Meilisearch indexes
 *
 * Fields NOT indexed by Scout (money, dates, ownership, bodies) cannot be
 * recovered and are left null/default. Idempotent: safe to re-run.
 */
class RecoverFromSearchSeeder extends Seeder
{
    private string $host;
    private ?string $key;
    /** Temp password for recovered accounts. Set RECOVERY_TEMP_PASSWORD in the
     *  environment; if unset, a random one is generated and printed. Never hardcode. */
    private string $tempPassword;

    public function run(): void
    {
        $this->host = rtrim((string) config('scout.meilisearch.host', 'http://meilisearch:7700'), '/');
        $this->key = config('scout.meilisearch.key');
        $this->tempPassword = (string) env('RECOVERY_TEMP_PASSWORD') ?: Str::password(16);
        $this->command->warn('Recovered accounts temp password: '.$this->tempPassword.' (set RECOVERY_TEMP_PASSWORD to control it).');

        // 1. Standard roles + the full permission set.
        $this->call(RolesPermissionsSeeder::class);

        // 2. Custom roles that were created at runtime (not in the seeder). Exact
        //    permission sets are gone, so grant sensible read + CRM access.
        $customRoles = [
            'Electric & Electronic Engineer',
            'Business Development Associate',
            'Client Relations & Op. Coordinator',
            'Contracts & Billing Administrator',
        ];
        $customPerms = array_values(array_intersect(
            ['view crm', 'access crm', 'view proposals', 'view opportunities', 'view dashboards',
                'view contracts', 'view follow ups', 'manage contacts', 'manage companies', 'manage leads'],
            Permission::pluck('name')->all(),
        ));
        foreach ($customRoles as $name) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'web'])->syncPermissions($customPerms);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 3. Organization.
        $orgId = DB::table('organizations')->where('slug', 'quakelogic')->value('id');
        if (! $orgId) {
            $orgId = DB::table('organizations')->insertGetId([
                'ulid' => (string) Str::ulid(),
                'name' => 'QuakeLogic',
                'slug' => 'quakelogic',
                'is_active' => 1,
                'country' => 'US',
                'timezone' => 'America/Los_Angeles',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 4. Users (captured before the wipe). Name = email local part (refine later).
        $users = [
            ['email' => 'admin@quakelogic.net', 'name' => 'Admin', 'role' => 'Super Admin'],
            ['email' => 'omer@quakelogic.net', 'name' => 'Omer', 'role' => 'Super Admin'],
            ['email' => 'erol@quakelogic.net', 'name' => 'Erol', 'role' => 'Super Admin'],
            ['email' => 'halil@quakelogic.net', 'name' => 'Halil', 'role' => 'Electric & Electronic Engineer'],
            ['email' => 'akin@quakelogic.net', 'name' => 'Akin', 'role' => 'Business Development Manager'],
            ['email' => 'gorkem@quakelogic.net', 'name' => 'Gorkem', 'role' => 'Business Development Manager'],
            ['email' => 'beyazit@quakelogic.net', 'name' => 'Beyazit', 'role' => 'Business Development Associate'],
            ['email' => 'sophia@quakelogic.net', 'name' => 'Sophia', 'role' => 'Sales Representative'],
            ['email' => 'alla@quakelogic.net', 'name' => 'Alla', 'role' => 'Contracts & Billing Administrator'],
        ];

        $adminId = null;
        foreach ($users as $u) {
            $id = DB::table('users')->where('email', $u['email'])->value('id');
            if (! $id) {
                $id = DB::table('users')->insertGetId([
                    'ulid' => (string) Str::ulid(),
                    'organization_id' => $orgId,
                    'name' => $u['name'],
                    'email' => $u['email'],
                    'password' => Hash::make($this->tempPassword),
                    'email_verified_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $user = \App\Models\User::find($id);
            $user->syncRoles([$u['role']]);
            $adminId = $adminId ?? ($u['email'] === 'admin@quakelogic.net' ? $id : $adminId);
        }
        $createdBy = $adminId ?? DB::table('users')->min('id');

        // 5. Reconstruct records from Meilisearch.
        $agencyMap = $this->recoverAgencies($orgId, $createdBy);   // name => id
        $companyMap = $this->recoverCompanies($orgId, $createdBy); // name => id
        $this->recoverContacts($orgId, $createdBy, $companyMap, $agencyMap);
        $this->recoverProposals($orgId, $createdBy);
        $this->recoverOpportunities($orgId, $createdBy);

        $this->command->info('Recovery complete.');
    }

    /** Fetch every document from a Meilisearch index (paginated). */
    private function fetchAll(string $index): array
    {
        $all = [];
        $offset = 0;
        $limit = 1000;
        do {
            $req = Http::acceptJson();
            if ($this->key) {
                $req = $req->withToken($this->key);
            }
            $res = $req->get("{$this->host}/indexes/{$index}/documents", ['limit' => $limit, 'offset' => $offset]);
            if (! $res->ok()) {
                $this->command->warn("  {$index}: fetch failed ({$res->status()})");
                break;
            }
            $batch = $res->json('results', []);
            $all = array_merge($all, $batch);
            $offset += $limit;
        } while (count($batch) === $limit);

        return $all;
    }

    private function recoverAgencies(int $orgId, int $createdBy): array
    {
        $map = [];
        $rows = [];
        foreach ($this->fetchAll('agencies') as $d) {
            $rows[] = [
                'id' => $d['id'],
                'ulid' => (string) Str::ulid(),
                'organization_id' => $orgId,
                'created_by' => $createdBy,
                'name' => $d['name'] ?? 'Unknown agency',
                'acronym' => $d['acronym'] ?? null,
                'federal_code' => $d['federal_code'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $map[$d['name'] ?? ''] = $d['id'];
        }
        if ($rows) {
            DB::table('agencies')->upsert($rows, ['id'], ['name', 'acronym', 'federal_code']);
        }
        $this->command->info('  agencies: '.count($rows));

        return $map;
    }

    private function recoverCompanies(int $orgId, int $createdBy): array
    {
        $map = [];
        $rows = [];
        foreach ($this->fetchAll('companies') as $d) {
            $rows[] = [
                'id' => $d['id'],
                'ulid' => (string) Str::ulid(),
                'organization_id' => $orgId,
                'created_by' => $createdBy,
                'owner_id' => $createdBy,
                'name' => $d['name'] ?? 'Unknown company',
                'industry' => $d['industry'] ?? null,
                'cage_code' => $d['cage_code'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $map[$d['name'] ?? ''] = $d['id'];
        }
        if ($rows) {
            DB::table('companies')->upsert($rows, ['id'], ['name', 'industry', 'cage_code']);
        }
        $this->command->info('  companies: '.count($rows));

        return $map;
    }

    private function recoverContacts(int $orgId, int $createdBy, array $companyMap, array $agencyMap): void
    {
        $rows = [];
        foreach ($this->fetchAll('contacts') as $d) {
            $full = trim($d['full_name'] ?? '');
            $parts = $full !== '' ? preg_split('/\s+/', $full, 2) : ['Unknown'];
            $rows[] = [
                'id' => $d['id'],
                'ulid' => (string) Str::ulid(),
                'organization_id' => $orgId,
                'created_by' => $createdBy,
                'owner_id' => $createdBy,
                'first_name' => $parts[0] ?? 'Unknown',
                'last_name' => $parts[1] ?? '—',
                'title' => $d['title'] ?? null,
                'email' => $d['email'] ?? null,
                'company_id' => $companyMap[$d['company_name'] ?? ''] ?? null,
                'agency_id' => $agencyMap[$d['agency_name'] ?? ''] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        if ($rows) {
            DB::table('contacts')->upsert($rows, ['id'], ['first_name', 'last_name', 'title', 'email', 'company_id', 'agency_id']);
        }
        $this->command->info('  contacts: '.count($rows));
    }

    private function recoverProposals(int $orgId, int $createdBy): void
    {
        $rows = [];
        foreach ($this->fetchAll('proposal_submissions') as $d) {
            $rows[] = [
                'id' => $d['id'],
                'ulid' => (string) Str::ulid(),
                'organization_id' => $orgId,
                'created_by' => $createdBy,
                'owner_id' => $createdBy,
                'proposal_number' => $d['proposal_number'] ?? ('QL-RECOVERED-'.$d['id']),
                'project_name' => $d['project_name'] ?? 'Recovered proposal',
                'solicitation_number' => $d['solicitation_number'] ?? null,
                'status' => $d['status'] ?? 'draft',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        if ($rows) {
            DB::table('proposal_submissions')->upsert($rows, ['id'], ['proposal_number', 'project_name', 'solicitation_number', 'status']);
        }
        $this->command->info('  proposals: '.count($rows));
    }

    private function recoverOpportunities(int $orgId, int $createdBy): void
    {
        $docs = $this->fetchAll('opportunities');
        $total = 0;
        foreach (array_chunk($docs, 500) as $chunk) {
            $rows = [];
            foreach ($chunk as $d) {
                $rows[] = [
                    'id' => $d['id'],
                    'ulid' => (string) Str::ulid(),
                    'organization_id' => $orgId,
                    'created_by' => $createdBy,
                    'title' => $d['title'] ?? 'Recovered opportunity',
                    'description' => $d['description'] ?? null,
                    'agency_name' => $d['agency_name'] ?? null,
                    'naics_code' => $d['naics_code'] ?? null,
                    'solicitation_number' => $d['solicitation_number'] ?? null,
                    'source' => $d['source'] ?? 'manual',
                    'status' => $d['status'] ?? 'identified',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            if ($rows) {
                DB::table('opportunities')->upsert($rows, ['id'], ['title', 'description', 'agency_name', 'naics_code', 'solicitation_number', 'source', 'status']);
                $total += count($rows);
            }
        }
        $this->command->info('  opportunities: '.$total);
    }
}
