# YM Works Draft Readiness and Review Submission

**Task:** `YM-WORKS-DRAFT-READINESS-SUBMIT-001`
**Scope:** Internal administrative work authoring and submission

## User Journey

An authorized internal user creates a draft, completes content, taxonomy and
protected media, reviews the server-evaluated readiness panel, confirms the
transition, and submits the saved work to the existing review queue. Creation
never submits directly: it first redirects to the shared edit workspace.

## State Contract

- `draft` and `changes_requested` may be evaluated and submitted.
- A successful transition sets `status=submitted` and keeps
  `visibility_status=hidden`.
- Resubmission retains the current reviewer and previous change-request notes.
- Review and decision timestamps are reset; authoring becomes read-only.
- All other states return an `invalid_status` blocker.

## Central Readiness Rules

`WorksReviewReadinessService` is the single source for both the readiness
endpoint and the transactional submission check. Codes, not localized server
copy, form the frontend contract.

Blocking requirements cover non-empty title, summary and description; an
allowed media type; an active category; active compatible media with no
pending or failed processing; item limits; and a ready image cover for image
and gallery works. Video works do not currently require a cover.

Non-blocking recommendations cover designer assignment, tags, price, delivery
time, useful content length, and private notes only when the actor can view
that field.

## Authorization

Both endpoints require an authenticated internal role, `admin.works.access`,
and `admin.works.review.submit`. The super-admin bypass follows the existing
registry convention. Client and designer roles remain forbidden even if a
permission is assigned accidentally.

## API

- `GET /api/admin/works/{work}/review/readiness`
- `PATCH /api/admin/works/{work}/review/submit`

The submit body accepts only `expected_updated_at`. Query parameters and
additional body fields are rejected.

## Transaction and Concurrency

Submission locks the work row, compares `expected_updated_at`, reevaluates the
same readiness service against persisted data, and only then changes state.
A stale snapshot returns `409` with current status, timestamp and readiness.
New blockers return `422` with the complete readiness contract.

## Audit

Successful transitions emit one `works.review.submitted` event. Metadata is
limited to IDs, state transition flags, counts, settings version and timestamp;
it excludes work content, filenames, storage paths, request payloads and
credentials.

## Frontend

The edit workspace adds a Review section after basic data, taxonomy and media.
It refreshes after successful persisted mutations, rejects stale responses,
blocks submission while any section is dirty or busy, and uses an accessible
confirmation dialog with focus trapping and restoration. Success does not
redirect automatically and exposes links to the review queue and all works.

## Boundaries

This task does not change reviewer decisions, queue filters, authentication,
storage, protected media URLs, public publishing, or review policies. It does
not make draft readiness a prerequisite for saving an incomplete draft.
