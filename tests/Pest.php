<?php

declare(strict_types=1);

use BBSLab\LaravelOkta\Tests\BaseOnlyTestCase;
use BBSLab\LaravelOkta\Tests\BrowserTestCase;
use BBSLab\LaravelOkta\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

// The base package installed on its own — no adapter provider (see BaseOnlyTestCase).
uses(BaseOnlyTestCase::class)->in('BaseOnly');

// Browser tests (Pest v4 + Playwright) need a browser, so they are grouped and
// excluded from the default suite — run them with `composer test:browser`.
uses(BrowserTestCase::class)->group('browser')->in('Browser');
