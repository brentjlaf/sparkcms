# Spark CMS

Spark CMS is a lightweight content management system written in PHP. It aims to provide basic page editing, blog functionality, and an extendable theme system.

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
- **logs** – View system logs
- **maps** – Embed maps
- **media** – Upload and organize media files
- **menus** – Manage navigation menus
- **pages** – Create and edit pages
- **search** – Site search configuration
- **settings** – Global site settings
- **sitemap** – XML sitemap generation
- **users** – User management

## Coding Standards

- PHP files follow a simple procedural style with one file per feature.
- JavaScript modules in `theme/js` are written in plain ES6.
- CSS uses custom properties defined in `theme/css/root.css`; overrides go in `theme/css/override.css`.
- JSON data files are formatted with two‑space indentation.

## Customizing Themes

Theme files are stored under the `theme` directory. To modify the site appearance:

1. Edit stylesheets in `theme/css`. You can override variables or add new rules in `override.css`.
2. Update markup in `theme/templates`. The `blocks` directory holds reusable sections while `pages` contains full page templates.
3. Add images to `theme/images` and JavaScript to `theme/js` as needed.

After editing templates or CSS, refresh your browser to see the changes.

## Developing Modules

Modules are self contained directories inside `CMS/modules`. A module typically contains PHP endpoints and optional JavaScript. To create a custom module:

1. Create a new directory under `CMS/modules/<your-module>`.
2. Add any PHP files required for your functionality.
3. Expose routes by referencing the PHP files via AJAX or direct links from the admin interface.

Refer to existing modules such as `pages` or `blogs` for minimal examples of loading and saving JSON data.

## License

Spark CMS is distributed under the MIT License. See `LICENSE` for details.
