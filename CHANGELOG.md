# Changelog

## 1.3.0

- Settings, Compliance: a "Forms with the Consent field" table lists every form using the field and, for each Mailchimp connection (WPForms) or active Mailchimp feed (Gravity Forms), whether it only subscribes people who ticked the box. Adding the box changes nothing in Mailchimp by itself; connections that would still subscribe everyone are shown in red.
- Settings, Compliance: the Privacy Policy URL now names the page it links to, offers the page set under Settings, Privacy when the box is empty (it fills the box, nothing is saved until Save Changes), and notes when the two differ. Only a published page is suggested, and it is never used silently.
- A Help tab on Settings, Compliance (what it does, adding the field, Mailchimp, why a form refuses to submit, emergency switches), and a link to the setup guide in the README.
- The form builders say, beside the Consent field, that it does nothing to Mailchimp on its own and which rule to add.
- Every entry from a form with the field now keeps the exact wording the visitor was shown, with the time and plugin version, and the entry screen shows it. The stored answer is unchanged: "agreed" or empty.
- Fix: on Gravity Forms 3.x the editor allowed a second Consent field, and Duplicate made another. The field now cannot be added twice or duplicated.
- Fix: with PKWYC_DISABLE set, Gravity Forms forms carrying a required Consent field could not be submitted at all. The switch now makes that field optional.
- Gravity Forms: the field is called "PKWY Consent" in the editor, to tell it apart from Gravity Forms' own Consent field.
- Translation template shipped (languages/pkwycompliance.pot); translations load on init, and the WPForms field no longer makes WordPress 6.7+ log a "translation loaded too early" notice.
- Plugin header states the minimum WordPress (5.6) and PHP (7.4). Tests run on GitHub on PHP 7.4, 8.1, 8.3 and 8.4.

## 1.2.0

- Admin notice on every admin screen when the consent field is not usable, linking to Settings, Compliance. Stronger when the Privacy Policy URL is missing, softer when only the site wording is the problem.
- The settings screen, and the admin hint on an unusable form, now name the actual reason: missing Privacy Policy URL, blank wording, or wording that uses {terms} with no Terms URL.
- Uninstall removes the settings and the cached update check, on every site of a multisite network.
- Fix: activating Gravity Forms 3.x beside this plugin was a fatal error for the whole site. The field's get_field_label() signature was incompatible with GF_Field's. Present since 1.0.0; never seen because no site had Gravity Forms active.
- Fix: on Gravity Forms an unticked required box said "This field is required." instead of "You must agree to continue.", and with the settings cleared it still asked the visitor to tick the box. A form's own Error Message is still used when set.
- Fix: WordPress 6.7+ logged "translation loading was triggered too early" for this plugin on every request.

## 1.0.0

First release.

- Consent field for WPForms and Gravity Forms, sharing one set of wording.
- Wording, Privacy Policy link and Terms link set once under Settings, Compliance.
- Fails closed: an unconfigured plugin refuses submission rather than collecting a meaningless consent.
- Stored value is normalised to `agreed` or empty.
- One instance per form.
- Never pre-ticked.
- Self-updates from GitHub releases, with `PKWYC_NO_AUTO_UPDATE` and `PKWYC_DISABLE` as escape hatches.
