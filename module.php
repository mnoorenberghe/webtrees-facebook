<?php
// Facebook Module for webtrees
//
// Copyright (C) 2012 Matthew N.
//
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program. If not, see <http://www.gnu.org/licenses/>.

if (!defined('WT_WEBTREES')) {
    header('HTTP/1.0 403 Forbidden');
    exit;
}

define('WT_FACEBOOK_VERSION', 0.1);

class facebook_WT_Module extends WT_Module implements WT_Module_Config, WT_Module_Menu {
    const scope = 'user_birthday,user_hometown,user_location,user_relationships,user_relationship_details,email';
    const user_setting_facebook_username = 'facebook_username';

    private $hideStandardForms = false;

    // Implement WT_Module_Config
    public function getConfigLink() {
        return 'module.php?mod='.$this->getName().'&amp;mod_action=admin';
    }

    // Extend WT_Module
    public function getTitle() {
        return /* I18N: Name of a module */ WT_I18N::translate('Facebook');
    }

    // Extend WT_Module
    public function getDescription() {
        return /* I18N: Description of the "Facebook" module */ WT_I18N::translate('Allow users to login with Facebook.');
    }

    // Extend WT_Module
    public function modAction($mod_action) {
        switch($mod_action) {
            case 'admin':
                $this->admin();
                break;
	    case 'connect':
                $this->connect();
                break;
	    default:
                header('HTTP/1.0 404 Not Found');
        }
    }

    private function admin() {
        $mod_name = $this->getName();
        $preApproved = unserialize(get_module_setting($mod_name, 'preapproved'));

        $ALL_EDIT_OPTIONS = array( // from admin_users.php
          'none'  => /* I18N: Listbox entry; name of a role */ WT_I18N::translate('Visitor'),
          'access'=> /* I18N: Listbox entry; name of a role */ WT_I18N::translate('Member'),
          'edit'  => /* I18N: Listbox entry; name of a role */ WT_I18N::translate('Editor'),
          'accept'=> /* I18N: Listbox entry; name of a role */ WT_I18N::translate('Moderator'),
          'admin' => /* I18N: Listbox entry; name of a role */ WT_I18N::translate('Manager')
        );

        if (WT_Filter::post('saveAPI') && WT_Filter::checkCsrf()) {
            set_module_setting($mod_name, 'app_id', WT_Filter::post('app_id', WT_REGEX_ALPHANUM));
            set_module_setting($mod_name, 'app_secret', WT_Filter::post('app_secret', WT_REGEX_ALPHANUM));
            set_module_setting($mod_name, 'require_verified', WT_Filter::post('require_verified', WT_REGEX_INTEGER, false));
            set_module_setting($mod_name, 'hide_standard_forms', WT_Filter::post('hide_standard_forms', WT_REGEX_INTEGER, false));
            WT_FlashMessages::addMessage(WT_I18N::translate('Settings saved'));
        } else if (WT_Filter::post('addLink') && WT_Filter::checkCsrf()) {
            $user_id = WT_Filter::post('user_id', WT_REGEX_INTEGER);
            $facebook_username = $this->cleanseFacebookUsername(WT_Filter::post('facebook_username', WT_REGEX_USERNAME));
            if ($user_id && $facebook_username && !$this->get_user_id_from_facebook_username($facebook_username)) {
                set_user_setting($user_id, self::user_setting_facebook_username, $facebook_username);
                WT_FlashMessages::addMessage(WT_I18N::translate('User linked'));
            }
        } else if (WT_Filter::post('deleteLink') && WT_Filter::checkCsrf()) {
            $user_id = WT_Filter::post('deleteLink', WT_REGEX_INTEGER);
            if ($user_id) {
                set_user_setting($user_id, self::user_setting_facebook_username, NULL);
                WT_FlashMessages::addMessage(WT_I18N::translate('User unlinked'));
            }
        } else if (WT_Filter::post('addPreapproved') && WT_Filter::checkCsrf()) {
            $table = WT_Filter::post('preApproved');
            $row = $table['new'];
            $facebook_username = WT_Filter::post('preApproved_new_facebook_username', WT_REGEX_USERNAME);
            unset($row['facebook_username']);
            //var_dump($row);exit;
            $this->appendPreapproved($preApproved, $facebook_username, $row);
            //var_dump($preApproved);
            set_module_setting($mod_name, 'preapproved', serialize($preApproved));
            WT_FlashMessages::addMessage(WT_I18N::translate('Pre-approved user added'));
        } else if (WT_Filter::post('savePreapproved') && WT_Filter::checkCsrf()) {
            $table = WT_Filter::post('preApproved');
            unset($table['new']);
            foreach($table as $facebook_username => $row) {
                $this->appendPreapproved($preApproved, $facebook_username, $row);
            }
            //var_dump($preApproved);
            set_module_setting($mod_name, 'preapproved', serialize($preApproved));
            WT_FlashMessages::addMessage(WT_I18N::translate('Changes to pre-approved users saved'));
        } else if (WT_Filter::post('deletePreapproved') && WT_Filter::checkCsrf()) {
            $facebook_username = trim(WT_Filter::post('deletePreapproved', WT_REGEX_USERNAME));
            if ($facebook_username) {
                unset($preApproved[$facebook_username]);
                set_module_setting($mod_name, 'preapproved', serialize($preApproved));
                WT_FlashMessages::addMessage(WT_I18N::translate('Pre-approved user deleted'));
            }
        }

        $controller = new WT_Controller_Page();
        $controller
            ->requireAdminLogin()
            ->setPageTitle($this->getTitle())
            ->pageHeader();

        $linkedUsers = array();
        $users = $this->get_users_with_module_settings();
        foreach ($users as $userid => $user) {
            if (empty($user[0]->facebook_username)) {
                $unlinkedUsers[$userid] = $user[0];
            } else {
                $linkedUsers[$userid] = $user[0];
            }
        }

        $unlinkedOptions = $this->user_options($unlinkedUsers);

        require_once WT_ROOT.'includes/functions/functions_edit.php';
        require 'templates/admin.php';
    }

