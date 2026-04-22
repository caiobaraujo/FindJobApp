# FindJobApp

## Overview

Discovery-first job search workspace built with Laravel, Vue, and Inertia.

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
- Treat `JobLead` as the core discovery entity.
- Treat `UserProfile` as the base resume and ATS matching entity.
- Treat `Application` as the supporting tracker after a lead becomes active.

## Flow

- User registers or logs in.
- User discovers and centralizes job opportunities as job leads.
- User maintains a resume profile with base resume text and core skills.
- User reviews source context, relevance, lead status, ATS keywords, and resume match gaps.
- User later tracks active applications in the secondary application tracker.
- The product is prepared for future ingestion and tailored resume or cover-letter customization.

## Diagram

```text
+---------+      HTTP       +-------------------+      SQL       +--------+
| Browser | <-------------> | Laravel + Inertia | <------------> | MySQL  |
+---------+                 +-------------------+                +--------+
     |                               |
     | Vue pages + forms             | Auth, discovery CRUD, resume matching
     v                               v
+------------------------+   +-------------------------------------------+
| Job leads workspace    |   | JobLead + UserProfile + Application models |
| Discovery + ATS review |   | Requests / Controllers / Match services    |
+------------------------+   +-------------------------------------------+
```

## Product Direction

- Discover relevant jobs across the internet.
- Centralize promising opportunities in one workspace.
- Help optimize resumes per lead with ATS-aware keyword extraction and deterministic resume matching.
- Prepare for future tailored resumes and cover-letter customization.
- Keep the application tracker as a supporting downstream feature.

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
