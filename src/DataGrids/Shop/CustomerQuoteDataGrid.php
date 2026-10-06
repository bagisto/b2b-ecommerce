<?php

namespace Webkul\B2BSuite\DataGrids\Shop;

use Illuminate\Support\Facades\DB;
use Webkul\B2BSuite\Models\CustomerQuote;
use Webkul\DataGrid\DataGrid;

class CustomerQuoteDataGrid extends DataGrid
{
    /**
     * Primary column.
     *
     * @var string
     */
    protected $primaryColumn = 'quote_id';

    /**
     * Prepare query builder.
     */
    public function prepareQueryBuilder()
    {
        $tablePrefix = DB::getTablePrefix();

        $customerName = 'COALESCE(NULLIF(TRIM('.db_grammar()->concatWs(
            ' ',
            $tablePrefix.'customer.first_name',
            $tablePrefix.'customer.last_name'
        )."), ''), ".$tablePrefix.'b2b_customer_quotes.customer_name)';
        $customer = auth()->guard('customer')->user();

        $companyId = DB::table('b2b_customer_companies')
            ->where('customer_id', $customer->id)
            ->value('company_id') ?? $customer->id;

        $queryBuilder = DB::table('b2b_customer_quotes')
            ->distinct()
            ->leftJoin('customers as company', 'b2b_customer_quotes.company_id', '=', 'company.id')
            ->leftJoin('customers as customer', 'b2b_customer_quotes.customer_id', '=', 'customer.id')
            ->addSelect(
                'b2b_customer_quotes.id as quote_id',
                'b2b_customer_quotes.quotation_number',
                'b2b_customer_quotes.name',
                'company.email as company_email',
                'b2b_customer_quotes.base_total',
                'b2b_customer_quotes.negotiated_total',
                'b2b_customer_quotes.state',
                'b2b_customer_quotes.status',
                'b2b_customer_quotes.order_id',
                'b2b_customer_quotes.order_date',
                'b2b_customer_quotes.expected_arrival_date',
                'b2b_customer_quotes.expiration_date',
                'b2b_customer_quotes.created_at',
                'b2b_customer_quotes.updated_at'
            )
            ->addSelect(DB::raw($customerName.' as customer_name'))
            ->where('b2b_customer_quotes.soft_deleted', 0)
            ->whereIn('b2b_customer_quotes.state', [
                CustomerQuote::STATE_QUOTATION,
                CustomerQuote::STATE_PURCHASE_ORDER,
            ])
            ->whereIn('b2b_customer_quotes.status', [
                CustomerQuote::STATUS_DRAFT,
                CustomerQuote::STATUS_OPEN,
                CustomerQuote::STATUS_NEGOTIATION,
                CustomerQuote::STATUS_ACCEPTED,
                CustomerQuote::STATUS_REJECTED,
                CustomerQuote::STATUS_ORDERED,
                CustomerQuote::STATUS_COMPLETED,
            ])
            ->where('b2b_customer_quotes.company_id', $companyId);

        $this->addFilter('quotation_number', 'b2b_customer_quotes.quotation_number');
        $this->addFilter('name', 'b2b_customer_quotes.name');
        $this->addFilter('state', 'b2b_customer_quotes.state');
        $this->addFilter('status', 'b2b_customer_quotes.status');
        $this->addFilter('base_total', 'b2b_customer_quotes.base_total');
        $this->addFilter('negotiated_total', 'b2b_customer_quotes.negotiated_total');
        $this->addFilter('customer_name', DB::raw($customerName));
        $this->addFilter('created_at', 'b2b_customer_quotes.created_at');

        return $queryBuilder;
    }

    /**
     * Prepare columns.
     */
    public function prepareColumns()
    {
        $this->addColumn([
            'index' => 'quotation_number',
            'label' => trans('b2b::app.shop.customers.account.quotes.index.datagrid.quote-id'),
            'type' => 'string',
            'searchable' => false,
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'name',
            'label' => trans('b2b::app.shop.customers.account.quotes.index.datagrid.name'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'customer_name',
            'label' => trans('b2b::app.shop.customers.account.quotes.index.datagrid.customer'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'base_total',
            'label' => trans('b2b::app.shop.customers.account.quotes.index.datagrid.base_total'),
            'type' => 'decimal',
            'filterable' => true,
            'sortable' => true,
            'closure' => function ($row) {
                return core()->formatPrice($row->base_total, core()->getCurrentCurrencyCode());
            },
        ]);

        $this->addColumn([
            'index' => 'negotiated_total',
            'label' => trans('b2b::app.shop.customers.account.quotes.index.datagrid.negotiated_total'),
            'type' => 'decimal',
            'filterable' => true,
            'sortable' => true,
            'closure' => function ($row) {
                return core()->formatPrice($row->negotiated_total, core()->getCurrentCurrencyCode());
            },
        ]);

        $this->addColumn([
            'index' => 'status',
            'label' => trans('b2b::app.shop.customers.account.quotes.index.datagrid.status'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'filterable_type' => 'dropdown',
            'filterable_options' => [
                [
                    'label' => trans('b2b::app.shop.customers.account.quotes.index.datagrid.draft'),
                    'value' => CustomerQuote::STATUS_DRAFT,
                ],
                [
                    'label' => trans('b2b::app.shop.customers.account.quotes.index.datagrid.open'),
                    'value' => CustomerQuote::STATUS_OPEN,
                ],
                [
                    'label' => trans('b2b::app.shop.customers.account.quotes.index.datagrid.accepted'),
                    'value' => CustomerQuote::STATUS_ACCEPTED,
                ],
                [
                    'label' => trans('b2b::app.shop.customers.account.quotes.index.datagrid.negotiation'),
                    'value' => CustomerQuote::STATUS_NEGOTIATION,
                ],
                [
                    'label' => trans('b2b::app.shop.customers.account.quotes.index.datagrid.expired'),
                    'value' => CustomerQuote::STATUS_EXPIRED,
                ],
                [
                    'label' => trans('b2b::app.shop.customers.account.quotes.index.datagrid.rejected'),
                    'value' => CustomerQuote::STATUS_REJECTED,
                ],
                [
                    'label' => trans('b2b::app.shop.customers.account.quotes.index.datagrid.ordered'),
                    'value' => CustomerQuote::STATUS_ORDERED,
                ],
                [
                    'label' => trans('b2b::app.shop.customers.account.quotes.index.datagrid.completed'),
                    'value' => CustomerQuote::STATUS_COMPLETED,
                ],
            ],
            'sortable' => true,
            'closure' => function ($row) {
                return CustomerQuote::statusBadge(
                    $row->status,
                    trans('b2b::app.shop.customers.account.quotes.index.datagrid.'.$row->status)
                );
            },
        ]);

        $this->addColumn([
            'index' => 'created_at',
            'label' => trans('b2b::app.shop.customers.account.quotes.index.datagrid.created-at'),
            'type' => 'datetime',
            'filterable' => true,
            'filterable_type' => 'datetime_range',
            'sortable' => true,
        ]);
    }

    /**
     * Prepare actions.
     *
     * @return void
     */
    public function prepareActions()
    {
        $this->addAction([
            'index' => 'edit',
            'icon' => 'icon-eye',
            'title' => trans('b2b::app.admin.quotes.index.datagrid.view'),
            'method' => 'GET',
            'url' => function ($row) {
                return route('shop.customers.account.quotes.view', $row->quote_id);
            },
        ]);
    }
}
