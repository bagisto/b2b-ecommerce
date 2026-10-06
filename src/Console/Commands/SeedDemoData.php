<?php

namespace Webkul\B2BSuite\Console\Commands;

use Faker\Factory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Webkul\B2BSuite\Database\Seeders\DemoSeeders\DemoCompanyCatalogSeeder;
use Webkul\B2BSuite\Database\Seeders\DemoSeeders\DemoDataSeeder;

class SeedDemoData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'b2b-suite:seed-demo
        {--companies=2500 : Number of demo companies to create}
        {--members=10 : Maximum member sub-users per company}
        {--quotations=5 : Maximum quotations per company}
        {--purchase-orders=3 : Maximum purchase orders per company}
        {--clean : Remove all demo data and exit (no seeding)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed realistic demo companies, company credits, quotations and purchase orders.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $seeder = app(DemoDataSeeder::class);

        if ($this->option('clean')) {
            $seeder->clean();

            $this->components->info('All B2B demo data removed.');

            return self::SUCCESS;
        }

        if (! class_exists(Factory::class)) {
            $this->components->error('Demo seeding needs fakerphp/faker, which is only installed with Composer\'s development dependencies.');

            return self::FAILURE;
        }

        $seeder->companies = max(1, (int) $this->option('companies'));
        $seeder->members = max(0, (int) $this->option('members'));
        $seeder->quotations = max(0, (int) $this->option('quotations'));
        $seeder->purchaseOrders = max(0, (int) $this->option('purchase-orders'));

        $this->components->info(sprintf(
            'Seeding %d demo companies (≤%d members, ≤%d quotations, ≤%d purchase orders each) …',
            $seeder->companies,
            $seeder->members,
            $seeder->quotations,
            $seeder->purchaseOrders,
        ));

        $start = microtime(true);

        $seeder->run();

        $this->summary($seeder, microtime(true) - $start);

        return self::SUCCESS;
    }

    /**
     * Print a short summary of what was created.
     */
    protected function summary(DemoDataSeeder $seeder, float $seconds): void
    {
        $domain = '%@'.DemoDataSeeder::DEMO_DOMAIN;

        $companyIds = DB::table('customers')
            ->where('type', 'company')
            ->where('email', 'like', $domain)
            ->pluck('id');

        $this->newLine();

        $this->components->twoColumnDetail('<fg=green>Companies</>', (string) $companyIds->count());
        $this->components->twoColumnDetail('<fg=green>Members</>', (string) DB::table('customers')->where('type', 'user')->where('email', 'like', $domain)->count());
        $this->components->twoColumnDetail('<fg=green>Credit lines</>', (string) DB::table('b2b_company_credits')->whereIn('company_id', $companyIds)->count());
        $this->components->twoColumnDetail('<fg=green>Quotations</>', (string) DB::table('b2b_customer_quotes')->whereIn('company_id', $companyIds)->where('state', 'quotation')->count());
        $this->components->twoColumnDetail('<fg=green>Purchase orders</>', (string) DB::table('b2b_customer_quotes')->whereIn('company_id', $companyIds)->where('state', 'purchase_order')->count());
        $this->components->twoColumnDetail('<fg=green>Company catalogs</>', (string) DB::table('b2b_company_catalogs')->where('name', 'like', '%'.DemoCompanyCatalogSeeder::DEMO_TAG)->count());
        $this->components->twoColumnDetail('<fg=green>Companies on a catalog</>', (string) DB::table('customers')->where('type', 'company')->where('email', 'like', $domain)->whereNotNull('company_catalog_id')->count());

        $this->newLine();

        $this->components->info(sprintf('Done in %.1fs. Demo accounts use the @%s domain (password: "admin123").', $seconds, DemoDataSeeder::DEMO_DOMAIN));
    }
}
