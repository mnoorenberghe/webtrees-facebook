<?php
namespace My\facebook;

use Facebook\WebDriver\WebDriverExpectedCondition;

use My\FBStewardTestCase;

class LoginTest extends FBStewardTestCase {

    protected function loginToFacebook($email, $password) {
        $this->wd->get('https://www.facebook.com/login.php');

        $userField = $this->waitForId('email');
        $userField->sendKeys($email);

        $pwField = $this->waitForId('pass');
        $pwField->sendKeys($password);

        $loginButton = $this->waitForId('loginbutton');
        $loginButton->click();

        $this->wd->wait()->until(
                                 WebDriverExpectedCondition::not(
                                                                 WebDriverExpectedCondition::urlContains('login')
                                                                 )
                                 );
    }

    protected function loginToWebtreesWithFB() {
        $this->wd->get(BASE_URL . '/login.php');
        $loginButton = $this->waitForId('facebook-login-button', true);

        $this->ensureLoggedOut();

        $loginButton->click();
    }

    protected function ensureLoggedOut() {
        $this->assertEquals(count($this->findMultipleByCss('.menu-logout')), 0);
    }

    protected function ensureLoggedIn() {
        $this->waitForCss('.menu-logout', true);

        $this->assertTrue(strpos($this->wd->getCurrentURL(), '/index.php') !== false);
    }

    // Begin tests

    public function testLoginFullyInstalledUser() {
        $this->loginToFacebook(self::$installedUser['email'],
                               self::$installedUser['password']);

        $this->loginToWebtreesWithFB();
        $this->ensureLoggedIn();
    }

    public function testLoginNewUnknownUser() {
        $this->loginToFacebook(self::$uninstalledUser['email'],
                               self::$uninstalledUser['password']);

        $this->loginToWebtreesWithFB();

        $confirmButton = $this->waitForCss('button[name="__CONFIRM__"]', true);
        $confirmButton->click();
        $adminVerificationText = $this->waitForCss('#user-verify', true);
        $this->assertContains('confirmed', $adminVerificationText->getText());
        $this->assertContains('administrator', $adminVerificationText->getText());

        $this->ensureLoggedOut();
    }
}
