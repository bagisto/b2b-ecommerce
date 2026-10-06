<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Company (B2B) Feature ACLs
    |--------------------------------------------------------------------------
    |
    | Only company features are permission-controlled by company roles. Personal
    | account features (profile, address, orders, downloadables, reviews, wishlist,
    | GDPR) are intentionally NOT listed here — a member always controls their own
    | account and CustomerBouncer always allows those keys.
    |
    | Following the core admin ACL pattern, a permission's `route` may be a single
    | route OR an array of routes — so one key (e.g. `create` / `edit`) gates both
    | the form and every write/action endpoint it owns (store, update, …). Read-only
    | helper endpoints (AJAX lookups, item lists) are folded into the parent feature
    | rather than given their own toggle.
    |
    | Ordered to match the account menu (config/shop/menu.php).
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Company Profile ACLs
    |--------------------------------------------------------------------------
    */
    [
        'key' => 'company_profile',
        'name' => 'b2b::app.shop.acl.company-profile',
        'route' => 'shop.customers.account.company_profile.index',
        'sort' => 1,
    ], [
        'key' => 'company_profile.edit',
        'name' => 'b2b::app.shop.acl.edit',
        'route' => [
            'shop.customers.account.company_profile.edit',
            'shop.customers.account.company_profile.update',
        ],
        'sort' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Company Credit ACLs
    |--------------------------------------------------------------------------
    */
    [
        'key' => 'company_credit',
        'name' => 'b2b::app.shop.acl.company-credit',
        'route' => 'shop.customers.account.credit.index',
        'sort' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Quotes ACLs
    |--------------------------------------------------------------------------
    */
    [
        'key' => 'quotes',
        'name' => 'b2b::app.shop.acl.quotes',
        'route' => [
            'shop.customers.account.quotes.index',
            'shop.customers.account.quotes.get_product',
        ],
        'sort' => 3,
    ], [
        'key' => 'quotes.view',
        'name' => 'b2b::app.shop.acl.view',
        'route' => [
            'shop.customers.account.quotes.view',
            'shop.customers.account.quotes.download',
            'shop.customers.account.quotes.submit_quote',
            'shop.customers.account.quotes.update',
            'shop.customers.account.quotes.add_to_cart',
        ],
        'sort' => 1,
    ], [
        'key' => 'quotes.messages',
        'name' => 'b2b::app.shop.acl.messages',
        'route' => [
            'shop.customers.account.quotes.messages',
            'shop.customers.account.quotes.send_message',
        ],
        'sort' => 2,
    ], [
        'key' => 'quotes.delete',
        'name' => 'b2b::app.shop.acl.delete',
        'route' => [
            'shop.customers.account.quotes.delete_quote',
            'shop.customers.account.quotes.reject_quote',
        ],
        'sort' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Purchase Orders ACLs
    |--------------------------------------------------------------------------
    */
    [
        'key' => 'purchase_orders',
        'name' => 'b2b::app.shop.acl.purchase-orders',
        'route' => 'shop.customers.account.purchase_orders.index',
        'sort' => 4,
    ], [
        'key' => 'purchase_orders.view',
        'name' => 'b2b::app.shop.acl.view',
        'route' => 'shop.customers.account.purchase_orders.view',
        'sort' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Requisitions ACLs
    |--------------------------------------------------------------------------
    */
    [
        'key' => 'requisitions',
        'name' => 'b2b::app.shop.acl.requisitions',
        'route' => [
            'shop.customers.account.requisitions.index',
            'shop.customers.account.requisitions.list',
            'shop.customers.account.requisitions.items',
            'shop.customers.account.requisitions.get_product',
        ],
        'sort' => 5,
    ], [
        'key' => 'requisitions.create',
        'name' => 'b2b::app.shop.acl.create',
        'route' => [
            'shop.customers.account.requisitions.store',
        ],
        'sort' => 1,
    ], [
        'key' => 'requisitions.edit',
        'name' => 'b2b::app.shop.acl.edit',
        'route' => [
            'shop.customers.account.requisitions.edit',
            'shop.customers.account.requisitions.update',
            'shop.customers.account.requisitions.add_product',
            'shop.customers.account.requisitions.update_items',
            'shop.customers.account.requisitions.move_to_cart',
        ],
        'sort' => 2,
    ], [
        'key' => 'requisitions.delete',
        'name' => 'b2b::app.shop.acl.delete',
        'route' => [
            'shop.customers.account.requisitions.delete',
            'shop.customers.account.requisitions.delete_items',
        ],
        'sort' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Quick Orders ACLs
    |--------------------------------------------------------------------------
    |
    | A single feature — every endpoint (search, sku lookup, sample, add) is part
    | of using quick orders, so they all sit under the one parent key.
    |
    */
    [
        'key' => 'quick_orders',
        'name' => 'b2b::app.shop.acl.quick-orders',
        'route' => [
            'shop.customers.account.quick_orders.index',
            'shop.customers.account.quick_orders.search',
            'shop.customers.account.quick_orders.fetchBySkus',
            'shop.customers.account.quick_orders.downloadSample',
            'shop.customers.account.quick_orders.store',
        ],
        'sort' => 6,
    ],

    /*
    |--------------------------------------------------------------------------
    | Company Users ACLs
    |--------------------------------------------------------------------------
    |
    | Inviting / adding an existing platform user are part of "create"; revoking a
    | pending invitation is part of "edit" (managing members).
    |
    */
    [
        'key' => 'users',
        'name' => 'b2b::app.shop.acl.users',
        'route' => 'shop.customers.account.users.index',
        'sort' => 7,
    ], [
        'key' => 'users.create',
        'name' => 'b2b::app.shop.acl.create',
        'route' => [
            'shop.customers.account.users.create',
            'shop.customers.account.users.store',
            'shop.customers.account.users.add_existing',
            'shop.customers.account.users.invite',
        ],
        'sort' => 1,
    ], [
        'key' => 'users.edit',
        'name' => 'b2b::app.shop.acl.edit',
        'route' => [
            'shop.customers.account.users.edit',
            'shop.customers.account.users.update',
            'shop.customers.account.users.revoke_invitation',
        ],
        'sort' => 2,
    ], [
        'key' => 'users.delete',
        'name' => 'b2b::app.shop.acl.delete',
        'route' => 'shop.customers.account.users.delete',
        'sort' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Company Roles ACLs
    |--------------------------------------------------------------------------
    */
    [
        'key' => 'roles',
        'name' => 'b2b::app.shop.acl.roles',
        'route' => 'shop.customers.account.roles.index',
        'sort' => 8,
    ], [
        'key' => 'roles.create',
        'name' => 'b2b::app.shop.acl.create',
        'route' => [
            'shop.customers.account.roles.create',
            'shop.customers.account.roles.store',
        ],
        'sort' => 1,
    ], [
        'key' => 'roles.edit',
        'name' => 'b2b::app.shop.acl.edit',
        'route' => [
            'shop.customers.account.roles.edit',
            'shop.customers.account.roles.update',
        ],
        'sort' => 2,
    ], [
        'key' => 'roles.delete',
        'name' => 'b2b::app.shop.acl.delete',
        'route' => 'shop.customers.account.roles.delete',
        'sort' => 3,
    ],
];
