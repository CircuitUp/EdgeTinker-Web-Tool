# Deploying EdgeTinker to Hostinger

A step-by-step guide for someone who has never deployed a Laravel site.
Allow about an hour the first time. Updating the site afterwards takes a few minutes ([section 13](#13-deploying-an-update)).

**What you need before you start**

- A Hostinger **Cloud** hosting plan with your domain added to it.
- The project on your own computer, working (see the README).
- The project in a Git repository (GitHub is easiest), or the ability to upload a ZIP file.

**How Hostinger differs from your computer**

- Hostinger always serves a website from a folder called `public_html`. Laravel must only expose its `public` folder to the internet (everything else, including passwords in `.env`, stays private). Step 5 connects the two.
- There is no always-running background process. A scheduled task (a "cron job") runs every minute and handles emails and image resizing (step 9).
- Screens in hPanel change from time to time. If a menu is not exactly where this guide says, use the search box in hPanel's sidebar.

> Throughout this guide, replace `u123456789` with your Hostinger username and `edgetinker.com` with your domain. Your username is shown in hPanel under **Advanced → SSH Access**.

---

## Contents

1. [Set the PHP version](#1-set-the-php-version)
2. [Create the database](#2-create-the-database)
3. [Create the mailbox](#3-create-the-mailbox)
4. [Put the files on the server](#4-put-the-files-on-the-server)
5. [Point the domain at the `public` folder](#5-point-the-domain-at-the-public-folder)
6. [Create the `.env` file](#6-create-the-env-file)
7. [Install, migrate and link storage](#7-install-migrate-and-link-storage)
8. [Create the Content Admin](#8-create-the-content-admin)
9. [Set up the cron job](#9-set-up-the-cron-job)
10. [Turn on SSL (HTTPS)](#10-turn-on-ssl-https)
11. [Check that everything works](#11-check-that-everything-works)
12. [Backups](#12-backups)
13. [Deploying an update](#13-deploying-an-update)
14. [If you have no SSH access](#14-if-you-have-no-ssh-access)
15. [Troubleshooting](#15-troubleshooting)

---

## 1. Set the PHP version

1. In hPanel open **Websites → Manage** (your site) **→ Advanced → PHP Configuration**.
2. On the **PHP Version** tab choose **PHP 8.3** or newer and save. The site does not run on PHP 8.2 or older.
3. On the **PHP Extensions** tab make sure these are ticked: `pdo_mysql`, `mbstring`, `openssl`, `curl`, `fileinfo`, `zip`, `intl`, `exif`, `sodium`, and `gd` or `imagick` (either works; the site uses Imagick when it is there and GD otherwise).
4. On the **PHP Options** tab set `upload_max_filesize` to at least `32M` and `post_max_size` to at least `40M`, so documents up to 30 MB can be uploaded in Content Studio.

## 2. Create the database

1. hPanel → **Databases → Management**.
2. Enter a database name, a username and a strong password, then **Create**.
3. Write down all three, plus the full names hPanel shows (they get a prefix, for example `u123456789_edgetinker`). You need them in step 6.

The database host is `localhost`.

## 3. Create the mailbox

The site sends email (notifications, password resets, contact-form messages) through a normal mailbox.

1. hPanel → **Emails** → your domain → **Create email account**.
2. Create `no-reply@edgetinker.com` with a strong password and write the password down.

Hostinger's outgoing mail settings are `smtp.hostinger.com`, port `465`, with SSL. These are already filled in in `.env.production.example`. If hPanel shows different values under **Emails → Configuration settings**, use those.

## 4. Put the files on the server

First turn on SSH: hPanel → **Advanced → SSH Access → Enable**. Note the IP address, port and username shown there.

Connect from your computer (PowerShell or Terminal):

```bash
ssh -p 65002 u123456789@YOUR.SERVER.IP
```

The application goes in a folder **next to** `public_html`, not inside it, so that nothing except the `public` folder can ever be reached from the internet:

```
/home/u123456789/domains/edgetinker.com/
├── app/            ← the whole project goes here (private)
└── public_html/    ← what visitors see (set up in step 5)
```

### Option A: with Git (recommended)

```bash
cd ~/domains/edgetinker.com
git clone https://github.com/YOUR-ACCOUNT/YOUR-REPOSITORY.git app
```

For a private repository, GitHub asks for a username and a *personal access token* in place of a password. Create one at GitHub → Settings → Developer settings → Personal access tokens, with read access to the repository.

> hPanel also has **Advanced → Git**, which can pull a repository automatically when you push. It is designed to place files inside `public_html`. Because this project must sit outside `public_html`, use the `git clone` command above instead.

### Option B: upload a ZIP

1. On your computer, in the project folder, run `git archive --format=zip -o edgetinker.zip HEAD`. This creates a ZIP of the committed project without `vendor`, `node_modules` or your local `.env`.
2. hPanel → **Files → File Manager**. Go to `domains/edgetinker.com`, create a folder named `app`, open it, upload `edgetinker.zip` and choose **Extract**.
3. Delete the ZIP file from the server.

The compiled CSS and JavaScript (`public/build`) are part of the project, so the server does not need Node.js.

## 5. Point the domain at the `public` folder

Hostinger does not let you change which folder the main domain uses, so `public_html` is replaced by a link to the project's `public` folder. In the SSH session:

```bash
cd ~/domains/edgetinker.com

# Keep whatever was in public_html (usually just a default page), in case you need it.
mv public_html public_html_backup

# Make public_html a link to the project's public folder.
ln -s ~/domains/edgetinker.com/app/public ~/domains/edgetinker.com/public_html

# Check it: this should list index.php, build, placeholders, .htaccess …
ls -la public_html/
```

From now on, anything in `app/public` is what visitors see, and updates need no extra copying.

If, after finishing the remaining steps, the site shows "403 Forbidden" for every page, the server is not following the link. Undo it (`rm public_html && mv public_html_backup public_html`) and use the copy method in [section 14](#14-if-you-have-no-ssh-access), step 2, instead.

## 6. Create the `.env` file

`.env` holds the passwords and settings for this server. It is never stored in Git.

```bash
cd ~/domains/edgetinker.com/app
cp .env.production.example .env
nano .env
```

Fill in every value marked `CHANGE-ME`:

| Setting | Value |
|---|---|
| `APP_URL` | `https://edgetinker.com` (your real address, with `https`, no slash at the end) |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | from step 2 |
| `MAIL_USERNAME`, `MAIL_FROM_ADDRESS` | the mailbox from step 3 (both the same address) |
| `MAIL_PASSWORD` | the mailbox password. If it contains spaces or `#`, wrap it in double quotes. |
| `CONTACT_RECIPIENT` | where contact-form messages should be emailed |

Leave `APP_KEY` for now; the next step generates it. Leave `APP_DEBUG=false`: `true` would show error details to visitors.

In nano, save with **Ctrl+O**, **Enter**, and leave with **Ctrl+X**.

```bash
chmod 600 .env    # only your account can read the file
```

## 7. Install, migrate and link storage

Still in `~/domains/edgetinker.com/app`:

```bash
# Check the PHP version the command line uses. It must say 8.3 or newer.
php -v
```

If it shows an older version, use the full path to a newer one in every command below, for example `/opt/alt/php83/usr/bin/php` in place of `php` (list what is installed with `ls /opt/alt/`).

```bash
# 1. Download the PHP libraries (takes a minute or two).
#    --no-scripts: Hostinger disables PHP's proc_open, which Composer needs to run its
#    automatic follow-up step, so that step is run by hand on the next line instead.
composer install --no-dev --optimize-autoloader --no-scripts
php artisan package:discover
#    If "composer" is not found or is version 1, use composer2 in place of composer.

# 2. Generate the encryption key (writes APP_KEY into .env).
#    --force skips the "Application in production, are you sure?" question,
#    which cannot be shown on Hostinger for the same proc_open reason.
php artisan key:generate --force

# 3. Create the database tables.
php artisan migrate --force

# 4. Make uploaded images and documents reachable from the web.
php artisan storage:link

# 5. Cache the configuration, routes and templates for speed.
php artisan optimize
```

**Do not run `php artisan db:seed` on the live site.** It loads the demo catalogue (placeholder products and images). The live site starts empty and you add real content in Content Studio.

**If step 4 fails** with a message about `symlink()` being disabled, create the link with the shell instead:

```bash
ln -s ~/domains/edgetinker.com/app/storage/app/public ~/domains/edgetinker.com/app/public/storage
```

If that is refused too, store uploads directly inside the web folder: add this line to `.env` and run `php artisan optimize` again.

```
PUBLIC_DISK_ROOT=/home/u123456789/domains/edgetinker.com/app/public/storage
```

Finally make sure the server can write logs, cache files and uploads:

```bash
chmod -R 775 storage bootstrap/cache
```

## 8. Create the Content Admin

There is exactly one Content Admin, and this command is the only way to create it. Give the answers on the command line (on Hostinger the command cannot ask questions interactively, because `proc_open` is disabled):

```bash
php artisan studio:create-admin --name="Your Name" --email="you@edgetinker.com" --password="Choose-A-Strong-Passw0rd"
history -c
```

The password needs at least 10 characters, with upper and lower case letters and a number. `history -c` clears the server's command history, so the password you just typed is not left there. You can also change the password after signing in, under **Account**.

You can now sign in at `https://edgetinker.com/contentcreator`. The address is not linked from anywhere on the site; bookmark it. After signing in, turn on two-factor authentication under **Account**.

Content Creators register themselves at `https://edgetinker.com/contentcreator/register`, confirm their email address, and then appear under **Users** for you to activate.

## 9. Set up the cron job

Two scheduled tasks. The first runs every minute: it sends queued emails and resizes uploaded images. The second runs once a day and tidies up.

Hostinger switches off `proc_open`, which Laravel's usual `schedule:run` needs to start the email worker, so the worker is started directly.

1. hPanel → **Advanced → Cron Jobs**.
2. Choose type **Custom** (not PHP) if offered.
3. Every minute: enter `*` in all five boxes (minute, hour, day, month, weekday), which reads `* * * * *`, with this command:

   ```
   /usr/bin/php /home/u123456789/domains/edgetinker.com/app/artisan queue:work --stop-when-empty --max-time=50 --tries=3
   ```

4. Once a day: minute `15`, hour `3`, and `*` in the other three boxes, with this command:

   ```
   /usr/bin/php /home/u123456789/domains/edgetinker.com/app/artisan queue:prune-failed --hours=168
   ```

   (Expired password-reset links are refused anyway; they are simply left in the database.)
5. Save each job. If `php -v` in step 7 needed a full path, use that same path in place of `/usr/bin/php`.

If you set this up earlier with `schedule:run`, delete that job and add the two above: on Hostinger it runs without errors but never sends anything.

hPanel's cron form does not accept characters such as `>` or `&`, so do not add `>> /dev/null 2>&1` to the command.

After a few minutes, **Cron Jobs → View output** should show the task running without errors.

## 10. Turn on SSL (HTTPS)

1. hPanel → **Security → SSL**. A free certificate is normally installed automatically within an hour of the domain pointing at Hostinger. If it is not listed as active, choose **Install SSL** for the domain.
2. Turn on **Force HTTPS** for the domain (on the same page or under the domain's settings), so that `http://` addresses redirect to `https://`.

`.env` already sets `SESSION_SECURE_COOKIE=true`, so sign-in only works over HTTPS. If you cannot sign in, check that the address bar shows `https://`.

## 11. Check that everything works

| Check | Expected |
|---|---|
| `https://edgetinker.com` | The home page, with no products yet |
| `https://edgetinker.com/contentcreator` | The sign-in page; you can sign in |
| Content Studio → **Site settings** → **Send a test email to me** | "A test email was sent"; it arrives within a couple of minutes |
| Content Studio → **Site settings** | Fill in the contact email, phone and address (the site shows `[PLACEHOLDER]` until you do) |
| Add a category, then a product with an image | The product appears on the site; a minute later its image shows "resized" in the Media library |
| `https://edgetinker.com/contact` → send a message | It appears under **Messages** and arrives by email |
| `https://edgetinker.com/styleguide` | "We could not find that page" (the styleguide is switched off on the live site) |
| `https://edgetinker.com/.env` | "403 Forbidden" or "not found". It must never show your settings. |
| `https://edgetinker.com/sitemap.xml` | A list of your pages. Submit this address in Google Search Console. |

## 12. Backups

Three things hold your data. Everything else can be re-created from Git.

| What | Where |
|---|---|
| The database | All content, users, revisions and messages |
| `app/storage/app/public` | Every uploaded image and document |
| `app/.env` | Passwords and the encryption key. Without the same `APP_KEY`, two-factor secrets cannot be read. |

**Automatic:** hPanel → **Files → Backups** shows the backups Hostinger takes for your plan and lets you restore files or a database. Check how often your plan backs up and how long backups are kept.

**Your own copy** (recommended before every update, and at least monthly):

```bash
cd ~/domains/edgetinker.com
mkdir -p backups

# Database (asks for the database password from .env)
mysqldump -u u123456789_dbuser -p u123456789_edgetinker | gzip > backups/db-$(date +%F).sql.gz

# Uploaded files and settings
tar -czf backups/files-$(date +%F).tar.gz app/storage/app/public app/.env
```

Download the two files to your own computer (File Manager → `backups` → Download) and keep them somewhere safe. A backup that only exists on the same server is lost with it.

**To restore** a database backup:

```bash
gunzip < backups/db-2026-01-31.sql.gz | mysql -u u123456789_dbuser -p u123456789_edgetinker
```

## 13. Deploying an update

On your computer: make the change, run `npm run build` if you changed CSS, JavaScript or templates, run `php artisan test`, then commit and push.

On the server:

```bash
cd ~/domains/edgetinker.com/app

php artisan down                 # shows a "back shortly" page to visitors
git pull                         # fetch the new version
composer install --no-dev --optimize-autoloader --no-scripts
php artisan package:discover     # the step Composer cannot run itself on Hostinger
php artisan migrate --force      # apply any database changes (take a backup first, section 12)
php artisan optimize:clear       # drop the old cached configuration and templates
php artisan optimize             # cache the new ones
php artisan up                   # back online
```

Take a backup first (section 12). If you uploaded a ZIP instead of using Git, upload and extract the new ZIP over the `app` folder (this does not touch `.env` or `storage`), then run the same commands without `git pull`.

If something goes wrong: `git log --oneline` shows earlier versions, `git checkout <id>` returns to one, and re-running the commands from `composer install` onwards brings the site back. Restore the database backup as well if the update changed the database.

## 14. If you have no SSH access

Cloud plans include SSH, and it makes everything above much easier. If you truly cannot use it:

1. **Files:** on your computer run `composer install --no-dev --optimize-autoloader`, then ZIP the whole project **including** the `vendor` folder (but not `node_modules` or `.env`). Upload and extract it into `domains/edgetinker.com/app` with File Manager.
2. **Web folder:** a link cannot be created without SSH. Instead, copy everything inside `app/public` (including the hidden `.htaccess`) into `public_html`. Then edit `public_html/index.php` in File Manager and change the two lines that contain `__DIR__.'/../` so that they point at the app folder:

   ```php
   require __DIR__.'/../app/vendor/autoload.php';
   $app = require_once __DIR__.'/../app/bootstrap/app.php';
   ```

   (The line that mentions `storage/framework/maintenance.php` needs the same `../app/` prefix.) After every update, copy the new contents of `app/public/build` into `public_html/build` again.
3. **`.env`:** create it in File Manager from `.env.production.example`. Generate the key on your own computer with `php artisan key:generate --show` and paste it in as `APP_KEY`. Add this line, so uploads are stored where the web server can serve them:

   ```
   PUBLIC_DISK_ROOT=/home/u123456789/domains/edgetinker.com/public_html/storage
   ```

4. **Commands:** run each one once as a temporary cron job (hPanel → Cron Jobs), wait for it to run, check its output, then delete the job. Run them in this order:

   ```
   /usr/bin/php /home/u123456789/domains/edgetinker.com/app/artisan migrate --force
   /usr/bin/php /home/u123456789/domains/edgetinker.com/app/artisan optimize
   /usr/bin/php /home/u123456789/domains/edgetinker.com/app/artisan studio:create-admin --name="Your Name" --email=you@edgetinker.com --password="A-Temporary-Passw0rd"
   ```

   The password in the last command is visible in hPanel, so sign in straight away and change it under **Account**.
5. Add the permanent every-minute cron job from step 9.

## 15. Troubleshooting

| Symptom | Likely cause and fix |
|---|---|
| "The Process class relies on proc_open, which is not available" during `composer install` | Hostinger disables `proc_open`. Add `--no-scripts` to `composer install`, then run `php artisan package:discover` yourself (step 7). |
| "Call to undefined function Laravel\Prompts\proc_open()" with "Are you sure you want to run this command?" | Answer `no`, then run the same command again with `--force` (for example `php artisan key:generate --force`). For `studio:create-admin`, give `--name`, `--email` and `--password` on the command line (step 8). |
| A blank page or "500 Server Error" | Look at the last lines of `app/storage/logs/laravel-<date>.log`. Most often `.env` has a wrong database name or password, `APP_KEY` is empty, or `storage` is not writable (`chmod -R 775 storage bootstrap/cache`). |
| "Composer detected issues in your platform: Your Composer dependencies require a PHP version >= 8.3" | The web or command-line PHP is too old. Repeat step 1, and use the full PHP path in commands (step 7). |
| The home page works but every other page says "Not Found" | `.htaccess` is missing from the web folder. Check that `ls -la public_html/` shows it. |
| Pages have no styling | `APP_URL` in `.env` does not match the real address (for example `http` instead of `https`), or `public/build` was not uploaded. Fix it and run `php artisan optimize`. |
| Images show as broken | The storage link is missing: repeat the last part of step 7. Also check `APP_URL`. |
| Cannot sign in; the form just reloads | You are on `http://`. Use `https://` and turn on Force HTTPS (step 10). |
| "419 Page Expired" on every form | Cookies are not being saved: the same `http`/`https` mismatch, or the `sessions` table is missing (run `php artisan migrate --force`). |
| Emails never arrive | Check **Site settings → Send a test email**; it shows the mail server's error. Usually the mailbox password is wrong, or `MAIL_FROM_ADDRESS` differs from `MAIL_USERNAME`. Also confirm the cron job is running (step 9), since emails are sent by it. |
| Uploaded images stay on "Resizing queued" | The cron job is not running, or neither `gd` nor `imagick` is enabled (step 1). `php artisan queue:failed` lists jobs that failed and why. |
| The cron job runs, but emails stay queued | The job uses `schedule:run`, which cannot start the worker on Hostinger. Use the `queue:work` command from step 9. |
| Home banner editor: "… could not be downloaded" when choosing a font | The server could not reach Google Fonts (`fonts.googleapis.com`, `fonts.gstatic.com`). Fonts are downloaded once, then served from this site. Try again later, or choose a site font. `storage/logs` has the exact error. |
| The browser tab still shows the old icon | Browsers keep favicons for a long time. Open the site in a private window to check; it updates by itself later. |
| A change in `.env` has no effect | Settings are cached. Run `php artisan optimize` after every `.env` change. |
| Locked out of the Content Admin account | Use **Forgot your password?** on the sign-in page. If two-factor codes are lost and the recovery codes too, run `php artisan tinker` and then `App\Models\User::admin()->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null, 'two_factor_recovery_codes' => null])->save();` |
