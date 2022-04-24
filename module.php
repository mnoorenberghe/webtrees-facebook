<?php

/**
 * Facebook Module for webtrees
 */

declare(strict_types=1);

namespace WTFacebook;

// Unlike most other modules, this one has a separate file for the class definition.
// This is because the constructor has some dependencies, so we must create it
// with "Webtrees::make(FacebookModule::class)" rather than "new FacebookModule()".
// This means we can't use an anonymous class, and our coding standards
// mean that the class needs to go in its own file.
use Fisharebest\Webtrees\Webtrees;

require __DIR__ . '/FacebookModule.php';

return Webtrees::make(FacebookModule::class);
