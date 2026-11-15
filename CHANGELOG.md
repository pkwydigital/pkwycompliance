# Changelog

## 1.3.4

- Fix: with PKWYC_DISABLE set, a WPForms Mailchimp connection set to "process if Consent is not empty" subscribed everyone, because WPForms skips a rule on a field type it does not know. The switch now records the Consent field as blank, so that connection subscribes nobody while it is on. Deactivating, deleting or renaming the plugin still lets WPForms skip the rule: the disabled notice, the Help tab and the README now say to pause those Mailchimp connections first.
- Fix: a second Consent field on a WPForms form (for example from Duplicate) was hidden but still required, so every submission failed with an error the visitor could not see. Only the first Consent field is shown and checked, the builder no longer offers Duplicate on the field, and Settings, Compliance flags a form carrying more than one.
- Settings, Compliance lists WPForms forms that send people to Mailchimp but have no Consent field at all.
- Fix: a value pre-filled on the WPForms field (dynamic population or a default) could replace "agreed" as the value a ticked box submits.
- Gravity Forms: the Visibility setting is no longer offered on the Consent field and a stored Administrative or Hidden value is ignored, because a field the visitor never sees can never be ticked. No consent record is stored for such a field.
- Updates: the plugin header names this repository as its update source (Update URI); forced auto-updates apply only to this repository's own release download; only the attached pkwycompliance.zip is taken, never another zip or GitHub's source archive; and an unpacked update that is not PKWY Compliance at the offered version is refused before the old copy is removed. Check again on Dashboard, Updates now refetches the release at once.
- Translation template: bug reports point at the GitHub issues page.
- Development: tests now cover the real WordPress, WPForms Pro and Gravity Forms behaviour on a private test site before each release, and GitHub Actions are pinned to exact versions with read-only permissions.

## 1.3.3

- Fix: the consent sentence used the theme's body text size instead of the form's own label size, so on many themes it was noticeably larger than the rest of the form (21.8px against 14px on Gravity Forms and 16px on WPForms with Twenty Twenty-Five). It now carries each form plugin's own inline checkbox-label class.
- Fix: on Gravity Forms the sentence dropped below the checkbox instead of sitting beside it. The field now takes Gravity Forms' choice-field layout, as its built-in Consent field does.

## 1.3.2

- Fix: on Gravity Forms 3.x a newly added PKWY Consent field was labelled "Untitled" and was not Required, because its defaults were set through a hook Gravity Forms does not have. A new field is now labelled "Consent", Required, with the label hidden, as intended. Fields already on forms are unchanged; check that each one has the Required setting you want.
- Settings, Compliance and its Help tab link to an illustrated setup guide (PDF, attached to each release) and a short video.

## 1.3.1

- WPForms entry screen: the consent wording box now has inner padding, so the text no longer sits against the box edge.

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
