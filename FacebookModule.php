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

namespace WTFacebook;

define('WT_FACEBOOK_VERSION', "v2.1-alpha.0");
define('WT_FACEBOOK_UPDATE_CHECK_URL', "https://api.github.com/repos/mnoorenberghe/webtrees-facebook/contents/versions.json?ref=gh-pages");
define('WT_REGEX_ALPHA', '[a-zA-Z]+');
define('WT_REGEX_ALPHANUM', '[a-zA-Z0-9]+');
define('WT_REGEX_USERNAME', '[^<>"%{};]+');

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Controller\PageController;

use Fisharebest\Webtrees\Filter;
use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Log;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Site;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\User;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\HomePage;
use Fisharebest\Webtrees\Http\RequestHandlers\UserPage;
use Fisharebest\Webtrees\Http\ViewResponseTrait;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleGlobalInterface;
use Fisharebest\Webtrees\Module\ModuleGlobalTrait;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Services\UserService;
use GuzzleHttp\Client;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\JoinClause;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class FacebookModule extends AbstractModule implements ModuleCustomInterface, ModuleConfigInterface, ModuleGlobalInterface, RequestHandlerInterface
{
    const scope = 'user_birthday,user_hometown,user_location,email,user_gender,user_link';
    const user_fields = 'id,birthday,email,name,first_name,last_name,gender,hometown,link,locale,timezone,updated_time,verified';
    const user_setting_facebook_username = 'facebook_username';
    const profile_photo_large_width = 1024;
    const api_dir = "v13.0/"; // TODO: make an admin preference so new installs can use this module.

    private $hideStandardForms = false;
    private TreeService $tree_service;
    private UserService $user_service;

    /**
     * FacebookModule constructor.
     *
     * @param TreeService   $tree_service
     * @param UserService   $user_service
     */
    public function __construct(
        TreeService $tree_service,
        UserService $user_service
    ) {
        $this->tree_service = $tree_service;
        $this->user_service = $user_service;
    }

    // For every module interface that is implemented, the corresponding trait *should* also use be used.
    use ModuleConfigTrait;
    use ModuleCustomTrait;
    use ModuleGlobalTrait;
    use ViewResponseTrait;

    /**
     * Initialization.
     *
     * @return void
     */
    public function boot(): void
    {
        Registry::routeFactory()->routeMap()->get('facebook-login', '/facebook/login{/tree}', $this)->allows(RequestMethodInterface::METHOD_POST);
    }

    // Implement ModuleInterface
    public function title(): string {
        return /* I18N: Name of the module */ I18N::translate('Facebook');
    }

    // Implement ModuleInterface
    public function description(): string {
        return /* I18N: Description of the "Facebook" module */ I18N::translate('Allow users to login with Facebook.');
    }

    public function resourcesFolder(): string {
        return __DIR__ . '/resources/';
    }

    /**
     * The person or organisation who created this module.
     *
     * @return string
     */
    public function customModuleAuthorName(): string
    {
        return 'Matt N.';
    }

    /**
     * The version of this module.
     *
     * @return string
     */
    public function customModuleVersion(): string
    {
        return WT_FACEBOOK_VERSION;
    }

    /**
     * A URL that will provide the latest version of this module.
     *
     * @return string
     */
    /* TODO
    public function customModuleLatestVersionUrl(): string
    {
        return 'https://github.com/mnoorenberghe/webtrees-custom-xref-prefix/raw/main/latest-version.txt';
    }
    */

    /**
     * Where to get support for this module.  Perhaps a github repository?
     *
     * @return string
     */
    public function customModuleSupportUrl(): string
    {
        return 'https://github.com/mnoorenberghe/webtrees-facebook/issues/';
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->connect($request);
    }

    private function roles()
    {
        return array( // from admin_users.php
                     'none'  => /* I18N: Listbox entry; name of a role */ I18N::translate('Visitor'),
                     'access'=> /* I18N: Listbox entry; name of a role */ I18N::translate('Member'),
                     'edit'  => /* I18N: Listbox entry; name of a role */ I18N::translate('Editor'),
                     'accept'=> /* I18N: Listbox entry; name of a role */ I18N::translate('Moderator'),
                     'admin' => /* I18N: Listbox entry; name of a role */ I18N::translate('Manager')
        );
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->saveAdmin($request);

        // Don't redirect otherwise FlashMessages don't work.
        return $this->getAdminAction($request);
    }

    protected function saveAdmin(ServerRequestInterface $request)
    {
        $preApproved = unserialize($this->getPreference('preapproved'));

        $parsedBody = Validator::parsedBody($request);
        if ($parsedBody->string('saveAPI')) {
            $this->setPreference('app_id', $parsedBody->string('app_id'));
            $this->setPreference('app_secret', $parsedBody->string('app_secret'));
            $this->setPreference('require_verified', $parsedBody->integer('require_verified', false));
            $this->setPreference('hide_standard_forms', $parsedBody->integer('hide_standard_forms', false));
            Log::addConfigurationLog("Facebook: API settings changed");
            FlashMessages::addMessage(I18N::translate('Settings saved'));
        } else if ($parsedBody->string('addLink')) {
            $user_id = $parsedBody->integer('user_id');
            $facebook_username = $this->cleanseFacebookUserID($parsedBody->string('facebook_username'));
            if ($user_id && $facebook_username && !$this->get_wt_user_id_from_facebook_user_id($facebook_username)) {
                $user = $this->user_service->find($user_id);
                $user->setPreference(self::user_setting_facebook_username, $facebook_username);
                if (isset($preApproved[$facebook_username])) {
                    // Delete a pre-approval for the Facebook username.
                    unset($preApproved[$facebook_username]);
                    $this->setPreference('preapproved', serialize($preApproved));
                }
                Log::addConfigurationLog("Facebook: User $user_id linked to Facebook user $facebook_username");
                FlashMessages::addMessage(I18N::translate('User %1$s linked to Facebook user %2$s', $user_id, $facebook_username));
            } else {
                FlashMessages::addMessage(I18N::translate('The user could not be linked'));
            }
        } else if ($parsedBody->string('deleteLink')) {
            $user_id = $parsedBody->integer('deleteLink');
            if ($user_id) {
                $user = $this->user_service->find($user_id);
                $user->deletePreference(self::user_setting_facebook_username);
                Log::addConfigurationLog("Facebook: User $user_id unlinked from a Facebook user");
                FlashMessages::addMessage(I18N::translate('User unlinked'));
            } else {
                FlashMessages::addMessage(I18N::translate('The link could not be deleted'));
            }
        } else if ($parsedBody->string('savePreapproved')) {
            $table = $parsedBody->array('preApproved');
            if ($facebook_username = $this->cleanseFacebookUserID($parsedBody->string('preApproved_new_facebook_username'))) {
                // Process additions
                $row = $table['new'];
                $this->appendPreapproved($preApproved, $facebook_username, $row);
                $this->setPreference('preapproved', serialize($preApproved));
                Log::addConfigurationLog("Facebook: Pre-approved Facebook user: $facebook_username");
                FlashMessages::addMessage(I18N::translate('Pre-approved user "%s" added', $facebook_username));
            }
            unset($table['new']);
            // Process changes
            foreach($table as $facebook_username => $row) {
                $this->appendPreapproved($preApproved, $facebook_username, $row);
            }
            $this->setPreference('preapproved', serialize($preApproved));
            Log::addConfigurationLog("Facebook: Pre-approved Facebook users changed");
            FlashMessages::addMessage(I18N::translate('Changes to pre-approved users saved'));
        } else if ($parsedBody->string('deletePreapproved')) {
            $facebook_username = trim($parsedBody->string('deletePreapproved'));
            if ($facebook_username && isset($preApproved[$facebook_username])) {
                unset($preApproved[$facebook_username]);
                $this->setPreference('preapproved', serialize($preApproved));
                Log::addConfigurationLog("Facebook: Pre-approved Facebook user deleted: $facebook_username");
                FlashMessages::addMessage(I18N::translate('Pre-approved user "%s" deleted', $facebook_username));
            } else {
                FlashMessages::addMessage(I18N::translate('The pre-approved user "%s" could not be deleted', $facebook_username));
            }
        }

        return $this->getAdminAction($request);
    }

    public function getAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $linkedUsers = array();
        $unlinkedUsers = array();
        $users = $this->get_users_with_module_settings();
        foreach ($users as $user) {
            if (empty($user->facebook_username)) {
                $unlinkedUsers[$user->user_id] = $user;
            } else {
                $linkedUsers[$user->user_id] = $user;
            }
        }

        $unlinkedOptions = $this->user_options($unlinkedUsers);

        $this->layout = 'layouts/administration';

        return $this->viewResponse('../../modules_v4/facebook/templates/admin', [
            'title'               => $this->title(),
            'app_id'              => $this->getPreference('app_id', ''),
            'app_secret'          => $this->getPreference('app_secret', ''),
            'fb_api_dir'          => self::api_dir,
            'require_verified'    => $this->getPreference('require_verified', 1),
            'hide_standard_forms' => $this->getPreference('hide_standard_forms', 0),
            'linkedUsers'         => $linkedUsers,
            'unlinkedOptions'     => $unlinkedOptions,
            'unlinkedUsers'       => $unlinkedUsers,
            'all_trees'           => $this->tree_service->all(),
            'roles'               => $this->roles(),
        ]);
    }

    private function appendPreapproved(&$preApproved, $facebook_username, $row) {
        $facebook_username = $this->cleanseFacebookUserID($facebook_username);
        if (!$facebook_username) {
            FlashMessages::addMessage(I18N::translate('Missing Facebook username'));
            return;
        }
        if ($this->get_wt_user_id_from_facebook_user_id($facebook_username)) {
            FlashMessages::addMessage(I18N::translate('User is already registered'));
            return;
        }

        $preApproved[$facebook_username] = array();
        foreach ($row as $gedcom => $settings) {
            $preApproved[$facebook_username][$gedcom] = array(
                'rootid' => array_key_exists('rootid', $settings)
                ? filter_var($settings['rootid'], FILTER_VALIDATE_REGEXP, array("options" => array("regexp" => '/^(' .  Gedcom::REGEX_XREF . ')$/u')))
                    : NULL,
                'gedcomid' => array_key_exists('gedcomid', $settings)
                ? filter_var(@$settings['gedcomid'], FILTER_VALIDATE_REGEXP, array("options" => array("regexp" => '/^(' . Gedcom::REGEX_XREF . ')$/u')))
                    : NULL,
                'canedit' => filter_var($settings['canedit'], FILTER_VALIDATE_REGEXP, array("options" => array("regexp" => '/^(' . WT_REGEX_ALPHA . ')$/u')))
            );
        }
    }

    private function isSetup() {
        $app_id = $this->getPreference('app_id');
        $app_secret = $this->getPreference('app_secret');
        $this->hideStandardForms = $this->getPreference('hide_standard_forms', false);

        return !empty($app_id) && !empty($app_secret);
    }

    private function connect(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->treeOptional();
        $user = Validator::attributes($request)->user();
        $url  = Validator::parsedBody($request)->isLocalUrl()->string('url', Validator::queryParams($request)->isLocalUrl()->string('url', route(HomePage::class)));
        // If we’ve clicked login from the login page, we don’t want to go back there.
        if (strpos($url, 'login') !== false || strpos($url, 'facebook') !== false) {
            $url = '';
        }

        // Redirect to the homepage/$url if the user is already logged-in.
        if ($user instanceof User) {
            return redirect($url ?: route(UserPage::class, ['tree' => $tree instanceof Tree ? $tree->name() : '']));
        }

        // No tree?  perhaps we came here from a page without one.
        if ($tree === null) {
            $default = Site::getPreference('DEFAULT_GEDCOM');
            $tree    = $this->tree_service->all()->get($default) ?? $this->tree_service->all()->first();

            if ($tree instanceof Tree) {
                return redirect(route('facebook-login', ['tree' => $tree->name(), 'url' => $url]));
            }
        }

        $app_id = $this->getPreference('app_id');
        $app_secret = $this->getPreference('app_secret');
        $connect_url = $this->getConnectURL($tree, $url);

        if (!$app_id || !$app_secret) {
            return $this->error_page(I18N::translate('Facebook logins have not been setup by the administrator.'));
        }

        if (isset($_REQUEST['code']))
            $code = $_REQUEST["code"];

        if (!empty($_REQUEST['error'])) {
            Log::addErrorLog('Facebook Error: ' . Validator::queryParams($request)->string('error') . '. Reason: ' . Validator::queryParams($request)->string('error_reason'));
            if ($_REQUEST['error_reason'] == 'user_denied') {
                return $this->error_page(I18N::translate('You must allow access to your Facebook account in order to login with Facebook.'));
            } else {
                return $this->error_page(I18N::translate('An error occurred trying to log you in with Facebook.'));
            }
        } else if (empty($code) && !Session::has('facebook_access_token')) {
            // Duplicate upstream CSRF check since this can be a GET? Try to get this exposed upstream.
            $params        = (array) $request->getParsedBody();
            $client_token  = $params['_csrf'] ?? $request->getHeaderLine('X-CSRF-TOKEN');
            $session_token = Session::get('CSRF_TOKEN');

            $request = $request->withParsedBody($params);

            if ($client_token !== $session_token) {
                // TODO: return $this->error_page(I18N::translate('This form has expired.  Try again.'));
            }

            Session::put('timediff', Validator::parsedBody($request)->integer('timediff', 0)); // Same range as date('Z')
            // FB Login flow has not begun so redirect to login dialog.
            Session::put('facebook_state', md5(uniqid(rand(), TRUE))); // CSRF protection
            $dialog_url = "https://www.facebook.com/dialog/oauth?client_id="
                . $app_id . "&redirect_uri=" . urlencode($connect_url) . "&state="
                . Session::get('facebook_state') . "&scope=" . self::scope;
            return redirect($dialog_url);
        } else if (Session::has('facebook_access_token')) {
            // User has already authorized the app and we have a token so get their info.
            $graph_url = "https://graph.facebook.com/" . self::api_dir . "me?fields=" . self::user_fields . "&access_token="
                . Session::get('facebook_access_token');

            $client = new Client();
            $response = $client->get($graph_url);

            if ($response->getStatusCode() !== StatusCodeInterface::STATUS_OK) {
                Log::addErrorLog("Facebook: Access token is no longer valid");
                // Clear the state and try again with a new token.
                try {
                    Session::forget('facebook_access_token');
                    Session::forget('facebook_state');
                } catch (\Exception $e) {
                }

                return redirect($this->getConnectURL($tree, $url));
            }

            $user = json_decode($response->getBody()->getContents());
            return $this->login_or_register($tree, $user, $url);
        } else if (Session::has('facebook_state') && (Session::get('facebook_state') === $_REQUEST['state'])) {
            // User has already been redirected to login dialog.
            // Exchange the code for an access token.
            $token_url = "https://graph.facebook.com/" . self::api_dir . "oauth/access_token?"
                . "client_id=" . $app_id . "&redirect_uri=" . urlencode($connect_url)
                . "&client_secret=" . $app_secret . "&code=" . $code;

            $client = new Client();
            $response = $client->get($token_url);

            if ($response->getStatusCode() !== StatusCodeInterface::STATUS_OK) {
                Log::addErrorLog("Facebook: Couldn't exchange the code for an access token");
                return $this->error_page(I18N::translate("Your Facebook code is invalid. This can happen if you hit back in your browser after login or if Facebook logins have been setup incorrectly by the administrator."));
            }
            $params = json_decode($response->getBody()->getContents());
            if (!isset($params->access_token)) {
                Log::addErrorLog("Facebook: The access token was empty");
                return $this->error_page(I18N::translate("Your Facebook code is invalid. This can happen if you hit back in your browser after login or if Facebook logins have been setup incorrectly by the administrator."));
            }

            Session::put('facebook_access_token', $params->access_token);
            $graph_url = "https://graph.facebook.com/" . self::api_dir . "me?fields=" . self::user_fields . "&access_token="
                . Session::get('facebook_access_token');
            $client = new Client();
            $meResponse = $client->get($graph_url);
            if ($meResponse->getStatusCode() !== StatusCodeInterface::STATUS_OK) {
                return $this->error_page(I18N::translate("Could not fetch your information from Facebook. Please try again."));
            }
            $user = json_decode($meResponse->getBody()->getContents());
            return $this->login_or_register($tree, $user, $url);
        } else {
            return $this->error_page(I18N::translate("The state does not match. You may been tricked to load this page."));
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
        return DB::table('user')
            ->leftJoin('user_setting', static function (JoinClause $join): void {
                $join
                    ->on('user.user_id', '=', 'user_setting.user_id')
                    ->where('user_setting.setting_name', '=', 'facebook_username');
            })
            ->where('user.user_id', '>', 0)
            ->select(['user.user_id', 'user_name', 'real_name', 'email', 'user_setting.setting_value as facebook_username'])
            ->orderBy('user_name')
            ->get();
    }

    private function get_wt_user_id_from_facebook_user_id($fbUserId) {
        return DB::table('user_setting')
            ->select('user_id')
            ->where('setting_name', '=', self::user_setting_facebook_username)
            ->where('setting_value', '=', $this->cleanseFacebookUserID($fbUserId))
            ->value('user_id');
    }

    private function cleanseFacebookUserID($user_id) {
        // This is just a numeric string
        return $user_id;
    }

    private function getConnectURL(Tree $tree, $returnTo = '')
    {
        return route('facebook-login', [
            'tree' => $tree instanceof Tree ? $tree->name() : '',
            'url' => $returnTo // TODO: Bring back workaround for FB bug where "&url=" (empty value) prevents OAuth
        ]);
    }

    private function login($user_id) {
        $user = $this->user_service->find($user_id);
        $user_name = $user->userName();

        // Below copied from login.php
        $is_admin=$user->getPreference('canadmin');
        $verified=$user->getPreference('verified');
        $approved=$user->getPreference('verified_by_admin');
        if ($verified && $approved || $is_admin) {
            Auth::login($user);
            Log::addAuthenticationLog('Login: ' . Auth::user()->userName() . '/' . Auth::user()->realName());

            Session::put('locale', Auth::user()->getPreference('language'));
            Session::put('theme_id', Auth::user()->getPreference('theme'));
            Auth::user()->setPreference(UserInterface::PREF_TIMESTAMP_ACTIVE, (string) time());
            I18N::init(Auth::user()->getPreference('language'));

            return $user_id;
        } elseif (!$is_admin && !$verified) {
            Log::addAuthenticationLog('Login failed ->'.$user_name.'<- not verified');
            return -1;
        } elseif (!$is_admin && !$approved) {
            Log::addAuthenticationLog('Login failed ->'.$user_name.'<- not approved');
            return -2;
        }
        throw new \Exception('Login failure: Unexpected condition');
    }

    public function createUser($wt_username, $name, $email, $password, $hashcode, $verifiedByAdmin, $fb_user_id) {
        // From login.php:
        Log::addAuthenticationLog('User registration requested for: ' . $wt_username);
        $user = $this->user_service->create($wt_username, $name, $email, $password);
        if (!$user) {
            return $user;
        }

        $user->setPreference(self::user_setting_facebook_username, $this->cleanseFacebookUserID($fb_user_id));
        $user->setPreference('language',          WT_LOCALE);
        $user->setPreference('verified',          '1');
        $user->setPreference('verified_by_admin', $verifiedByAdmin ? '1' : '0');
        $user->setPreference('reg_timestamp',     date('U'));
        $user->setPreference('reg_hashcode',      $hashcode);
        $user->setPreference('contactmethod',     'messaging2');
        $user->setPreference('visibleonline',     '1');
        $user->setPreference('auto_accept',       '0');
        $user->setPreference('canadmin',          '0');
        $user->setPreference(UserInterface::PREF_TIMESTAMP_ACTIVE, $verifiedByAdmin ? (string) time() : '0');

        return $user;
    }

    /**
     * If the Facebook username or email is associated with an account, login to it. Otherwise, register a new account.
     *
     * @param object $facebookUser Facebook user
     * @param string $url          (optional) URL to redirect to afterwards.
     */
    private function login_or_register(Tree $tree, &$facebookUser, $url = ''): ResponseInterface
    {
        if ($this->getPreference('require_verified', 1) && empty($facebookUser->verified)) {
            return $this->error_page(I18N::translate('Only verified Facebook accounts are authorized. Please verify your account on Facebook and then try again'));
        }

        $user_id = $this->get_wt_user_id_from_facebook_user_id($facebookUser->id);
        if (!$user_id) {
            if (!isset($facebookUser->email)) {
                return $this->error_page(I18N::translate('You must grant access to your email address via Facebook in order to use this website. Please uninstall the application on Facebook and try again.'));
            }
            $user = $this->user_service->findByIdentifier($facebookUser->email);
            if ($user) {
                $user_id = $user->id();
            }
        }

        if ($user_id) { // This is an existing user so log them in if they are approved
            $login_result = $this->login($user_id);
            $message = '';
            switch ($login_result) {
                case -1: // not validated
                    $message=I18N::translate('This account has not been verified.  Please check your email for a verification message.');
                    break;
                case -2: // not approved
                    $message=I18N::translate('This account has not been approved.  Please wait for an administrator to approve it.');
                    break;
                default:
                    $user = $this->user_service->find($user_id);
                    $user->setPreference(self::user_setting_facebook_username, $this->cleanseFacebookUserID($facebookUser->id));
                    // redirect to the homepage/$url
                    return redirect($url ?: route(UserPage::class, ['tree' => $tree instanceof Tree ? $tree->name() : '']));
            }
            return $this->error_page($message);
        } else { // This is a new Facebook user who may or may not already have a manual account

            if (!Site::getPreference('USE_REGISTRATION_MODULE')) {
                return $this->error_page('<p>' . I18N::translate('The administrator has disabled registrations.') . '</p>');
            }

            // check if the username is already in use
            $username = $this->cleanseFacebookUserID($facebookUser->id);
            $wt_username = substr($username, 0, 32); // Truncate the username to 32 characters to match the DB.

            if ($this->user_service->findByIdentifier($wt_username)) {
                // fallback to email as username since we checked above that a user with the email didn't exist.
                $wt_username = $facebookUser->email;
                $wt_username = substr($wt_username, 0, 32); // Truncate the username to 32 characters to match the DB.
            }

            // Generate a random password since the user shouldn't need it and can always reset it.
            $password = md5(uniqid(rand(), TRUE));
            $hashcode = md5(uniqid(rand(), true));
            $preApproved = unserialize($this->getPreference('preapproved'));
            $verifiedByAdmin = isset($preApproved[$username]);

            if ($user = $this->createUser($wt_username,
                                          $facebookUser->name,
                                          $facebookUser->email,
                                          $password,
                                          $hashcode,
                                          $verifiedByAdmin,
                                          $facebookUser->id)) {

                $user
                    ->setPreference('comment',
                                    @$facebookUser->birthday . "\n " .
                                    "https://www.facebook.com/" . $this->cleanseFacebookUserID($facebookUser->id));

                // Apply pre-approval settings
                if (isset($preApproved[$username])) {
                    $userSettings = $preApproved[$username];

                    foreach ($userSettings as $gedcom => $userGedcomSettings) {
                        foreach (array('gedcomid', 'rootid', 'canedit') as $userPref) {
                            if (empty($userGedcomSettings[$userPref])) {
                                continue;
                            }

                            // Use a direct DB query instead of $tree->setUserPreference since we
                            // can't get a reference to the WT_Tree since it checks permissions but
                            // we are trying to give the permissions.
                            // TODO: The above may no longer be true and it also invalidates a cache.
                            DB::table('user_gedcom_setting')
                            ->upsert(
                                [
                                    'user_id' => $user->id(),
                                    'gedcom_id' => $gedcom,
                                    'setting_name' => $userPref,
                                    'setting_value' => $userGedcomSettings[$userPref]
                                ],
                                ['user_id', 'gedcom_id', 'setting_name'],
                                ['setting_value']
                            );
                        }
                    }
                    // Remove the pre-approval record
                    unset($preApproved[$username]);
                    $this->setPreference('preapproved', serialize($preApproved));
                }

                // We need jQuery below
                global $controller;
                $controller = new PageController();
                $controller
                    ->setPageTitle($this->title())
                    ->pageHeader();

                echo '<form id="verify-form" name="verify-form" method="post" action="', WT_LOGIN_URL, '" class="ui-autocomplete-loading" style="width:16px;height:16px;padding:0">';
                echo $this->hidden_input("action", "verify_hash");
                echo $this->hidden_input("user_name", $wt_username);
                echo $this->hidden_input("user_password", $password);
                echo $this->hidden_input("user_hashcode", $hashcode);
                echo Filter::getCsrf();
                echo '</form>';

                if ($verifiedByAdmin) {
                    $controller->addInlineJavaScript('
function verify_hash_success() {
  // now the account is approved but not logged in. Now actually login for the user.
  window.location = "' . $this->getConnectURL($tree, $url) . '";
}

function verify_hash_failure() {
  alert("' . I18N::translate("There was an error verifying your account. Contact the site administrator if you are unable to access the site.")  .'");
  window.location = "' . Validator::attributes($request)->string('base_url') . '";
}
$(document).ready(function() {
  $.post("' . WT_LOGIN_URL . '", $("#verify-form").serialize(), verify_hash_success).fail(verify_hash_failure);
});
');
                } else {
                    echo '<script>document.getElementById("verify-form").submit()</script>';
                }

            } else {
                Log::addErrorLog("Facebook: Couldn't create the user account");
                return $this->error_page(
                    '<p>' . I18N::translate('Unable to create your account.  Please try again.') . '</p>' .
                    '<div class="back"><a href="javascript:history.back()">' . I18N::translate('Back') . '</a></div>'
                );
            }
        }
    }

    private function error_page($message): ResponseInterface
    {
        try {
            Session::forget('facebook_access_token');
            Session::forget('facebook_state');
        } catch (\Exception $e) {
        }

        FlashMessages::addMessage($message);

        return $this->viewResponse('layouts/administration', [
            'content' => '',
            'title' => '',
            'tree' => NULL
        ]);
    }

    /* Inject JS into some pages to show the Facebook login button */

    // Implement ModuleGlobalInterface
    public function bodyContent(): string {
        global $THUMBNAIL_WIDTH;

        $result = '';
        if (!$this->isSetup()) {
            return $result;
        }

        $result .= "<script>
        var FACEBOOK_LOGIN_TEXT = '" . addslashes(I18N::translate('Login with Facebook')) . "';
        $('head').append('<link rel=\"stylesheet\" href=\"". $this->assetUrl("facebook.css") . "\" />');" .
        ($this->hideStandardForms ? '$(function() {$("#login-form[name=\'login-form\'], #register-form").hide();});' : "");
        $result .= "</script>";

        $result .= "<script src=\"".$this->assetUrl('facebook.js')."\"></script>";

        $result .= "<style>#facebook-login-button { background-image: url(" . $this->assetUrl('images/f_logo.png') . "); }</style>";

          // Use the Facebook profile photo if there isn't an existing photo
          /* TODO:
          if (!empty($controller->record) && method_exists($controller->record, 'findHighlightedMedia')
              && !$controller->record->findHighlightedMedia()) {
              $fbUsername = $this->getFacebookUsernameForINDI($controller->record);
              if ($fbUsername) {
                  $fbPicture = 'https://graph.facebook.com/' . self::api_dir . $fbUsername . '/picture';
                  $controller->addInlineJavaScript('$(document).ready(function() {' .
                      '$("#indi_mainimage").html("<a class=\"gallery\" href=\"'.$fbPicture.'?width=' .
                      self::profile_photo_large_width.'\" data-obje-url=\"'.$fbPicture.'?type=large\">' .
                      '<img width=\"'.$THUMBNAIL_WIDTH.'\" src=\"'.$fbPicture.'?width='.$THUMBNAIL_WIDTH.'\"/>' .
                      '</a>");' .
                 '});');
              }
          }
          */


        return $result;
    }

    /* TODO
    public function getFacebookUsernameForINDI($indi) {
        global $WT_TREE;

        // If they have an account, look for the link on their user record.
        if ($user = $this->user_service->findByGenealogyRecord($indi)) {
            return $user->getPreference(self::user_setting_facebook_username);
        }

        // Otherwise, look in the list of pre-approved users.
        $preApproved = unserialize($this->getPreference('preapproved'));
        if (empty($preApproved)) {
            return NULL;
        }

        foreach ($preApproved as $fbUsername => $details) {
            if ($indi->getXref() == @$details[$WT_TREE->getTreeId()]['gedcomid']) {
                return $fbUsername;
            }
        }
        return NULL;
    }
    */
}