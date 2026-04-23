# Handoff

## Current Branch / Status Assumptions

- Current branch: `main`.
- Worktree was clean before creating this handoff.
- Latest verified checks before this handoff:
  - `php artisan test`
  - `npm run build`

## Verify First When Reopening

- Run `git status --short`.
- Confirm migrations are current with `php artisan migrate`.
- Run `php artisan test`.
- Run `npm run build`.
- Check `/resume-profile`, `/matched-jobs`, and `/job-leads/create`.

## Quick Start Commands

```bash
make setup
php artisan serve
npm run dev
```

## Main User Value Today

- Resume-first setup.
- URL-first job lead intake.
- Pasted job description keyword extraction.
- Matched Jobs comparison:
  - matched keywords
  - missing keywords
  - source URL
  - "Go to job"

## Known Bugs / Fragile Areas

- URL-only intake does not parse job content.
- Users may expect LinkedIn URL keyword extraction; UI now states this does not happen yet.
- PDF, DOC, and DOCX uploads persist files but do not extract text.
- Matching can only work from persisted resume text or core skills.
- Matched jobs can look empty when leads lack `extracted_keywords`.
- Keep auth ownership checks strict on `JobLead`, `UserProfile`, and `Application`.

## Latest Architectural Decisions

- Product direction is resume-first matched jobs.
- `JobLead` is the primary discovery entity.
- `Application` tracking is secondary.
- URL-only intake saves a lead only.
- Pasted job description enables ATS keyword extraction now.
- Matching is deterministic and local.
- Multilingual UI supports `pt`, `en`, and `es`.
- No scraping, browser extension, external APIs, or AI integrations yet.

## If You Continue Tomorrow, Start Here

- Start with `JobLeadController::store`.
- Add a local raw job content parsing path after URL intake.
- Preserve current URL-only behavior when no raw content is available.
- Keep tests around:
  - URL-only creates no fake keywords.
  - pasted description creates keywords.
  - matched jobs shows honest missing-analysis states.
