# YM-WORKS-REVIEW-STATION-CLOSURE-006

## Purpose

Close the internal Admin Works authoring and review station after validating the complete draft-to-publication administrative workflow.

## Closed Scope

- Admin works list, draft creation, editing, taxonomy assignment, and protected media management.
- Server-authoritative draft readiness and submission or resubmission.
- Queued video processing with safe progress, retry, and readiness refresh.
- Review queue, reviewer assignment, review start, changes request, approval, rejection, and publish-after-approval.
- Read-only review detail workspace with protected image, video, and gallery previews.
- Central Super Admin permission bypass without bypassing validation or business invariants.

## State Cycle

```text
draft | changes_requested
  -> submitted
  -> in_review
  -> changes_requested | approved | rejected
  -> published (from approved when policy and permission allow)
```

Submission and review mutations use transactions, row locking where required, and `expected_updated_at`. Stale versions or incompatible repeated state changes return `409`; readiness and payload failures remain `422`; authorization failures remain `403`.

## Security and Audit Guarantees

- Backend authorization and state rules remain authoritative.
- Super Admin receives every registered administrative permission through the central invariant, but cannot bypass readiness, validation, concurrency, or state transitions.
- Media previews use authenticated content endpoints and Blob URLs; no storage paths or public media URLs are exposed.
- Successful workflow mutations write one safe Audit Event inside the transaction; rejected or repeated mutations do not duplicate success events.

## Runtime Ownership

- `composer run dev` starts the backend, frontend, and a persistent `works-media,default` Queue worker.
- Production worker configuration is recorded in `deploy/supervisor/yemen-motion-works-media.conf.example`.
- Deployments must run migrations and restart Queue workers through the documented operational commands.

## Verification Record

Executed by the closure agent on `2026-07-25`:

```text
Targeted closure suite: 348 passed / 3693 assertions
Nuxt production build: Client, Server, and Nitro completed
git diff --check: passed
```

The user separately confirmed the complete manual and visual workflow, including media processing, submission, queue visibility, reviewer actions, `409` conflicts, protected previews, search, sorting, and numbering. The closure agent did not repeat that manual browser verification.

Known non-blocking build warnings remain limited to sourcemap generation, the existing `authStore` mixed static/dynamic import warning, and chunk size.

## Excluded Scope

- Public Works Platform and client or designer Works experiences.
- Public comments, likes, engagement, and the complete public publishing experience.
- Other administrative stations that have not completed their own closure review.

## Next Candidate

`/admin/works/visibility` — Admin Works Visibility Station, only after explicit user confirmation.
