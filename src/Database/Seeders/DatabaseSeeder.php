<?php

namespace Webkul\B2BSuite\Database\Seeders;

use Illuminate\Database\Seeder;
use Webkul\B2BSuite\Repositories\CompanyAttributeRepository;

class DatabaseSeeder extends Seeder
{
    /**
     * The seeders that are safe to run on every install.
     *
     * @var array
     */
    protected $seeders = [
        CoreConfigTableSeeder::class,
    ];

    /**
     * The company attribute seeders, parents first. They clean their tables before inserting,
     * so they only run while no company attribute exists and a re-install keeps the admin's data.
     *
     * @var array
     */
    protected $attributeSeeders = [
        CompanyAttributeTableSeeder::class,
        CompanyAttributeOptionTableSeeder::class,
        CompanyAttributeGroupTableSeeder::class,
        CompanyAttributeGroupMappingTableSeeder::class,
    ];

    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $seeders = app(CompanyAttributeRepository::class)->count()
            ? $this->seeders
            : array_merge($this->seeders, $this->attributeSeeders);

        foreach ($seeders as $seeder) {
            $this->callWith($seeder, [
                'parameters' => [
                    'default_locale' => app()->getLocale(),
                    'locales' => core()->getAllLocales()->pluck('code')->toArray(),
                    'now' => now()->toDateTimeString(),
                ],
            ]);
        }
    }
}
