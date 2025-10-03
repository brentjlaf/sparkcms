# Theme Refactor Opportunities

This document lists concrete areas inside the `theme/` directory that could benefit from refactoring. Each entry highlights the issue and the reason it is worth addressing.

## JavaScript

- **JavaScript build tooling** – `global.js` and `script.js` are served individually today. Introduce an automated bundler that consumes these canonical sources, applies minification/tree-shaking, and emits cache-busted assets for production without reintroducing duplicated bundles.【F:theme/js/global.js†L2-L8】【F:theme/js/script.js†L2-L8】
- **`js/global.js` & `js/script.js`** – Both define nearly identical base-path helpers (`normalizeBasePath` vs. `basePath`) and maintain independent caching for fetch calls. Extract shared utilities into a reusable module to avoid drift and reduce bundle size.【F:theme/js/global.js†L14-L75】【F:theme/js/script.js†L6-L36】
- **`js/global.js`** – Contains long procedural blocks for rendering blog posts (e.g., `renderBlogList`) with repeated DOM manipulation patterns. Break these into smaller renderer functions or use templating helpers to improve readability and testability.【F:theme/js/global.js†L147-L292】
- **`js/script.js`** – The dynamic form builder mixes DOM creation, validation, and event wiring in a single file. Consider splitting responsibilities into separate modules (field factory, state manager, validation) and leverage templating for repeated markup like feedback blocks and choice inputs.【F:theme/js/script.js†L97-L268】
- **All JS files** – The IIFEs never expose a namespace, which complicates reuse and testing. Migrate to ES modules or a bundler that scopes exports/imports automatically.【F:theme/js/global.js†L2-L8】【F:theme/js/script.js†L2-L8】

## CSS

- **`css/root.css`** – The design tokens are hand-written with repeated number series (primary/secondary/third/fourth color scales). Move these into a data-driven format or use CSS custom property fallbacks/mixins to avoid manual duplication and ensure consistent naming (e.g., fix `--text-color-on-success`).【F:theme/css/root.css†L21-L191】
- **`css/skin.css`** – Component styles (buttons, headings, gallery layouts) mix layout, color, and interaction rules. Consider splitting into component-specific files or adopting utility classes to reuse transitions and spacing tokens instead of redefining them per block.【F:theme/css/skin.css†L7-L144】
- **`css/override.css`** – Contains global resets, layout primitives, navigation, hero, footer, cards, forms, and responsive rules all in one stylesheet. Break it into scoped modules (e.g., navigation, hero, cards) to simplify maintenance and reduce cascade conflicts.【F:theme/css/override.css†L1-L320】
- **`css/override.css`** – Hard-coded colors and shadows (`#667eea`, `rgba(255, 107, 107, 0.3)`, etc.) bypass the token system from `root.css`. Refactor to reference CSS variables so themes remain consistent and configurable.【F:theme/css/override.css†L44-L151】

## Templates

- **`templates/pages/page.php` vs `templates/partials/head.php`** – `page.php` reimplements head markup, menu renderers, and footer structure instead of reusing the partials, leading to duplicated favicon/font logic and menu helpers.【F:theme/templates/pages/page.php†L5-L200】【F:theme/templates/partials/head.php†L1-L37】
- **`templates/pages/search.php`** – Duplicates the menu helper functions already defined in `page.php` and then includes the same partials, highlighting the need for a shared menu utility or controller layer.【F:theme/templates/pages/search.php†L5-L55】
- **`templates/partials/footer.php`** – Embeds social links, footer navigation, copyright, and button markup directly. Extract subcomponents (social list, legal links, back-to-top button) into reusable partials so block templates can share them without copy/paste.【F:theme/templates/partials/footer.php†L1-L48】
- **Layout block templates (`layout.column-2.php`, `layout.column-3.php`, `layout.column-4.php`)** – Share nearly identical `<templateSetting>` structures and hard-coded gap/alignment options (including duplicated values such as `g-5` for both “Large” and “Extra Large”). Centralize these controls or generate them dynamically.【F:theme/templates/blocks/layout.column-2.php†L4-L35】【F:theme/templates/blocks/layout.column-3.php†L4-L23】【F:theme/templates/blocks/layout.column-4.php†L3-L26】
- **`templates/blocks/layout.section.php`** – Repeats wrapper markup inside `<toggle>` blocks to switch between `container` and `container-fluid`. Replace with a single template that toggles class names via logic or macros instead of duplicating HTML.【F:theme/templates/blocks/layout.section.php†L3-L70】
- **`templates/blocks/interactive.form.php`** – Relies on raw `<select>` placeholders to be populated by JS. Refactor to include server-rendered options or a data attribute describing endpoint configuration so the JS can stay generic.【F:theme/templates/blocks/interactive.form.php†L3-L23】

## Assets

- **Images (`images/logo.png`, `images/favicon.png`)** – Static assets are duplicated in PHP fallbacks and CSS backgrounds. Evaluate moving asset paths into configuration variables to avoid hard-coded references in multiple files.【F:theme/templates/pages/page.php†L7-L133】【F:theme/templates/partials/head.php†L2-L20】

Refactoring these areas will reduce duplication, improve maintainability, and make the theme easier to extend.
