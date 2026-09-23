<?php

declare(strict_types=1);

use BBSLab\LaravelOkta\LaravelOktaServiceProvider;
use Illuminate\Support\ServiceProvider;

it('publishes an optional okta_id migration under the okta-migrations tag', function (): void {
    $paths = ServiceProvider::pathsToPublish(LaravelOktaServiceProvider::class, 'okta-migrations');

    expect($paths)->not->toBeEmpty();

    $stub = (string) array_key_first($paths);

    expect($stub)->toContain('add_okta_id_to_users_table')
        ->and(file_get_contents($stub))->toContain('okta_id')
        ->and(file_get_contents($stub))->toContain('unique');
});
