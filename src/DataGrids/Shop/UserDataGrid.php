<?php

namespace Webkul\B2BSuite\DataGrids\Shop;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\Customer\Repositories\CustomerGroupRepository;
use Webkul\DataGrid\DataGrid;

class UserDataGrid extends DataGrid
{
    /**
     * Index.
     *
     * @var string
     */
    protected $primaryColumn = 'user_id';

    /**
     * The viewer's company id. Its owner row is listed (so members see themselves) but kept
     * non-editable/non-deletable from this grid.
     *
     * @var int|null
     */
    protected $companyId = null;

    /**
     * The viewer's own customer id — they can't remove themselves from the company.
     *
     * @var int|null
     */
    protected $currentUserId = null;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(protected CustomerGroupRepository $customerGroupRepository) {}

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
            $tablePrefix.'customers.first_name',
            $tablePrefix.'customers.last_name'
        );
        $customer = auth()->guard('customer')->user();

        $this->currentUserId = $customer->id;

        $this->companyId = $companyId = $customer->type === 'company'
            ? $customer->id
            : DB::table('b2b_customer_companies')
                ->where('customer_id', $customer->id)
                ->value('company_id');

        /**
         * List every member of the company — the owner (type "company", self-linked in
         * customer_companies) and all of its sub-users (type "user") — including the person
         * viewing the grid, so they always see themselves in the roster.
         */
        $queryBuilder = DB::table('customers')
            ->leftJoin('b2b_company_roles', 'customers.company_role_id', '=', 'b2b_company_roles.id')
            ->join('b2b_customer_companies', function ($join) use ($companyId) {
                $join->on('customers.id', '=', 'b2b_customer_companies.customer_id')
                    ->where('b2b_customer_companies.company_id', $companyId);
            })
            ->addSelect(
                'customers.id as user_id',
                'customers.email',
                'customers.phone',
                'customers.status',
                'customers.is_suspended',
                'customers.type as customer_type',
                'b2b_company_roles.name as role'
            )
            ->addSelect(DB::raw($fullName.' as full_name'))
            ->whereIn('customers.type', ['company', 'user'])
            ->groupBy('customers.id');

        $this->addFilter('user_id', 'customers.id');
        $this->addFilter('email', 'customers.email');
        $this->addFilter('full_name', DB::raw($fullName));
        $this->addFilter('role', 'b2b_company_roles.name');
        $this->addFilter('phone', 'customers.phone');
        $this->addFilter('status', 'customers.status');

        return $queryBuilder;
    }

    /**
     * Add columns.
     *
     * @return void
     */
    public function prepareColumns()
    {
        $this->addColumn([
            'index' => 'user_id',
            'label' => trans('b2b::app.shop.customers.account.users.index.datagrid.id'),
            'type' => 'integer',
            'filterable' => true,
        ]);

        $this->addColumn([
            'index' => 'full_name',
            'label' => trans('b2b::app.shop.customers.account.users.index.datagrid.name'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'email',
            'label' => trans('b2b::app.shop.customers.account.users.index.datagrid.email'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'phone',
            'label' => trans('b2b::app.shop.customers.account.users.index.datagrid.phone'),
            'type' => 'integer',
            'filterable' => true,
        ]);

        $this->addColumn([
            'index' => 'status',
            'label' => trans('b2b::app.shop.customers.account.users.index.datagrid.status'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'filterable_type' => 'dropdown',
            'filterable_options' => [
                [
                    'label' => trans('b2b::app.shop.customers.account.users.index.datagrid.active'),
                    'value' => 1,
                ],
                [
                    'label' => trans('b2b::app.shop.customers.account.users.index.datagrid.inactive'),
                    'value' => 0,
                ],
            ],
            'sortable' => true,
            'closure' => function ($row) {
                if ($row->status) {
                    return '<p class="label-active">'.trans('b2b::app.shop.customers.account.users.index.datagrid.active').'</p>';
                }

                return '<p class="label-canceled">'.trans('b2b::app.shop.customers.account.users.index.datagrid.inactive').'</p>';
            },
        ]);

        $this->addColumn([
            'index' => 'role',
            'label' => trans('b2b::app.shop.customers.account.users.index.datagrid.role'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
            'closure' => function ($row) {
                return $row->role ?: '—';
            },
        ]);

        $this->addColumn([
            'index' => 'is_suspended',
            'label' => trans('b2b::app.shop.customers.account.users.index.datagrid.is-suspended'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'filterable_type' => 'dropdown',
            'filterable_options' => [
                [
                    'label' => trans('b2b::app.shop.customers.account.users.index.datagrid.suspended'),
                    'value' => 1,
                ],
                [
                    'label' => trans('b2b::app.shop.customers.account.users.index.datagrid.not-suspended'),
                    'value' => 0,
                ],
            ],
            'sortable' => true,
            'closure' => function ($row) {
                if ($row->is_suspended) {
                    return '<p class="label-canceled">'.trans('b2b::app.shop.customers.account.users.index.datagrid.suspended').'</p>';
                }

                return '<p class="label-active">'.trans('b2b::app.shop.customers.account.users.index.datagrid.not-suspended').'</p>';
            },
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
            'icon' => 'icon-edit',
            'title' => trans('b2b::app.shop.customers.account.users.index.datagrid.edit'),
            'method' => 'GET',
            'url' => function ($row) {
                return route('shop.customers.account.users.edit', $row->user_id);
            },
        ]);

        $this->addAction([
            'index' => 'remove',
            'icon' => 'icon-cancel',
            'title' => trans('b2b::app.shop.customers.account.users.index.datagrid.remove'),
            'method' => 'POST',
            'url' => function ($row) {
                return route('shop.customers.account.users.delete', $row->user_id);
            },
        ]);
    }

    /**
     * Strip the company owner's row actions — the owner is shown for context (and tagged in
     * the view via customer_type) but must not be edited or removed through the sub-user grid.
     */
    protected function formatRecords($records): mixed
    {
        $records = parent::formatRecords($records);

        foreach ($records as $record) {
            /**
             * The company owner is read-only here.
             */
            if ((int) $record->user_id === (int) $this->companyId) {
                $record->actions = [];

                continue;
            }

            /**
             * A member can't remove themselves from the company (edit is still allowed).
             */
            if ((int) $record->user_id === (int) $this->currentUserId) {
                $record->actions = array_values(array_filter(
                    $record->actions,
                    fn ($action) => ($action['index'] ?? '') !== 'remove'
                ));
            }
        }

        return $records;
    }
}
