# EdgeTinker website and Content Studio

One Laravel application with three parts:

1. **Public website**: product catalogue and company site (`/`, `/products`, `/product/…`, `/search`, `/services`, `/contact`).
2. **Wiki** (`/wiki`): product documentation written in Markdown.
3. **Content Studio** (`/contentcreator`): the private editing tool. Every change goes through an approval workflow, so nothing reaches the live site without the Content Admin's sign-off.

To put the site on Hostinger, follow **[docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)**.

## Stack

- Laravel 13 (requires **PHP 8.3 or newer**) and MySQL 8
- Blade, Tailwind CSS 4 and Alpine.js on the public site and wiki; Livewire 4 in Content Studio
- Database queue (run from cron), file or database cache, local file storage. No Redis, Node.js, Docker or search server is needed on the server.
- Inter and JetBrains Mono are self-hosted. Nothing is loaded from other sites.

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
upload_max_filesize = 32M
post_max_size = 40M
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
php artisan migrate --seed       # creates tables and loads the demo content
php artisan storage:link         # makes uploaded files reachable from the browser
npm install
npm run build

php artisan studio:create-admin  # create your Content Admin (asks for name, email, password)
php artisan serve
```

Open <http://127.0.0.1:8000>.

Emails and image resizing run on a queue. To process them locally, run this in a second terminal:

```powershell
php artisan queue:work
```

With `MAIL_MAILER=log` (the default in `.env.example`), emails are written to `storage/logs/laravel.log` instead of being sent.

While changing CSS, JavaScript or templates, run `npm run dev` for live reload. Run `npm run build` before committing: the compiled files in `public/build` are committed so the server never needs Node.js.

## Addresses

| Address | What |
|---|---|
| `/` | Home page |
| `/products`, `/products/{category}` | Listings with filters and sorting |
| `/search?q=esp32` | Search results (try `esp23` for the "Did you mean" suggestion) |
| `/product/{slug}` | Product page |
| `/services`, `/contact` | Company pages |
| `/wiki`, `/wiki/{category}/{product}/{page}`, `/wiki/search` | Wiki |
| `/sitemap.xml`, `/robots.txt` | For search engines |
| `/contentcreator` | Content Studio (not linked from anywhere; type the address) |
| `/styleguide` | Every design token and component. Local and staging only; not available in production. |

## Demo data and accounts

`php artisan db:seed` loads 5 categories, 12 products, a 3-slide home banner and sample wiki pages for three products, with generated artwork labelled "PLACEHOLDER IMAGE". Product names, prices and specifications are illustrative. It is safe to re-run.

Outside production it also creates a demo Content Creator with two sample submissions:

| Role | Email | Password |
|---|---|---|
| Content Creator (demo) | `creator@edgetinker.test` | `Creator-Demo-123` |
| Content Admin | the one you create with `php artisan studio:create-admin` | yours |

The demo creator is never created in production, and the Content Admin is never seeded.

## Content Studio in brief

- **Two roles.** One Content Admin (full control, reviews everything, own edits publish immediately) and any number of Content Creators (self-register, confirm their email, then wait for the admin to activate them).
- **Approval workflow.** A change is a *revision*: Draft → Submitted → Approved (goes live) or Rejected (returns with the admin's comment). The live tables only ever hold approved content; pending changes live in the `revisions` table and are copied in, inside a transaction, when approved.
- **Deleting** is a request too: the item stays live until the admin approves.
- **Two people editing the same item:** when one change is approved, the other is flagged as out of date and its author is asked to review and resubmit.
- **History and restore** for every item, a before/after comparison, a preview of the page as it would look live, and an audit log.

Screens: Dashboard, Products, Categories, Tags, Pages (Home, Services, Contact), Home banner, Wiki, Media library, My submissions; and for the admin Review queue, Messages, Users, Audit log, Site settings.

**Product page button.** A product that is in stock (or low stock) and has a **Store link** shows **Buy**, which opens that link in a new tab. Otherwise (out of stock, coming soon, or no store link) it shows **Send Inquiry**, which opens the contact form with the product in the subject. The store link is set on each product's form.

**Home banner.** The slideshow at the top of the home page is designed visually in **Content Studio → Home banner**: drag text and buttons on a live canvas, set fonts and colours (with transparency), a solid or gradient overlay, image, video or colour backgrounds, and a separate layout for desktop, tablet and mobile. It goes through the same draft, approval and publish steps as everything else. The editor guide and developer notes are in [docs/banner-editor.md](docs/banner-editor.md).

**Favicons.** Built from `resources/brand/edgetinker-mark.png` with `php artisan brand:favicons` (re-run it if the logo changes).

**Partners.** The last section of the home page, just above the footer, is a strip of partner logos moving from right to left. Add, reorder or remove partners in **Pages → Home → Partners**; with none, the section is hidden. It pauses on hover and with its pause button, and stands still for visitors who ask their device for reduced motion.

## Tests

```powershell
php artisan test
npm test   # checks the banner editor's CSS matches the live site's
```

Most tests use an in-memory SQLite database. Search tests (`tests/Feature/Search`) run against the `edgetinker_test` MySQL database, because search depends on FULLTEXT indexes; MySQL must be running or those tests are skipped. If your MySQL login differs from `root` with no password, set `TEST_DB_HOST`, `TEST_DB_PORT`, `TEST_DB_DATABASE`, `TEST_DB_USERNAME` and `TEST_DB_PASSWORD`.

What is covered: the approval workflow rules, role permissions, sign-in (lockout, two-factor, password reset, email verification), every Content Studio screen, uploads, search matching and ranking, listing filters, the contact form, the wiki and Markdown safety, the sitemap, security headers and error pages.

## How it is organised

| Path | Contents |
|---|---|
| `app/Studio/RevisionService.php` | The approval workflow. The only code that writes live content. |
| `app/Studio/ContentTypes` | One class per kind of content (product, category, tag, wiki page, page): how to validate it, read the live version and publish it |
| `app/Livewire/Studio` | Content Studio screens; templates in `resources/views/livewire/studio` |
| `app/Http/Controllers/Studio/Auth` | Sign-in, registration, email verification, password reset, two-factor |
| `app/Search` | Search interface, MySQL implementation, URL filter object |
| `app/Support` | Currency (USD/LKR), settings, page content and its field definitions, Markdown rendering, rich-text sanitiser, cache list |
| `app/Support/Banner` | Home banner: config schema and validation, CSS generation, fonts (see docs/banner-editor.md) |
| `resources/css/app.css` | Design tokens (colours, type scale, radius, shadow) defined once and mapped into Tailwind |
| `resources/views/components` | Reusable Blade components: button, badge, product card, tabs, table, modal, form fields, … |
| `resources/js/components` | Alpine.js behaviour: mega menu, slideshow, gallery, filters, search suggestions |
| `resources/js/banner` | The banner editor (Content Studio only) and its shared CSS generator |
| `lang/en/site.php` | Public interface text, ready for translation |
| `config/edgetinker.php` | Default site settings (placeholder contact details) |
| `routes/web.php`, `routes/studio.php` | Public routes; Content Studio routes |
| `database/seeders` | Demo data only |

### Adding a new kind of approved content

Write a class that extends `App\Studio\ContentTypes\ContentType` and register it in `AppServiceProvider`. The workflow, review queue, comparison, history, restore and notifications then work for it without further code.

### Adding a field to the Home, Services or Contact page

Add it to the schema and the defaults in `App\Support\PageContent`, then use it in the page template. The Content Studio form and its validation are generated from the schema.

## How search works

All search goes through the `App\Search\ProductSearch` interface. The current implementation, `MySqlProductSearch`, uses the MySQL FULLTEXT index plus `LIKE` matching:

- Every word of the search must match the product name, SKU, descriptions, a tag or the category name. Partial names and SKU fragments match.
- Name matches rank first, then SKU, tags and description text.
- If nothing matches, it broadens the match (any word, trimmed word endings) so a typo such as "convertor" still finds "converter".
- If there are fewer than three matches, it looks for a "Did you mean …?" correction among the words used in product names, SKUs, tags and categories.

To move to a dedicated search engine later, write another class that implements `ProductSearch` and change the binding in `AppServiceProvider`.

Filters live in the URL so results can be shared: `?q=…&category=…&tags=usb-c,wi-fi&min=5&max=20&in_stock=1&sort=price_asc`. Selecting several tags narrows the results (a product must have all of them).

## Security notes

- Content Studio is not linked from the public site, is absent from the sitemap and `robots.txt`, and sends `noindex` headers. That is obscurity, not security, so it also has rate-limited sign-in with lockout, hashed passwords with a strength policy, CSRF protection, secure session cookies and optional two-factor authentication.
- Every action is authorised on the server, in `RevisionService` and in each screen, never only by hiding buttons.
- Rich text is sanitised when saved and again when shown. Wiki Markdown is rendered with raw HTML escaped and unsafe links removed.
- Uploads are limited to an allow-list of image and document types, checked by extension and content, verified to be readable images, stored under random names, and never executable (`public/.htaccess`).
- Responses carry a Content-Security-Policy and related headers (`app/Http/Middleware/SecurityHeaders.php`).

## Checking performance and accessibility

Lighthouse is not part of the project. To run it against a local or live copy (needs Chrome or Edge):

```powershell
npx lighthouse http://127.0.0.1:8000/ --form-factor=mobile --screenEmulation.mobile --view
npx lighthouse http://127.0.0.1:8000/product/edgecore-esp32-s3-dev-board --form-factor=mobile --screenEmulation.mobile --view
```

`php artisan serve` handles one request at a time and does not compress responses, so performance scores measured locally are lower than on the live server.

## Phase 2 commerce

Selling online is out of scope for now. The data model leaves room for it: carts, orders and customers can be added as new tables that reference `products.id`, and each product already stores `price_usd` and `price_lkr`. No existing table needs to change.
