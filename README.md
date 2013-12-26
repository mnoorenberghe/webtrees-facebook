webtrees Facebook Module
========================

* webtrees 1.5.0 or higher is required.
* 'Allow visitors to request account registration' must be enabled in Site Configuration if you want to allow new users via Facebook.

## Installation ##
1. Install and enable module.
2. Setup Facebook API App ID and Secret at {WEBTREES_ROOT}/module.php?mod=facebook&mod_action=admin

## TODO ##
* handle transition from internal account to FB if using different email address. - Maybe not a big deal if admin is approving since they can merge.
* allow user comment on account creation
* more validation on pre-approved users (check valid Gedcom and XREF)
* friend picker for pre-approval and linking
* relationship gedcom pref
