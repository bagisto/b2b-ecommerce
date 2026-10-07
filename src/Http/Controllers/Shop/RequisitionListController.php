<?php

namespace Webkul\B2BSuite\Http\Controllers\Shop;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\View\View;
use Webkul\B2BSuite\DataGrids\Shop\CustomerRequisitionListDataGrid;
use Webkul\B2BSuite\Http\Resources\RequisitionItemResource;
use Webkul\B2BSuite\Models\CustomerRequisitionList;
use Webkul\B2BSuite\Repositories\CustomerRequisitionRepository;
use Webkul\Checkout\Facades\Cart;
use Webkul\Customer\Repositories\CustomerRepository;
use Webkul\Product\Repositories\ProductRepository;
use Webkul\Shop\Http\Controllers\Controller;
use Webkul\Shop\Http\Resources\ProductResource;

class RequisitionListController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected CustomerRequisitionRepository $customerRequisitionRepository,
        protected CustomerRepository $customerRepository,
        protected ProductRepository $productRepository,
    ) {}

    /**
     * Display the requisition lists of the current customer.
     *
     * @return View
     */
    public function index()
    {
        if (request()->ajax()) {
            return datagrid(CustomerRequisitionListDataGrid::class)->process();
        }

        $customerId = auth()->guard('customer')->user()->id;

        $totalRequisitionList = $this->customerRequisitionRepository->findByField('customer_id', $customerId);

        return view('b2b::shop.customers.account.requisitions.index')->with('totalRequisition', $totalRequisitionList->count());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(): JsonResponse
    {
        $customerId = auth()->guard('customer')->user()->id;

        $totalRequisition = $this->customerRequisitionRepository->count(['customer_id' => $customerId]);

        if ($totalRequisition >= (int) core()->getConfigData('b2b.general.settings.no_requisition_list')) {
            return new JsonResponse([
                'message' => trans('b2b::app.shop.customers.account.requisitions.limit-reached'),
            ], 422);
        }

        Event::dispatch('customer.requisitions.create.before');

        $customer = $this->customerRepository->find($customerId);

        $data = array_merge(request()->only([
            'name',
            'description',
        ]), [
            'customer_id' => $customer->id,
            'company_id' => $customer->companies->first()->id,
        ]);

        $requisition = $this->customerRequisitionRepository->create($data);

        if ($requisition) {
            if ($isDefault = request()->has('is_default')) {
                $this->customerRequisitionRepository->where('customer_id', $customer->id)->update(['is_default' => 0]);
            }

            $requisition->update([
                'is_default' => $isDefault || ! $totalRequisition,
            ]);
        }

        Event::dispatch('customer.requisitions.create.after', $requisition);

        session()->flash('success', trans('b2b::app.shop.customers.account.requisitions.create-success'));

        return new JsonResponse([
            'redirect_url' => ! request()->input('product_id')
                ? route('shop.customers.account.requisitions.index')
                : null,
        ]);
    }

    /**
     * Show the edit form for one of the current customer's requisition lists.
     *
     * @return View
     */
    public function edit(int $id)
    {
        $requisition = $this->customerRequisitionRepository->findOneWhere([
            'id' => $id,
            'customer_id' => auth()->guard('customer')->user()->id,
        ]);

        if (! $requisition) {
            abort(404);
        }

        return view('b2b::shop.customers.account.requisitions.edit')->with('requisition', $requisition);
    }

    /**
     * Update one of the current customer's requisition lists.
     *
     * @return JsonResponse
     */
    public function update(int $id, Request $request)
    {
        $data = $this->validate($request, [
            'requisition_id' => 'required|integer',
            'name' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
            'is_default' => 'sometimes',
        ]);

        $customerId = auth()->guard('customer')->user()->id;

        if (! $this->customerRequisitionRepository->findOneWhere([
            'id' => $id,
            'customer_id' => $customerId,
        ])) {
            abort(404);
        }

        Event::dispatch('customer.requisitions.update.before', $id);

        $customer = $this->customerRepository->find($customerId);

        $data = array_merge(request()->only([
            'name',
            'description',
        ]), [
            'customer_id' => $customer->id,
            'company_id' => $customer->companies->first()->id,
        ]);

        $requisition = $this->customerRequisitionRepository->update($data, $id);

        if ($requisition) {
            if ($isDefault = request()->has('is_default')) {
                $this->customerRequisitionRepository->where('customer_id', $customer->id)->update(['is_default' => 0]);
            }

            $requisition->update([
                'is_default' => $isDefault,
            ]);
        }

        Event::dispatch('customer.requisitions.update.after', $requisition);

        session()->flash('success', trans('b2b::app.shop.customers.account.requisitions.update-success'));

        return new JsonResponse([
            'data' => $requisition,
            'redirect_url' => route('shop.customers.account.requisitions.edit', $requisition->id),
        ]);
    }

    /**
     * Updates the quantity of the items present in the requisition list.
     */
    public function updateItems(): JsonResponse
    {
        $this->validate(request(), [
            'requisition_id' => 'required|exists:b2b_customer_requisition_lists,id',
            'qty' => 'array',
        ]);

        try {
            $data = request()->all();

            $requisition = $this->customerRequisitionRepository->with(['items'])->findOneWhere([
                'id' => $data['requisition_id'],
                'customer_id' => auth()->guard('customer')->user()->id,
            ]);

            if (! $requisition) {
                abort(404);
            }

            $qtyArray = $data['qty'] ?? [];

            $requisitionItems = $qtyArray ? $requisition->items->whereIn('id', array_keys($qtyArray)) : $requisition->items;

            foreach ($requisitionItems as $item) {
                $qty = $qtyArray[$item->id] ?? 1;

                $additional = $item->additional ? json_decode($item->additional, true) : [];

                $item->update([
                    'qty' => $qty,
                    'total' => $item->price * $qty,
                    'base_total' => $item->base_price * $qty,
                    'additional' => $additional ? json_encode(array_merge($additional, ['quantity' => $qty])) : null,
                ]);
            }

            /**
             * Reload the updated items.
             */
            $updatedItems = $requisition->items()->get();

            return new JsonResponse([
                'data' => RequisitionItemResource::collection($updatedItems),
                'message' => trans('b2b::app.shop.customers.account.requisitions.item-updated'),
            ]);
        } catch (\Exception $exception) {
            return new JsonResponse([
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Delete one of the current customer's requisition lists.
     */
    public function destroy(int $id): JsonResponse
    {
        $requisition = $this->customerRequisitionRepository->findOneWhere([
            'id' => $id,
            'customer_id' => auth()->guard('customer')->user()->id,
        ]);

        if (! $requisition) {
            abort(404);
        }

        Event::dispatch('customer.requisitions.delete.before', $id);

        $this->customerRequisitionRepository->delete($id);

        Event::dispatch('customer.requisitions.delete.after', $id);

        return new JsonResponse([
            'message' => trans('b2b::app.shop.customers.account.requisitions.delete-success'),
            'redirect_url' => route('shop.customers.account.requisitions.index'),
        ]);
    }

    /**
     * List the current customer's active requisition lists for the add-to-list dropdown.
     */
    public function list(): JsonResponse
    {
        $requisitions = $this->customerRequisitionRepository->findWhere([
            'status' => CustomerRequisitionList::STATUS_ACTIVE,
            'customer_id' => auth()->guard('customer')->user()->id,
        ])
            ->sortByDesc('created_at')
            ->take(5)
            ->map(function ($requisition) {
                return [
                    'id' => $requisition->id,
                    'name' => $requisition->name,
                ];
            });

        $totalRequisition = $this->customerRequisitionRepository->count([
            'customer_id' => auth()->guard('customer')->user()->id,
        ]);

        return new JsonResponse([
            'requisitions' => $requisitions,
            'allow_new_list' => (int) core()->getConfigData('b2b.general.settings.no_requisition_list') > $totalRequisition,
        ]);
    }

    /**
     * Removes the item from the requisition if it exists.
     */
    public function destroyItems(): JsonResponse
    {
        $this->validate(request(), [
            'requisition_id' => 'required|exists:b2b_customer_requisition_lists,id',
            'requisition_item_ids' => 'required|array',
        ]);

        $requisition = $this->customerRequisitionRepository->findOneWhere([
            'id' => request()->input('requisition_id'),
            'customer_id' => auth()->guard('customer')->user()->id,
        ]);

        if (! $requisition) {
            abort(404);
        }

        $requisitionItems = $requisition->items->whereIn('id', request()->input('requisition_item_ids'));

        foreach ($requisitionItems as $item) {
            $item->delete();
        }

        /**
         * Reload the updated items.
         */
        $updatedItems = $requisition->items()->get();

        return new JsonResponse([
            'data' => RequisitionItemResource::collection($updatedItems),
            'message' => trans('b2b::app.shop.customers.account.requisitions.success-remove'),
        ]);
    }

    /**
     * For getting the product details.
     */
    public function getProduct(): JsonResponse
    {
        $product = $this->productRepository->find(request()->id);

        return new JsonResponse([
            'data' => new ProductResource($product),
        ]);
    }

    /**
     * Add product to the requisition list.
     */
    public function addProduct(): JsonResponse
    {
        $data = $this->validate(request(), [
            'requisition_id' => 'required|integer',
            'product_id' => 'sometimes',
            'cart_id' => 'sometimes',
            'selected_configurable_option' => 'sometimes',
            'quantity' => 'sometimes|integer|min:1',
        ]);

        try {
            $requisition = $this->customerRequisitionRepository->findOneWhere([
                'id' => $data['requisition_id'],
                'customer_id' => auth()->guard('customer')->id(),
            ]);

            if (! $requisition) {
                return response()->json([
                    'message' => trans('b2b::app.shop.customers.account.requisitions.not-found'),
                ], 404);
            }

            $this->customerRequisitionRepository->saveItems($requisition, $data);

            return new JsonResponse([
                'message' => trans('b2b::app.shop.customers.account.requisitions.add-product-success'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get the requisition items.
     */
    public function getItems(): JsonResponse
    {
        $requisition = $this->customerRequisitionRepository->findOneWhere([
            'id' => request()->id,
            'customer_id' => auth()->guard('customer')->user()->id,
        ]);

        if (! $requisition) {
            abort(404);
        }

        return new JsonResponse([
            'data' => RequisitionItemResource::collection($requisition->items),
        ]);
    }

    /**
     * Move the selected requisition items to the cart, leaving behind any product outside
     * the customer's company catalog.
     */
    public function moveToCart(): JsonResponse
    {
        $this->validate(request(), [
            'requisition_id' => 'required|exists:b2b_customer_requisition_lists,id',
            'ids' => 'required|array',
        ]);

        $data = request()->all();

        $requisition = $this->customerRequisitionRepository->with(['items'])->findOneWhere([
            'id' => $data['requisition_id'],
            'customer_id' => auth()->guard('customer')->user()->id,
        ]);

        if (! $requisition) {
            abort(404);
        }

        [$visibleItems, $hiddenItems] = $requisition->items
            ->whereIn('id', $data['ids'])
            ->partition(fn ($item) => $this->productRepository->isVisible($item->product));

        foreach ($visibleItems as $item) {
            $additional = $item->additional ? json_decode($item->additional, true) : [];

            $additional['quantity'] = $item->qty ?? 1;

            $additional['product_id'] = $item->product_id;

            try {
                Cart::addProduct($item->product, $additional);
            } catch (\Exception $e) {
            }
        }

        if ($visibleItems->isNotEmpty()) {
            session()->flash('success', trans('b2b::app.shop.customers.account.requisitions.move-to-cart-success'));
        }

        if ($hiddenItems->isNotEmpty()) {
            session()->flash('warning', trans('b2b::app.shop.checkout.cart.product-not-in-catalog'));
        }

        return new JsonResponse([
            'redirect_url' => route('shop.checkout.cart.index'),
        ]);
    }
}
