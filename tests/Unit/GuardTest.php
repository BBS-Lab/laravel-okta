<?php

declare(strict_types=1);

use BBSLab\LaravelOkta\Contracts\OktaPanel;
use BBSLab\LaravelOkta\Support\Guard;
use BBSLab\LaravelOkta\Support\NullOktaPanel;

it('uses the panel guard when set', function (): void {
    app()->bind(OktaPanel::class, fn (): OktaPanel => new class extends NullOktaPanel
    {
        public function guard(): ?string
        {
            return 'custom-guard';
        }
    });

    expect(Guard::name())->toBe('custom-guard');
});

it('falls back to the default auth guard when the panel guard is null', function (): void {
    config(['auth.defaults.guard' => 'web']);

    // The harness panel (FakeOktaPanel) returns a null guard.
    expect(Guard::name())->toBe('web');
});

it('always returns a string, even when neither guard nor default is set', function (): void {
    // FakeOktaPanel's guard() is null and no default guard is configured: the result
    // is the empty string (a string), never null — the method is contractually
    // string-typed, so the (string) cast must apply.
    config(['auth.defaults.guard' => null]);

    expect(Guard::name())->toBe('');
});
