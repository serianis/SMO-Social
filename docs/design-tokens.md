# SMO Social Design Tokens

The SMO admin UI uses CSS custom properties (tokens) defined in:

- `assets/css/smo-design-foundations.css` (single source of truth)

All admin views should consume these tokens (directly via `var(...)` or via shared component classes) instead of hard-coded values.

## Theme + motion

- Light/dark: `html[data-theme="dark"]` (set by `AppLayout` theme toggle) and `prefers-color-scheme` both map to dark-mode token values.
- Reduced motion: `prefers-reduced-motion: reduce` collapses transition/animation durations.

## Breakpoints

These are exposed as variables for consistency and tooling (note: media queries still use literal values):

- `--smo-breakpoint-sm: 600px`
- `--smo-breakpoint-md: 960px`
- `--smo-breakpoint-lg: 1280px`

## Core color tokens

### Brand + status

- `--smo-primary`, `--smo-primary-dark`, `--smo-primary-light`
- `--smo-secondary`
- `--smo-accent`
- `--smo-success`, `--smo-warning`, `--smo-error`, `--smo-info`

### Surfaces + text

- `--smo-bg`
- `--smo-surface`
- `--smo-surface-muted`
- `--smo-border`
- `--smo-text-primary`
- `--smo-text-secondary`
- `--smo-text-muted`

## Gradients

- `--smo-gradient-brand` (alias: `--smo-primary-gradient`)
- `--smo-gradient-accent`
- `--smo-gradient-success` (alias: `--smo-success-gradient`)
- `--smo-gradient-warning` (alias: `--smo-warning-gradient`)
- `--smo-gradient-info` (alias: `--smo-info-gradient`)

Gradient ramps (used for stats/header accents):

- `--smo-gradient-ramp-1..4`

## Typography

- `--smo-font-sans`, `--smo-font-mono`
- Font sizes: `--smo-font-size-xs/sm/md/lg/xl/2xl/3xl/4xl`
- Line heights: `--smo-line-height-tight`, `--smo-line-height-base`

## Spacing

Primary scale:

- `--smo-space-0..9`

Back-compat aliases:

- `--smo-spacing-xs/sm/md/lg/xl`

## Radius

- `--smo-radius-sm/md/lg/xl/full`

## Elevation (shadows)

- `--smo-shadow-1..4`
- Back-compat aliases: `--smo-shadow-sm/md/lg/xl`

## Motion

- Easing: `--smo-ease-standard`, `--smo-ease-emphasized`
- Durations: `--smo-duration-fast/base/slow`
- Back-compat transitions: `--smo-transition-fast/base/slow`

## Usage guidelines

1. Prefer shared components from `assets/css/smo-unified-design-system.css` (cards, buttons, form controls, tabs).
2. For page-specific needs, add styles to `assets/css/smo-admin-components.css` using the existing token set.
3. Avoid inline `<style>` blocks; the build enqueues shared styles globally via `includes/Admin/AssetManager.php`.
4. Avoid redefining core tokens in feature CSS. If a feature needs extra variables, prefix them with `--smo-<feature>-...` and keep them local to a wrapper.
