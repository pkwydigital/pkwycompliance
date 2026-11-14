# PKWY Compliance

Compliance building blocks for WordPress sites maintained by Parkway Digital.

The first component adds a marketing consent field to **WPForms** and **Gravity Forms**, with the legal wording held in one place instead of retyped on every form.

## Why it exists

Consent text copied onto each form drifts. One form says something slightly different from the next, nobody notices, and the record of what somebody agreed to becomes unreliable. This keeps the wording, the Privacy Policy link and the Terms link in a single setting, and every form that uses the field shows the same thing by default.

## Setting it up

The short version, in the order it should be done on a site.

1. **Install and activate** the plugin (see Install below). It updates itself from then on.
2. **Settings, Compliance.** Fill in the company name, the Privacy Policy URL and, only if the site has one, the Terms URL. Under the Privacy Policy URL the screen names the page it links to, offers the page set under Settings, Privacy if the box is empty, and warns when the two differ: check the name is really the privacy policy. Edit the wording, then read the Preview and click Save Changes. The red warning on that screen, and the yellow one across wp-admin, go away once it is usable.
3. **Add the field** to each form that sends people to a mailing list: **Consent** in WPForms (Fancy Fields), **PKWY Consent** in Gravity Forms (Advanced Fields). One per form. Tick **Optional** (WPForms) or untick **Required** (Gravity Forms) when the form must still be sendable without agreeing to marketing, which is usually the case for an enquiry form.
4. **Make Mailchimp depend on it.** Ticking the box changes nothing in Mailchimp by itself. Open the form's Mailchimp connections. On every connection that subscribes people, keep any rules already there and add **Consent is not empty** to each rule group, using **And** inside the group. Do not use Add New Group for it: a new group is an "or", so people who did not tick would still be subscribed through the other groups. A connection with no rules: turn on conditional logic with just that one rule. On Gravity Forms the rule is **Consent is agreed**.
5. **Check it.** Settings, Compliance lists every form with the field and says, for each Mailchimp connection, whether it only takes people who ticked the box. Anything in red still needs step 4. Connections that do not subscribe anyone (recording an event, for example) are left for a person to judge.
6. **Do not send test enquiries through a live form.** A real submission reaches everything the form is connected to: notification emails, CRMs, ad conversion tracking. Check with the form's Preview instead, and look at the first real entries afterwards: the Consent column reads `agreed` when ticked and is blank when not.

## Install

Download the latest release and install it like any other plugin, or drop the folder in `wp-content/plugins/pkwycompliance`.

Then set the wording under **Settings, Compliance**. Until the Privacy Policy URL and the wording are filled in, any form carrying the field will refuse to submit. The Terms URL is optional; leave it blank if the site has no terms page, but then the wording must not use {terms}. That is deliberate: collecting agreement to documents a visitor cannot open is worse than collecting nothing.

Until that is done, administrators see a warning at the top of every admin screen, linking to the settings. It can be dismissed for the page being viewed but returns on the next one, until the settings are usable. With no Privacy Policy URL the warning says forms with the field will refuse to submit. With a Privacy Policy URL but site wording that is blank, or that uses {terms} with no Terms URL, it is softer, because forms carrying their own custom wording still work. The settings screen names the exact reason. No warning is shown while `PKWYC_DISABLE` is set, which has its own notice.

## Using the field

Add **Consent** to a form from the field list, the same way as any other field. On Gravity Forms it is called **PKWY Consent**, to tell it apart from Gravity Forms' own Consent field, which does not carry the site wording. It is required by default, so the form cannot be submitted unless the box is ticked. To make it optional on one form, tick **Optional** in the field's options on WPForms, or untick **Required** on Gravity Forms. An optional box left unticked stores an empty value. Either way the field still refuses submission until the wording and links are set. Only one per form: on WPForms a second is ignored when the form is shown; on Gravity Forms the form editor refuses a second one and has no Duplicate button for it (a form built through the API or an import can still carry two).

A form that needs different wording, for example an optional opt-in beside a required one elsewhere on the site, can set **Custom wording** in the field's options. Leave it blank to use the site wording. The links still come from the settings, and are added if the custom wording leaves them out. Every form with custom wording is listed under **Settings, Compliance**, so overrides stay visible.

The field can drive conditional logic, so a Mailchimp connection, a notification, a confirmation or another field can depend on the answer. On WPForms, use **Consent is not empty** for ticked and **Consent is empty** for not ticked. On Gravity Forms, use **Consent is agreed**. Showing and hiding other fields works live in the browser as well as on submission.

The stored value is `agreed` or empty, nothing else, whatever the request contains.

## Settings

| Setting | Purpose |
|---|---|
| Company name | Substituted wherever `{company}` appears |
| Privacy Policy URL | Linked from the wording |
| Terms URL | Optional. Linked from the wording when set |
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

## Uninstalling

Deleting the plugin from the Plugins screen removes its settings (on every site of a multisite network) and its cached update check. Deactivating it removes nothing. Entries already collected by WPForms or Gravity Forms belong to those plugins and are left as they are.

## Development

Pure logic lives in `includes/components/consent/logic.php`, `includes/components/consent/audit.php` and `includes/updater.php`, with no WordPress dependency, so it is testable from a terminal:

```bash
bash tests/run-all.sh
```

GitHub runs the same on every push, on PHP 7.4, 8.1, 8.3 and 8.4.

Adapters translate that logic into each form plugin's API and hold no decisions of their own.

## Requirements

WordPress 5.6 or later, PHP 7.4 or later, and at least one of WPForms or Gravity Forms.

## Licence

GPL-2.0-or-later.
