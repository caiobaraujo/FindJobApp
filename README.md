# FindJobApp

## Overview

Laravel + Vue + Inertia app for tracking job applications.

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

- Use Laravel for routing, auth, validation, policies, and persistence.
- Use Inertia as the server-client bridge.
- Use Vue pages and components for the UI.
- Use MySQL for application data.
- Keep all application records scoped to the authenticated user.

## Flow

- User registers or logs in.
- User lands on the dashboard.
- User creates, edits, filters, and deletes job applications.
- Policy checks protect every application action.

## Diagram

```text
+---------+      HTTP       +-------------------+      SQL       +--------+
| Browser | <-------------> | Laravel + Inertia | <------------> | MySQL  |
+---------+                 +-------------------+                +--------+
     |                               |
     | Vue pages + forms             | Auth, policies, CRUD
     v                               v
+-------------------+        +----------------------+
| Dashboard / CRUD  |        | Application model    |
| Applications UI   |        | Requests / Controller|
+-------------------+        +----------------------+
```

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
