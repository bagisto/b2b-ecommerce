<?php

namespace Webkul\B2BSuite\DataGrids\Admin;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\B2BSuite\Models\Customer;
use Webkul\DataGrid\DataGrid;
use Webkul\User\Models\AdminProxy;

class CompanyDataGrid extends DataGrid
{
    /**
     * Primary column.
     *
     * @var string
     */
    protected $primaryColumn = 'customer_id';

    /**
     * Prepare query builder.
     *
     * @return Builder
     */
    public function prepareQueryBuilder()
    {
        $tablePrefix = DB::getTablePrefix();

        $fullName = db_grammar()->concatWs(
            ' ',
            $tablePrefix.'b2b_company_flat.first_name',
            $tablePrefix.'b2b_company_flat.last_name'
        );

        $queryBuilder = DB::table('b2b_company_flat')
            ->distinct()
            ->leftJoin('customers', 'b2b_company_flat.customer_id', '=', 'customers.id')
            ->leftJoin('admins as sales_rep', 'customers.sales_rep_id', '=', 'sales_rep.id')
            ->select(
                'b2b_company_flat.customer_id',
                'b2b_company_flat.email',
                'b2b_company_flat.phone',
                'b2b_company_flat.business_name',
                'b2b_company_flat.website_url',
                'b2b_company_flat.vat_tax_id',
                'sales_rep.name as sales_rep_name',
                'customers.status',
                'b2b_company_flat.created_at',
                'b2b_company_flat.updated_at'
            )
            ->addSelect(DB::raw($fullName.' as full_name'))
            ->where('customers.type', 'company')
            ->where('b2b_company_flat.locale', app()->getLocale());

        $this->addFilter('customer_id', 'b2b_company_flat.customer_id');
        $this->addFilter('full_name', DB::raw($fullName));
        $this->addFilter('email', 'b2b_company_flat.email');
        $this->addFilter('phone', 'b2b_company_flat.phone');
        $this->addFilter('business_name', 'b2b_company_flat.business_name');
        $this->addFilter('website_url', 'b2b_company_flat.website_url');
        $this->addFilter('vat_tax_id', 'b2b_company_flat.vat_tax_id');
        $this->addFilter('sales_rep_name', 'sales_rep.name');
        $this->addFilter('status', 'customers.status');

        /**
         * A sales rep only sees the companies they manage; super-admins see all.
         */
        if ($repId = Customer::salesRepScopeId()) {
            $queryBuilder->where('customers.sales_rep_id', $repId);
        }

        return $queryBuilder;
    }

    /**
     * Prepare columns.
     *
     * @return void
     */
    public function prepareColumns()
    {
        $this->addColumn([
            'index' => 'customer_id',
            'label' => trans('b2b::app.admin.companies.index.datagrid.id'),
            'type' => 'integer',
            'searchable' => false,
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'full_name',
            'label' => trans('b2b::app.admin.companies.index.datagrid.name'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'business_name',
            'label' => trans('b2b::app.admin.companies.index.datagrid.business-name'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'email',
            'label' => trans('b2b::app.admin.companies.index.datagrid.email'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'phone',
            'label' => trans('b2b::app.admin.companies.index.datagrid.phone'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'website_url',
            'label' => trans('b2b::app.admin.companies.index.datagrid.website-url'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'vat_tax_id',
            'label' => trans('b2b::app.admin.companies.index.datagrid.vat-tax-id'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'sales_rep_name',
            'label' => trans('b2b::app.admin.companies.index.datagrid.sales-representative'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'status',
            'label' => trans('b2b::app.admin.companies.index.datagrid.status'),
            'type' => 'boolean',
            'filterable' => true,
            'filterable_options' => [
                [
                    'label' => trans('b2b::app.admin.companies.index.datagrid.active'),
                    'value' => 1,
                ],
                [
                    'label' => trans('b2b::app.admin.companies.index.datagrid.pending'),
                    'value' => 0,
                ],
            ],
            'sortable' => true,
            'closure' => function ($row) {
                if ($row->status) {
                    return '<span class="label-active">'.trans('b2b::app.admin.companies.index.datagrid.active').'</span>';
                }

                return '<span class="label-pending">'.trans('b2b::app.admin.companies.index.datagrid.pending').'</span>';
            },
        ]);

        $this->addColumn([
            'index' => 'created_at',
            'label' => trans('b2b::app.admin.companies.index.datagrid.created-at'),
            'type' => 'datetime',
            'searchable' => false,
            'filterable' => true,
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
        if (bouncer()->hasPermission('b2b.companies.edit')) {
            $this->addAction([
                'index' => 'edit',
                'icon' => 'icon-edit',
                'title' => trans('b2b::app.admin.companies.index.datagrid.edit'),
                'method' => 'GET',
                'url' => function ($row) {
                    return route('admin.b2b.companies.edit', $row->customer_id);
                },
            ]);
        }

        if (bouncer()->hasPermission('b2b.company-credit')) {
            $this->addAction([
                'index' => 'credit',
                'icon' => 'icon-sales',
                'title' => trans('b2b::app.admin.companies.credit.manage'),
                'method' => 'GET',
                'url' => function ($row) {
                    return route('admin.b2b.company_credits.view', $row->customer_id);
                },
            ]);
        }

        if (bouncer()->hasPermission('b2b.companies.delete')) {
            $this->addAction([
                'index' => 'delete',
                'icon' => 'icon-delete',
                'title' => trans('b2b::app.admin.companies.index.datagrid.delete'),
                'method' => 'DELETE',
                'url' => function ($row) {
                    return route('admin.b2b.companies.delete', $row->customer_id);
                },
            ]);
        }
    }

    /**
     * Prepare mass actions.
     *
     * @return void
     */
    public function prepareMassActions()
    {
        if (bouncer()->hasPermission('b2b.companies.edit')) {
            $this->addMassAction([
                'title' => trans('b2b::app.admin.companies.index.datagrid.update-status'),
                'method' => 'POST',
                'url' => route('admin.b2b.companies.mass_update_status'),
                'options' => [
                    [
                        'label' => trans('b2b::app.admin.companies.index.datagrid.approve'),
                        'value' => 1,
                    ],
                    [
                        'label' => trans('b2b::app.admin.companies.index.datagrid.disable'),
                        'value' => 0,
                    ],
                ],
            ]);

            $this->addMassAction([
                'title' => trans('b2b::app.admin.companies.index.datagrid.assign-sales-rep'),
                'method' => 'POST',
                'url' => route('admin.b2b.companies.mass_assign_sales_rep'),
                'options' => AdminProxy::modelClass()::orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn ($admin) => [
                        'label' => $admin->name,
                        'value' => $admin->id,
                    ])
                    ->values()
                    ->toArray(),
            ]);
        }

        if (bouncer()->hasPermission('b2b.companies.delete')) {
            $this->addMassAction([
                'title' => trans('b2b::app.admin.companies.index.datagrid.mass-delete'),
                'method' => 'POST',
                'url' => route('admin.b2b.companies.mass_delete'),
            ]);
        }
    }
}
