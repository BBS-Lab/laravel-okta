<?php

declare(strict_types=1);

namespace BBSLab\LaravelOkta\Support;

use BBSLab\LaravelOkta\Contracts\OktaPanel;

class Guard
{
    /**
     * The guard the package authenticates against — the panel's configured
     * guard, falling back to the application default.
     */
    public static function name(): string
    {
        return (string) (app(OktaPanel::class)->guard() ?: config('auth.defaults.guard'));
    }
}
