<?php

namespace Webkul\B2BSuite\Repositories;

use Illuminate\Container\Container;
use Illuminate\Support\Arr;
use Webkul\B2BSuite\Contracts\CustomerRequisitionList;
use Webkul\Checkout\Repositories\CartRepository;
use Webkul\Core\Eloquent\Repository;
use Webkul\Product\Models\Product;
use Webkul\Product\Repositories\ProductRepository;

class CustomerRequisitionRepository extends Repository
{
    /**
     * Create a new repository instance.
     */
    public function __construct(
        protected ProductRepository $productRepository,
        protected CartRepository $cartRepository,
        protected Container $container,
    ) {
        parent::__construct($container);
    }

    /**
     * Specify model class name.
     */
    public function model()
    {
        return CustomerRequisitionList::class;
    }

    /**
     * Save the requisition list items.
     *
     * @param  \Webkul\B2BSuite\Models\CustomerRequisitionList  $requisition
     * @param  array  $data
     * @return \Webkul\B2BSuite\Models\CustomerRequisitionList
     */
    public function saveItems($requisition, $data)
    {
        $items = [];

        if (! empty($data['product_id'])) {
            $items = $this->prepareRequisitionItemsByProduct($data);
        } else {
            if (! empty($data['cart_id'])) {
                $items = $this->prepareRequisitionItemsByCart($data);
            }
        }

        foreach ($items as $item) {
            $requisition->items()->updateOrCreate(
                [
                    'product_id' => $item['product_id'],
                    'variant_id' => $item['variant_id'],
                ],
                Arr::except($item, ['product_id', 'variant_id'])
            );
        }

        return $requisition;
    }

    /**
     * Prepare requisition items from cart.
     */
    public function prepareRequisitionItemsByCart(array $data): array
    {
        $cart = $this->cartRepository->findOneWhere([
            'id' => $data['cart_id'],
            'customer_id' => auth()->guard('customer')->id(),
        ]);

        if (! $cart) {
            return [];
        }

        $items = [];
        foreach ($cart->items as $cartItem) {
            $additional = $cartItem->additional;

            $items[] = [
                'product_id' => $cartItem->product_id,
                'variant_id' => $additional['selected_configurable_option'] ?? null,
                'type' => $cartItem->type,
                'sku' => $cartItem->sku,
                'name' => $cartItem->name,
                'qty' => $cartItem->quantity,
                'price' => core()->convertPrice($cartItem->base_price),
                'base_price' => $cartItem->base_price,
                'total' => core()->convertPrice($cartItem->base_total),
                'base_total' => $cartItem->base_total,
                'additional' => $additional ? json_encode($additional) : null,
            ];
        }

        return $items;
    }

    /**
     * Prepare requisition items from product details, refusing a product outside the
     * customer's company catalog.
     */
    public function prepareRequisitionItemsByProduct(array $data): array
    {
        $product = $this->productRepository->with(['variants'])->findOrFail($data['product_id']);

        if (! $this->productRepository->isVisible($product)) {
            throw new \Exception(trans('b2b::app.shop.checkout.cart.product-not-in-catalog'));
        }

        $items = [];
        switch ($product->type) {
            case 'configurable':
                $child = $this->productRepository->findOrFail($data['selected_configurable_option']);
                $additional = json_encode($product->getTypeInstance()->getAdditionalOptions($data));

                $items[] = $this->buildRequisitionItem($child, [
                    'product_id' => $product->id,
                    'variant_id' => $child->id,
                    'quantity' => $data['quantity'],
                    'additional' => $additional,
                ]);

                break;
            case 'grouped':
                foreach ($product->grouped_products as $grouped) {
                    $items[] = $this->buildRequisitionItem($grouped->associated_product, [
                        'product_id' => $grouped->associated_product->id,
                        'variant_id' => null,
                        'quantity' => $grouped->qty,
                        'additional' => null,
                    ]);
                }
                break;
            default:
                $items[] = $this->buildRequisitionItem($product, [
                    'product_id' => $product->id,
                    'variant_id' => null,
                    'quantity' => $data['quantity'],
                    'additional' => null,
                ]);
                break;
        }

        return $items;
    }

    /**
     * Build requisition item array.
     *
     * @param  Product  $product
     */
    public function buildRequisitionItem($product, array $data): array
    {
        $price = $product->getTypeInstance()->getFinalPrice();
        $convertedPrice = core()->convertPrice($price);

        if (! empty($data['variant_id'])) {
            $product = $product->parent;
        }

        return array_merge([
            'type' => $product->type,
            'sku' => $product->sku,
            'name' => $product->name,
            'qty' => $data['quantity'],
            'price' => $convertedPrice,
            'base_price' => $price,
            'total' => $convertedPrice * $data['quantity'],
            'base_total' => $price * $data['quantity'],
        ], $data);
    }
}
