# YM-UI-001 TopBar and Hero Polish Spec

Status: Temporarily Accepted

Baseline: `9a7c91a fix: polish topbar and dashboard hero visuals`

## Purpose

This spec records the visual polish round for the dashboard TopBar and Hero
areas that was accepted temporarily at the baseline commit.

## Scope

- TopBar visual polish.
- Dashboard Hero visual polish.
- Tooltip cleanup connected to the TopBar.
- Preservation of `/admin` as the reference template.

## Constraints

- Do not reintroduce native `title` tooltips.
- Do not reintroduce `data-tooltip`.
- Do not reintroduce `.has-tooltip::after`.
- Do not reintroduce `content: attr(data-tooltip)`.
- Do not change Auth, roles, permissions, routes, dependencies, or backend code.

## Follow-up Rule

Any future improvement on this area must be done through a new task.
