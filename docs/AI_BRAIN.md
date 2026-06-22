# QuakeLogic Brain — A Self-Hosted, Company-Wide AI

> **Status:** Architecture & strategy document (no code yet).
> **Decision on record:** Fully **in-house / maximum privacy** — everything runs on QuakeLogic's own
> hardware; no proposal data, documents, or prompts ever leave our servers.
> **Audience:** Leadership + engineering. This is the "how would we actually build this" answer.

---

## 1. The Vision

One assistant — **"QuakeLogic Brain"** — that lives across *every* app in the platform (Proposals,
Shipments, CRM, Inventory, Procurement, Manufacturing, Assets, Calibration, Service Desk, Finance,
Expenses). You should be able to:

- **Ask it anything** about the company and get a grounded, cited answer ("what did we bid on the Navy
  CNC job, and did we win?", "which suppliers can deliver a tensile tester in 4 weeks?").
- **Have it do things** — not just talk. "Draft the technical approach for QL-2026-0142", "mark the
  Helium leak detector proposal as submitted", "create a follow-up for the Akin IAEA bid", "build a
  datasheet from these photos".
- **Automate the writing** — proposals, datasheets, follow-up emails, summaries.
- **Power the search bar** — type a question, get an answer (not just a list of links).
- **Read our documents** — scrape every uploaded proposal/spec PDF, understand it, and answer from it.
- **Recommend work** — surface the most relevant past proposals and new opportunities to each user.

It is the **brain of the company**: it remembers everything we've ever written, and it can act inside
the apps on our behalf — safely, within each user's permissions, **entirely on our own infrastructure.**

---

## 2. The Good News: We Already Have ~60% of the Plumbing

This is not a from-scratch build. The platform already has a mature AI layer; the Brain is mostly
**extending** what's here, not replacing it. Concretely:

| Capability | Already exists | Where |
| --- | --- | --- |
| **Swappable AI provider** (single seam for the whole app) | ✅ singleton interface + factory that degrades to a fake when offline | `app/Services/Ai/AiProviderInterface.php`, `AiProviderFactory.php`, bound in `app/Providers/AppServiceProvider.php` |
| **Local/Ollama model support** | ✅ exists (text-only; needs embeddings finished) | `app/Services/Ai/LocalLlmProvider.php`, `config/ai.php` |
| **RAG pipeline** (chunk → embed → store → retrieve) | ✅ end-to-end, auto-syncing | `app/Services/Ai/KnowledgeBaseService.php`, table `document_embeddings`, `app/Observers/EmbeddingObserver.php`, `app/Jobs/ReindexEmbeddingJob.php`, `kb:embed` (nightly) |
| **Document text extraction** (PDF/DOCX/doc/txt) | ✅ robust | `app/Services/Documents/DocumentTextExtractionService.php` |
| **Chatbot** (RAG + live context grounded) | ✅ working (synchronous) | `app/Http/Controllers/Web/AiAssistantController.php::chat()`, `app/Services/Ai/PortalContextService.php` |
| **Proposal Writer / Datasheet Writer** | ✅ working | `app/Services/Proposals/ProposalWriterService.php`, `app/Services/Datasheets/DatasheetWriterService.php` |
| **Global search bar** | ✅ exists (raw SQL `LIKE`) | `resources/js/Components/layout/GlobalSearch.tsx` → `app/Http/Controllers/Web/SearchController.php` |
| **Search engine** (Meilisearch) | ⚠️ configured + populated, but **never queried** | `config/scout.php`, 5 `Searchable` models |
| **Module plug-in system** (drop-in apps) | ✅ self-describing manifests | `app/Support/Modules/ModuleRegistry.php`, `module.json` files |
| **RBAC + org isolation** | ✅ enforced, incl. in the AI layer | Spatie; `RolesPermissionsSeeder.php`; scoping in `PortalContextService` |

**Everything routes through one interface** (`AiProviderInterface`, a singleton). Swap what's behind it
and *every* AI feature — chat, writers, extraction, RAG — instantly runs on the new model. That single
seam is what makes "go fully in-house" tractable.

### The honest gaps we have to close

1. **The AI can't *do* anything yet.** It only generates text/JSON. Every write to the database is
   human-initiated. "Make it do things in our apps" is a **net-new agentic / tool-calling layer.**
2. **No real vector index.** Similarity search is an O(n) loop in PHP over ≤20,000 rows, per org
   (`KnowledgeBaseService::search()`). Fine for today; won't scale to a true company brain.
3. **The search bar isn't AI.** It's raw SQL `LIKE`. Meilisearch is populated but nothing reads from it.
4. **Module data isn't in the brain's memory.** Only the "flat" apps (proposals, opportunities, CRM) are
   embedded; Inventory/Procurement/Tickets/etc. are not.
5. **No streaming**, no conversation memory store, no KB ingestion UI.
6. **The big one — hardware.** Our server is **CPU-only (8 vCPU / ~16 GB RAM / no GPU).** It **cannot**
   run a capable model locally today. Going fully in-house *requires buying or renting a GPU.*

---

## 3. Target Architecture (Fully In-House)

```
                    ┌───────────────────────────────────────────────────────────┐
                    │                   QUAKELOGIC SERVERS                       │
                    │                  (nothing leaves here)                     │
                    │                                                            │
   Browser  ──────► │  nginx ─► Laravel (php-fpm)                                │
  (Inertia)         │            │                                              │
                    │            ├─ AiProviderInterface (singleton)  ───────────┼──► ┌─────────────────┐
                    │            │     • complete() / generateFromMedia()       │    │  INFERENCE BOX   │
                    │            │     • embed()                                │    │   (GPU host)     │
                    │            │                                              │    │                 │
                    │            ├─ AiBrain module (NEW)                        │    │  vLLM (OpenAI-   │
                    │            │     • Orchestrator + agent loop              │    │  compatible API)│
                    │            │     • Tool registry (per-module actions)     │    │   • Qwen2.5-32B  │
                    │            │     • Tool-call audit + confirmations        │    │   • Qwen2.5-VL   │
                    │            │                                              │    │   • bge / nomic  │
                    │            ├─ KnowledgeBaseService (RAG)  ────────────────┼──► │     (embeddings) │
                    │            │                                              │    └─────────────────┘
                    │            ▼                                              │
                    │   ┌──────────────┐  ┌──────────────┐  ┌───────────────┐  │
                    │   │   MariaDB    │  │  Meilisearch │  │   Qdrant (NEW)│  │
                    │   │ (records +   │  │  (lexical    │  │  (vector ANN  │  │
                    │   │  audit log)  │  │   search)    │  │   index)      │  │
                    │   └──────────────┘  └──────────────┘  └───────────────┘  │
                    │            └───────── hybrid retrieval ─────────┘         │
                    └───────────────────────────────────────────────────────────┘
```

Four layers, three of which already exist in some form:

1. **Inference layer (NEW, on a GPU host).** A self-hosted model server — **vLLM** (recommended) or
   **Ollama** (simpler) — exposing an **OpenAI-compatible API** for: text generation, vision
   (reading PDFs/photos), and embeddings. This is the only piece that needs new hardware.
2. **Provider layer (EXISTING).** A `VllmProvider` (or finishing `LocalLlmProvider`) makes the whole app
   talk to the in-house model through the existing `AiProviderInterface`. Flip `AI_PROVIDER` and every
   current feature runs on-prem.
3. **Brain layer (NEW — an `AiBrain` module).** The orchestrator: takes a user request, decides which
   **tools** to call, runs them (as the user, within permissions), feeds results back to the model, and
   returns an answer or confirmation. Plus conversation memory and a full audit trail.
4. **Retrieval layer (UPGRADE).** Add a self-hosted **Qdrant** vector database (a real approximate-
   nearest-neighbor index) and finally **use** Meilisearch for keyword search → combine them into
   **hybrid search**. `document_embeddings` stays as the re-embeddable source of truth.

---

## 4. What Model Do We Use, and How Do We Get It on the Server?

### 4.1 The hardware question (must be answered first)

Self-hosting a capable model is a **GPU problem**, and VRAM (GPU memory) is the limiting number — it
decides how big a model you can run.

| Tier | GPU (examples) | VRAM | What it runs well | Good for |
| --- | --- | --- | --- | --- |
| Entry | RTX 4090, L4, A10 | 24 GB | 7–14B quantized + an embedding model | a small team, fast responses |
| **Recommended** | RTX 6000 Ada, 2×4090, A100-40G | 40–48 GB | **32B** quantized (+ vision + embeddings) | company-wide, strong quality |
| High | A100-80G, H100 | 80 GB | **70B** quantized, long context | best quality, headroom |

> Our current production box is **CPU-only with no GPU** and **cannot** host any of these at usable speed.
> Going in-house means **adding one GPU server** (bought and racked, or rented from a provider where we
> control the instance). That is the single prerequisite for this whole plan.

### 4.2 The model recommendation (open-weight, self-hostable)

| Job | Recommended | Alternatives | Notes |
| --- | --- | --- | --- |
| **Generation** (chat, writing, reasoning, tool use) | **Qwen2.5-32B-Instruct** (AWQ/GPTQ quant) | Llama-3.3-70B-Instruct (more VRAM), Qwen2.5-14B (entry tier) | Native function-calling — required for the agentic layer. |
| **Vision** (read PDFs, spec sheets, photos) | **Qwen2.5-VL-7B/32B** | Llama-3.2-Vision | Powers Datasheet Writer + document understanding on-prem. |
| **Embeddings** (the brain's memory) | **bge-large-en-v1.5** or **nomic-embed-text** | Qwen3-Embedding, e5-large | Small, runs cheaply; replaces today's Gemini-API embeddings. |

Why open-weight Qwen/Llama: top-tier quality you can run **entirely offline**, permissive licenses,
first-class **tool-calling** support, and they're what the self-hosting ecosystem (vLLM/Ollama) is built
around. Honest caveat: a 32B open model trails a frontier API (Claude/GPT) on the hardest reasoning — we
close most of that gap with strong RAG and, later, fine-tuning on our own winning proposals (§10).

### 4.3 How the model gets onto the server (two concrete recipes)

First, one-time host setup: install an NVIDIA GPU driver + the **NVIDIA Container Toolkit** so Docker can
see the GPU. Then add an inference service to `docker-compose.yml`.

**Option A — vLLM (recommended: fastest, OpenAI-compatible, best throughput):**

```yaml
# docker-compose.yml (sketch — added later, not now)
  vllm:
    image: vllm/vllm-openai:latest
    command: >
      --model Qwen/Qwen2.5-32B-Instruct-AWQ
      --quantization awq --max-model-len 16384
    volumes:
      - hf_cache:/root/.cache/huggingface   # weights cached here
    environment:
      - HUGGING_FACE_HUB_TOKEN=${HF_TOKEN}
    deploy:
      resources:
        reservations:
          devices: [{ capabilities: ["gpu"] }]
    ports: ["8001:8000"]
```

On first start vLLM **downloads the weights from Hugging Face** into the mounted cache (or pre-stage them
with `huggingface-cli download Qwen/Qwen2.5-32B-Instruct-AWQ`). It then serves an OpenAI-compatible API at
`http://vllm:8000/v1`. Run a second small container for the embedding model (e.g. an
`text-embeddings-inference` service serving `bge-large-en-v1.5`).

**Option B — Ollama (simpler ops, slightly less throughput):**

```bash
# inside an ollama container with the GPU attached
ollama pull qwen2.5:32b-instruct      # generation model
ollama pull qwen2.5vl                  # vision model
ollama pull nomic-embed-text           # embeddings
```

Ollama serves at `http://ollama:11434`. **We already have a provider for this** —
`LocalLlmProvider` talks to Ollama's `/api/generate`; it just needs `embed()` implemented and a small
config fix (see below).

**Then wire the app to it** (config only, no provider rewrite needed):

```dotenv
AI_PROVIDER=local
LOCAL_LLM_URL=http://vllm:8000      # or http://ollama:11434
LOCAL_LLM_MODEL=qwen2.5-32b
```

> **Known fix to make first:** `.env.example` defines `LOCAL_LLM_HOST` but `config/ai.php` reads
> `LOCAL_LLM_URL` — the example value never reaches the provider. Align these. Also implement
> `LocalLlmProvider::embed()` (today it returns `[]`, which silently disables RAG on the local provider).

The moment this is wired, **the entire existing platform — chat, Proposal Writer, Datasheet Writer,
extraction, RAG — runs on our own model, on our own server, with nothing leaving the building.**

### 4.4 The bridge while we don't have a GPU yet

We can't run this on the current CPU box. Two privacy-preserving ways to start before owning hardware:

- **Rent a GPU instance we control** (a dedicated/bare-metal GPU host) and run vLLM there — still our
  infrastructure, ephemeral, no third-party model API. *(Recommended bridge.)*
- **Run a small model on CPU** now (e.g. an 8B via Ollama) just to build and test the integration —
  functional but slow and lower quality; fine for development, not for production load.

Either lets engineering build Phases 0–2 while the permanent GPU server is procured.

---

## 5. Making It *Act*: The Agentic Tool Layer (the biggest new capability)

Today the AI writes words. To "make it do anything in our apps," we add **function/tool calling** — the
model can request a typed action, we execute it, and we feed the result back. Modern open models
(Qwen2.5, Llama 3.1+) support this natively through vLLM's OpenAI-compatible API.

**How it works (the agent loop):**

```
user: "mark the Helium leak detector proposal as submitted and create a follow-up for next Friday"
  → Brain sends the message + the tool catalog to the model
  → model: call find_proposal(query="Helium leak detector")        ← read tool
  → Brain runs it (as this user, org-scoped) → returns QL-2026-0107
  → model: call update_proposal_status(id=107, status="submitted")  ← write tool (needs confirm)
  → Brain shows the user a confirmation chip → user clicks ✓ → executes
  → model: call create_follow_up(proposal=107, due="2026-07-03")
  → model: "Done — QL-2026-0107 is submitted and a follow-up is set for Fri Jul 3."
```

**Design principles (non-negotiable):**

- **Tools are typed, audited actions** — `find_proposals`, `summarize_document`, `draft_section`,
  `create_datasheet`, `update_proposal_status`, `create_lead`, `create_follow_up`,
  `get_dashboard_metrics`, etc. Read tools first (P2), write tools later (P3).
- **Modules describe their own tools.** Each `module.json` gains an `ai_tools[]` section; the Brain reads
  them via the existing `ModuleRegistry`. Drop in a module → its actions become available to the Brain.
  No core edits — same philosophy as the current plug-in system.
- **Every tool runs as the acting user, through existing policies + `organization_id` scope.** Reuse the
  exact `$user->can()` / policy pattern the controllers already use. The agent can **never** do something
  the user couldn't do by hand, and **never** sees another tenant's data.
- **Destructive / outward-facing actions require confirmation** (a preview the user approves) and/or a
  specific permission. Bounded loop (cap iterations). **Every tool call is logged** to a new
  `ai_tool_calls` audit table — who, what, inputs, result, reversible or not.

---

## 6. The Brain's Memory: RAG Upgrade

The retrieval pipeline exists (`KnowledgeBaseService`); we make it a real company brain:

- **Index everything.** Make `KnowledgeBaseService::KINDS` pluggable so each module contributes its
  records (inventory items, POs, work orders, tickets, invoices, assets, expenses) — not just proposals.
- **Real vector index.** Move ranking from the in-PHP cosine loop to a self-hosted **Qdrant** container
  (fast ANN, per-org filtering, scales to millions of chunks). Keep `document_embeddings` as the
  re-embeddable source of truth, so we can rebuild the index any time.
- **Self-hosted embeddings.** Point `embed()` at the in-house embedding model (§4.2). One re-embed pass
  (`kb:embed --fresh`) rebuilds the corpus under the new model. *(Embedding dimensions change between
  models, so this is a fresh index, not an in-place edit.)*
- **Already-solved parts we keep:** auto-sync on every record change (`EmbeddingObserver` →
  `ReindexEmbeddingJob` on the `ai` queue), the nightly backfill, and document text extraction.

---

## 7. AI in the Search Bar

The global search bar (`GlobalSearch.tsx` → `SearchController`) graduates from raw `LIKE` to:

- **Hybrid search** — Meilisearch (keyword/typo-tolerant; already populated, just needs to be *queried*)
  + Qdrant (semantic), fused by reciprocal-rank. Better results for both exact terms and fuzzy intent.
- **An "Ask the Brain" mode** — when the query is a question, route it to the Brain: a cited RAG answer,
  and optionally an action ("...want me to mark it submitted?"). The search box becomes a command bar.

---

## 8. Document Scraping & Proposal Recommendations

- **Scrape & understand our documents.** Text extraction already feeds the embedder; we expose full
  **semantic Q&A with citations** over every uploaded proposal/spec PDF ("what cooling spec did we quote
  on the plasma cutter?" → answer + the exact document/section it came from). Vision models read scans
  and drawings natively.
- **Recommend proposals to users.** A nightly job embeds each user's active work/keywords and matches
  the most relevant **past proposals** and **new opportunities** by vector similarity — surfaced in the
  existing "For You" area. Reuses the embedding infra and the established scheduled-job pattern
  (`EnrichProposalOrgsJob`, the `routes/console.php` schedule).

---

## 9. Streaming & UX

Add **SSE streaming** to chat (vLLM streams tokens) so answers appear as they're written, and show
**tool-call status** ("looking up QL-2026-0107…", "updating status…") so the agent's actions are
transparent and confirmable in real time.

---

## 10. Phased Roadmap

| Phase | Outcome | Key work |
| --- | --- | --- |
| **P0 — Foundation** | The whole existing platform runs on our own model, on our own server | GPU host + NVIDIA toolkit; vLLM/Ollama + embedding model; finish `LocalLlmProvider`/add `VllmProvider`; flip `AI_PROVIDER`; fix the `LOCAL_LLM_HOST`/`URL` config bug |
| **P1 — Memory** | Fast, scalable, all-app RAG, fully on-prem | Self-hosted embeddings; Qdrant container; hybrid search (wire Meilisearch); index module data; one re-embed pass |
| **P2 — Ask** | Agent answers anything with read-only tools; streaming chat | Orchestrator + tool registry; read tools (find/search/summarize); SSE streaming; conversation memory |
| **P3 — Act** | Agent performs actions across all apps, safely | Write tools (create/update) with confirmation + audit; per-module `ai_tools[]`; RBAC/policy enforcement in the tool layer |
| **P4 — Everywhere** | AI in the search bar; recommendations; automations | Search-bar Ask mode; proposal recommender; auto-draft/auto-datasheet/digests |
| **P5 — Sharpen** | Trustworthy, measured, tuned | Eval harness + guardrails + observability; optional fine-tune on QuakeLogic's winning proposals |

---

## 11. Risks, Cost & Honest Caveats

- **Hardware is the gate.** No GPU = no in-house model. Budget a GPU server (capex) or a controlled GPU
  rental (opex). Everything else waits on this.
- **Open models trail frontier APIs** on the hardest reasoning. Mitigations: run the largest model the
  GPU allows, lean hard on RAG (our data is the moat), and fine-tune on our own proposals in P5.
- **Agentic actions are powerful and dangerous.** Strict RBAC, confirmations for writes, full audit, and
  evaluation before we trust any write tool in production.
- **Ops burden.** Self-hosting means we own uptime, GPU drivers, model updates, and the vector DB. Worth
  it for the privacy guarantee, but it's real work.
- **Re-embedding cost.** Switching embedding models = one full re-index (a background job, but plan for
  the run time).

---

## 12. Concrete Change Map (for when we build — not now)

- `docker-compose.yml` — add `vllm` (or `ollama`) + a `qdrant` service, with GPU access.
- `config/ai.php` + `.env.example` — in-house provider settings, embedding model, Qdrant; **fix
  `LOCAL_LLM_HOST` vs `LOCAL_LLM_URL`**.
- `app/Services/Ai/` — new `VllmProvider` (OpenAI-compatible) **or** finish `LocalLlmProvider` (implement
  `embed()`).
- **New `app/Modules/AiBrain/`** — orchestrator, tool registry, agent loop; migrations for
  `ai_conversations` + `ai_tool_calls`; routes; permissions.
- `app/Services/Ai/KnowledgeBaseService.php` — pluggable module kinds + Qdrant backend.
- `app/Http/Controllers/Web/SearchController.php` + `resources/js/Components/layout/GlobalSearch.tsx` —
  hybrid search + Ask mode.
- `app/Http/Controllers/Web/AiAssistantController.php` — streaming + tool dispatch.

---

## 13. Getting Started This Week (the smallest first step)

1. **Decide GPU**: buy vs. rent (a controlled instance). This unblocks everything.
2. Stand up **vLLM + an embedding model** on it.
3. Point **`LocalLlmProvider`** (or a new `VllmProvider`) at it; implement `embed()`; set `AI_PROVIDER=local`.
4. Run **`kb:embed --fresh`** to rebuild memory under the local embeddings.

After that single step, QuakeBot, the Proposal Writer, the Datasheet Writer, and RAG are **all running
in-house** — and we build the agentic Brain (P2 onward) on top of a platform that already keeps every
byte of our data on our own servers.

---

*This document is a plan, not an implementation. No code or infrastructure has been changed. Nothing here
runs until we provision a GPU and build the phases above.*
