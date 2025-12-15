# Verification Matrix

## Context
Theme variants verification for Light and Dark modes.
Goal: Ensure no hardcoded colors remain and all components react to `[data-theme="dark"]`.

## Coverage

| Component / Page | CSS File | PHP View | Status | Notes |
|------------------|----------|----------|--------|-------|
| General Admin | `admin.css` | Multiple | ✅ Updated | Replaced hardcoded colors, gradients, and status backgrounds with tokens. |
| Dashboard | `dashboard-redesign.css` | `EnhancedDashboard.php` | ✅ Updated | Removed conflicting dark mode block, tokenized backgrounds and text. |
| Media Library | `smo-media-library.css` | `MediaLibrary.php` | ✅ Updated | Removed conflicting `prefers-color-scheme`, tokenized UI elements. |
| Settings | `smo-settings-modern.css` | `Settings.php` | ✅ Updated | Tokenized form elements, cards, and navigation. |
| Platforms | `platforms.css` | `Platforms.php` | ✅ Updated | Tokenized platform cards, status badges, and removed media queries. |
| Image Editor | `smo-image-editor.css` | N/A | ✅ Updated | Replaced hardcoded colors. Used `color-mix` for transparent overlays. |
| Advanced Scheduling | N/A | `AdvancedScheduling.php` | ✅ Updated | Replaced inline styles for text colors and backgrounds. |
| Maintenance | N/A | `Maintenance.php` | ✅ Updated | Replaced inline styles. |

## Changes Implemented

1. **Extended Design Tokens**: Added `--smo-bg-success`, `--smo-bg-warning`, etc. and their dark mode variants to `smo-unified-design-system.css`. Added status text tokens.
2. **Refactored CSS**: Replaced hex codes (`#fff`, `#2c3e50`, etc.) with `var(--smo-*)` tokens across all key CSS files.
3. **Removed Hardcoded Dark Mode**: Deleted `@media (prefers-color-scheme: dark)` blocks that contained hardcoded colors, ensuring the system relies on the `[data-theme="dark"]` attribute and unified tokens.
4. **Cleaned Inline Styles**: Updated PHP views to use tokens in `style="..."` attributes.

## Known Limitations

- **Shadows & Overlays**: Some complex shadows or overlays using `rgba` were kept or approximated using `color-mix` where possible. Dark overlays generally work for both modes.
- **JS Dynamic Styles**: Any styles injected dynamically by JavaScript (not in PHP/CSS files) were not audited.
- **OS Preference**: `prefers-color-scheme` media queries were removed to prioritize the manual toggle (`data-theme`). Automatic OS theme detection now depends on the JavaScript implementation setting the data attribute.
