<?php

namespace Webkul\B2BSuite\DataGrids\Admin;

use Illuminate\Support\Facades\DB;
use Webkul\B2BSuite\Models\Customer;
use Webkul\DataGrid\DataGrid;

class CompanyCreditDataGrid extends DataGrid
{
    /**
     * Primary column.
     *
     * @var string
     */
    protected $primaryColumn = 'company_id';

    /**
     * Prepare query builder.
     */
    public function prepareQueryBuilder()
    {
        $tablePrefix = DB::getTablePrefix();

        $companyName = "COALESCE(NULLIF({$tablePrefix}b2b_company_flat.business_name, ''), ".db_grammar()->concatWs(
            ' ',
            $tablePrefix.'customers.first_name',
            $tablePrefix.'customers.last_name'
        ).')';
        $available = $tablePrefix.'b2b_company_credits.credit_limit - '.$tablePrefix.'b2b_company_credits.outstanding_balance';

        $queryBuilder = DB::table('b2b_company_credits')
            ->leftJoin('customers', 'b2b_company_credits.company_id', '=', 'customers.id')
            ->leftJoin('b2b_company_flat', function ($join) {
                $join->on('b2b_company_credits.company_id', '=', 'b2b_company_flat.customer_id')
                    ->where('b2b_company_flat.locale', '=', app()->getLocale());
            })
            ->select(
                'b2b_company_credits.id',
                'b2b_company_credits.company_id',
                'customers.email as company_email',
                'b2b_company_credits.credit_limit',
                'b2b_company_credits.outstanding_balance',
                'b2b_company_credits.status',
                DB::raw($companyName.' as company_name'),
                DB::raw('('.$available.') as available_credit')
            );

        $this->addFilter('company_name', DB::raw($companyName));
        $this->addFilter('company_email', 'customers.email');
        $this->addFilter('credit_limit', 'b2b_company_credits.credit_limit');
        $this->addFilter('outstanding_balance', 'b2b_company_credits.outstanding_balance');
        $this->addFilter('available_credit', DB::raw($available));
        $this->addFilter('status', 'b2b_company_credits.status');

        // A sales rep only sees credit for the companies they manage; super-admins see all.
        if ($repId = Customer::salesRepScopeId()) {
            $queryBuilder->where('customers.sales_rep_id', $repId);
        }

        return $queryBuilder;
    }

    /**
     * Prepare columns.
     */
    public function prepareColumns()
    {
        $this->addColumn([
            'index' => 'company_name',
            'label' => trans('b2b::app.admin.companies.credit.datagrid.company'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'company_email',
            'label' => trans('b2b::app.admin.companies.credit.datagrid.email'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'credit_limit',
            'label' => trans('b2b::app.admin.companies.credit.credit-limit'),
            'type' => 'decimal',
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'outstanding_balance',
            'label' => trans('b2b::app.admin.companies.credit.outstanding-balance'),
            'type' => 'decimal',
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'available_credit',
            'label' => trans('b2b::app.admin.companies.credit.available-credit'),
            'type' => 'decimal',
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'status',
            'label' => trans('b2b::app.admin.companies.credit.status'),
            'type' => 'boolean',
            'filterable' => true,
            'filterable_type' => 'dropdown',
            'filterable_options' => [
                ['label' => trans('b2b::app.admin.companies.credit.enabled'), 'value' => 1],
                ['label' => trans('b2b::app.admin.companies.credit.disabled'), 'value' => 0],
            ],
            'sortable' => true,
            'closure' => function ($row) {
                if ($row->status) {
                    return '<p class="label-active">'.trans('b2b::app.admin.companies.credit.enabled').'</p>';
                }

                return '<p class="label-canceled">'.trans('b2b::app.admin.companies.credit.disabled').'</p>';
            },
        ]);
    }

    /**
     * Prepare actions.
     */
    public function prepareActions()
    {
        if (bouncer()->hasPermission('b2b.company-credit')) {
            $this->addAction([
                'index' => 'manage',
                'icon' => 'icon-edit',
                'title' => trans('b2b::app.admin.companies.credit.manage'),
                'method' => 'GET',
                'url' => fn ($row) => route('admin.b2b.company_credits.view', $row->company_id),
            ]);
        }
    }
}
