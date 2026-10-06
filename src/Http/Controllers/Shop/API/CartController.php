<?php

namespace Webkul\B2BSuite\Http\Controllers\Shop\API;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Webkul\B2BSuite\Helpers\CompanyCatalog;
use Webkul\B2BSuite\Repositories\CustomerQuoteItemRepository;
use Webkul\CartRule\Repositories\CartRuleCouponRepository;
use Webkul\Checkout\Facades\Cart;
use Webkul\Customer\Repositories\CustomerRepository;
use Webkul\Product\Exceptions\InsufficientProductInventoryException;
use Webkul\Product\Repositories\ProductRepository;
use Webkul\Shop\Http\Controllers\API\CartController as BaseCartController;
use Webkul\Shop\Http\Resources\CartResource;

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
     * Store items in cart - Override to prevent adding negotiated products when already in cart.
     *
     * No return type is declared (matching the core controller) because the success path
     * returns a JsonResource while the error path returns a JsonResponse (HTTP 400).
     */
    public function store()
    {
        $this->validate(request(), [
            'product_id' => 'required|integer|exists:products,id',
            'is_buy_now' => 'integer|in:0,1',
            'quantity' => 'integer|min:1',
            'qty' => 'array',
            'qty.*' => 'integer|min:0',
            'bundle_option_qty' => 'array',
            'bundle_option_qty.*' => 'integer|min:1',
        ]);

        $product = $this->productRepository->with('parent')->findOrFail(request()->input('product_id'));

        try {
            if (! $product->status) {
                throw new \Exception(trans('shop::app.checkout.cart.inactive-add'));
            }

            if (! $this->isWithinCompanyCatalog($product)) {
                throw new \Exception(trans('b2b::app.shop.checkout.cart.product-not-in-catalog'));
            }

            $cart = Cart::getCart();

            if ($cart) {
                /**
                 * Look up an existing line for this product with a direct query (items())
                 * rather than the cached `items` relation. Touching $cart->items here would
                 * cache the collection on the very cart instance that Cart::addProduct()
                 * later re-reads in collectTotals()/validateItems(); when the cart is empty
                 * that stale cache makes addProduct wrongly drop the whole cart and return
                 * null — which is what made the first add-to-cart fail.
                 */
                $existingCartItem = $cart->items()->where('product_id', $product->id)->first();

                if ($existingCartItem) {
                    $existingAdditional = $existingCartItem->additional ?? [];

                    if (isset($existingAdditional['quote_id'])) {
                        return response()->json([
                            'message' => trans('b2b::app.shop.checkout.cart.cannot-add-product-with-negotiated-price'),
                        ], Response::HTTP_BAD_REQUEST);
                    }
                }
            }

            $response = [];

            if (request()->get('is_buy_now')) {
                Cart::deActivateCart();

                $response['redirect'] = route('shop.checkout.onepage.index');
            }

            $cart = Cart::addProduct($product, request()->all());

            return new JsonResource(array_merge([
                'data' => new CartResource($cart),
                'message' => trans('shop::app.checkout.cart.item-add-to-cart'),
            ], $response));
        } catch (InsufficientProductInventoryException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $exception) {
            return response()->json([
                'redirect_uri' => route('shop.product_or_category.index', $product->url_key),
                'message' => $exception->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Updates the quantity of the items present in the cart.
     */
    public function update(): JsonResource
    {
        $this->validate(request(), [
            'qty' => 'present|array',
            'qty.*' => 'required|integer|min:0',
        ]);

        try {
            $data = request()->input();

            $cart = Cart::getCart();

            foreach ($data['qty'] as $cartItemId => $quantity) {
                $cartItem = $cart->items->where('id', $cartItemId)->first();

                if (! $cartItem) {
                    continue;
                }

                $additional = $cartItem->additional;

                if (isset($additional['quote_id']) && isset($additional['quote_item_id'])) {
                    $negotiation = $this->customerQuoteItemRepository
                        ->where('id', $additional['quote_item_id'])
                        ->where('customer_quote_id', $additional['quote_id'])
                        ->first();

                    if ($negotiation && $quantity != $negotiation->negotiated_qty) {
                        return new JsonResource([
                            'message' => trans('b2b::app.shop.checkout.cart.cannot-change-negotiated-quantity'),
                        ]);
                    }
                }
            }

            Cart::updateItems($data);

            return new JsonResource([
                'data' => new CartResource(Cart::getCart()),
                'message' => trans('shop::app.checkout.cart.index.quantity-update'),
            ]);

        } catch (\Exception $exception) {
            return new JsonResource([
                'message' => $exception->getMessage(),
            ]);
        }
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
