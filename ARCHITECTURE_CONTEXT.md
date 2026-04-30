# Architecture Context

This is the canonical architecture and product context file for FindJobApp.

It must be treated as the single source of truth for:

- product direction
- current phase
- architectural rules
- discovery behavior
- validation workflow

Do not rely on other markdown files for architecture decisions.

---

## Product Phase

Current phase:

- Validate discovery volume.
- Validate lead usefulness.
- Ensure the system discovers enough real opportunities to justify its value.

This phase is NOT about adding features. It is about measuring whether discovery works.

Success is defined by:

- Sufficient number of useful `JobLead` records discovered
- Clear understanding of which sources produce leads
- Clear understanding of which leads are hidden or low-quality
- Deterministic explanation of system behavior

---

## Product Direction

FindJobApp is a resume-first job discovery platform.

Core idea:

- Users do not search for jobs — the system discovers them
- The system aggregates hard-to-find opportunities
- The system evaluates these opportunities against the user's resume

Principles:

- Discovery is the primary value
- Matching supports evaluation, not discovery
- The primary user interaction is search-first discovery
- Workspace filtering must stay clearly separate from discovery
- Increasing Brazilian/national technology job volume is now a priority discovery goal
- Discovery should include both resume-matched leads and broader IT opportunities
- Unmatched IT leads are still valuable discovery output
- A future workflow may adapt or generate resume/application material from a selected `JobLead`, but that is not part of the current phase
- Applications are secondary
- No AI should be introduced in the current phase unless explicitly approved later

---

## Core Domain Model

### UserProfile

- Single resume-first entity per user
- Stores:
    - resume text (`base_resume_text`)
    - core skills
    - preferences (`target_roles`, `preferred_locations`, `preferred_work_modes`)
- Tracks discovery runs:
    - `last_discovered_at`
    - `last_discovered_new_count`
    - `last_discovery_batch_id`
- Deterministic resume-derived discovery signals are computed from stored resume text and explicit core skills only
- These signals may include:
    - canonical skills
    - role family tags
    - alias terms
    - query profiles for future discovery expansion

---

### JobLead

Central entity of the system.

- Belongs to a user
- Represents a discovered or manually saved opportunity
- Always valid, even with minimal data

May contain:

- source metadata
- description text
- extracted keywords
- ATS hints
- ranking signals
- discovery batch grouping

Important:

- URL-only leads must remain valid
- The system must never invent missing data
- Extracted keywords must come from deterministic taxonomy-based analysis of job-specific text only
- Contextual-only keyword sets such as `api`, `data`, `cloud`, or `backend` without stronger stack-specific evidence must be treated as limited analysis
- Workspace keyword explanations may apply deterministic display labels and redundancy suppression without changing canonical stored or matched keyword values

---

### Application

Secondary entity.

- Created after a lead becomes relevant
- Must not drive architecture decisions

---

## Non-Negotiable Constraints

- Matching must remain deterministic and explainable
- JobLead is the central entity
- Application is secondary
- URL-only leads must remain valid and honest
- Do not invent job data or keywords
- All data is user-scoped
- No AI features in the current phase
- Do not introduce paid, opaque, or AI-based external APIs
- Discovery must be deterministic and testable with fixtures
- Matched and missing job keywords must be computed from canonical taxonomy-based resume signals, not raw substring matching against resume text

---

## Discovery Rules

Allowed:

- deterministic, source-specific fetching/parsing
- HTML parsing
- RSS/XML parsing
- curated career page parsing
- controlled detail-page fetching (only when explicitly implemented)

Not allowed:

- arbitrary scraping of user-provided URLs
- AI enrichment
- opaque ranking logic
- uncontrolled external integrations

Definition:

"Scraping" is allowed only when:

- tied to a specific known source
- deterministic
- test-covered
- explainable

---

## Matching Rules

- Job keyword analysis and resume keyword analysis must share the same deterministic technical taxonomy
- Resume comparison signals are built from:
    - canonical technical keywords extracted from stored resume text
    - canonical technical keywords derived from explicit core skills
    - deterministic role-family expansions such as `javascript`, `frontend`, `backend`, `database`, and `ai_applied` when supported by canonical resume evidence
