<?php

namespace Webkul\B2BSuite\DataGrids\Admin;

use Illuminate\Support\Facades\DB;
use Webkul\B2BSuite\Models\Customer;
use Webkul\B2BSuite\Models\CustomerQuote;
use Webkul\B2BSuite\Repositories\CustomerQuoteRepository;
use Webkul\DataGrid\DataGrid;

class CustomerPurchaseOrderDataGrid extends DataGrid
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

        $companyName = "COALESCE(NULLIF({$tablePrefix}b2b_company_flat.business_name, ''), ".db_grammar()->concatWs(
            ' ',
            $tablePrefix.'company.first_name',
            $tablePrefix.'company.last_name'
        ).')';

        $queryBuilder = DB::table('b2b_customer_quotes')
            ->distinct()
            ->leftJoin('customers as company', 'b2b_customer_quotes.company_id', '=', 'company.id')
            ->leftJoin('customers as customer', 'b2b_customer_quotes.customer_id', '=', 'customer.id')
            ->leftJoin('admins as agent', 'company.sales_rep_id', '=', 'agent.id')
            ->leftJoin('b2b_company_flat', function ($join) {
                $join->on('b2b_customer_quotes.company_id', '=', 'b2b_company_flat.customer_id')
                    ->where('b2b_company_flat.locale', '=', app()->getLocale());
            })
            ->addSelect(
                'b2b_customer_quotes.id as quote_id',
                'b2b_customer_quotes.po_number',
                'b2b_customer_quotes.name',
                'customer.email as customer_email',
                'company.email as company_email',
                'agent.name as agent_name',
                'b2b_customer_quotes.base_total',
                'b2b_customer_quotes.negotiated_total',
                'b2b_customer_quotes.status',
                'b2b_customer_quotes.soft_deleted',
                'b2b_customer_quotes.created_at',
                'b2b_customer_quotes.expiration_date'
            )
            ->addSelect(DB::raw($customerName.' as customer_name'))
            ->addSelect(DB::raw($companyName.' as company_name'))
            ->where('b2b_customer_quotes.state', CustomerQuote::STATE_PURCHASE_ORDER)
            ->whereIn('b2b_customer_quotes.status', [
                CustomerQuote::STATUS_ORDERED,
                CustomerQuote::STATUS_COMPLETED,
            ]);

        $this->addFilter('po_number', 'b2b_customer_quotes.po_number');
        $this->addFilter('name', 'b2b_customer_quotes.name');
        $this->addFilter('status', 'b2b_customer_quotes.status');
        $this->addFilter('base_total', 'b2b_customer_quotes.base_total');
        $this->addFilter('negotiated_total', 'b2b_customer_quotes.negotiated_total');
        $this->addFilter('customer_name', DB::raw($customerName));
        $this->addFilter('customer_email', 'customer.email');
        $this->addFilter('company_name', DB::raw($companyName));
        $this->addFilter('company_email', 'company.email');
        $this->addFilter('agent_name', 'agent.name');
        $this->addFilter('created_at', 'b2b_customer_quotes.created_at');
        $this->addFilter('expiration_date', 'b2b_customer_quotes.expiration_date');

        /**
         * A sales rep only sees purchase orders for the companies they manage; super-admins see all.
         */
        if ($repId = Customer::salesRepScopeId()) {
            $queryBuilder->where('company.sales_rep_id', $repId);
        }

        return $queryBuilder;
    }

    /**
     * Prepare columns.
     */
    public function prepareColumns()
    {
        $this->addColumn([
            'index' => 'po_number',
            'label' => trans('b2b::app.admin.purchase-orders.index.datagrid.id'),
            'type' => 'integer',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'name',
            'label' => trans('b2b::app.admin.quotes.index.datagrid.name'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'company_name',
            'label' => trans('b2b::app.admin.quotes.index.datagrid.company'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'company_email',
            'label' => trans('b2b::app.admin.quotes.index.datagrid.company-email'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'customer_name',
            'label' => trans('b2b::app.admin.quotes.index.datagrid.customer'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'customer_email',
            'label' => trans('b2b::app.admin.quotes.index.datagrid.customer-email'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'agent_name',
            'label' => trans('b2b::app.admin.quotes.index.datagrid.sales-representative'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'base_total',
            'label' => trans('b2b::app.admin.quotes.index.datagrid.base_total'),
            'type' => 'decimal',
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'negotiated_total',
            'label' => trans('b2b::app.admin.quotes.index.datagrid.negotiated_total'),
            'type' => 'decimal',
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'items',
            'label' => trans('b2b::app.admin.purchase-orders.index.datagrid.items'),
            'type' => 'string',
            'exportable' => false,
            'closure' => function ($value) {
                $quote = app(CustomerQuoteRepository::class)->with('items')->find($value->quote_id);

                return view('b2b::admin.quotes.items', compact('quote'))->render();
            },
        ]);

        $this->addColumn([
            'index' => 'status',
            'label' => trans('b2b::app.admin.quotes.index.datagrid.status'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'filterable_type' => 'dropdown',
            'filterable_options' => [
                [
                    'label' => trans('b2b::app.admin.quotes.index.datagrid.ordered'),
                    'value' => CustomerQuote::STATUS_ORDERED,
                ],
                [
                    'label' => trans('b2b::app.admin.quotes.index.datagrid.completed'),
                    'value' => CustomerQuote::STATUS_COMPLETED,
                ],
            ],
            'sortable' => true,
            'closure' => function ($row) {
                $deleted = $row->soft_deleted
                    ? '<p class="label-canceled">'.trans('b2b::app.admin.quotes.index.datagrid.deleted').'</p>'
                    : '';

                $label = trans('b2b::app.admin.quotes.index.datagrid.'.$row->status);

                return '<div class="flex flex-row gap-1"><p class="'.CustomerQuote::statusLabelClass($row->status).'">'.$label.'</p>'.$deleted.'</div>';
            },
        ]);

        $this->addColumn([
            'index' => 'created_at',
            'label' => trans('b2b::app.admin.quotes.index.datagrid.created-at'),
            'type' => 'datetime',
            'filterable' => true,
            'filterable_type' => 'datetime_range',
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'expiration_date',
            'label' => trans('b2b::app.admin.quotes.index.datagrid.expiration-date'),
            'type' => 'date',
            'filterable' => true,
            'filterable_type' => 'date_range',
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
        if (bouncer()->hasPermission('b2b.purchase-orders.view')) {
            $this->addAction([
                'index' => 'view',
                'icon' => 'icon-view',
                'title' => trans('b2b::app.admin.purchase-orders.index.datagrid.view'),
                'method' => 'GET',
                'url' => function ($row) {
                    return route('admin.b2b.purchase_orders.view', $row->quote_id);
                },
            ]);
        }
    }
}
