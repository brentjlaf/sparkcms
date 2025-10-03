# Spark CMS

Spark CMS is a lightweight PHP content management system focused on delivering an approachable editing experience for small marketing sites. The project combines flat-file JSON storage with a drag-and-drop live editor, modular administration screens, and a themable front-end so teams can ship sites without a traditional database.

## Feature Highlights

- **Modular administration area.** Each capability (pages, blog, analytics, media library, SEO, etc.) lives in its own module under `CMS/modules`, making it easy to maintain or disable features individually.
- **Drag-and-drop Live Editor.** Content authors can launch the builder at `/liveed/builder.php?id=<pageId>` to rearrange blocks, preview responsive breakpoints, review page history, and manage media without writing HTML.
- **Flat-file persistence.** Site content, users, and configuration are stored as JSON documents in `CMS/data`, making deployments simple and version-control friendly.
- **Theme-driven presentation.** Public pages render via `theme/templates` templates with assets in `theme/css`, `theme/js`, and `theme/images`. A simple bundler script (`bundle.php`) combines core CSS/JS for production.
- **Built-in marketing tooling.** Modules ship for analytics reporting, SEO audits, speed checks, accessibility scoring, forms, search, and more so site operators can monitor performance from the dashboard.
- **PHP-first stack.** Spark CMS runs anywhere PHP 8.0+ is available—Apache, Nginx, or the built-in PHP server—and does not require Composer or external databases.

## System Architecture

Spark CMS follows a classic PHP front controller model:

1. `index.php` receives incoming requests, serves static assets directly, and delegates dynamic requests to `CMS/index.php`.
2. `CMS/index.php` loads JSON data, site settings, and theme templates before rendering the requested page.
3. Admin routes live under `CMS/` (e.g., `CMS/admin.php`, `CMS/login.php`). Authentication helpers in `CMS/includes/auth.php` guard these endpoints.
4. Modules register their own PHP entry points under `CMS/modules/<module>`, optionally paired with JavaScript/CSS assets.
5. The Live Editor (`/liveed`) consumes the same JSON data and templates to provide an interactive editing experience.

All content data is cached through utility functions in `CMS/includes/data.php`, while template rendering helpers live in `CMS/includes/template_renderer.php`. Shared services (analytics, search, reporting, etc.) live under `CMS/includes` and are reused by multiple modules.

## Directory Layout

```
/
├── CMS/                     # Admin interface and shared includes
│   ├── data/                # JSON datasets (pages, posts, menus, settings, users, etc.)
│   ├── images/              # Admin UI assets
│   ├── includes/            # Authentication, data access, sanitizers, templating helpers
│   ├── modules/             # Feature modules (pages, blogs, media, analytics, …)
│   ├── admin.php            # Admin shell that loads modules
│   ├── index.php            # Front-end bootstrapper used by public theme
│   └── spark-cms.css        # Admin styles
├── forms/                   # Public form submission endpoints
├── liveed/                  # Drag-and-drop page builder (PHP + JS + CSS)
├── theme/                   # Public-facing theme assets and templates
├── tests/                   # Lightweight PHP test scripts for services/modules
├── bundle.php               # Utility script to concatenate core theme assets
├── router.php               # Helper router for `php -S`
└── index.php                # Front controller used by web server
```

## Installation

1. **Clone or copy the project.**
   ```bash
   git clone <repo-url>
   cd sparkcms
   ```
2. **Configure file permissions.** Ensure the web server can write to `CMS/data` and any upload directories you plan to use (e.g., `CMS/images/uploads`).
3. **Serve the application.**
   - **Built-in PHP server:** `php -S localhost:8000 router.php`
   - **Apache/Nginx:** Point the virtual host document root to the repository root and route PHP requests to `index.php`.
4. **First run.** When the CMS boots it will seed default JSON data under `CMS/data` if the files are missing.

### Default Credentials

Log in at `/CMS/login.php` with the bundled administrator account:

- **Username:** `admin`
- **Password:** `password`

Change the password immediately from the Users module; user records are stored in `CMS/data/users.json`.

## Configuration & Data Storage

Spark CMS stores site configuration and content in JSON files located under `CMS/data`:

- `pages.json`, `page_history.json` – Page content, metadata, and revision history
- `blog_posts.json` – Blog posts with author, status, and category metadata
- `menus.json` – Navigation structures referenced by templates
- `forms.json`, `form_submissions.json` – Form definitions and captured submissions
- `media.json` – Media library metadata (files live alongside in `CMS/images`)
- `settings.json` – Site-wide settings such as homepage slug, theme options, feature toggles
- `users.json` – Admin user accounts and hashed passwords
- `events.json`, `calendar_events.json`, `calendar_categories.json` – Data powering the Events and Calendar modules
- `speed_snapshot.json`, `analytics`/`seo` reports – Cached marketing metrics

The helper functions in `CMS/includes/data.php` provide cached read/write access with automatic fallbacks for missing files. Backups can be taken by copying the `CMS/data` directory; for production deployments consider storing this directory outside your web root and symlinking it back into the project.

## Administration Workflow

1. Visit `/CMS/login.php` and authenticate.
2. Navigate between modules using the sidebar in `CMS/admin.php`.
3. Modules present data grids, analytics dashboards, or settings forms depending on their purpose. Most modules read and write the JSON files listed above.
4. The Live Editor integrates with the Pages module to launch a visual builder for individual pages (`Edit ➜ Open Builder`). Authors can preview responsive layouts, manage blocks, undo/redo, review revision history, and launch media pickers without leaving the page.
5. Use the Settings module to configure site defaults (homepage, theme options, SEO metadata). Changes propagate immediately to front-end rendering.

