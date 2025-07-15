# Spark CMS

Spark CMS is a lightweight content management system written in PHP. It aims to provide basic page editing, blog functionality, and an extendable theme system.

## Features at a Glance

- Drag‑and‑drop **Live Builder** for creating pages visually
- Markdown‑like **blog engine** with categories and tags
- **Media library** for uploading images and files
- Menu and form builders
- Simple JSON file storage – no database required
- Theme system with reusable block templates

## Requirements

- PHP 8.0 or higher
- A web server (Apache, Nginx or the built in PHP server)

## Installation

1. Clone the repository or download the files to your web server directory:
   ```bash
   git clone <repo-url>
   ```
2. Ensure the `CMS/data` and `uploads` directories are writable by the web server.
3. Point your web server's document root to the project directory. If using the PHP development server you can run:
   ```bash
   php -S localhost:8000 router.php
   ```

When first run, Spark CMS will create default data files in the `CMS/data` directory.

## Default Credentials

The CMS ships with an administrator account for the initial login:

- **Username:** `admin`
- **Password:** `password`

You should log in and change the password immediately after installation. User data is stored in `CMS/data/users.json`.

## Folder Structure

```
/
├── CMS/                – Backend PHP files and modules
│   ├── data/           – JSON files storing pages, posts, users and settings
│   ├── includes/       – Helper libraries for authentication and data access
│   └── modules/        – Individual modules like pages, blogs, media and more
├── liveed/             – Files for the live page builder
│   ├── modules/        – Builder JavaScript modules
│   ├── builder.js      – Main builder script
│   └── builder.php     – Loads a page inside the builder UI
├── theme/              – Public theme assets
│   ├── css/            – Stylesheets
│   ├── js/             – Front‑end scripts
│   └── templates/      – Page and block templates
├── index.php           – Front controller
└── router.php          – Helper for PHP's built‑in server
```

## Modules Overview

Modules live in `CMS/modules` and provide specific functionality:

- **analytics** – Tracking code management
- **backups** – Data export and import
- **blogs** – Blog post management
- **dashboard** – Admin home page
- **forms** – Contact forms
- **import_export** – Data migration tools
- **logs** – View system logs
- **maps** – Embed maps
- **media** – Upload and organize media files
- **menus** – Manage navigation menus
- **pages** – Create and edit pages
- **search** – Site search configuration
- **settings** – Global site settings
- **sitemap** – XML sitemap generation
- **users** – User management

### Module Details

- **analytics** – Add custom tracking snippets like Google Analytics or Facebook Pixel. Stored snippets are injected into your theme.
- **backups** – Export or import JSON data files for pages, posts and settings. Useful for migrating content between installs.
- **blogs** – Manage blog posts and categories. Posts are stored as JSON and can be edited with a rich text editor.
- **dashboard** – Landing page after login showing quick links and recent activity.
- **forms** – Build contact or inquiry forms. Submissions are emailed to the address configured in settings.
- **import_export** – Bulk migrate data from another Spark CMS instance using JSON exports.
- **logs** – View basic system logs and recent actions taken by users.
- **maps** – Configure map embeds (for example Google Maps) to use throughout your pages.
- **media** – Upload images, PDFs and other files. Items can be tagged and organized into folders.
- **menus** – Build navigation menus. Pages and custom links can be arranged into nested lists.
- **pages** – Create new pages and set the homepage. Pages can be edited with the live builder.
- **search** – Configure the front‑end site search. Results come from pages, blog posts and media tags.
- **settings** – Global site configuration such as site name, logo and social links.
- **sitemap** – Generates `sitemap.xml` for better SEO whenever a page is saved.
- **users** – Add or remove CMS users and assign admin roles.

## Live Page Builder

When logged in an "Edit" button appears on each page allowing you to launch the
visual builder. The builder loads your theme and lets you drag blocks from the
palette into the page canvas. Content is auto‑saved as a draft and you can undo
or redo changes. When you save, the page content is written to `CMS/data/pages.json`
and a new entry is added to `page_history.json`.

Key builder features include:

- **Drag & Drop** – reorder sections with a mouse or touch.
- **WYSIWYG editing** – text areas are editable in place.
- **Media picker** – insert images from the media library.
- **History panel** – browse previous saves and restore older content.
- **Accessibility checker** – run quick a11y checks on your page.

Blocks come from files inside `theme/templates/blocks`. Create new PHP templates
there to extend the palette with your own components.

## Coding Standards

- PHP files follow a simple procedural style with one file per feature.
- JavaScript modules in `theme/js` are written in plain ES6.
- CSS uses custom properties defined in `theme/css/root.css`; overrides go in `theme/css/override.css`.
- JSON data files are formatted with two‑space indentation.

## Customizing Themes

Theme files are stored under the `theme` directory. A theme is made up of CSS, JavaScript and PHP templates that render the public site.

1. Edit stylesheets in `theme/css`. Core variables live in `root.css` while look‑and‑feel tweaks go in `override.css` so updates survive upgrades.
2. Update markup in `theme/templates`. The `blocks` sub‑folder contains the drag‑and‑drop templates used by the live builder. The `pages` folder houses full page templates and partials.
3. Static assets such as images or custom scripts belong in `theme/images` and `theme/js`.

Whenever you modify theme files simply reload the browser. No compilation step is required.

The default theme is intentionally simple to make customization easy. You can replace any of the templates or create an entirely new `theme` directory to change the site's look. The live builder will automatically pick up new block templates placed under `theme/templates/blocks`.

## Developing Modules

Modules are self contained directories inside `CMS/modules`. A module typically contains PHP endpoints and optional JavaScript. To create a custom module:

1. Create a new directory under `CMS/modules/<your-module>`.
2. Add any PHP files required for your functionality.
3. Expose routes by referencing the PHP files via AJAX or direct links from the admin interface.

Refer to existing modules such as `pages` or `blogs` for minimal examples of loading and saving JSON data.

## License

Spark CMS is distributed under the MIT License. See `LICENSE` for details.
