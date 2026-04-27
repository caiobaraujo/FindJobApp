# Handoff

## Product Vision

- Resume-first job matching app.
- Goal: save time for job seekers by collecting jobs from across the internet, matching them against the user resume, ranking them, and letting the user click "Go to job".
- Future goal: Chrome extension for capturing jobs, but not yet.

## Tech Stack

- Laravel 12
- Vue 3
- Inertia
- MySQL
- Breeze-style auth
- Tailwind
- Pest
- Vite

## Current Implemented Features

- User auth
- Resume profile
- Resume upload and text extraction
- `.txt` extraction
- `.pdf` extraction with local `pdftotext` when available
- `.docx` extraction via `ZipArchive`
- `.doc` stored as fallback-only
- JobLead CRUD
- URL-first job intake
- Optional pasted job description
- Deterministic keyword extraction
- ATS hints
- Matched jobs page
- Deterministic resume/job matching
- Ranking by match quality
- `JobLead` to `Application` conversion
- JobLead deletion
- Canonical source metadata: `normalized_source_url` and `source_host`
- Same-user deduplication by `normalized_source_url`
- Global i18n using `useI18n()`
- Locales: `en`, `pt`, `es`
- Filters for `lead_status`, `analysis_state`, `work_mode`
- Card badges for match quality and analysis state
- Quick actions on job cards using `lead_status`

## Important Product Decisions

- No scraping yet
- No AI yet
- No external APIs yet
- No browser extension yet
- `JobLead` is the discovery entity
- `Application` is secondary
- `UserProfile` powers matching
- Keep URL-only leads valid and honest
- Do not invent keywords or missing data
- Keep all user data scoped to authenticated user

## Current Architecture Notes

- Laravel `lang` files remain the translation source of truth
- Inertia shares `locale`, `availableLocales`, and `translations` globally
- Frontend uses `resources/js/composables/useI18n.js`
- Matching and keyword extraction are deterministic/local
- `source_url` remains the outbound "Go to job" link
- `normalized_source_url` supports dedupe-ready behavior

## Latest Completed Task

- Added quick actions on `JobLead` cards: `saved`, `shortlisted`, `applied`, `ignored`
- Uses the existing update route with partial status update
- Keeps the user on the same page
- Tests passed: 136 tests / 1181 assertions
- `npm run build` passed

## Recommended Next Product Step

Implement a daily-use focus workflow:

- Prioritize active and not ignored leads
- Deprioritize or hide ignored leads by default
- Allow showing ignored leads
- Show simple counters: active leads, ignored leads, applied leads
- Reuse `lead_status`
- Do not add complex new pages
- Do not add AI, scraping, or external APIs

## Commands

```bash
php artisan test
npm run build
php artisan serve
npm run dev
```
