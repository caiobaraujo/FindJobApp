# FindJobApp

## Overview

Resume-first job discovery platform built with Laravel, Vue, and Inertia. The current focus is finding useful job opportunities users would not easily surface through their normal search flow, then layering deterministic matching on top of those leads.

## Tech Stack

- Laravel 12
- PHP 8.2
- Vue 3
- Inertia.js
- Tailwind CSS
- MySQL
- Pest
- Vite

## Architecture

- Use Laravel for routing, auth, validation, and persistence.
- Use Inertia as the server-client bridge.
- Use Vue pages and components for the UI.
- Use MySQL for application data.
- Keep all records scoped to the authenticated user.
- Treat `UserProfile` as the primary automation input.
- Treat `JobLead` as the discovery entity that gets filtered into matched jobs.
- Treat `Application` as a secondary workflow, not the core product path.

## Flow

- User registers or logs in.
- User pastes a base resume.
- The app compares resume content against job leads.
- The app shows matched jobs with matched and missing keywords.
- The user jumps directly to the source listing from each matched job card.
- Application tracking remains available as a future or secondary workflow.

## Diagram

```text
+---------+      HTTP       +-------------------+      SQL       +--------+
| Browser | <-------------> | Laravel + Inertia | <------------> | MySQL  |
+---------+                 +-------------------+                +--------+
     |                               |
     | Vue pages + forms             | Auth, resume matching, lead filtering
     v                               v
+------------------------+   +-------------------------------------------+
| Resume + matched jobs  |   | JobLead + UserProfile + Application models |
| Faster matching UX     |   | Requests / Controllers / Match services    |
+------------------------+   +-------------------------------------------+
```

## Product Direction

- Discover hard-to-find job leads from deterministic, explicitly configured sources.
- Let users save one resume profile and evaluate discovered leads quickly.
- Use deterministic keyword matching to show overlap and gaps without opaque scoring.
- Validate discovery volume and lead usefulness before expanding product scope.
- Keep application tracking secondary to discovery and matching.

## Run

```bash
make setup
php artisan serve
npm run dev
```

## Test

```bash
make test
```

## E2E Smoke Test

The repo includes one minimal Playwright smoke test for the discovery UI flow.

Run it locally with:

```bash
php artisan serve
npm run dev
npm run test:e2e
```

Notes:

- The E2E setup prepares a deterministic local user: `e2e@example.com` / `password`.
- The Playwright run starts the app in fixture-backed discovery mode so the browser flow does not depend on live external job boards.
- Playwright saves screenshots, traces, videos, and an HTML report on failures.