    private function appendPreapproved(&$preApproved, $facebook_username, $row) {
        $facebook_username = $this->cleanseFacebookUsername($facebook_username);
        if (!$facebook_username) {
            WT_FlashMessages::addMessage(WT_I18N::translate('Missing Facebook username'));
            return;
        }
        if ($this->get_user_id_from_facebook_username($facebook_username)) {
            WT_FlashMessages::addMessage(WT_I18N::translate('User is already registered'));
            return;
        }

        $preApproved[$facebook_username] = array();
        foreach ($row as $gedcom => $settings) {
            // TODO: check valid gedcom and possibly xref
            $preApproved[$facebook_username][$gedcom] = array(
                                                              'rootid' => filter_var($settings['rootid'], FILTER_VALIDATE_REGEXP, array("options" => array("regexp" => '/^(' .  WT_REGEX_XREF . ')$/u'))),
                                                              'gedcomid' => filter_var($settings['gedcomid'], FILTER_VALIDATE_REGEXP, array("options" => array("regexp" => '/^(' . WT_REGEX_XREF . ')$/u'))),
                                                              'canedit' => filter_var($settings['canedit'], FILTER_VALIDATE_REGEXP, array("options" => array("regexp" => '/^(' . WT_REGEX_ALPHA . ')$/u')))
            );
        }
    }

    private function isSetup() {
        $mod_name = $this->getName();
        $app_id = get_module_setting($mod_name, 'app_id');
        $app_secret = get_module_setting($mod_name, 'app_secret');
        $this->hideStandardForms = get_module_setting($mod_name, 'hide_standard_forms', false);

        return !empty($app_id) && !empty($app_secret);
    }

