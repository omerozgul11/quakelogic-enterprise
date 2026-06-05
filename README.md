# QuakeLogic Enterprise — AI-Powered Bid Intelligence Platform

Enterprise-grade government contracting platform for bid intelligence, proposal management, capture management, CRM, commission tracking, and AI-assisted analysis.

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 12, PHP 8.4 |
| Frontend | React 19, TypeScript, Inertia.js |
| Styling | Tailwind CSS, Radix UI |
| Charts | Recharts |
| Auth | Laravel Fortify + Sanctum |
| RBAC | Spatie Laravel Permission |
| Search | Laravel Scout + Meilisearch |
| Queue | Redis |
| Storage | MinIO (S3-compatible) |
| Database | MariaDB 10.11 |
| Email (dev) | Mailpit |

## Quick Start

```bash
# 1. Clone and enter the project
cd quakelogic-enterprise

# 2. Copy environment file
cp .env.example .env

# 3. Start all services
docker compose up -d

# 4. Install dependencies + run migrations + seed
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
docker compose exec app npm run build   # or: npm run dev (inside app container)
```

**App:** http://localhost  
**Mailpit:** http://localhost:8025  
**Meilisearch:** http://localhost:7700  
**MinIO Console:** http://localhost:9001 (user: `minioadmin` / pass: `minioadmin`)

## Demo Credentials

All demo users share the password: `password123!`

| Role | Email |
|------|-------|
| Super Admin | admin@quakelogic.net |
| CEO | ceo@quakelogic.net |
| Business Development Manager | bdm@quakelogic.net |
| Proposal Manager | pm@quakelogic.net |
| Proposal Writer | writer@quakelogic.net |
| Capture Manager | capture@quakelogic.net |
| Sales Representative | sales@quakelogic.net |
| Finance | finance@quakelogic.net |
| Read Only | readonly@quakelogic.net |

## Key Features

### Opportunity Management
- Ingest from SAM.gov, BidPrime, and 15+ sources
- Canonical hash deduplication across sources
- NAICS code filtering, watchlists, competitor tracking

### Capture Management
- 8-stage FSM (Discovery → Execution)
- Risk register, task tracking, go/no-go reviews
- Stage history audit trail

### Proposal Management
- Auto-incrementing QL-YYYY-NNNN numbers
- Status workflow (Draft → In Review → Submitted → Awarded/Lost)
- Private file storage with signed download URLs
- Team member assignment, compliance matrices

### AI Assistant
- Pluggable provider architecture (Fake/OpenAI/Anthropic/Local LLM)
- Go/No-Go recommendation, win probability estimation
- Document extraction, proposal summary, compliance matrix generation
- Defaults to **Fake provider** — no API keys needed to boot

### CRM
- Agencies, Companies, Contacts, Consultants, Partners, Vendors
- Full-text search via Meilisearch
- Activity tracking, decision-maker flagging

### Commissions
- Percentage, fixed, and tiered bracket calculation
- Period-based reporting (YYYY-MM)
- Finance-role approval workflow

### REST API v1
- Laravel Sanctum token authentication
- Endpoints for opportunities, proposals, agencies, companies, contacts, commissions
- Paginated JSON responses

## Running Tests

```bash
docker compose exec app php artisan test
docker compose exec app php artisan test --parallel
docker compose exec app php artisan test --coverage
```

## Building for Production

```bash
# In the app container
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

Or use the production Docker image target:
```bash
docker build --target production -t quakelogic-enterprise:prod .
```

## Environment Variables

See `.env.example` for full documentation. Key variables:

| Variable | Default | Description |
|----------|---------|-------------|
| `AI_PROVIDER` | `fake` | AI backend: `fake`, `openai`, `anthropic`, `local` |
| `SAM_GOV_SYNC_ENABLED` | `false` | Enable live SAM.gov sync |
| `BIDPRIME_SYNC_ENABLED` | `false` | Enable live BidPrime sync |
| `SAM_GOV_API_KEY` | _(none)_ | SAM.gov API key (not needed for demo) |
| `OPENAI_API_KEY` | _(none)_ | OpenAI key (not needed for demo) |

## Architecture

See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) for system design details.
