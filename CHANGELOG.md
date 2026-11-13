# Changelog

## Unreleased

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