    private function connect() {
        global $WT_SESSION;

        $url = WT_Filter::post('url', NULL, WT_Filter::get('url', NULL, ''));
        // If we’ve clicked login from the login page, we don’t want to go back there.
        if (strpos($url, 'login.php') === 0
            || (strpos($url, 'mod=facebook') !== false
                && strpos($url, 'mod_action=connect') !== false)) {
            $url = '';
        }

        // Redirect to the homepage/$url if the user is already logged-in.
        if ($WT_SESSION->wt_user) {
            header('Location: ' . WT_SCRIPT_PATH . $url);
            exit;
        }

        $app_id = get_module_setting($this->getName(), 'app_id');
        $app_secret = get_module_setting($this->getName(), 'app_secret');
        $connect_url = $this->getConnectURL($url);

        if (!$app_id || !$app_secret) {
            $this->error_page(WT_I18N::translate('Facebook logins have not been setup by the administrator.'));
            return;
        }

        $code = @$_REQUEST["code"];

        if (!empty($_REQUEST['error'])) {
            AddToLog('Facebook Error: ' . WT_Filter::get('error') . '. Reason: ' . WT_Filter::get('error_reason'), 'error'); // TODO: filter better?
            if ($_REQUEST['error_reason'] == 'user_denied') {
                $this->error_page(WT_I18N::translate('You must allow access to your Facebook account in order to login with Facebook.'));
            } else {
                $this->error_page(WT_I18N::translate('An error occurred trying to log you in with Facebook.'));
            }
        } else if (empty($code) && empty($WT_SESSION->facebook_access_token)) {
            if (!WT_Filter::checkCsrf()) {
                echo WT_I18N::translate('This form has expired.  Try again.');
                return;
            }
            // FB Login flow has not begun so redirect to login dialog.
            $WT_SESSION->facebook_state = md5(uniqid(rand(), TRUE)); // CSRF protection
            $dialog_url = "https://www.facebook.com/dialog/oauth?client_id="
                . $app_id . "&redirect_uri=" . urlencode($connect_url) . "&state="
                . $WT_SESSION->facebook_state . "&scope=" . self::scope;
            Zend_Session::writeClose();
            echo("<script> top.location.href='" . $dialog_url . "'</script>");
        } else if (!empty($WT_SESSION->facebook_access_token)) {
            // User has already authorized the app and we have a token so get their info.
            $graph_url = "https://graph.facebook.com/me?access_token="
                . $WT_SESSION->facebook_access_token;
            $user = json_decode(file_get_contents($graph_url));
            $this->login_or_register($user, $url);
        } else if (!empty($WT_SESSION->facebook_state) && ($WT_SESSION->facebook_state === $_REQUEST['state'])) {
            // User has already been redirected to login dialog.
            // Exchange the code for an access token.
            $token_url = "https://graph.facebook.com/oauth/access_token?"
                . "client_id=" . $app_id . "&redirect_uri=" . urlencode($connect_url)
                . "&client_secret=" . $app_secret . "&code=" . $code;

            $response = @file_get_contents($token_url);
            if ($response === FALSE) {
                $this->error_page(WT_I18N::translate("Your Facebook code is invalid. This can happen if you hit back in your browser after login or if Facebook logins have been setup incorrectly by the administrator."));
            }
            $params = null;
            parse_str($response, $params);

            $WT_SESSION->facebook_access_token = $params['access_token'];

            $graph_url = "https://graph.facebook.com/me?access_token="
                . $WT_SESSION->facebook_access_token;
            $meResponse = @file_get_contents($graph_url);
            if ($meResponse === FALSE) {
                $this->error_page(WT_I18N::translate("Could not fetch your information from Facebook. Please try again."));
            }
            $user = json_decode($meResponse);
            $this->login_or_register($user, $url);
        } else {
            $this->error_page(WT_I18N::translate("The state does not match. You may been tricked to load this page."));
        }
    }

