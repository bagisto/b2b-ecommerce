<?php

namespace Webkul\B2BSuite\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Webkul\B2BSuite\Listeners\Company;
use Webkul\B2BSuite\Listeners\CompanyNotification;
use Webkul\B2BSuite\Listeners\Order;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        /**
         * Customer/Company related events.
         */
        'customer.registration.after' => [
            [Company::class, 'afterUpdate'],
            [Company::class, 'provisionCompanyCredit'],
        ],
        'customer.update.after' => [
            [Company::class, 'afterUpdate'],
        ],

        /**
         * Company lifecycle email notifications.
         */
        'b2b.company.registered' => [
            [CompanyNotification::class, 'registered'],
        ],
        'b2b.company.approved' => [
            [CompanyNotification::class, 'approved'],
        ],
        'b2b.company.disabled' => [
            [CompanyNotification::class, 'disabled'],
        ],

        /**
         * Sales related events.
         */
        'checkout.order.save.after' => [
            [Order::class, 'afterCreated'],
        ],
        'sales.order.cancel.after' => [
            [Order::class, 'afterCancelled'],
        ],
        'sales.refund.save.after' => [
            [Order::class, 'afterRefunded'],
        ],

        /**
         * Invoice & Shipment related events.
         */
        'sales.invoice.save.after' => [
            [Order::class, 'afterUpdated'],
        ],
        'sales.shipment.save.after' => [
            [Order::class, 'afterUpdated'],
        ],
    ];

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $templates = [
            [
                'event' => 'bagisto.admin.layout.head.before',
                'template' => 'b2b::components.admin.layouts.style',
            ],
            [
                'event' => 'bagisto.shop.layout.head.before',
                'template' => 'b2b::components.shop.layouts.style',
            ],
            [
                'event' => 'bagisto.shop.products.view.additional_actions.before',
                'template' => 'b2b::shop.customers.account.requisitions.list-modal',
            ],
            [
                'event' => 'bagisto.shop.checkout.cart.continue_shopping.before',
                'template' => 'b2b::shop.customers.account.requisitions.list-modal',
            ],
        ];

        /**
         * Checks if the `core_config` table exists.
         * This is necessary because if someone installs Bagisto and the B2B Suite module simultaneously,
         * it may cause an error due to the `core_config` table not being available during the installation process.
         */
        if (
            Schema::hasTable('core_config')
            && core()->getConfigData('b2b.general.settings.active')
        ) {
            foreach ($templates as $template) {
                Event::listen(current($template), fn ($e) => $e->addTemplate(end($template)));
            }
        }
    }
}
