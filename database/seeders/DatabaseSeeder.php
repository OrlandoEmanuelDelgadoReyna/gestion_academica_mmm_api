<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** Coordinates the minimum deterministic data required by SIGE-MMM. */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(InstitutionalCatalogSeeder::class);
        $this->call(DemoOperationalSeeder::class);
    }
}