    private function hidden_input($name, $value) {
        return '<input type="hidden" name="'.$name.'" value="'.$value.'"/>';
    }

    private function user_options($users) {
        $output = '';
        foreach ($users as $user_id => $user) {
            $output .= '<option value="'.$user_id.'">' . $user->user_name . ' (' . $user->real_name . ')</option>';
        }
        return $output;
    }

    private function get_users_with_module_settings() {
	$sql=
		"SELECT u.user_id, user_name, real_name, email, facebook_username.setting_value as facebook_username".
		" FROM `##user` u".
		" LEFT JOIN `##user_setting` facebook_username ON (u.user_id = facebook_username.user_id AND facebook_username.setting_name='facebook_username')".
                " WHERE u.user_id > 0".
                " ORDER BY user_name ASC";
	return WT_DB::prepare($sql)->execute()->fetchAll(PDO::FETCH_OBJ | PDO::FETCH_GROUP);
    }

    private function get_user_id_from_facebook_username($facebookUsername) {
        $statement = WT_DB::prepare(
                                    "SELECT SQL_CACHE user_id FROM `##user_setting` WHERE setting_name=? AND setting_value=?"
                                   );
	return $statement->execute(array(self::user_setting_facebook_username, $this->cleanseFacebookUsername($facebookUsername)))->fetchOne();
    }

    // Guidelines from https://www.facebook.com/help/105399436216001
    private function cleanseFacebookUsername($username) {
        // Case and periods don't matter
        return strtolower(trim(str_replace('.', '', $username)));
    }

    private function getConnectURL($returnTo='') {
        return WT_SERVER_NAME . WT_SCRIPT_PATH . "module.php?mod=" . $this->getName()
            . "&mod_action=connect" . ($returnTo ? "&url=" . urlencode($returnTo) : ""); // Workaround FB bug where "&url=" (empty value) prevents OAuth
    }

    private function login($user_id) {
        global $WT_SESSION;
        $user_name = get_user_name($user_id);

        // Below copied from authenticateUser in authentication.php
        $is_admin=get_user_setting($user_id, 'canadmin');
        $verified=get_user_setting($user_id, 'verified');
        $approved=get_user_setting($user_id, 'verified_by_admin');
        if ($verified && $approved || $is_admin) {
            // Whenever we change our authorisation level change the session ID
            Zend_Session::regenerateId();
            $WT_SESSION->wt_user = $user_id;
            Zend_Session::writeClose();
            AddToLog('Login successful', 'auth');
            return $user_id;
        } elseif (!$is_admin && !$verified) {
            AddToLog('Login failed ->'.$user_name.'<- not verified', 'auth');
            return -1;
        } elseif (!$is_admin && !$approved) {
            AddToLog('Login failed ->'.$user_name.'<- not approved', 'auth');
            return -2;
        }
        throw new Exception('Login failure: Unexpected condition');
    }

