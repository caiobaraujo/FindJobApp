# FindJobApp Browser Extension

Minimal local Chrome extension for sending visible post content into FindJobApp as extension-origin `JobLead` records.

## Local setup

1. Start FindJobApp locally, for example:

```bash
php artisan serve
```

2. In Chrome, open `chrome://extensions/`.
3. Enable **Developer mode**.
4. Click **Load unpacked**.
5. Select the `browser-extension/` folder from this repo.
6. Log into FindJobApp locally at `http://localhost:8000` first.
7. Open a LinkedIn post, feed page, or any normal web page with visible hiring content.
8. Click the extension icon.

## Manual capture

Use this mode when you already have one visible post or text block you want to save.

1. Select the text you want to capture if possible.
2. Open the extension popup.
3. Stay on **Manual capture**.
4. Review the captured text and optional fields.
5. Click **Send to FindJobApp**.

## Scan visible posts

Use this mode when you want the extension to inspect the visible current page for likely hiring opportunities.

1. Open the extension popup.
2. Switch to **Scan visible posts**.
3. Click **Scan visible posts**.
4. Review the candidate list.
5. Keep or uncheck candidates before sending.
6. Click **Send selected to FindJobApp**.

## Behavior

- Manual capture:
  - selected text is preferred
  - if nothing is selected, the popup uses a conservative visible text snippet
- Scan visible posts:
  - inspects only visible content in the active tab when you click scan
  - looks for post-like blocks with hiring terms plus technical/profile terms
  - avoids duplicate text blocks
  - limits results to the top 10 candidates
- Both modes always use the current page URL as `source_post_url`.
- If the page hostname includes `linkedin.com`, the extension sends `source_platform=linkedin`.
- Otherwise it sends `source_platform=web`.

## Payload

The popup sends `POST` requests to:

`http://localhost:8000/job-leads/import/post`

Using the current authenticated browser session, with:

- `source_platform`
- `source_post_url`
- `source_context_text`
- `source_url` optional
- `job_title` optional
- `company_name` optional

For scanned candidates:

- `source_context_text` is the detected candidate block text
- `source_url` is only included if the candidate text contains a clear `http` or `https` URL
- `job_title` and `company_name` are not invented

Before posting, the popup fetches:

`http://localhost:8000/csrf-token`

and sends the returned token in the `X-CSRF-TOKEN` header. The request still uses `credentials: "include"` so it stays bound to the normal Laravel session.

## Troubleshooting

- If the popup says to log into FindJobApp first, open `http://localhost:8000` in Chrome and sign in again.
- If the popup says it could not fetch a CSRF token, refresh the local app in Chrome and retry.
- If the popup says the request failed, confirm `php artisan serve` is running at `http://localhost:8000`.
- If scan finds no candidates, the current visible page may not contain enough hiring and technical signals for the heuristic.
- If you change extension files, reload the unpacked extension from `chrome://extensions/`.

## Limitations

- The extension only scans content visible in the current tab at the moment you click scan.
- It does not scroll, paginate, or fetch hidden content.
- It does not automate LinkedIn actions or bypass any platform restrictions.
- The first scan heuristic is deterministic and simple. It may miss valid posts or include false positives.
- No AI or external APIs are used.

## Scope

- Local development only
- No background scraping
- No LinkedIn automation
- No AI
- No external APIs