- Display labels are applied only after canonical matched and missing keyword sets are computed
- Ambiguous terms such as `go` must only match when technical context is present
- Generic contextual terms must not create misleading matched or missing keyword explanations by themselves

---

## Discovery Pipeline

Main entry point:

- `App\Http\Controllers\JobLeadController::discover()`

Core services:

- `App\Services\JobDiscovery\JobLeadDiscoveryRunner`
- `App\Services\JobLeadImportService`
- `App\Services\JobDiscovery\JobDiscoveryQueryMatcher`

Flow:

1. Controller starts a discovery run
    - manual discovery starts from a simple user search query
2. A `discovery_batch_id` is generated
3. Runner selects enabled sources from config
4. For each source:
    - fetch entries
    - enrich entries if needed
    - apply deterministic query matching
    - normalize simple natural-query intent deterministically
    - when a user provides a manual discovery query, expand eligibility with applicable deterministic resume-derived query profiles
    - import via JobLeadImportService
5. Import service:
    - normalizes URLs
    - deduplicates per user
    - stores leads
6. Discovery run metadata is stored in UserProfile

---

### Deduplication

- Per-user deduplication based on `normalized_source_url`
- Query strings and fragments are ignored
- This may collapse distinct job variants that differ only by query parameters
- Tradeoff: reduces noise but may reduce recall

---

## Source Inventory

Implemented:

- PythonJobBoard
- DjangoCommunityJobs
- WeWorkRemotely
- Remotive
- LaraJobs
- CompanyCareerPages
- BrazilianTechJobBoards
- GupyPublicJobs

`CompanyCareerPages` is now a curated Brazil-first source set, not generic crawling.

- Fixed company targets only
- Fixed career page URLs only
- Deterministic target parser strategies only:
    - `structured_lists`
    - `ats_board`
- Current curated targets:
    - Nubank
    - iFood
    - Mercado Livre
    - VTEX
    - Stone
    - PagBank
    - Hotmart
    - QuintoAndar
    - Grupo OLX
    - Magazine Luiza
- Import eligibility remains strict:
    - job title required
    - job URL required
    - company name required

`BrazilianTechJobBoards` is a curated Brazil-first public board source set, not generic scraping.

- Fixed public listing URLs only
- Fixed platform parser strategies only:
    - `programathor_cards`
    - `remotar_cards`
- Detail-page enrichment for supported boards must prefer job-specific description sections over whole-page text when available
- Import eligibility remains strict:
    - job title required
    - job URL required
    - company name required
- Current curated platforms:
    - ProgramaThor
    - Remotar

`GupyPublicJobs` is a curated Brazil-first public Gupy source set, not generic scraping.

- Fixed public Gupy listing URLs only
- Fixed curated company targets only
- Deterministic parser strategy only:
    - `gupy_listing`
- Detail-page enrichment is allowed for matched curated candidate URLs and should prefer job-specific content sections over page chrome
- Import eligibility remains strict:
    - job title required
    - job URL required
    - company name required from the curated target or explicit page evidence
- Current curated targets include:
    - Afya
    - Omie
    - FCamara
    - Minsait
    - Global Hitss
    - Gaudium
    - Montreal
    - CIGAM
    - Positivo Tecnologia
    - Gran
    - JBS

Enabled by default:

- python-job-board
- django-community-jobs
- we-work-remotely
- larajobs
- company-career-pages

Enabled by default in local/development:

- brazilian-tech-job-boards

Local enablement is controlled by:

- `JOB_DISCOVERY_ENABLE_BRAZILIAN_TECH_JOB_BOARDS=true|false`
- `JOB_DISCOVERY_ENABLE_GUPY_PUBLIC_JOBS=true|false`

Disabled but available:

- remotive
- gupy-public-jobs

Fixture mode:

- larajobs
- company-career-pages
- brazilian-tech-job-boards
- gupy-public-jobs

