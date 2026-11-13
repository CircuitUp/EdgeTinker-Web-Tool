# EdgeTinker website and Content Studio

One Laravel application with three parts:

1. **Public website**: product catalogue and company site.
2. **Wiki** (`/wiki`): product documentation.
3. **Content Studio** (`/contentcreator`): private editing tool with an approval workflow.

## Build status

| Phase | Scope | Status |
|---|---|---|
| 1 | Project setup, design system, `/styleguide`, header, footer, mega menu, home page | Done |
| 2 | Products: categories, product page, search and filters | Not started |
| 3 | Content Studio: authentication, roles, approval workflow | Not started |
| 4 | Page editing, Services, Contact, wiki | Not started |
| 5 | Hardening, tests, SEO, performance, Hostinger deployment guide | Not started |

Pages that belong to a later phase currently show a "coming in phase N" notice.

## Stack

- Laravel 13 (requires **PHP 8.3 or newer**), MySQL 8
- Blade, Tailwind CSS 4 and Alpine.js on the public site; Livewire in Content Studio (Phase 3)
- Database queue, database cache, local file storage. No Redis, Node.js or Docker is needed on the server.
- Inter and JetBrains Mono are self-hosted (bundled into `public/build`).

## Local setup (Windows)

### 1. Install the tools (once)

With [Scoop](https://scoop.sh):

```powershell
scoop install php composer mysql nodejs-lts
```

Enable the PHP extensions Laravel needs by creating
`%USERPROFILE%\scoop\persist\php\cli\conf.d\laravel.ini`:

```ini
extension_dir = "C:\Users\<you>\scoop\apps\php\current\ext"
extension = curl
extension = exif
extension = fileinfo
extension = gd
extension = intl
extension = mbstring
extension = openssl
extension = pdo_mysql
extension = pdo_sqlite
extension = sodium
extension = sqlite3
extension = zip
```

Open a **new** terminal afterwards and check with `php -m` that `pdo_mysql` and `gd` are listed.

### 2. Start MySQL and create the databases

```powershell
# Leave this running in its own terminal window:
mysqld --console

# In another terminal (root has an empty password on a fresh Scoop install):
mysql -u root -e "CREATE DATABASE edgetinker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE edgetinker_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

MySQL is required locally (not SQLite) because product search uses FULLTEXT indexes.

### 3. Install and run the app

```powershell
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed      # creates tables and loads the demo catalogue
npm install
npm run build
php artisan serve
```

Open <http://127.0.0.1:8000>.

While changing CSS, JavaScript or templates, run `npm run dev` in a second terminal for live reload.
Run `npm run build` before committing: the compiled files in `public/build` are committed so the
server never needs Node.js.

## Useful URLs

| URL | What |
|---|---|
| `/` | Home page |
| `/styleguide` | Every design token and component (local and staging only; not registered in production) |

## Tests

```powershell
php artisan test
```

Most tests use an in-memory SQLite database. Search tests (Phase 2) run against the
`edgetinker_test` MySQL database.

## Where things are

| Path | Contents |
|---|---|
| `resources/css/app.css` | Design tokens (colours, type scale, radius, shadow) defined once and mapped into Tailwind |
| `resources/views/components` | Reusable Blade components: button, badge, product card, tabs, table, modal, form fields, … |
| `resources/views/components/site` | Header (mega menu, mobile drawer), footer, search form, currency toggle |
| `resources/js/components` | Alpine.js behaviour: mega menu, slideshow, tabs |
| `lang/en/site.php` | All public interface text, ready for translation |
| `config/edgetinker.php` | Default site settings (placeholder contact details) |
| `app/Support` | Currency (USD/LKR), Settings, placeholder artwork generator |
| `database/seeders` | Demo data. All demo products and images are placeholders. |

## Demo data

`php artisan db:seed` loads 5 categories, 12 products and 3 hero slides, and regenerates the
placeholder artwork in `public/placeholders`. It is safe to re-run. Every image is labelled
"PLACEHOLDER IMAGE"; product names, prices and specifications are illustrative.
