# ADR-0001 Adopt YM-Lite SDD

Status: Accepted

Baseline: `9a7c91a`

## Context

The project needs a lightweight way to organize agent work, record decisions,
and prevent accidental scope expansion without adding external tools or changing
runtime code.

## Decision

Adopt YM-Lite SDD v0.1 inside `docs/ym-sdd`.

Agents should read the context, rules, workflow, UI standards, relevant spec,
and relevant task before implementation. Work must be task-bound, and commits
require human review.

## Consequences

- Project organization improves without adding dependencies.
- Documentation becomes the coordination layer for agent work.
- Each implementation should stay limited to one task.
- Future large features should have specs before implementation tasks.
