<?php

namespace Webkul\B2BSuite\Http\Controllers\Shop\API;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Webkul\B2BSuite\Helpers\CompanyCatalog;
use Webkul\B2BSuite\Repositories\CustomerQuoteItemRepository;
use Webkul\CartRule\Repositories\CartRuleCouponRepository;
use Webkul\Checkout\Facades\Cart;
use Webkul\Customer\Repositories\CustomerRepository;
use Webkul\Product\Repositories\ProductRepository;
use Webkul\Shop\Http\Controllers\API\CartController as BaseCartController;

class CartController extends BaseCartController
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(protected CustomerQuoteItemRepository $customerQuoteItemRepository)
    {
        parent::__construct(app(ProductRepository::class), app(CartRuleCouponRepository::class));
    }

    /**
     * Refuse what B2B forbids, then add the product as core does, so each Bagisto release keeps its own buy now flow.
     *
     * No return type is declared (matching the core controller) because the success path
     * returns a JsonResource while the error path returns a JsonResponse (HTTP 400).
     */
    public function store()
    {
        $this->validate(request(), [
            'product_id' => 'required|integer|exists:products,id',
        ]);

        $product = $this->productRepository->with('parent')->findOrFail(request()->input('product_id'));

        return $this->refusal($product) ?? parent::store();
    }

    /**
     * Refuse a change to a quantity a quote negotiated, then update the cart as core does.
     */
    public function update(): JsonResource
    {
        $this->validate(request(), [
            'qty' => 'present|array',
            'qty.*' => 'required|integer|min:0',
        ]);

        if ($this->changesNegotiatedQuantity(request()->input('qty'))) {
            return new JsonResource([
                'message' => trans('b2b::app.shop.checkout.cart.cannot-change-negotiated-quantity'),
            ]);
        }

        return parent::update();
    }

    /**
     * Why an active product may not be added, or null when nothing stands in the way.
     *
     * An inactive product is left to core, which refuses it with its own message.
     */
    protected function refusal($product): ?JsonResponse
    {
        if (! $product->status) {
            return null;
        }

        if (! $this->isWithinCompanyCatalog($product)) {
            return response()->json([
                'redirect_uri' => route('shop.product_or_category.index', $product->url_key),
                'message' => trans('b2b::app.shop.checkout.cart.product-not-in-catalog'),
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($this->hasNegotiatedLine($product)) {
            return response()->json([
                'message' => trans('b2b::app.shop.checkout.cart.cannot-add-product-with-negotiated-price'),
            ], Response::HTTP_BAD_REQUEST);
        }

        return null;
    }

    /**
     * Whether the cart already holds the product at a price a quote negotiated.
     *
     * The line is found with a query rather than the cached `items` relation: caching it here
     * would leave a stale collection on the cart instance that Cart::addProduct() reads again,
     * which on an empty cart makes it drop the whole cart and return null.
     */
    protected function hasNegotiatedLine($product): bool
    {
        $cart = Cart::getCart();

        if (! $cart) {
            return false;
        }

        $line = $cart->items()->where('product_id', $product->id)->first();

        return isset($line?->additional['quote_id']);
    }

    /**
     * Whether the requested quantities change a line whose quantity a quote negotiated.
     */
    protected function changesNegotiatedQuantity(array $quantities): bool
    {
        $cart = Cart::getCart();

        if (! $cart) {
            return false;
        }

        foreach ($quantities as $cartItemId => $quantity) {
            $additional = $cart->items->firstWhere('id', $cartItemId)?->additional ?? [];

            if (! isset($additional['quote_id'], $additional['quote_item_id'])) {
                continue;
            }

            $negotiation = $this->customerQuoteItemRepository->findOneWhere([
                'id' => $additional['quote_item_id'],
                'customer_quote_id' => $additional['quote_id'],
            ]);

            if (
                $negotiation
                && $quantity != $negotiation->negotiated_qty
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ensure the product is within the customer's company catalog (allowlist).
     * Returns true when no catalog restriction applies.
     */
    protected function isWithinCompanyCatalog($product): bool
    {
        if (! (bool) core()->getConfigData('b2b.general.settings.active')) {
            return true;
        }

        $groupId = app(CustomerRepository::class)->getCurrentGroup()?->id;

        $catalog = app(CompanyCatalog::class)->resolveByGroupId($groupId);

        if (! $catalog) {
            return true;
        }

        $productId = $product->parent_id ?? $product->id;

        return $catalog->products()
            ->where('products.id', $productId)
            ->exists();
    }
}
