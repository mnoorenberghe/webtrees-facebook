<?php
namespace My; // Note the "My" namespace maps to the "tests" folder, as defined in the autoload part of `composer.json`.

use My\StewardTestCase;

use Fisharebest\Webtrees\Module;

class HomePageTest extends StewardTestCase {
    public function testShouldHaveLoginButtonAndStandardForm() {
        Module::getModuleByName('facebook')->setSetting('hide_standard_forms', '0');

        // Load the URL (will wait until page is loaded)
        $this->wd->get(BASE_URL); // $this->wd holds instance of \RemoteWebDriver

        $this->assertContains('webtrees', $this->wd->getTitle());

        // You can use $this->log(), $this->warn() or $this->debug() with sprintf-like syntax
        $this->debug('Current page "%s" has title "%s"', $this->wd->getCurrentURL(), $this->wd->getTitle());

        $loginButton = $this->waitForId('facebook-login-button', true);
        $this->assertContains('Login with Facebook', $loginButton->getText());

        // Standard login form block
        $this->waitForId('username', true);
        $this->waitForId('password', true);
    }

    public function testShouldHaveLoginButtonWithoutStandardForm() {
        Module::getModuleByName('facebook')->setSetting('hide_standard_forms', '1');

        // Load the URL (will wait until page is loaded)
        $this->wd->get(BASE_URL); // $this->wd holds instance of \RemoteWebDriver

        $this->assertContains('webtrees', $this->wd->getTitle());

        $loginButton = $this->waitForId('facebook-login-button', true);
        $this->assertContains('Login with Facebook', $loginButton->getText());

        // Standard login form block
        $this->assertFalse($this->waitForId('username', false)->isDisplayed());
        $this->assertFalse($this->waitForId('password', false)->isDisplayed());
    }

    public function testShouldHaveLoginButtonOnLoginPage() {
        $this->wd->get(BASE_URL . '/login.php');
        $loginButton = $this->waitForId('facebook-login-button');
        $this->assertContains('Login with Facebook', $loginButton->getText());
    }
}
