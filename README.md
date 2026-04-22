# FindJobApp

## Overview

Resume-first job matching workspace built with Laravel, Vue, and Inertia.

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

- Discover relevant jobs across the internet.
- Let users paste one base resume and get value faster.
- Surface matched jobs with clear overlap and gap signals.
- Prepare for future tailored resumes and cover-letter customization.
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
