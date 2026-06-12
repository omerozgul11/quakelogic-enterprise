# CLAUDE.md — QuakeLogic Enterprise

## Project Overview
Enterprise SaaS platform for government contracting business development. Laravel 12 + PHP 8.4 backend, React 19 + TypeScript + Inertia.js frontend.

## Running Commands

All commands run inside the Docker container:
```bash
docker compose exec app php artisan <command>
docker compose exec app composer <command>
docker compose exec app npm run dev   # Vite dev server
docker compose exec app npm run build # Production build
```

## Key Architecture Decisions

### Multi-tenancy
All business records have `organization_id`. All queries must scope by `where('organization_id', $user->organization_id)` or use the `forOrganization()` scope. Never return cross-tenant data.

### RBAC
9 roles via Spatie Permission. Use `$this->authorize()` in controllers, and `can('permission-name')` in Blade/Inertia. Do not hard-code role checks — use permission names.

### AI Provider
The `AiProviderInterface` is bound via `AiProviderFactory`. Default is `FakeAiProvider`. Controlled by `AI_PROVIDER` env var. Never assume a specific provider is active.

### External Integrations
`SAM_GOV_SYNC_ENABLED=false` and `BIDPRIME_SYNC_ENABLED=false` by default. When disabled, fake clients kick in automatically. Do not call real external APIs in tests.

### File Storage
All uploaded files go to `local` disk (private). Never use `public` disk for proposal files. Download via signed controller action only.

### Commission Calculation
Handled entirely in `CommissionCalculationService`. The `computeCommission()` method is pure (no DB) and unit-testable. Tiered calculation uses bracket arithmetic.

### Proposal Numbers
Generated in `ProposalNumberService` inside a `DB::transaction` with `lockForUpdate()`. Format: `QL-YYYY-NNNN`. Never bypass this service.

## Code Conventions

- Controllers: thin, delegate to Services. No business logic.
- Models: scopes, casts, relationships only.  
- Services: stateless business logic.
- No hard-coded secrets anywhere.
- PHP 8.4 enums with methods (`label()`, `color()`, `allowedTransitions()`).
- TypeScript strict mode. All `Props` interfaces typed.
- No comments unless WHY is non-obvious.
- `Str::ulid()` for public-facing IDs alongside bigint PKs.

## Database

MariaDB 10.11. Decimal columns (not float) for all monetary values. Soft deletes on all business records. Foreign keys enforced.

## Testing

Tests use `RefreshDatabase`. No mocking of DB. No real external API calls — fake providers handle all integration boundaries.

Test users all use password `password123!`.
