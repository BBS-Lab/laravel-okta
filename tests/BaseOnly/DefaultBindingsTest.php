<?php

declare(strict_types=1);

use BBSLab\LaravelOkta\Contracts\OktaPanel;
use BBSLab\LaravelOkta\Contracts\OktaUserResolver;
use BBSLab\LaravelOkta\Resolvers\DefaultOktaUserResolver;
use BBSLab\LaravelOkta\Support\NullOktaPanel;

// Bound to BaseOnlyTestCase (see Pest.php): no adapter provider is registered, so
// the base package's own bindIf() defaults are what answer the container.

it('binds the null panel as the default when no adapter overrides it', function (): void {
    expect(app(OktaPanel::class))->toBeInstanceOf(NullOktaPanel::class);
});

it('binds the default resolver when no adapter overrides it', function (): void {
    expect(app(OktaUserResolver::class))->toBeInstanceOf(DefaultOktaUserResolver::class);
});
