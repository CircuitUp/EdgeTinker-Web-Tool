# EdgeTinker website and Content Studio

One Laravel application with three parts:

1. **Public website**: product catalogue and company site.
2. **Wiki** (`/wiki`): product documentation.
3. **Content Studio** (`/contentcreator`): private editing tool with an approval workflow.

## Build status

| Phase | Scope | Status |
|---|---|---|
| 1 | Project setup, design system, `/styleguide`, header, footer, mega menu, home page | Done |
| 2 | Products: categories, product page, search and filters, currency toggle | Done |
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
| `/products` | All products, with filters and sorting |
| `/products/sensor-boards` | One category |
| `/search?q=esp32` | Search results (try `esp23` for the "Did you mean" suggestion) |
| `/product/edgecore-esp32-s3-dev-board` | A product page |
| `/styleguide` | Every design token and component (local and staging only; not registered in production) |

## Tests

```powershell
php artisan test
```

Most tests use an in-memory SQLite database. Search tests (`tests/Feature/Search`) run against
the `edgetinker_test` MySQL database, because search depends on FULLTEXT indexes; MySQL must be
running or those tests are skipped. If your MySQL login differs from `root` with no password,
set `TEST_DB_HOST`, `TEST_DB_PORT`, `TEST_DB_DATABASE`, `TEST_DB_USERNAME` and `TEST_DB_PASSWORD`.

## How search works

All search goes through the `App\Search\ProductSearch` interface. The current implementation,
`MySqlProductSearch`, uses the MySQL FULLTEXT index plus `LIKE` matching:

- Every word of the search must match the product name, SKU, descriptions, a tag or the category name.
  Partial names and SKU fragments match.
- Name matches rank first, then SKU, tags and description text.
- If nothing matches, it broadens the match (any word, trimmed word endings) so a typo such as
  "convertor" still finds "converter".
- If there are fewer than three matches, it looks for a "Did you mean …?" correction among the
  words used in product names, SKUs, tags and categories.

To move to a dedicated search engine later, write another class that implements `ProductSearch`
and change the binding in `app/Providers/AppServiceProvider.php`.

Filters live in the URL so results can be shared:
`?q=…&category=…&tags=usb-c,wi-fi&min=5&max=20&in_stock=1&sort=price_asc`.
Selecting several tags narrows the results (a product must have all of them).

## Where things are

| Path | Contents |
|---|---|
| `resources/css/app.css` | Design tokens (colours, type scale, radius, shadow) defined once and mapped into Tailwind |
| `resources/views/components` | Reusable Blade components: button, badge, product card, tabs, table, modal, form fields, … |
| `resources/views/components/site` | Header (mega menu, mobile drawer), footer, search form, currency toggle |
| `resources/js/components` | Alpine.js behaviour: mega menu, slideshow, tabs |
| `lang/en/site.php` | All public interface text, ready for translation |
| `config/edgetinker.php` | Default site settings (placeholder contact details) |
| `app/Support` | Currency (USD/LKR), Settings, rich-text sanitiser, placeholder artwork generator |
| `app/Search` | Search interface, MySQL implementation, URL filter object |
| `database/seeders` | Demo data. All demo products and images are placeholders. |

## Demo data

`php artisan db:seed` loads 5 categories, 12 products and 3 hero slides, and regenerates the
placeholder artwork in `public/placeholders`. It is safe to re-run. Every image is labelled
"PLACEHOLDER IMAGE"; product names, prices and specifications are illustrative.
