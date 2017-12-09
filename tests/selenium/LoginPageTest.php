<?php
namespace My;

use My\StewardTestCase;

use Fisharebest\Webtrees\Module;

class LoginPageTest extends StewardTestCase {
    public function testShouldHaveLoginButtonAndStandardForm() {
        Module::getModuleByName('facebook')->setSetting('hide_standard_forms', '0');
        $this->wd->get(BASE_URL . '/login.php');
        $loginButton = $this->waitForId('facebook-login-button');
        $this->assertContains('Login with Facebook', $loginButton->getText());

        // Standard login form fields
        $this->waitForId('username', true);
        $this->waitForId('password', true);
    }

    public function testShouldHaveLoginButtonWithoutStandardForm() {
        Module::getModuleByName('facebook')->setSetting('hide_standard_forms', '0');
        $this->wd->get(BASE_URL . '/login.php');
        $loginButton = $this->waitForId('facebook-login-button');
        $this->assertContains('Login with Facebook', $loginButton->getText());

        // Standard login form fields
        $this->assertFalse($this->waitForId('username', false)->isDisplayed());
        $this->assertFalse($this->waitForId('password', false)->isDisplayed());
    }
}
