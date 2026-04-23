# Project Status

## Product Vision

- Build a resume-first matched jobs product.
- Help users find jobs beyond LinkedIn-only workflows.
- Help users optimize their resume per job lead.
- Keep manual application tracking as a secondary workflow.

## Current Product Flow

- User signs up or logs in.
- User uploads a resume or pastes resume text.
- User adds a job lead from a URL.
- URL-only intake saves the lead.
- Pasted job description extracts ATS keywords today.
- Matched Jobs compares job keywords with persisted resume/profile data.
- User reviews matched and missing keywords.
- User opens the original job URL with "Go to job".

## What Already Works

- Authentication with Laravel Breeze-style auth.
- Resume upload for `pdf`, `doc`, `docx`, and `txt`.
- Resume file metadata persistence.
- Text extraction for uploaded `.txt` resumes.
- Resume text fallback.
- Detected resume skills from profile skills and resume text.
- Job lead CRUD.
- URL-first job lead intake.
- Optional pasted job description analysis.
- Deterministic keyword extraction.
- Deterministic resume-to-job keyword matching.
- Matched Jobs page with matched and missing keywords.
- Multilingual main UI support for `pt`, `en`, and `es`.
- Secondary application tracker with CRUD and Kanban status updates.
- Structured JSON logging with request context.

## What Does Not Work Yet

- No scraping or external job page fetching.
- No keyword extraction from URL-only intake.
- No PDF, DOC, or DOCX text extraction.
- No browser extension.
- No AI resume tailoring.
- No generated resume files.
- No external job source integrations.

## Current Technical Architecture

- Backend: Laravel 12.
- Frontend: Vue 3 with Inertia.
- Styling: Tailwind CSS.
- Database: MySQL.
- Tests: Pest feature and unit tests.
- Auth: Laravel auth routes and authenticated route groups.
- Matching: deterministic PHP services, no external APIs.
- Logging: JSON structured logs with request IDs.

## Main User-Facing Routes

- `/dashboard`: resume-first onboarding and matched jobs CTA.
- `/matched-jobs`: primary matched jobs workspace.
- `/job-leads/create`: URL-first job intake.
- `/job-leads/{jobLead}/edit`: job lead details, ATS analysis, and match analysis.
- `/resume-profile`: resume upload/setup.
- `/resume-profile/create`: create resume fallback.
- `/applications`: secondary application tracker.
- `/profile`: account profile settings.

## Key Data Concepts

## UserProfile

- One profile per user.
- Stores resume setup data and core skills.
- Stores uploaded resume metadata.
- `base_resume_text` powers matching when available.
- Uploaded `.txt` files can populate `base_resume_text`.
- Uploaded `pdf`, `doc`, and `docx` files are stored but not parsed yet.

## JobLead

- Core discovery entity.
- Belongs to one user.
- Stores source URL, company, title, optional full job description, ATS keywords, and hints.
- `source_url` is required for reduced-friction intake.
- `description_text` is optional but required for keyword extraction today.
- `extracted_keywords` powers matched jobs.

## Applications

- Secondary workflow.
- Belongs to one user.
- Tracks company, title, source URL, status, applied date, and notes.
- Supports CRUD, filters, and Kanban status updates.

## Current Limitations

- URL-only leads do not create keywords.
- Matching requires resume/profile data and job extracted keywords.
- Non-text resume uploads need manual text fallback before matching can work.
- Job intake still depends on users pasting job text for ATS signals.
- Applications exist but are not the main product loop.

## Important Implementation Notes

- Keep all user data scoped by authenticated user ID.
- Keep `JobLead` as the discovery entity.
- Keep `Application` secondary.
- Do not fake keyword extraction from URLs.
- Do not call external APIs in matching or intake.
- Use Form Requests for validation.
- Keep functions small.
- Prefer early returns.
- Avoid duplicated UI copy and logic.
- Run tests after changes.

## Commands To Run Locally

```bash
make setup
php artisan serve
npm run dev
php artisan test
npm run build
```

## Current Recommended Next Step

- Add raw job content parsing to the URL-first intake flow.
- Keep it deterministic and local first.
- Do not add scraping, browser extension, external APIs, or AI yet.
