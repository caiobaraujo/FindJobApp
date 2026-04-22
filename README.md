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
- Treat `Application` as the supporting tracker after a lead becomes active.

## Flow

- User registers or logs in.
- User discovers and centralizes job opportunities as job leads.
- User reviews source context, relevance, and lead status.
- User later tracks active applications in the secondary application tracker.
- The product is prepared for future ingestion and resume or cover-letter customization.

## Diagram

```text
+---------+      HTTP       +-------------------+      SQL       +--------+
| Browser | <-------------> | Laravel + Inertia | <------------> | MySQL  |
+---------+                 +-------------------+                +--------+
     |                               |
     | Vue pages + forms             | Auth, discovery CRUD, tracker CRUD
     v                               v
+------------------------+   +------------------------------+
| Job leads workspace    |   | JobLead + Application models |
| Discovery + review UI  |   | Requests / Controllers       |
+------------------------+   +------------------------------+
```

## Product Direction

- Discover relevant jobs across the internet.
- Centralize promising opportunities in one workspace.
- Help optimize applications with better source context and prioritization.
- Prepare for future resume and cover-letter customization.
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
