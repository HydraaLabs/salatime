# Worldwide prayer pages

The directory has 1,292 cities in 242 countries and territories, in French,
Arabic (RTL), English, Spanish, Bengali and Hindi: 7,752 city pages, 1,452 country directories
and six world directories. The existing 20 Moroccan cities keep their URLs.

Examples:
- `/fr/horaires-priere`
- `/fr/horaires-priere/france/paris-2988507`
- `/ar/prayer-times/saudi-arabia/makkah-104515`
- `/en/prayer-times/japan/tokyo-1850147`
- `/es/horarios-oracion/marruecos/fes`

## Source and maintenance

`resources/data/prayer-cities.json` is a checked-in snapshot generated from
GeoNames `cities15000.zip` and `alternateNamesV2.zip`. Selection: populated
places with at least 500,000 inhabitants plus national capitals, excluding
Morocco (the existing curated data remains in `config/prayer_pages.php`).
Population definitions vary by source; this is a city directory, not a ranking
of metropolitan areas. Names use preferred, current language alternatives when
available, otherwise the original GeoNames name. Country names use Babel/CLDR.

Source: https://download.geonames.org/export/dump/ . GeoNames data is licensed
under CC BY 4.0; attribution is visible in the footer. The snapshot records its
source, generation date and input SHA-256 hashes. No external API or database is
required to serve the pages.

To rebuild deliberately, download the two public ZIP files and run:

```sh
python3 scripts/build_prayer_catalog.py /path/cities15000.zip /path/alternateNamesV2.zip
```

This maintenance command requires Python with Babel. Review the diff before
replacing the catalog: a renamed city or country can change its slug and needs
a permanent redirect from the previous URL. There is no automatic import job.

## Calculation and indexing

Each city uses its IANA time zone, regional calculation preset and Asr school.
Presets are estimates, not a claim of official mosque times. The page displays
the method, school, local zone and the high-latitude adjustment rule. Umm al-Qura
uses the preset's 90-minute Isha interval; no automatic Ramadan extension is
applied. At polar sunrise/sunset absence, times are unavailable and the visitor
is directed to a local timetable.

The date is local to the city. Daily, weekly and current-month schedules render
on the server. Noon is used to determine the daytime UTC offset on clock-change
dates. Next-prayer selection includes the previous day's Isha after midnight.
HTTP caching stops at the next prayer/local midnight, capped at 60 seconds.
Calendar cache keys include coordinates, method, school, timezone and version.
The shared Asr calculation now uses the requested date and longitude rather
than the server clock; the mobile calendar cache version was incremented too.

Every page has a canonical URL, reciprocal language alternates and English as
x-default. City pages include WebPage, City, GeoCoordinates, BreadcrumbList and
FAQPage structured data. Directories use CollectionPage/ItemList/BreadcrumbList.
All URLs are in `/sitemap.xml`, and the homepage links to the world directory.
Search is accent-insensitive and works with names in all six languages.
Unknown countries, city slugs and mismatched country/city pairs return HTTP 404.

## Verification and release

```sh
php8.3 vendor/bin/phpunit
php8.3 artisan route:cache
php8.3 artisan route:clear
php8.3 artisan config:cache
php8.3 artisan config:clear
```

The world feature suite renders every configured country and city in each
language and covers sitemap completeness, alternate URLs, clock changes, local
midnight, midnight Isha, polar dates and cache separation. Before release, verify
mobile RTL, search and monthly tables in a browser. Set `PUBLIC_SITE_URL` to the
public canonical origin. Deploy the catalog JSON together with the controllers,
services, config, translations, views and routes; rebuild the production caches
using the existing deployment process. Local validation does not publish pages
or guarantee their indexing by search engines.

The homepage and calendars share the same flag language picker. Bengali and Hindi
include translated interface text, country names from Babel/CLDR and curated
Moroccan city names. Other city names currently use the English catalog spelling;
the generator uses GeoNames Bengali/Hindi names when available on the next refresh.
Country URLs for Arabic, Bengali and Hindi use the English country slug.
