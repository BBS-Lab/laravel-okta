<?php

declare(strict_types=1);

namespace Workbench\Database\Seeders;

use Illuminate\Database\Seeder;
use Workbench\App\Models\User;

class DatabaseSeeder extends Seeder
{
    /**
     * A single user so the package can be exercised end-to-end with
     * `composer serve` (password is "password").
     */
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'okta@laravel.com'],
            [
                'name' => 'Laravel Okta',
                'password' => 'password',
            ],
        );
    }
}
