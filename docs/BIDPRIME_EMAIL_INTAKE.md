# BidPrime Email Opportunity Intake

Reads BidPrime **daily alert emails** from the `quakelogicenterprise@gmail.com` inbox over
IMAP (no BidPrime API), extracts the listed opportunities, deduplicates them, scores them
against QuakeLogic keyword groups, and imports them into the existing Opportunity pipeline
(`source = bidprime`). Mirrors the SAM.gov import flow.

## How it works

```
Gmail inbox (IMAP, App Password)
  └─ ImapGmailInboxClient.fetch()            read-only; sender/subject filtered
       └─ BidprimeEmail (raw HTML stored, per Gmail message-id)   ← audit + reprocess
            └─ BidPrimeEmailParser.extractOpportunities()         → BidSourceResultDTO[]
                 └─ BidPrimeImportService.ingestDto()             dedup + upsert
                      ├─ OpportunityDeduplicationService          (hash / external_id / solicitation #)
                      ├─ Opportunity (source=bidprime, status=new)
                      └─ OpportunityScorer.scoreAndStore()        relevance_score + priority + matched_keywords
```

Every opportunity links back to its source email (`bidprime_import_items.bidprime_email_id`),
and the raw email is preserved for re-parsing.

## Configuration (`.env`)

The IMAP credentials default to the app's existing Gmail mailbox + App Password
(`MAIL_USERNAME` / `MAIL_PASSWORD`), so usually you only set the toggle:

```env
GMAIL_INGEST_ENABLED=true            # off = fake fixture inbox (dev/tests)
# Optional overrides (defaults shown):
GMAIL_IMAP_HOST=imap.gmail.com
GMAIL_IMAP_PORT=993
GMAIL_IMAP_ENCRYPTION=ssl
GMAIL_IMAP_USERNAME=                 # falls back to MAIL_USERNAME
GMAIL_IMAP_APP_PASSWORD=             # falls back to MAIL_PASSWORD
GMAIL_IMAP_MAILBOX=INBOX
GMAIL_INGEST_SINCE_DAYS=3
GMAIL_BIDPRIME_FROM=bidprime.com,bidprime   # sender fragments that identify a BidPrime email
GMAIL_BIDPRIME_SUBJECT=              # optional subject fragments
```

The Gmail **App Password** is a 16-char code from the Google account
(Security → 2-Step Verification → App passwords). It works for both SMTP and IMAP.
When `GMAIL_INGEST_ENABLED` is off, the `FakeGmailInboxClient` (fixture emails) is used so
the whole pipeline runs in dev and tests without touching a live mailbox.

## Running it

- **Scheduled:** when enabled, `bidprime:ingest-email` runs daily at 06:15 (`routes/console.php`).
- **Manual:** `php artisan bidprime:ingest-email [--since=DAYS] [--limit=N] [--reprocess] [--organization=ID]`
- **Admin UI:** `/admin → BidPrime` (Super Admin) — *Import now*, *Reprocess last 7 days*,
  *Reprocess failed*, per-email reprocess, and Approve/Reject on each opportunity.

## Scoring & keyword groups

Relevance is computed from **admin-editable keyword groups** (`/admin → Keyword Groups`):
keyword strength (title weighted higher than body) × group weight, plus NAICS fit, due-date
urgency and estimated value; **exclusion** groups force *Not Relevant*. Output: a 0–100
`relevance_score`, a `priority` (High / Medium / Low / Not Relevant), `matched_keywords`, and
an explainable `score_breakdown`.

> BidPrime digest emails are **title-only** (no description/NAICS), so a single strong keyword
> match typically lands **Medium**. To promote a category to **High**, raise its group **weight**
> in the keyword-group editor.

## Deduplication & safety

- Dedup by canonical hash, `source + external_id` (BidPrime bid UUID), and cross-source
  solicitation number (flags `is_duplicate_flagged`, never overwrites).
- Re-imports and reprocessing are **idempotent** — no duplicate opportunities.
- Dedup-updates only fill **missing** fields, so manually-edited opportunities are never clobbered.
- IMAP access is **read-only**; emails are never modified or deleted.
- The credential is encrypted at rest and never logged or shown in the UI.

## Data model

| Table | Purpose |
|-------|---------|
| `bidprime_emails` | One row per ingested Gmail message: raw HTML/text, Gmail ids, parse status, error |
| `bidprime_imports` / `bidprime_import_items` | Per-run + per-opportunity import log (reused from the API importer) |
| `opportunity_keyword_groups` | Editable keyword/NAICS groups for scoring (+ exclusions) |
| `opportunities` | `relevance_score`, `priority`, `score_breakdown`, `matched_keywords` columns added |

## Troubleshooting

- **No emails fetched:** confirm `GMAIL_INGEST_ENABLED=true`, the App Password is valid, and
  the sender filter (`GMAIL_BIDPRIME_FROM`) matches (BidPrime sends from `no-reply@bidprime.com`).
- **Email parsed but 0 opportunities:** non-digest BidPrime mail (e.g. marketing) is stored as
  `no_opportunities` — harmless. Use *Reprocess* after parser updates.
- **A parse failed:** the email is kept with `status=failed` + the error; fix and use
  *Reprocess failed* on the dashboard.
- **Run against the fake inbox in production by mistake:** the command refuses unless
  `--allow-fake` is passed; the scheduler only registers when ingest is enabled.
