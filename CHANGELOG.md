# Changelog

## Unreleased

- Admin notice on every admin screen when the consent field is not usable, linking to Settings, Compliance. Stronger when the Privacy Policy URL is missing, softer when only the site wording is the problem.
- The settings screen, and the admin hint on an unusable form, now name the actual reason: missing Privacy Policy URL, blank wording, or wording that uses {terms} with no Terms URL.
- Uninstall removes the settings and the cached update check, on every site of a multisite network.

## 1.0.0

First release.

- Consent field for WPForms and Gravity Forms, sharing one set of wording.
- Wording, Privacy Policy link and Terms link set once under Settings, Compliance.
- Fails closed: an unconfigured plugin refuses submission rather than collecting a meaningless consent.
- Stored value is normalised to `agreed` or empty.
- One instance per form.
- Never pre-ticked.
- Self-updates from GitHub releases, with `PKWYC_NO_AUTO_UPDATE` and `PKWYC_DISABLE` as escape hatches.
