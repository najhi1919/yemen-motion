# YM Admin Visual System

**Specification:** `YM-ADMIN-VISUAL-SYSTEM-001`
**Reference station:** `/admin/works/all`
**First adopting station:** `/admin/works/review`

## Purpose

This specification records a lightweight visual contract for Yemen Motion
administration stations. It is not a general UI library and does not move
business rules into presentation components. Adoption proceeds one station at
a time, with `/admin/works/all` remaining the approved visual reference.

## Foundations

- Use the existing administrative font, locale state, theme state, and layout
  variables.
- Use translucent glass surfaces that preserve the watermark, with a stronger
  raised surface for table headers and floating panels.
- Shared aliases live in `frontend/assets/css/admin-visual-system.css`.
- Preferred radii are `10px` for small controls, `12px` for controls,
  `14–18px` for cards and raised surfaces.
- Controls have a minimum interactive height of `44px`.
- Body copy is normally `13–15px`; primary data must not be smaller than
  `14px` on mobile.
- Transitions are `160–220ms`, never continuous, and must respect
  `prefers-reduced-motion`.

## Surfaces and Semantic Colour

- Violet and magenta identify the administrative creative accent.
- Cyan identifies information and active context.
- Emerald identifies success or an enabled safe policy.
- Amber identifies attention without implying failure.
- Rose identifies errors, reports, or destructive actions.
- Colour must not be the only carrier of state; every semantic state also has
  text, an icon, or both.
- Light and dark modes use the same semantic aliases with the existing layout
  surfaces as their source of truth.

## Page Composition

1. Compact page hero with breadcrumb, eyebrow, badge, title, and short copy.
2. Optional compact policy bar.
3. Wrapping mini-metric strip.
4. Compact command filter bar.
5. Data surface containing loading, updating, empty, error, and content states.

The page hero must use an Arabic-safe line height of at least `1.2` and must
not contain a large metric card.

## Filters

- Search remains directly visible.
- Finite technical values use translated selects, never raw text inputs.
- Related secondary filters are grouped in a popover or mobile sheet.
- Applied filters are readable, removable chips.
- Internal numeric identifiers are not exposed unless a documented safe
  user-search contract exists.
- Filter popovers use a body-level overlay and remain inside the viewport.

## Metrics

- Metrics are compact, wrap without horizontal scrolling, and use server
  summary values when the endpoint supplies them.
- Missing values use an unavailable state; page rows must not be used to invent
  global counts.
- Updating preserves the last coherent values while exposing a busy state.
- Visible numbers use `ymFormatting.ts` and Latin numerals.

## Data Tables and Cards

- Desktop data tables use no more than seven functional columns.
- Horizontal page scrolling is prohibited.
- Dense secondary values move into an accessible information overlay.
- Table headers use a slightly stronger glass surface than rows.
- Tablet and mobile layouts switch to cards before content becomes cramped.
- Mobile cards retain the primary state, assignees, selected time, leading
  signal, details, and permitted actions.

## Icons and Actions

- Use local SVG icons or restrained typographic symbols with consistent visual
  weight.
- Icon actions have a visible tooltip, `aria-label`, focus ring, permission
  state, and a visible disabled reason.
- Influential workflow actions retain their existing confirmation step.
- Hover movement is limited to `translateY(-1px)`.

## Floating Content

- Tooltips and compact information panels teleport to `body`.
- Positioning uses the actual trigger element and `getBoundingClientRect()`.
- Panels clamp to a `12px` viewport margin and recompute on scroll and resize.
- Text and background colours are explicit in light and dark modes.
- Mouse, focus, `Enter`, `Space`, and `Escape` are supported.
- Opening an overlay must not issue a data request.

## Direction, Numerals, and Accessibility

- Layout uses logical properties and follows the current `RTL` or `LTR`
  administrative direction.
- Arabic and English use Latin digits through `ymFormatting.ts`.
- Dates and numeric technical values use isolated `LTR` presentation without
  changing surrounding text direction.
- Every state supports keyboard navigation, visible focus, screen-reader
  labels, and reduced motion.
- Dialog-like controls restore focus to their trigger after closing.

## Layer Contract

- Sticky data surfaces stay in normal page stacking.
- Tooltips and filter popovers use the shared tooltip layer.
- Drawers sit above tooltips.
- Confirmation dialogs sit above drawers.
- New station-specific arbitrary extreme `z-index` values are prohibited.

## Adoption Boundary

The shared contract intentionally mirrors `/admin/works/all` without changing
that station's rendering or behaviour. `/admin/works/review` is the first
consumer. Other administration stations adopt the contract only through their
own scoped review and closure tasks.
