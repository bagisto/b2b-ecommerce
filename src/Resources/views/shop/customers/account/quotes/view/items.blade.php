<div class="mt-4 flex flex-col rounded-xl border bg-white p-5 max-xl:flex-auto max-sm:p-2">
    <!-- Update Quote Items modal -->
    <x-shop::form
        method="PUT"
        action="{{ route('shop.customers.account.quotes.add_to_cart', $quote->id) }}"
    >
        <div class="flex items-center justify-between gap-x-2.5">
            <p class="mb-4 text-base font-semibold text-gray-800">
                @if ($quote->state == 'quotation')
                    @lang('b2b::app.shop.customers.account.quotes.view.quote-items')
                @else
                    @lang('b2b::app.shop.customers.account.purchase-orders.view.po-items')
                @endif
            </p>

            @php
                /**
                 * The buyer can accept the admin's offer (still in "negotiation") which adds
                 * the negotiated items to the cart and confirms the quote, or simply re-add an
                 * already accepted quote to the cart.
                 */
                $canAcceptOffer = $quote->state == 'quotation'
                    && $quote->status == 'negotiation'
                    && ! empty($isAdminLastQuotation ?? null);

                $showAddToCart = $quote->items->count()
                    && $quote->state == 'quotation'
                    && ($canAcceptOffer || $quote->status == 'accepted');
            @endphp

            @if ($showAddToCart)
                <button
                    type="submit"
                    class="primary-button mb-4 cursor-pointer p-3 text-sm"
                >
                    @if ($canAcceptOffer)
                        @lang('b2b::app.shop.customers.account.quotes.view.btn-accept-add-to-cart')
                    @else
                        @lang('b2b::app.shop.customers.account.quotes.view.btn-add-to-cart')
                    @endif
                </button>
            @endif
        </div>

        @if (! $quote->items->count())
            <div class="text-sm font-medium text-zinc-500">
                @lang('b2b::app.shop.customers.account.quotes.view.no-items')
            </div>
        @else
            @php
                $isNegotiated = in_array($quote->status, ['open', 'negotiation', 'accepted', 'ordered', 'completed']);

                /**
                 * The stored negotiated_price folds the whole-quote discount into each unit.
                 * For display we mirror the Send Quotation modal: show the per-unit price
                 * after the ITEM discount only, and surface the whole-quote discount as its
                 * own line (Sub Total → Discount on Total → Negotiated Total).
                 */
                $itemNegotiatedPrice = function ($item) {
                    $price = (float) $item->price;
                    $value = (float) $item->discount_value;

                    if ($item->discount_type === 'percent') {
                        return $price - ($price * $value / 100);
                    }

                    if ($item->discount_type === 'fixed') {
                        return max(0, $price - $value);
                    }

                    return $price;
                };

                $negotiatedSubTotal = 0;

                foreach ($quote->items as $qi) {
                    $negotiatedSubTotal += $itemNegotiatedPrice($qi) * (int) $qi->negotiated_qty;
                }

                $discountOnTotal = $negotiatedSubTotal - (float) $quote->negotiated_total;
            @endphp

            <!-- For Desktop View -->
            <div class="overflow-x-auto max-md:hidden">
                <table class="w-full border ltr:text-left rtl:text-right text-sm">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-4 py-2">@lang('b2b::app.shop.customers.account.quotes.view.product')</th>
                            <th class="px-4 py-2">@lang('b2b::app.shop.customers.account.quotes.view.name')</th>
                            <th class="px-4 py-2">@lang('b2b::app.shop.customers.account.quotes.view.price')</th>

                            @if ($isNegotiated)
                                <th class="px-4 py-2">@lang('b2b::app.shop.customers.account.quotes.view.discount')</th>
                                <th class="px-4 py-2 ltr:text-right rtl:text-left">@lang('b2b::app.shop.customers.account.quotes.view.negotiated-price')</th>
                            @endif

                            <th class="px-4 py-2">@lang('b2b::app.shop.customers.account.quotes.view.quantity')</th>
                            <th class="px-4 py-2 ltr:text-right rtl:text-left">@lang('b2b::app.shop.customers.account.quotes.view.sub-total')</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($quote->items as $item)
                            @php
                                $item->additional = json_decode($item->additional, true);
                            @endphp

                            <tr class="border-b">
                                <td class="px-4 py-2">
                                    @if ($item->product)
                                        <a
                                            href="{{ route('shop.product_or_category.index', $item->product->url_key) }}"
                                            class="inline-block h-[60px] w-[60px]"
                                        >
                                            <img
                                                src="{{ product_image()->getProductBaseImage($item->product)['small_image_url'] }}"
                                                alt="{{ $item->product->name }}"
                                                class="h-full w-full rounded border border-gray-300 object-cover hover:shadow"
                                                title="{{ $item->product->name }}"
                                            />
                                        </a>
                                    @else
                                        <div class="flex h-[60px] w-[60px] items-center justify-center rounded border border-gray-300 bg-zinc-100 text-xs font-medium text-zinc-500">
                                            @lang('b2b::app.shop.customers.account.quotes.view.product-not-found')
                                        </div>
                                    @endif
                                </td>

                                <td class="grid gap-2 px-4 py-2">
                                    {{ $item->name }}

                                    <span class="text-sm italic text-zinc-500">{{ $item->sku }}</span>

                                    @if (isset($item->additional['attributes']))
                                        <div>
                                            @foreach ($item->additional['attributes'] as $attribute)
                                                @if (
                                                    ! isset($attribute['attribute_type'])
                                                    || $attribute['attribute_type'] !== 'file'
                                                )
                                                    <b>{{ $attribute['attribute_name'] }} : </b>{{ $attribute['option_label'] }}<br>
                                                @else
                                                    {{ $attribute['attribute_name'] }} :

                                                    <a
                                                        href="{{ Storage::url($attribute['option_label']) }}"
                                                        class="text-blue-600 hover:underline"
                                                        download="{{ File::basename($attribute['option_label']) }}"
                                                    >
                                                        {{ File::basename($attribute['option_label']) }}
                                                    </a>

                                                    <br>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                </td>

                                <td class="px-4 py-2">{{ core()->formatPrice($item->price, $quote->currency_code) }}</td>

                                @if ($isNegotiated)
                                    <!-- Per-item Discount -->
                                    <td class="px-4 py-2">
                                        @if (
                                            $item->discount_type
                                            && (float) $item->discount_value > 0
                                        )
                                            <span class="font-medium text-green-700">
                                                {{ $item->discount_type === 'percent'
                                                    ? (float) $item->discount_value . '%'
                                                    : core()->formatPrice($item->discount_value, $quote->currency_code) }}
                                            </span>
                                        @else
                                            <span class="text-zinc-400">—</span>
                                        @endif
                                    </td>

                                    <!-- Negotiated Price (after the item discount) -->
                                    <td class="px-4 py-2 ltr:text-right rtl:text-left font-semibold">
                                        {{ core()->formatPrice($itemNegotiatedPrice($item), $quote->currency_code) }}
                                    </td>
                                @endif

                                <td class="px-4 py-2">{{ $isNegotiated ? $item->negotiated_qty : $item->qty }}</td>

                                <td class="px-4 py-2 ltr:text-right rtl:text-left font-semibold">
                                    @if ($isNegotiated)
                                        {{ core()->formatPrice($itemNegotiatedPrice($item) * $item->negotiated_qty, $quote->currency_code) }}
                                    @else
                                        {{ core()->formatPrice($item->price * $item->qty, $quote->currency_code) }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>

                    <tfoot>
                        @if ($isNegotiated)
                            <!-- Sub Total (after per-item discounts) -->
                            <tr class="border-t">
                                <td colspan="6" class="px-4 py-2 ltr:text-right rtl:text-left font-bold">@lang('b2b::app.shop.customers.account.quotes.view.sub-total')</td>
                                <td colspan="1" class="px-4 py-2 ltr:text-right rtl:text-left font-bold text-zinc-500">{{ core()->formatPrice($negotiatedSubTotal, $quote->currency_code) }}</td>
                            </tr>

                            @if ($discountOnTotal > 0)
                                <!-- Whole-quote discount -->
                                <tr class="border-t">
                                    <td colspan="6" class="px-4 py-2 ltr:text-right rtl:text-left font-bold">
                                        @lang('b2b::app.shop.customers.account.quotes.view.discount-on-total')

                                        @if (
                                            $quote->discount_type
                                            && (float) $quote->discount_value > 0
                                        )
                                            <span class="font-normal text-zinc-500">({{ $quote->discount_type === 'percent'
                                                ? (float) $quote->discount_value . '%'
                                                : core()->formatPrice($quote->discount_value, $quote->currency_code) }})</span>
                                        @endif
                                    </td>
                                    <td colspan="1" class="px-4 py-2 ltr:text-right rtl:text-left font-bold text-green-700">− {{ core()->formatPrice($discountOnTotal, $quote->currency_code) }}</td>
                                </tr>
                            @endif

                            <!-- Negotiated Total (final) -->
                            <tr class="border-t">
                                <td colspan="6" class="px-4 py-2 ltr:text-right rtl:text-left font-bold">@lang('b2b::app.shop.customers.account.quotes.view.negotiated-total')</td>
                                <td colspan="1" class="px-4 py-2 ltr:text-right rtl:text-left text-lg font-bold text-navyBlue">{{ core()->formatPrice($quote->negotiated_total, $quote->currency_code) }}</td>
                            </tr>
                        @else
                            <tr class="border-t">
                                <td colspan="4" class="px-4 py-2 ltr:text-right rtl:text-left font-bold">@lang('b2b::app.shop.customers.account.quotes.view.grand-total')</td>
                                <td colspan="1" class="px-4 py-2 ltr:text-right rtl:text-left text-lg font-bold text-navyBlue">{{ core()->formatPrice($quote->total, $quote->currency_code) }}</td>
                            </tr>
                        @endif
                    </tfoot>
                </table>
            </div>

            <!-- For Mobile View -->
            <div class="md:hidden">
                @foreach ($quote->items as $item)
                    <div class="w-full p-4 border rounded-md transition-all hover:bg-gray-50 [&>*]:border-0 mb-4 last:mb-0">
                        <div class="flex items-center gap-4">
                            @if ($item->product)
                                <a
                                    href="{{ $item->product->url_key }}"
                                    class="inline-block h-[60px] w-[60px] shrink-0"
                                >
                                    <img
                                        src="{{ product_image()->getProductBaseImage($item->product)['small_image_url'] }}"
                                        alt="{{ $item->product->name }}"
                                        class="h-full w-full rounded border border-gray-300 object-cover hover:shadow"
                                        title="{{ $item->product->name }}"
                                    />
                                </a>
                            @else
                                <div class="flex h-[60px] w-[60px] shrink-0 items-center justify-center rounded border border-gray-300 bg-zinc-100 text-xs font-medium text-zinc-500">
                                    @lang('b2b::app.shop.customers.account.quotes.view.product-not-found')
                                </div>
                            @endif

                            <div class="grow">
                                <p class="text-sm font-semibold text-gray-800">{{ $item->name }}</p>

                                <p class="mt-1 text-xs font-medium text-zinc-500">
                                    @lang('b2b::app.shop.customers.account.quotes.view.sku'): {{ $item->sku }}
                                </p>

                                <p class="mt-1 text-xs font-medium text-zinc-500">
                                    @lang('b2b::app.shop.customers.account.quotes.view.price'):
                                    {{ core()->formatPrice($item->price, $quote->currency_code) }}
                                </p>

                                @if (
                                    $isNegotiated
                                    && $item->discount_type
                                    && (float) $item->discount_value > 0
                                )
                                    <p class="mt-1 text-xs font-medium text-green-700">
                                        @lang('b2b::app.shop.customers.account.quotes.view.discount'):
                                        {{ $item->discount_type === 'percent'
                                            ? (float) $item->discount_value . '%'
                                            : core()->formatPrice($item->discount_value, $quote->currency_code) }}
                                    </p>
                                @endif

                                @if ($isNegotiated)
                                    <p class="mt-1 text-xs font-medium text-zinc-500">
                                        @lang('b2b::app.shop.customers.account.quotes.view.negotiated-price'):
                                        {{ core()->formatPrice($itemNegotiatedPrice($item), $quote->currency_code) }}
                                    </p>
                                @endif

                                <p class="mt-1 text-xs font-medium text-zinc-500">
                                    @lang('b2b::app.shop.customers.account.quotes.view.quantity'): {{ $isNegotiated ? $item->negotiated_qty : $item->qty }}
                                </p>

                                <p class="mt-1 text-sm font-semibold text-gray-800">
                                    @lang('b2b::app.shop.customers.account.quotes.view.sub-total'):
                                    {{ $isNegotiated
                                        ? core()->formatPrice($itemNegotiatedPrice($item) * $item->negotiated_qty, $quote->currency_code)
                                        : core()->formatPrice($item->price * $item->qty, $quote->currency_code) }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endforeach

                <div class="flex justify-end border-t pt-4">
                    <div class="mt-4 w-full max-w-xs">
                        @if ($isNegotiated)
                            <div class="flex justify-between text-sm font-bold text-gray-800">
                                <span>@lang('b2b::app.shop.customers.account.quotes.view.sub-total')</span>
                                <span class="text-zinc-500">{{ core()->formatPrice($negotiatedSubTotal, $quote->currency_code) }}</span>
                            </div>

                            @if ($discountOnTotal > 0)
                                <div class="mt-2 flex justify-between text-sm font-bold text-green-700">
                                    <span>@lang('b2b::app.shop.customers.account.quotes.view.discount-on-total')</span>
                                    <span>− {{ core()->formatPrice($discountOnTotal, $quote->currency_code) }}</span>
                                </div>
                            @endif

                            <div class="mt-2 flex justify-between text-sm font-bold text-gray-800">
                                <span>@lang('b2b::app.shop.customers.account.quotes.view.negotiated-total')</span>
                                <span class="text-navyBlue">{{ core()->formatPrice($quote->negotiated_total, $quote->currency_code) }}</span>
                            </div>
                        @else
                            <div class="flex justify-between text-sm font-bold text-gray-800">
                                <span>@lang('b2b::app.shop.customers.account.quotes.view.grand-total')</span>
                                <span class="text-navyBlue">{{ core()->formatPrice($quote->total, $quote->currency_code) }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </x-shop::form>
</div>
