# Next Steps

## Priority 1: Approved

## Add raw job content parsing to the URL-first intake flow

- Status: currently approved.
- Why it matters:
  - Reduces manual job intake friction.
  - Makes URL-first intake more useful for ATS matching.
  - Bridges today's pasted-description flow toward future automation.
  - Keeps the product aligned with resume-first matched jobs.
- Expected scope:
  - Do not scrape external pages.
  - Do not add browser extension work.
  - Do not add external APIs.
  - Accept raw job content in the URL-first intake flow.
  - Parse local/raw content into `description_text`.
  - Reuse existing `JobLeadKeywordExtractor`.
  - Keep URL-only leads valid and honest.
  - Add feature tests for URL-only, raw content, and keyword extraction behavior.

## Priority 2

## Add text extraction for uploaded resume files

- Why it matters:
  - Resume upload is the primary setup action.
  - Non-text uploads currently persist metadata but do not power matching.
  - Better extraction makes the first-run experience more automatic.
- Expected scope:
  - Start with safe local extraction.
  - Keep `.txt` support working.
  - Add parser boundaries for `pdf`, `doc`, and `docx`.
  - Do not add AI generation.
  - Add tests for extracted text and fallback states.

## Priority 3

## Improve matched job ranking and prioritization

- Why it matters:
  - Users need the best opportunities first.
  - Existing deterministic matching can rank leads without AI.
  - Better ranking increases trust and perceived value.
- Expected scope:
  - Use matched keyword counts and missing keyword counts.
  - Keep scoring transparent.
  - Do not invent match scores without clear rules.
  - Keep source URL and explanation visible.
  - Add tests for ranking order.

## Later Opportunities

- Browser extension for saving jobs from job boards.
- External job source import.
- AI-assisted resume tailoring.
- Cover letter drafting.
- Generated resume exports.
- Application workflow integration after a lead becomes applied.