`company-career-pages` fixture mode now uses the curated Brazil-first target set above and deterministic per-company HTML fixtures so import counts, deduplication, and per-target diagnostics remain repeatable.

`brazilian-tech-job-boards` fixture mode now uses deterministic ProgramaThor and Remotar HTML fixtures so import counts, deduplication, per-platform diagnostics, and resume-derived query-profile matching remain repeatable.

`gupy-public-jobs` fixture mode now uses deterministic curated Gupy listing and detail HTML fixtures so import counts, deduplication, per-company diagnostics, and detail-enrichment behavior remain repeatable.

The curated Gupy parser should keep broader deterministic IT roles when the public listing text is explicit, including infrastructure, SAP, information security, field service, IT operations, and access-management roles. It must still reject generic non-technology business roles from the same public boards.

The curated live Gupy inventory must stay pinned to current official public job-board subdomains. When a company moves from an outdated public subdomain to a new official one, the curated target should be updated directly rather than left to accumulate noisy failed diagnostics.

Fixture-only negative Gupy targets may remain visible in diagnostics to preserve deterministic missing-company coverage, but URL-only negative targets must not drive keep/review/deprioritize recommendations as if they were real curated companies.

---

## Workspace Behavior

The workspace is a computed view over JobLead.

The workspace now has three deterministic views over the same `JobLead` records:

- matched leads
- job leads
- unmatched technology leads

This distinction is presentational only. It does not change import behavior, ranking, source parsing, or `JobLead` validity.

The workspace is now search-first:

- `Search new jobs` triggers discovery against configured sources
- `Refine discovered leads` filters existing `JobLead` records only
- filtering must not be confused with source discovery
- unmatched technology leads remain visible and useful even when they do not overlap with the current resume

Filters include:

- ownership
- discovery batch
- lead group
- work mode
- search
- location scope

Natural-query workspace filtering now supports a small deterministic intent layer:

- canonical technology keywords
- basic `or` intent
- `brazil` location intent
- `remote`/`hybrid`/`onsite` work mode intent

This same deterministic intent normalization is reused for manual discovery queries.

Ranking prioritizes:

- active leads
- analyzed leads
- resume overlap
- keyword matches
- fewer missing keywords
- preference fit
- recency

---

### Brazil-first Behavior

- Default location scope = Brazil
- International leads are hidden by default
- This can distort perceived discovery volume

---

### Latest Discovery Batch

- `discovery_batch=latest` resolves to last run
- Forces:
    - location scope = ALL
    - ignored leads visible
    - filters cleared

This represents the true discovery output.

For matched-jobs workspace diagnostics, the UI now also exposes a deterministic latest-discovery funnel from imported `JobLead` records to visible matched jobs:

- total leads imported in the latest discovery batch
- imported leads not considered matched
- matched leads before default hiding
- visible matched leads under the current workspace filters
- sequential hidden counts for:
    - ignored-by-default
    - international hidden by Brazil-first scope
    - lead status filter
    - analysis readiness filter
    - analysis state filter
    - work mode filter
    - search text filter

These counts are diagnostic only. They do not change import behavior, matching logic, ranking, or source parsing.

For both matched and broader discovered-lead views, the UI now also exposes a deterministic latest-discovery workspace split:

- total discovered leads in the latest batch
- matched leads in the latest batch
- unmatched discovered leads in the latest batch
- visible matched leads under the current workspace filters
- visible unmatched leads under the current workspace filters
- hidden international leads under Brazil-first defaults

This makes it explicit that discovery output is broader than the currently matched subset.

---

## Validation & Diagnostics

Commands:

```bash
php artisan job-leads:discover
php artisan job-leads:discover-all
php artisan discovery:calibrate
php artisan discovery:diagnose
php artisan discovery:inspect-source
php artisan discovery:diagnose --brazil --fixture
```

Tests cover:

- source parsing
- query matching
- discovery commands
- UI discovery flow
- deterministic resume-derived discovery signal mapping
- deterministic taxonomy-based technical keyword extraction

