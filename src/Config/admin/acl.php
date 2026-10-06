<?php

return [
    /*
    |--------------------------------------------------------------------------
    | B2B Suite ACL
    |--------------------------------------------------------------------------
    |
    | Top-level B2B Suite permission. All B2B permissions live underneath it.
    |
    */
    [
        'key' => 'b2b',
        'name' => 'b2b::app.admin.acl.b2b',
        'route' => 'admin.b2b.companies.index',
        'sort' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Companies ACL
    |--------------------------------------------------------------------------
    */
    [
        'key' => 'b2b.companies',
        'name' => 'b2b::app.admin.acl.companies',
        'route' => ['admin.b2b.companies.index', 'admin.b2b.companies.get', 'admin.b2b.companies.search'],
        'sort' => 1,
    ], [
        'key' => 'b2b.companies.create',
        'name' => 'b2b::app.admin.acl.create',
        'route' => ['admin.b2b.companies.create', 'admin.b2b.companies.store'],
        'sort' => 1,
    ], [
        'key' => 'b2b.companies.edit',
        'name' => 'b2b::app.admin.acl.edit',
        'route' => [
            'admin.b2b.companies.edit',
            'admin.b2b.companies.update',
            'admin.b2b.companies.update_status',
            'admin.b2b.companies.mass_update_status',
            'admin.b2b.companies.mass_assign_sales_rep',
        ],
        'sort' => 2,
    ], [
        'key' => 'b2b.companies.delete',
        'name' => 'b2b::app.admin.acl.delete',
        'route' => ['admin.b2b.companies.delete', 'admin.b2b.companies.mass_delete'],
        'sort' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Company Credit ACL
    |--------------------------------------------------------------------------
    */
    [
        'key' => 'b2b.company-credit',
        'name' => 'b2b::app.admin.acl.company-credit',
        'route' => ['admin.b2b.company_credits.index', 'admin.b2b.company_credits.view'],
        'sort' => 2,
    ], [
        'key' => 'b2b.company-credit.edit',
        'name' => 'b2b::app.admin.acl.edit',
        'route' => ['admin.b2b.company_credits.update_limit', 'admin.b2b.company_credits.reimburse'],
        'sort' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Quotations ACL
    |--------------------------------------------------------------------------
    */
    [
        'key' => 'b2b.quotes',
        'name' => 'b2b::app.admin.acl.quotes',
        'route' => 'admin.b2b.quotes.index',
        'sort' => 2,
    ], [
        'key' => 'b2b.quotes.view',
        'name' => 'b2b::app.admin.acl.view',
        'route' => ['admin.b2b.quotes.view', 'admin.b2b.quotes.messages'],
        'sort' => 1,
    ], [
        'key' => 'b2b.quotes.create',
        'name' => 'b2b::app.admin.acl.create',
        'route' => [
            'admin.b2b.quotes.create',
            'admin.b2b.quotes.store',
            'admin.b2b.cart.index',
            'admin.b2b.cart.store',
            'admin.b2b.cart.items.store',
            'admin.b2b.cart.items.update',
            'admin.b2b.cart.items.destroy',
        ],
        'sort' => 2,
    ], [
        'key' => 'b2b.quotes.edit',
        'name' => 'b2b::app.admin.acl.edit',
        'route' => [
            'admin.b2b.quotes.submit_quote',
            'admin.b2b.quotes.send_message',
            'admin.b2b.quotes.accept_quote',
            'admin.b2b.quotes.reject_quote',
            'admin.b2b.quotes.mass_update',
        ],
        'sort' => 3,
    ], [
        'key' => 'b2b.quotes.delete',
        'name' => 'b2b::app.admin.acl.delete',
        'route' => 'admin.b2b.quotes.mass_delete',
        'sort' => 4,
    ],

    /*
    |--------------------------------------------------------------------------
    | Purchase Orders ACL
    |--------------------------------------------------------------------------
    */
    [
        'key' => 'b2b.purchase-orders',
        'name' => 'b2b::app.admin.acl.purchase-orders',
        'route' => 'admin.b2b.purchase_orders.index',
        'sort' => 3,
    ], [
        'key' => 'b2b.purchase-orders.view',
        'name' => 'b2b::app.admin.acl.view',
        'route' => 'admin.b2b.purchase_orders.view',
        'sort' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Company Attributes ACL
    |--------------------------------------------------------------------------
    */
    [
        'key' => 'b2b.attributes',
        'name' => 'b2b::app.admin.acl.attributes',
        'route' => 'admin.b2b.attributes.index',
        'sort' => 4,
    ], [
        'key' => 'b2b.attributes.create',
        'name' => 'b2b::app.admin.acl.create',
        'route' => ['admin.b2b.attributes.create', 'admin.b2b.attributes.store'],
        'sort' => 1,
    ], [
        'key' => 'b2b.attributes.edit',
        'name' => 'b2b::app.admin.acl.edit',
        'route' => [
            'admin.b2b.attributes.edit',
            'admin.b2b.attributes.update',
            'admin.b2b.attributes.edit_mapping',
            'admin.b2b.attributes.update_mapping',
        ],
        'sort' => 2,
    ], [
        'key' => 'b2b.attributes.delete',
        'name' => 'b2b::app.admin.acl.delete',
        'route' => ['admin.b2b.attributes.delete', 'admin.b2b.attributes.mass_delete'],
        'sort' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Company Catalogs ACL
    |--------------------------------------------------------------------------
    */
    [
        'key' => 'b2b.company-catalogs',
        'name' => 'b2b::app.admin.acl.company-catalogs',
        'route' => [
            'admin.b2b.company_catalogs.index',
            'admin.b2b.company_catalogs.companies',
            'admin.b2b.company_catalogs.products',
            'admin.b2b.company_catalogs.product_children',
            'admin.b2b.company_catalogs.category_preview',
        ],
        'sort' => 5,
    ], [
        'key' => 'b2b.company-catalogs.create',
        'name' => 'b2b::app.admin.acl.create',
        'route' => ['admin.b2b.company_catalogs.create', 'admin.b2b.company_catalogs.store'],
        'sort' => 1,
    ], [
        'key' => 'b2b.company-catalogs.edit',
        'name' => 'b2b::app.admin.acl.edit',
        'route' => [
            'admin.b2b.company_catalogs.edit',
            'admin.b2b.company_catalogs.update',
            'admin.b2b.company_catalogs.settings',
        ],
        'sort' => 2,
    ], [
        'key' => 'b2b.company-catalogs.delete',
        'name' => 'b2b::app.admin.acl.delete',
        'route' => 'admin.b2b.company_catalogs.delete',
        'sort' => 3,
    ],
];