    /**
     * If the Facebook username or email is associated with an account, login to it. Otherwise, register a new account.
     * TODO: handle transition from internal account to FB if using different email address.
     *
     * @param string $facebookUser Facebook username
     */
    private function login_or_register(&$facebookUser, $url='') {
	global $WT_SESSION;
        $REQUIRE_ADMIN_AUTH_REGISTRATION = WT_Site::preference('REQUIRE_ADMIN_AUTH_REGISTRATION');

        if (get_module_setting($this->getName(), 'require_verified', 1) && empty($facebookUser->verified)) {
            $this->error_page(WT_I18N::translate('Only verified Facebook accounts are authorized. Please verify your account on Facebook and then try again'));
        }

        if (empty($facebookUser->username)) {
            $facebookUser->username = $facebookUser->id;
        }
        $user_id = $this->get_user_id_from_facebook_username($facebookUser->username);
        if (!$user_id) {
            $user_id = get_user_by_email($facebookUser->email);
        }
        //var_dump($user_id);
        if ($user_id) { // This is an existing user so log them in if they are approved

            $login_result = $this->login($user_id);
            $message = '';
            switch ($login_result) {
		case -1: // not validated
		    $message=WT_I18N::translate('This account has not been verified.  Please check your email for a verification message.');
                    break;
		case -2: // not approved
                    $message=WT_I18N::translate('This account has not been approved.  Please wait for an administrator to approve it.');
                    break;
                default:
                    set_user_setting($user_id, self::user_setting_facebook_username, $this->cleanseFacebookUsername($facebookUser->username));
                    // redirect to the homepage/$url
                    header('Location: ' . WT_SCRIPT_PATH . $url);
                    return;
            }
            $this->error_page($message);
        } else { // This is a new Facebook user who may or may not already have a manual account

            if (!WT_Site::preference('USE_REGISTRATION_MODULE')) {
                $this->error_page('<p>' . WT_I18N::translate('The administrator has disabled registrations.') . '</p>');
            }

            // check if the username is already in use
            $username = $facebookUser->username;
            if (get_user_id($username)) {
                // fallback to email as username since we checked above that a user with the email didn't exist.
                $username = $facebookUser->email;
            }

            // Generate a random password since the user shouldn't need it and can always reset it.
            $password = md5(uniqid(rand(), TRUE));
            $hashcode = md5(uniqid(rand(), true));
            $preApproved = unserialize(get_module_setting($this->getName(), 'preapproved'));

            // From login.php:
            AddToLog('User registration requested for: ' . $username, 'auth');
            if ($user_id = create_user($username, $facebookUser->name, $facebookUser->email, $password)) {
                $verifiedByAdmin = !$REQUIRE_ADMIN_AUTH_REGISTRATION || isset($preApproved[$facebookUser->username]);
                set_user_setting($user_id, self::user_setting_facebook_username, $this->cleanseFacebookUsername($facebookUser->username));
                set_user_setting($user_id, 'language',          $WT_SESSION->locale);
                set_user_setting($user_id, 'verified',          1);
                set_user_setting($user_id, 'verified_by_admin', $verifiedByAdmin);
                set_user_setting($user_id, 'reg_timestamp',     date('U'));
                set_user_setting($user_id, 'reg_hashcode',      $hashcode);
                set_user_setting($user_id, 'contactmethod',     'messaging2');
                set_user_setting($user_id, 'visibleonline',     1);
                set_user_setting($user_id, 'editaccount',       1);
                set_user_setting($user_id, 'auto_accept',       0);
                set_user_setting($user_id, 'canadmin',          0);
                set_user_setting($user_id, 'sessiontime',       0);
                set_user_setting($user_id, 'comment',           @$facebookUser->birthday);

                // Apply pre-approval settings
                if (isset($preApproved[$facebookUser->username])) {
                    $userSettings = $preApproved[$facebookUser->username];
                    foreach ($userSettings as $gedcom => $userGedcomSettings) {
                        $tree = WT_Tree::get($gedcom);
                        if (!$tree) {
                            continue;
                        }
                        foreach (array('gedcomid', 'rootid', 'canedit') as $userPref) {
                            if (empty($userGedcomSettings[$userPref])) {
                                continue;
                            }
                            $tree->userPreference($user_id, $userPref, $userGedcomSettings[$userPref]);
                        }
                    }
                    // Remove the pre-approval record
                    unset($preApproved[$facebookUser->username]);
                    set_module_setting($this->getName(), 'preapproved', serialize($preApproved));
                }

                // We need jQuery below
                global $controller;
                $controller = new WT_Controller_Page();
                $controller
                    ->setPageTitle($this->getTitle())
                    ->pageHeader();

                echo '<form id="verify-form" name="verify-form" method="post" action="', WT_LOGIN_URL, '" class="ui-autocomplete-loading" style="width:16px;height:16px;padding:0">';
                echo $this->hidden_input("action", "verify_hash");
                echo $this->hidden_input("user_name", $username);
                echo $this->hidden_input("user_password", $password);
                echo $this->hidden_input("user_hashcode", $hashcode);
                echo WT_Filter::getCsrf();
                echo '</form>';

                if ($verifiedByAdmin) {
                    $controller->addInlineJavaScript('
function verify_hash_success() {
  // now the account is approved but not logged in. Now actually login for the user.
  // TODO: investigate if this could cause a loop.
  if (!parseInt(WT_USER_ID, 10)) {
    window.top.location = "' . $this->getConnectURL($url) . '";
  }
}

function verify_hash_failure() {
  alert("' . WT_I18N::translate("There was an error verifying your account. Contact the site administrator if you are unable to access the site.")  . '");
  window.top.location = "' . WT_SCRIPT_PATH . '";
}
$(document).ready(function() {
  console.log("before post");
  $.post("' . WT_LOGIN_URL . '", $("#verify-form").serialize(), verify_hash_success).fail(verify_hash_failure);
});
');
                } else {
                    echo '<script>document.getElementById("verify-form").submit()</script>';
                }

            } else {
                $this->error_page('<p>' . WT_I18N::translate('Unable to create your account.  Please try again.') . '</p>' .
                                  '<div class="back"><a href="javascript:history.back()">' . WT_I18N::translate('Back') . '</a></div>');
            }
        }
    }

    private function error_page($message) {
        global $controller, $WT_SESSION;

        unset($WT_SESSION->facebook_access_token);
        unset($WT_SESSION->facebook_state);
        Zend_Session::writeClose();

        $controller = new WT_Controller_Page();
        $controller
            ->setPageTitle($this->getTitle())
            ->pageHeader();

        try {
            WT_FlashMessages::addMessage($message);
        } catch (Zend_Session_Exception $zse) {
            echo '<div class="warning">'.$message.'</div>';
        }
        exit;
    }

    private function print_findindi_link($element_id, $indiname='', $gedcomTitle=WT_GEDURL) {
	return '<a href="#" onclick="findIndi(document.getElementById(\''.$element_id.'\'), document.getElementById(\''.$indiname.'\'), \''.$gedcomTitle.'\'); return false;" class="icon-button_indi" title="'.WT_I18N::translate('Find an individual').'"></a>';
    }

    private function indiField($field, $value='', $gedcomTitle=WT_GEDURL) {
        return '<input type="text" size="5" name="'.$field.'" id="'.$field.'" value="'.htmlspecialchars($value).'"> '
            . $this->print_findindi_link($field, '', $gedcomTitle);
    }

    /* Inject JS into some pages (via a menu) to show the Facebook login button */

    // Implement WT_Module_Menu
    public function defaultMenuOrder() {
        return 999;
    }

    // Implement WT_Module_Menu
    public function getMenu() {
        // We don't actually have a menu - this is just a convenient "hook" to execute
        // code at the right time during page execution
        global $controller;

        if (!$this->isSetup()) {
            return null;
        }

        $controller->addExternalJavascript(WT_MODULES_DIR . $this->getName() . '/facebook.js?v=' . WT_FACEBOOK_VERSION);
        /* Stylesheets added by addExternalStylesheet are never output
        if (method_exists($controller, 'addExternalStylesheet')) {
          $controller->addExternalStylesheet(WT_MODULES_DIR . $this->getName() . '/facebook.css?v=' . WT_FACEBOOK_VERSION); // Only in 1.3.3+
        } else {
         */
          $controller->addInlineJavaScript("
            $('head').append('<link rel=\"stylesheet\" href=\"".WT_MODULES_DIR . $this->getName() . "/facebook.css?v=" . WT_FACEBOOK_VERSION."\" />');" .
              ($this->hideStandardForms ? '$(document).ready(function() {$("#login-form[name=\'login-form\']").hide();})' : ""),
            WT_Controller_Page::JS_PRIORITY_LOW);
        //}

        return null;
    }

}
