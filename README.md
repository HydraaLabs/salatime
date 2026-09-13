# SalaTime

SalaTime is a prayer companion with a multilingual website, a Laravel administration
panel and an API for the [SalaTime mobile app](https://github.com/HydraaLabs/salatime_app).
It provides prayer times and city pages, Quran and religious reference content,
mobile accounts, cloud preferences and Quran/Athkar reading progress.

This repository contains the **website and backend**. The Flutter app is maintained
in [HydraaLabs/salatime_app](https://github.com/HydraaLabs/salatime_app).

## Requirements

- PHP **8.3** with PDO MySQL, mbstring, XML/DOM, cURL, fileinfo, OpenSSL and ZIP;
  GD is needed for image processing. The lockfile includes dependencies that need
  PHP 8.3 even though the application composer constraint is broader.
- Composer 2, Node.js **22** and npm.
- MySQL 8 or MariaDB 10.6+ (fresh installation tested with **MariaDB 11**).
- A writable `storage/` and `bootstrap/cache/` for the PHP process.

The complete migration history targets MySQL/MariaDB. SQLite is not supported
for a full installation because some historical migrations change foreign keys.

## Installation

```bash
git clone https://github.com/HydraaLabs/salatime.git
cd salatime
cp .env.example .env
composer install
php artisan key:generate
```

Create an **empty database** and a dedicated database user with access to it.
Update `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD` in `.env`. Set `APP_URL` and
`PUBLIC_SITE_URL` to the URL you will use. For local development the defaults are
`http://127.0.0.1:8000`. Keep `APP_INSTALLED=true` for this CLI installation path.

```bash
php artisan migrate --seed
php artisan storage:link
php artisan salatime:admin
npm ci
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

Open <http://127.0.0.1:8000> for the website or
<http://127.0.0.1:8000/login> for administration. `salatime:admin` asks for your
name, email and a hidden password of at least 12 characters, including letters
and numbers. **No administrator or shared password is seeded.**

Run the queue worker in another terminal for queued work:

```bash
php artisan queue:work --tries=3
```

For frontend development, run `npm run dev` alongside `php artisan serve`.
Assets are built by Vite; do not manually move assets or edit the Vite entrypoints.
The old browser purchase-code installer is not needed for this installation.

## Database migrations and safe seed data

The schema is versioned in `database/migrations/`, including empty tables for
administrators, mobile accounts, sessions, preferences and reading progress.
`php artisan migrate --seed` builds a new instance from source; no production
SQL dump is required or included.

The default seed contains:

- Roles and permission definitions, application and landing-page defaults.
- Quran chapters, verses, translator metadata and bundled translations/commentary.
- Duas, Dhikr, names, ingredient codes and reciter metadata.
- Donation category and payment-provider names, without credentials or payments.

It creates **no users, profiles, sessions, tokens, password resets, mobile
accounts/identities, device history, preferences, reading history, jobs,
donations or transactions**. Administrator creation is a separate explicit step.
Stale example prayer calendars and sample device records are not imported.

Seed again with `php artisan db:seed`: existing settings and nonempty catalogues
are preserved. Quran tables form one reference set and are seeded together only
when all are empty; partially populated Quran data is preserved with a warning.
This is an initial reference seed, not a production data synchronization tool.
Never run `migrate:fresh` against an existing installation you want to keep.

## Connect the mobile app

Build the app with `--dart-define=SALATIME_API_URL=https://your-domain.example`.
Use a URL reachable from the phone; its `localhost` is not your development PC.
See the [mobile installation guide](https://github.com/HydraaLabs/salatime_app#installation).

Email accounts need working mail delivery. For Mailgun SMTP, set `MAIL_MAILER=smtp`,
your region's Mailgun SMTP hostname, port `587`, `MAIL_ENCRYPTION=tls`, your own
SMTP username/password and a verified sender. Set `MOBILE_AUTH_MAIL_ENABLED=true`
after configuring delivery. SMTP credentials belong only in the backend `.env`.

Google login needs OAuth clients in your Google project with the correct Android
package/signing certificates and iOS bundle ID. Fill `MOBILE_GOOGLE_CLIENT_IDS`
with allowed audiences and `MOBILE_GOOGLE_SERVER_CLIENT_ID` with your web client
ID; configure the iOS client when building for iOS. Apple login similarly needs
your Apple IDs, redirect URI and a private key outside `public/`. Empty provider
settings do not enable a working provider. Administrator accounts and mobile
accounts are intentionally separate.

All `VITE_*` values are public build configuration. Never put secrets there.
Maps, payment providers and external content APIs require your own configuration;
the repository does not supply live service credentials.

## Production

Point Nginx/Apache at `public/`, enable HTTPS, set `APP_ENV=production` and
`APP_DEBUG=false`, and restrict `.env` access. Keep `APP_KEY` stable and back up
the database and uploaded files before upgrades. Use a dedicated database user.

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan config:cache
php artisan view:cache
php artisan queue:restart
```

Manage `queue:work` with a process supervisor. Run `php artisan schedule:run`
every minute using your host's scheduler. Adjust branding, legal text, mail,
payment settings and external links in administration for your own instance.
The development server is only for local development.

## Checks

```bash
composer validate --no-check-publish
npm run build
```

The installation regression test must use an **isolated empty MySQL/MariaDB test
database** whose name ends in `_test`, never production. It must have no tables. Configure the test process DB variables, then run:

```bash
SALATIME_INSTALL_TESTS=true APP_ENV=testing php artisan test --filter=OpenSourceInstallationTest
```

The test performs migrations and validates reference seeding, empty personal
tables, repeatability and explicit administrator creation. Other tests may have
their own isolated fixtures.

## License

SalaTime code that HydraaLabs can license is available under the [MIT license](LICENSE).
See [third-party notices](THIRD_PARTY_NOTICES.md) for upstream code, translations,
recordings and other assets, which retain their original terms.
