# Clevedon Brewery & Tap Bar PWA

A simple public information-board PWA for Clevedon Brewery & Tap Bar.

## Current content

- IPA
- BS21
- Fools Gold (Twisted Oak)
- Clevedon Show
- Clevedon Brewery Makers & Crafters Market
- Opening times: Thursday 4–7pm, Friday 4–9pm, Saturday 2–9pm, Sunday 2–6pm

## Development

The public PWA is plain HTML, CSS and JavaScript, with PHP used for the admin
area and anonymous statistics. There is no build step or npm dependency.

Use PHP 7.4 or newer and serve the repository as the document root when testing
locally.

## Server configuration

- On Plesk accounts that provide **Password-Protected Directories**, protect the
  `admin` directory and add a Plesk user for it. The admin pages recognise the
  server-verified `REMOTE_USER`, so no application password setting is needed.
  Always access the protected directory over HTTPS.
- Alternatively, before using `/admin/`, create a hash with PHP's
  `password_hash(..., PASSWORD_DEFAULT)`. For Plesk/PHP-FPM, Base64-encode that
  hash and set it as `BREWERY_ADMIN_PASSWORD_HASH_B64`; Base64 avoids PHP-FPM
  interpreting the hash's dollar signs. Other server environments may set the
  raw hash as `BREWERY_ADMIN_PASSWORD_HASH`. There is no password stored in this
  public repository, and admin login stays disabled until a valid server setting
  is present.
- Statistics default to the PHP-guarded `live-data/data/stats.php` file. For an
  additional layer of protection, set `BREWERY_STATS_DATA_DIR` to a writable
  directory outside the public document root.
- The first tracked event or authenticated dashboard visit migrates totals from
  the old `live-data/data/stats.json` file and removes its publicly readable
  contents.

The intended public URL is `https://clevedonbrewery.clevedon.me`.