## Module Reference

| Module | Purpose |
| --- | --- |
| **accessibility** | Generates accessibility scores for pages, surfaces issues, and tracks score history.
| **analytics** | Aggregates page view metrics and exports filtered datasets.
| **blogs** | CRUD for blog posts, including category filters and publishing workflow.
| **calendar** | Manages calendar categories and recurring events.
| **dashboard** | Landing page summarizing metrics from other modules.
| **events** | Handles event listings, orders, and categorizations distinct from calendar items.
| **forms** | Builds and manages contact/lead forms; submissions land in `form_submissions.json` and can trigger notifications.
| **logs** | Displays application logs and audit events.
| **media** | Uploads, crops, and organizes media assets consumed by the theme and Live Editor.
| **menus** | Constructs navigation menus and exposes drag-and-drop ordering.
| **pages** | Manages static pages, integrates with revision history and the Live Editor.
| **search** | Indexes pages/posts, provides search suggestions, and tunes relevance settings.
| **seo** | Runs SEO reports, flagging metadata gaps and providing recommendations.
| **settings** | Houses global configuration, feature toggles, and theme options.
| **sitemap** | Generates XML sitemaps and pings search engines when updated.
| **speed** | Captures performance budgets and Lighthouse-like insights for pages.
| **users** | Manages administrator accounts, roles, and password resets.

Each module usually exposes a `view.php` entry point with supporting services, JavaScript, and CSS. Modules can share helper classes from `CMS/includes` for consistent sanitization, reporting, and rendering.

## Live Editor & Theme Pipeline

- **Builder UI (`/liveed`).** Provides block palette management, responsive preview toggles, media picker, undo/redo, manual save, and page history integration. Builder assets live under `liveed/css`, `liveed/modules`, and `liveed/builder.js`.
- **Theme templates.** Located in `theme/templates` with folders for pages (`templates/pages`), blocks (`templates/blocks`), and partials. Templates expect arrays of pages, menus, blog posts, and site settings injected by `CMS/index.php`.
- **Asset bundling.** Run `php bundle.php` to concatenate the primary theme stylesheets (`theme/css/root.css`, `skin.css`, `override.css`) into `combined.css`. JavaScript is served directly from `theme/js/global.js` and `theme/js/script.js` so the canonical sources stay in sync across environments.
- **Customization.** Override CSS variables in `theme/css/override.css`, add scripts in `theme/js`, or introduce new block templates for the builder. To adjust the homepage hero, update the `--hero-gradient-1` and `--hero-gradient-2` tokens (ideally via overrides in `theme/css/override.css`) to point at new gradient colors while reusing the shared layout. The theme can be versioned independently of content by committing changes to the repository.

## Public Forms & Integrations

- Public form submissions post to `forms/submit.php`, which validates input, stores data in `CMS/data/form_submissions.json`, and can send notifications based on module settings.
- Form definitions are retrieved via `forms/get.php` so front-end templates can render dynamic forms without duplicating schema.
- Additional integrations (maps, newsletters, tag manager, etc.) can be built as new modules following the existing module structure.

## Testing & Quality Assurance

The `tests/` directory contains standalone PHP scripts that exercise key services (analytics aggregation, search ranking, media library behaviors, login handling, etc.). Execute a test with the PHP CLI:

```bash
php tests/analytics_service_test.php
php tests/login_handler_test.php
```

These scripts throw a `RuntimeException` if an assertion fails. They are designed for quick smoke-testing without PHPUnit. Manual QA scripts (e.g., `tests/page_repository_manual.md`) outline exploratory scenarios such as verifying session rotation after login.

## Development Guidelines

- **PHP style.** The project favors procedural PHP with one feature per file; avoid wrapping includes in `try/catch` blocks. Use helper functions from `CMS/includes` for data access and sanitization.
- **JavaScript/CSS.** Theme scripts use plain ES6; admin builder scripts rely on vanilla JS and lightweight libraries like Cropper.js. Keep custom CSS variables in `theme/css/root.css` and overrides in `override.css`.
- **Data formats.** JSON files use two-space indentation. Ensure new datasets follow existing schema conventions so modules can interoperate.
- **Extending modules.** Duplicate an existing module directory as a starting point, register new routes inside `view.php`, and read/write JSON data via `read_json_file` / `write_json_file` helpers. For marketing-focused modules, check `CMS/includes/reporting_helpers.php` for reusable patterns.
- **Access control.** Protect new admin endpoints by invoking `require_login()` from `CMS/includes/auth.php`. For front-end forms use sanitization helpers in `CMS/includes/sanitize.php`.

## Deployment Considerations

- Serve the project behind HTTPS and configure your web server to deny direct access to `CMS/data` and other sensitive directories.
- Regularly back up `CMS/data`, especially `users.json`, `forms`, and media uploads. Consider offloading uploads to cloud storage for high-traffic sites.
- Use strong passwords for admin accounts and rotate them periodically. Two-factor authentication is not included by default but can be added via the Users module.
- Monitor performance using the built-in Speed and Analytics modules, and address SEO/accessibility issues highlighted in their dashboards.

## License

Spark CMS is distributed under the MIT License. See `LICENSE` for details.
