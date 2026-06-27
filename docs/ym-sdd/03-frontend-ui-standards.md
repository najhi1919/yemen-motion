# Frontend UI Standards

- `/admin` is the reference template.
- The App Shell consists of:
  - BackgroundWatermark
  - Sidebar
  - TopBar
  - Page Content

## Tooltip Rules

The following tooltip patterns are forbidden:

- `title`
- `data-tooltip`
- `.has-tooltip::after`
- `content: attr(data-tooltip)`

The approved tooltip system uses:

- Vue state
- `getBoundingClientRect()`
- `Teleport to body`
- `aria-label` for accessibility

Any tooltip change needs an explicit task.

## Light Mode Guardrails

- No heavy blur.
- No broad white drop-shadow.
- No heavy filters on the watermark.
- No high-opacity overlays.

Any Light Mode change needs an explicit task.

## Dashboard Controls

- `width: fit-content`
- `max-width: 100%`
- `overflow: visible`

Placeholder routes must remain in place instead of returning 404 for sections
that are not built yet.
