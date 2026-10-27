# PKWY Compliance

Compliance building blocks for WordPress sites maintained by Parkway Digital.

The first component adds a marketing consent field to **WPForms** and **Gravity Forms**, with the legal wording held in one place instead of retyped on every form.

## Why it exists

Consent text copied onto each form drifts. One form says something slightly different from the next, nobody notices, and the record of what somebody agreed to becomes unreliable. This keeps the wording, the Privacy Policy link and the Terms link in a single setting, and every form that uses the field shows the same thing.

## Install

Download the latest release and install it like any other plugin, or drop the folder in `wp-content/plugins/pkwycompliance`.

Then set the wording under **Settings, Compliance**. Until the Privacy Policy URL, the Terms URL and the wording are all filled in, any form carrying the field will refuse to submit. That is deliberate: collecting agreement to documents a visitor cannot open is worse than collecting nothing.

## Using the field

Add **Consent** to a form from the field list, the same way as any other field. It is required by default and cannot be made optional, because an optional consent is not a consent. Only one per form; a second is ignored.

The stored value is `agreed` or empty, nothing else, whatever the request contains.

## Settings

| Setting | Purpose |
|---|---|
| Company name | Substituted wherever `{company}` appears |
| Privacy Policy URL | Linked from the wording |
| Terms URL | Linked from the wording |
| Consent wording | The sentence shown beside the checkbox |

Placeholders: `{company}`, `{privacy}`, `{terms}`. If the link placeholders are left out, the links are appended anyway.

## Updates

This plugin updates itself from this repository, without anyone pressing a button. That is intentional: the wording it displays is a compliance statement, and every site should show the current one.

Two escape hatches, for `wp-config.php`:

```php
define( 'PKWYC_NO_AUTO_UPDATE', true );  // keep the plugin, stop it updating
define( 'PKWYC_DISABLE', true );         // stop it enforcing anything at all
```

The second is the one to reach for if a release ever breaks form submission on a live site. It leaves the plugin installed and shows a warning in the admin so a disabled site does not look like a working one.

## Development

Pure logic lives in `includes/components/consent/logic.php` and `includes/updater.php`, with no WordPress dependency, so it is testable from a terminal:

```bash
php tests/test-consent.php
php tests/test-updater.php
```

Adapters translate that logic into each form plugin's API and hold no decisions of their own.

## Requirements

WordPress 5.6 or later, PHP 7.4 or later, and at least one of WPForms or Gravity Forms.

## Licence

GPL-2.0-or-later.
