<?php

declare(strict_types=1);

arch('no debugging helpers are left behind')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'dexit'])
    ->not->toBeUsed();

arch('the whole package declares strict types')
    ->expect('BBSLab\LaravelOkta')
    ->toUseStrictTypes();

arch('no class in the package is declared final')
    ->expect('BBSLab\LaravelOkta')
    ->not->toBeFinal();

arch('the package never depends on test or workbench code')
    ->expect('BBSLab\LaravelOkta')
    ->not->toUse(['Workbench', 'BBSLab\LaravelOkta\Tests']);

arch('the base package never references Nova or Filament')
    ->expect('BBSLab\LaravelOkta')
    ->not->toUse(['Laravel\Nova', 'Filament']);
