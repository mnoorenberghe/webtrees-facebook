<?php

namespace My;

use Fisharebest\Webtrees\File;

class FBStewardTestCase extends StewardTestCase {

    public static $installedUser;
    public static $uninstalledUser;

    public static function setUpBeforeClass() {
        parent::setUpBeforeClass();

        $app_id = parent::$module->getSetting('app_id');
        $app_secret = parent::$module->getSetting('app_secret');
        $access_token = $app_id . "|" . $app_secret;

        $fb = new \Facebook\Facebook([
                                      'app_id' => $app_id,
                                      'app_secret' => $app_secret,
                                      'default_graph_version' => str_replace("/", "", parent::$module::api_dir),
                                      'default_access_token' => $access_token
                                      ]);



        $response = $fb->post('/' . $app_id . '/accounts/test-users',
                              array(
                                    'installed' => 'true',
                                    'permissions' => parent::$module::scope,
                                    )
                              );
        $testUser = $response->getGraphNode();
        self::$installedUser = $testUser;
        var_dump(self::$installedUser);

        $response = $fb->post('/' . $app_id . '/accounts/test-users');
        $testUser = $response->getGraphNode();
        self::$uninstalledUser = $testUser;
        var_dump(self::$uninstalledUser);

        echo "installedUser: " . self::$installedUser['id'] . "\n" .
            "uninstalledUser: " . self::$uninstalledUser['id'] . "\n";

        // Create the WT user for the installedUser and link to the FB id
        parent::$module->createUser(self::$installedUser['id'],
                                    self::$installedUser['email'],
                                    self::$installedUser['email'],
                                    md5(uniqid(rand(), TRUE)),
                                    md5(uniqid(rand(), TRUE)),
                                    true,
                                    self::$installedUser['id']);
    }
}