Regular workspace discovery now also exposes, per source and per discovery batch, deterministic observability from existing `JobLead` fields only:

- imported lead count
- deduplicated lead count
- leads hidden by default workspace filters
- visible-by-default leads
- ready-analysis and limited-analysis counts
- missing-description and missing-keyword counts

These metrics are emitted in the discovery flash payload and structured discovery logs without changing import behavior.

`JobLead` keyword extraction now uses a deterministic general technology taxonomy instead of a short example-led allowlist.

- The taxonomy is grouped by categories such as:
    - programming languages
    - frontend frameworks
    - backend frameworks
    - databases
    - cloud
    - devops
    - testing
    - data
    - mobile
    - architecture
    - AI/ML
- Canonicalization is alias-driven, for example:
    - `go`, `golang` -> `go` only when technical context is present
    - `node.js`, `nodejs`, `node js` -> `nodejs`
    - `vue.js`, `vuejs` -> `vue`
    - `react.js`, `reactjs` -> `react`
    - `postgres`, `postgresql` -> `postgresql`
    - `ci/cd`, `cicd` -> `ci_cd`
    - `domain-driven design`, `ddd` -> `ddd`
- Extraction prefers upstream description cleanup first:
    - strip raw URLs
    - strip image file references
- Extracted terms are classified by signal quality:
    - strong: specific technologies, frameworks, tools, databases, languages, and cloud products
    - contextual: broad role or domain terms such as `api`, `data`, `cloud`, `backend`, `frontend`, `testing`, and `automation`
    - weak: non-technical or low-signal terms that should not explain a lead by themselves
- Strong specific terms suppress broader parent terms in match explanations when appropriate, for example:
    - `rest_api` over `api`
    - `data_engineering` over `data`
    - `postgresql` or `mysql` over generic database context
- Leads with only contextual or weak terms must remain valid `JobLead` records but are considered limited keyword analysis and should not look like strong resume matches
    - strip obvious page/schema/analytics/script tokens
- A small deterministic safety net removes obvious page or JavaScript chrome noise, but extraction is not driven by one-off blacklists

Resume profile inspection now also exposes deterministic resume-derived discovery signals from `UserProfile` input only:

- canonical skills detected from resume text and explicit core skills
- role family tags derived from observed canonical skills
- alias terms for configured technologies
- query profiles for future discovery expansion

These signals do not change `JobLead` import behavior and do not invent missing resume skills.

Manual discovery search now also uses applicable resume-derived query profiles as deterministic query expansion.

- The user-entered `search_query` remains the primary explicit query
- Resume-derived query profiles are used only when they intersect the user query
- A lead may become eligible when the explicit query misses a role-family synonym but an applicable profile matches deterministic skill terms
- Default no-query discovery remains unchanged
- Source observability remains source-scoped and compatible with existing diagnostics

`discovery:diagnose` now measures, per scenario batch and per source:

- imported lead count
- deduplicated lead count
- leads hidden by default workspace filters
- limited-analysis and missing-analysis signal counts
- basic usefulness indicators from existing deterministic fields only

For `company-career-pages`, `discovery:diagnose` also measures per curated target:

- fetched candidate count
- matched candidate count
- imported lead count
- deduplicated count
- skipped-by-query count
- hidden-by-default count
- international-hidden count
- query-skip rate
- import rate
- deterministic bucket:
    - `strong`
    - `promising`
    - `weak`
    - `no-signal`
- deterministic recommendation:
    - keep
    - review
    - deprioritize
    - investigate

The target diagnostics also preserve the configured parser strategy for each curated company target.

For `brazilian-tech-job-boards`, source diagnostics now also preserve per-platform target metrics:

- target platform
- fetched candidate count
- matched candidate count
- imported lead count
- deduplicated count
- skipped-by-query count
- skipped-expired count
- skipped-missing-company count
- failed count

`discovery:diagnose --fixture` now forces the fixture-backed source set and fixture career targets deterministically from the command flag.

`discovery:diagnose --brazil --fixture` now provides a deterministic Brazil-first calibration report across:

