webtrees Facebook Module
========================

Facebook integration for webtrees genealogy software.

* webtrees 1.5.0 or higher is required.
* 'Allow visitors to request account registration' must be enabled in Site Configuration if you want
  to allow new users via Facebook.

## Installation ##
1. Install and enable the module.
2. Setup Facebook API App ID and Secret at {WEBTREES_ROOT}/module.php?mod=facebook&mod_action=admin

## Known Issues ##
* If a user logs in with a Facebook account which uses a different email address than their existing
  webtrees email address, a second account will be created for the same individual. If administrator
  approval is required for new accounts, the administrator can delete the new account and link the
  existing account to the user's Facebook account. The administrator can also link existing users to
  their Facebook accounts in advance (where possible) to avoid this situation.
