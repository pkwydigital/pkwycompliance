# Changelog

## 1.0.0

First release.

- Consent field for WPForms and Gravity Forms, sharing one set of wording.
- Wording, Privacy Policy link and Terms link set once under Settings, Compliance.
- Fails closed: an unconfigured plugin refuses submission rather than collecting a meaningless consent.
- Stored value is normalised to `agreed` or empty.
- One instance per form.
- Never pre-ticked.
- Self-updates from GitHub releases, with `PKWYC_NO_AUTO_UPDATE` and `PKWYC_DISABLE` as escape hatches.