- `company-career-pages`
- `brazilian-tech-job-boards`
- `gupy-public-jobs`

The Brazil calibration scenarios now include:

- `no query`
- `python`
- `javascript`
- `frontend`
- `backend`
- `remoto`
- `data`
- `devops`

The Brazil calibration report now summarizes, per source:

- fetched candidate count
- parsed candidate count
- matched candidate count
- imported lead count
- deduplicated count
- skipped-by-query count
- skipped-missing-company count
- skipped-expired or closed count
- failed count
- hidden-by-default count
- limited-analysis count
- missing-description count
- missing-keyword count

The same report now also summarizes, per curated target or platform when available:

- source key
- target or company or platform name
- parser strategy
- fetched candidate count
- matched candidate count
- imported lead count
- deduplicated count
- skipped-by-query count
- skipped-missing-company count
- skipped-expired or closed count
- failed count
- import rate
- query-skip rate
- deterministic bucket:
    - `strong`
    - `promising`
    - `weak`
    - `no-signal`
- deterministic recommendation:
    - `keep`
    - `review`
    - `deprioritize`
    - `investigate`

`discovery:diagnose` also now accepts repeated `--query` values so fixture-backed calibration runs can stay deterministic while focusing on a smaller scenario subset without network access.

`discovery:inspect-source` now provides a no-import dry run for one source so local development can distinguish:

- page fetch failures
- zero parsed candidates
- query skips
- expired skips
- missing-company skips
- detail enrichment successes and failures

For multi-target deterministic sources, the top-level listing HTTP status must stay `200` when at least one curated target fetch succeeds. Individual target failures remain visible in the per-target `failed` counts instead of downgrading the whole source run to a misleading `404`.

Use it for Brazilian board diagnosis with commands like:

```bash
php artisan discovery:inspect-source brazilian-tech-job-boards --query=laravel
php artisan discovery:inspect-source brazilian-tech-job-boards --query=frontend --resume-text="Vue.js engineer" --skill=Vue.js
php artisan discovery:inspect-source gupy-public-jobs --query=python
```

---

### Current Limitation

The system still does NOT fully measure:

- cross-run usefulness trends outside explicit diagnostics runs
- long-term source usefulness history
- usefulness beyond existing deterministic fields

Improving these measurements is the current priority.

---

## Known Bottlenecks

- Limited number of enabled sources
- Brazilian/national source coverage is still small even after curated expansion
- Strict parsers reduce recall
- Company page coverage depends on manual curation
- Deduplication may collapse valid variants
- Preferences do not guide discovery
- Brazil filter hides valid leads
- Limited-analysis leads reduce match visibility

---

## Current Next Step

Increase deterministic Brazilian/national discovery volume BEFORE:

- introducing broad scraping
- relaxing import honesty rules
- changing ranking logic

Focus on:

- adding curated Brazil-first sources
- measuring leads per source
- measuring leads per curated target or platform
- measuring hidden leads
- comparing source effectiveness

---

## Evolution Rule

Do not expand discovery recklessly before measuring it properly.

Measurement must come before expansion.

---

## Agent Roles

Product Owner:

- approves direction

GPT Architect:

- identifies bottleneck
- defines next step
- generates Codex prompts

Codex:

- implements
- tests
- reports

Codex must not redefine product direction unless explicitly asked.

---

## Daily AI Workflow

### GPT Chat

- read this file
- identify bottleneck
- propose next step
- generate Codex prompt

---

### Codex Session

1. Read:
    - README.md
    - AGENTS.md
    - ARCHITECTURE_CONTEXT.md

2. Execute only assigned task

3. After execution:
    - run tests
    - report results
    - update this file if needed

---

## Commands

```bash
bin/setup
php artisan test
npm run build
php artisan serve
npm run dev
```

---

## Documentation Rules

- This is the only architecture source of truth
- Do not create parallel markdown files

Update this file when:

- discovery behavior changes
- sources change
- constraints change
- commands/tests change
- product phase changes
- current next step changes

Do NOT update for minor refactors
