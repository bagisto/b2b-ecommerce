<?php

namespace Webkul\B2BSuite\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Webkul\B2BSuite\Helpers\CreditManager;
use Webkul\Checkout\Facades\Cart;

class EnsurePayByCreditWithinLimit
{
    /**
     * Block placing an order that is paid by company credit but exceeds the company's
     * available credit. The method stays visible at checkout (with an "insufficient credit"
     * notice), but the order itself must never go through over the limit — enforced here,
     * before the order is created.
     */
    public function handle(Request $request, Closure $next)
    {
        if ($request->route()?->getName() !== 'shop.checkout.onepage.orders.store') {
            return $next($request);
        }

        $cart = Cart::getCart();

        if (! $cart || $cart->payment?->method !== 'paybycredit') {
            return $next($request);
        }

        $creditManager = app(CreditManager::class);

        $credit = $creditManager->activeCompanyCreditFor($cart->customer);

        if (
            ! $credit
            || ! $creditManager->canAfford($credit, (float) $cart->base_grand_total)
        ) {
            return response()->json([
                'message' => trans('b2b::app.shop.checkout.pay-by-credit.order-blocked'),
            ], 400);
        }

        return $next($request);
    }
}
