<?php

namespace Webkul\B2BSuite\DataGrids\Shop;

use Illuminate\Support\Facades\DB;
use Webkul\B2BSuite\Models\CustomerRequisitionList;
use Webkul\DataGrid\DataGrid;

class CustomerRequisitionListDataGrid extends DataGrid
{
    /**
     * Primary column.
     *
     * @var string
     */
    protected $primaryColumn = 'requisition_id';

    /**
     * Prepare query builder.
     */
    public function prepareQueryBuilder()
    {
        $tablePrefix = DB::getTablePrefix();

        $queryBuilder = DB::table('b2b_customer_requisition_lists')
            ->distinct()
            ->addSelect(
                'b2b_customer_requisition_lists.id as requisition_id',
                'b2b_customer_requisition_lists.customer_id',
                'b2b_customer_requisition_lists.name',
                'b2b_customer_requisition_lists.description',
                'b2b_customer_requisition_lists.status',
                'b2b_customer_requisition_lists.is_default',
                'b2b_customer_requisition_lists.updated_at',
            )
            ->where('b2b_customer_requisition_lists.customer_id', auth()->guard('customer')->user()->id);

        $this->addFilter('requisition_id', 'b2b_customer_requisition_lists.id');
        $this->addFilter('name', 'b2b_customer_requisition_lists.name');
        $this->addFilter('description', 'b2b_customer_requisition_lists.description');
        $this->addFilter('status', 'b2b_customer_requisition_lists.status');
        $this->addFilter('is_default', 'b2b_customer_requisition_lists.is_default');
        $this->addFilter('updated_at', 'b2b_customer_requisition_lists.updated_at');

        return $queryBuilder;
    }

    /**
     * Prepare columns.
     */
    public function prepareColumns()
    {
        $this->addColumn([
            'index' => 'requisition_id',
            'label' => trans('b2b::app.shop.customers.account.requisitions.index.datagrid.id'),
            'type' => 'string',
            'searchable' => false,
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'name',
            'label' => trans('b2b::app.shop.customers.account.requisitions.index.datagrid.name'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'description',
            'label' => trans('b2b::app.shop.customers.account.requisitions.index.datagrid.description'),
            'type' => 'string',
            'filterable' => true,
            'sortable' => true,
            'closure' => function ($row) {
                return strlen($row->description) > 50 ? substr($row->description, 0, 50).'...' : $row->description;
            },
        ]);

        $this->addColumn([
            'index' => 'items_count',
            'label' => trans('b2b::app.shop.customers.account.requisitions.index.datagrid.items'),
            'type' => 'string',
            'filterable' => true,
            'sortable' => true,
            'closure' => function ($row) {
                return DB::table('b2b_customer_requisition_list_products')
                    ->where('requisition_list_id', $row->requisition_id)
                    ->count();
            },
        ]);

        $this->addColumn([
            'index' => 'status',
            'label' => trans('b2b::app.shop.customers.account.requisitions.index.datagrid.status'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'filterable_type' => 'dropdown',
            'filterable_options' => [
                [
                    'label' => trans('b2b::app.shop.customers.account.requisitions.index.datagrid.active'),
                    'value' => CustomerRequisitionList::STATUS_ACTIVE,
                ],
                [
                    'label' => trans('b2b::app.shop.customers.account.requisitions.index.datagrid.inactive'),
                    'value' => CustomerRequisitionList::STATUS_INACTIVE,
                ],
            ],
            'sortable' => true,
            'closure' => function ($row) {
                switch ($row->status) {
                    case CustomerRequisitionList::STATUS_ACTIVE:
                        return '<p class="label-active">'.trans('b2b::app.shop.customers.account.requisitions.index.datagrid.active').'</p>';

                    case CustomerRequisitionList::STATUS_INACTIVE:
                        return '<p class="label-canceled">'.trans('b2b::app.shop.customers.account.requisitions.index.datagrid.inactive').'</p>';
                }
            },
        ]);

        $this->addColumn([
            'index' => 'is_default',
            'label' => trans('b2b::app.shop.customers.account.requisitions.index.datagrid.is-default'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'filterable_type' => 'dropdown',
            'filterable_options' => [
                [
                    'label' => trans('b2b::app.shop.customers.account.requisitions.index.datagrid.text-yes'),
                    'value' => CustomerRequisitionList::STATUS_YES,
                ],
                [
                    'label' => trans('b2b::app.shop.customers.account.requisitions.index.datagrid.text-no'),
                    'value' => CustomerRequisitionList::STATUS_NO,
                ],
            ],
            'sortable' => true,
            'closure' => function ($row) {
                if ($row->is_default == CustomerRequisitionList::STATUS_YES) {
                    return '<p class="label-active">'.trans('b2b::app.shop.customers.account.requisitions.index.datagrid.text-yes').'</p>';
                }

                return '-';
            },
        ]);

        $this->addColumn([
            'index' => 'updated_at',
            'label' => trans('b2b::app.shop.customers.account.requisitions.index.datagrid.updated-at'),
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
            'icon' => 'icon-edit',
            'title' => trans('b2b::app.shop.customers.account.requisitions.index.datagrid.edit'),
            'method' => 'GET',
            'url' => function ($row) {
                return route('shop.customers.account.requisitions.edit', $row->requisition_id);
            },
        ]);

        $this->addAction([
            'index' => 'delete',
            'icon' => 'icon-bin',
            'title' => trans('b2b::app.shop.customers.account.requisitions.index.datagrid.delete'),
            'method' => 'POST',
            'url' => function ($row) {
                return route('shop.customers.account.requisitions.delete', $row->requisition_id);
            },
        ]);
    }
}
