<?php

namespace Webkul\B2BSuite\Repositories;

use Carbon\Carbon;
use Illuminate\Container\Container;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Webkul\B2BSuite\Contracts\CustomerQuote;
use Webkul\Checkout\Facades\Cart;
use Webkul\Checkout\Repositories\CartRepository;
use Webkul\Core\Eloquent\Repository;
use Webkul\Product\Repositories\ProductRepository;

class CustomerQuoteRepository extends Repository
{
    /**
     * Create a new repository instance.
     */
    public function __construct(
        protected CartRepository $cartRepository,
        protected ProductRepository $productRepository,
        protected CustomerQuoteItemRepository $customerQuoteItemRepository,
        protected CustomerQuoteQuotationRepository $customerQuoteQuotationRepository,
        protected CustomerQuoteAttachmentRepository $customerQuoteAttachmentRepository,
        protected CustomerQuoteMessageRepository $customerQuoteMessageRepository,
        protected Container $container,
    ) {
        parent::__construct($container);
    }

    /**
     * Specify model class name.
     */
    public function model()
    {
        return CustomerQuote::class;
    }

    /**
     * Create a quote.
     */
    public function create(array $data): CustomerQuote
    {
        $cart = $data['cart'] ?? Cart::getCart();

        $data['expiration_date'] = $data['expiration_date'] ?? $this->calculateExpirationDate();

        $data = array_merge($data, $this->prepareCartData($cart));

        $quote = DB::transaction(function () use ($data, $cart) {
            $quote = $this->model->create($data);

            $quoteItemDetails = $this->prepareCartData($cart, $quote);

            if (! empty($quoteItemDetails['items'])) {
                foreach ($quoteItemDetails['items'] as $item) {
                    $this->customerQuoteItemRepository->create($item);
                }
            }

            $this->customerQuoteMessageRepository->create([
                'quote_id' => $quote->id,
                'user_id' => $data['customer_id'],
                'user_type' => isset($data['customer_id']) ? 'customer' : 'admin',
                'message' => $data['message'] ?? trans('b2b::app.shop.checkout.cart.request-quote.default-message', [
                    'status' => $quote->status,
                ]),
            ]);

            $this->upload($data, $quote, 'attachments');

            return $quote;
        });

        Cart::removeCart($cart);

        return $quote;
    }

    /**
     * Upload.
     *
     * @param  array  $data
     * @param  CustomerQuote  $quote
     */
    public function upload($data, $quote, string $uploadFileType): void
    {
        /**
         * Previous model ids for filtering.
         */
        $previousIds = $quote->attachments()->pluck('id');

        if (! empty($data[$uploadFileType])) {
            foreach ($data[$uploadFileType] as $indexOrModelId => $file) {
                if ($file instanceof UploadedFile) {
                    if (Str::contains($file->getMimeType(), 'image')) {
                        $path = 'b2b-quote/'.$quote->id.'/'.Str::random(40).'.webp';

                        Storage::put($path, image_manager()->fromUpload($file)->toWebp()->toBytes());
                    } else {
                        $path = $file->store('b2b-quote/'.$quote->id);
                    }

                    $this->customerQuoteAttachmentRepository->create([
                        'customer_quote_id' => $quote->id,
                        'name' => $file->getClientOriginalName(),
                        'path' => $path,
                        'mime_type' => $file->getClientMimeType(),
                        'size' => $file->getSize(),
                    ]);
                } else {
                    if (is_numeric($index = $previousIds->search($indexOrModelId))) {
                        $previousIds->forget($index);
                    }
                }
            }
        }

        foreach ($previousIds as $indexOrModelId) {
            if (! $model = $this->customerQuoteAttachmentRepository->find($indexOrModelId)) {
                continue;
            }

            Storage::delete($model->path);

            $this->customerQuoteAttachmentRepository->delete($indexOrModelId);
        }
    }

    /**
     * Update cart.
     *
     * @param  array  $data
     * @return CustomerQuote $quote
     */
    public function updateCart($id)
    {
        $quote = $this->find($id);

        $cart = Cart::getCart();

        if (! $cart) {
            $cart = $this->cartRepository->create([
                'company_id' => $quote?->customer?->company_id ?? null,
                'is_guest' => 1,
                'channel_id' => core()->getCurrentChannel()->id,
                'global_currency_code' => $baseCurrencyCode = core()->getBaseCurrencyCode(),
                'base_currency_code' => $baseCurrencyCode,
                'channel_currency_code' => core()->getChannelBaseCurrencyCode(),
                'cart_currency_code' => core()->getCurrentCurrencyCode(),
            ]);
        }

        foreach ($quote->items as $quoteItem) {
            $product = $this->productRepository->find($quoteItem->product_id);

            $existingCartItem = $cart->items->where('product_id', $product->id)->first();

            if ($existingCartItem) {
                Cart::removeItem($existingCartItem->id);
            }

            $additional = $quoteItem->additional ? json_decode($quoteItem->additional, true) : [];

            $data = array_merge($additional, [
                'product_id' => $product->id,
                'quantity' => $quoteItem->negotiated_qty,
                'quote_id' => $quote->id,
                'quote_item_id' => $quoteItem->id,
            ]);

            $cart = Cart::addProduct($product, $data);

            $cartItems = $cart->items->where('product_id', $product->id);

            foreach ($cartItems as $cartItem) {
                $additional = $cartItem->additional;

                if (
                    (isset($additional['quote_id']) && $additional['quote_id'] == $quote->id)
                    && (isset($additional['quote_item_id']) && $additional['quote_item_id'] == $quoteItem->id)
                ) {
                    $cartItem->custom_price = $quoteItem->base_negotiated_price;
                    $cartItem->save();
                }
            }

            Cart::collectTotals();
        }
    }

    /**
     * Generate quotation number and purchase order number.
     *
     * @param  int|null  $quoteId
     */
    public function generateQuotationNumber($quoteId): array
    {
        $maxId = $quoteId ?? $this->model->max('id') + 1;

        $quotePrefix = core()->getConfigData('b2b.quotes.settings.quote_prefix');
        $poPrefix = core()->getConfigData('b2b.quotes.settings.po_prefix');
        $defaultPadding = core()->getConfigData('b2b.quotes.settings.default_padding');

        $defaultNumber = Str::padLeft($maxId, $defaultPadding ?? 10, '0');

        if (! empty($quotePrefix)) {
            $quotationNumber = $quotePrefix.$defaultNumber;
        }

        if (! empty($poPrefix)) {
            $poNumber = $poPrefix.$defaultNumber;
        }

        return [
            'quotation_number' => $quotationNumber ?? $defaultNumber,
            'po_number' => $poNumber ?? $defaultNumber,
        ];
    }

    /**
     * Create/Update message quotation.
     *
     * @param  int  $id
     * @return CustomerQuote $quote
     */
    public function createOrUpdateMessageQuotation(array $data, $id)
    {
        $quote = $this->find($id);

        /**
         * Line items the admin removed during negotiation are deleted (their quotation
         * snapshots cascade). Guarded by a non-empty remaining set so a quote can never be
         * emptied.
         */
        if (! empty($data['items'])) {
            foreach (array_filter(array_map('intval', $data['removed_items'] ?? [])) as $removedId) {
                $removed = $this->customerQuoteItemRepository->find($removedId);

                if ($removed && (int) $removed->customer_quote_id === (int) $quote->id) {
                    $this->customerQuoteItemRepository->delete($removedId);
                }
            }

            $quote->refresh();
        }

        /**
         * First pass: apply each line's own discount to get its per-unit price and the
         * running subtotal (before any whole-quote discount).
         */
        $lines = [];
        $subtotal = 0;

        foreach ($data['items'] ?? [] as $itemId => $itemData) {
            if (! $item = $this->customerQuoteItemRepository->findOneWhere([
                'id' => $itemId,
                'customer_quote_id' => $quote->id,
            ])) {
                continue;
            }

            $type = in_array($itemData['discount_type'] ?? null, ['percent', 'fixed'])
                ? $itemData['discount_type']
                : null;

            $value = isset($itemData['discount_value']) && is_numeric($itemData['discount_value'])
                ? (float) $itemData['discount_value']
                : 0;

            $qty = max(1, (int) ($itemData['negotiated_qty'] ?? $item->qty));

            $price = $this->applyDiscount((float) $item->price, $type, $value);

            $lines[] = [
                'item' => $item,
                'qty' => $qty,
                'price' => $price,
                'discount_type' => $value > 0 ? $type : null,
                'discount_value' => $value > 0 ? $value : null,
            ];

            $subtotal += $price * $qty;
        }

        /**
         * Second pass: a whole-quote discount is distributed proportionally across the
         * lines, so the negotiated total stays internally consistent AND carries into the
         * order (which is rebuilt from each line's negotiated price — see moveToCart()).
         */
        $totalType = in_array($data['total_discount_type'] ?? null, ['percent', 'fixed'])
            ? $data['total_discount_type']
            : null;

        $totalValue = isset($data['total_discount_value']) && is_numeric($data['total_discount_value'])
            ? (float) $data['total_discount_value']
            : 0;

        $factor = $subtotal > 0
            ? $this->applyDiscount($subtotal, $totalType, $totalValue) / $subtotal
            : 1;

        $itemNegotiatedTotal = 0;

        $itemBaseNegotiatedTotal = 0;

        foreach ($lines as $line) {
            $item = $line['item'];

            $negotiatedPrice = round($line['price'] * $factor, 4);
            $negotiatedTotal = $negotiatedPrice * $line['qty'];

            $baseNegotiatedPrice = $this->toBaseAmount($negotiatedPrice, $item);
            $baseNegotiatedTotal = $baseNegotiatedPrice * $line['qty'];

            $itemNegotiatedTotal += $negotiatedTotal;

            $itemBaseNegotiatedTotal += $baseNegotiatedTotal;

            $this->customerQuoteItemRepository->update([
                'negotiated_qty' => $line['qty'],
                'negotiated_price' => $negotiatedPrice,
                'base_negotiated_price' => $baseNegotiatedPrice,
                'negotiated_total' => $negotiatedTotal,
                'base_negotiated_total' => $baseNegotiatedTotal,
                'discount_type' => $line['discount_type'],
                'discount_value' => $line['discount_value'],
                'note' => $data['message'] ?? trans('b2b::app.shop.customers.account.quotes.view.item-updated', [
                    'name' => $quote->customer->name,
                ]),
                'status' => $data['status'] ?? $quote->status,
            ], $item->id);

            $this->customerQuoteQuotationRepository->updateOrCreate([
                'message_id' => $data['message_id'] ?? null,
                'quote_id' => $quote->id,
                'quote_item_id' => $item->id,
            ], [
                'sku' => $item->sku,
                'name' => $item->name,
                'qty' => $line['qty'],
                'discount_type' => $line['discount_type'],
                'discount_value' => $line['discount_value'],
                'price' => $negotiatedPrice,
                'base_price' => $baseNegotiatedPrice,
                'total' => $negotiatedTotal,
                'base_total' => $baseNegotiatedTotal,
            ]);
        }

        $quote->update([
            'status' => $data['status'] ?? $quote->status,
            'negotiated_total' => $itemNegotiatedTotal,
            'base_negotiated_total' => $itemBaseNegotiatedTotal,
            'discount_type' => $totalValue > 0 ? $totalType : null,
            'discount_value' => $totalValue > 0 ? $totalValue : null,
        ]);

        return $quote;
    }

    /**
     * Convert a quote item amount to the base currency at the rate the item was quoted at.
     */
    protected function toBaseAmount(float $amount, $item): float
    {
        if ((float) $item->price <= 0) {
            return $amount;
        }

        return round($amount * (float) $item->base_price / (float) $item->price, 4);
    }

    /**
     * Subtract a percentage or fixed-amount discount from an amount (never below zero).
     */
    protected function applyDiscount(float $amount, ?string $type, float $value): float
    {
        if ($value <= 0 || $amount <= 0) {
            return $amount;
        }

        if ($type === 'percent') {
            return round(max(0, $amount - ($amount * min($value, 100) / 100)), 4);
        }

        return round(max(0, $amount - $value), 4);
    }

    /**
     * Prepare cart data for storage.
     */
    private function prepareCartData($cart, $quote = null): array
    {
        if ($quote) {
            return [
                'items' => $cart->items->map(function ($item) use ($quote) {
                    return [
                        'customer_quote_id' => $quote->id,
                        'product_id' => $item->product_id,
                        'type' => $item->product->type,
                        'sku' => $item->product->sku,
                        'name' => $item->product->name,
                        'qty' => $item->quantity,
                        'negotiated_qty' => $item->quantity,
                        'price' => $item->price,
                        'base_price' => $item->base_price,
                        'total' => $item->total,
                        'base_total' => $item->base_total,
                        'negotiated_price' => $item->price,
                        'base_negotiated_price' => $item->base_price,
                        'negotiated_total' => $item->total,
                        'base_negotiated_total' => $item->base_total,
                        'note' => '',
                        'status' => $quote->status,
                        'additional' => json_encode($item->additional),
                    ];
                })->toArray(),
            ];
        }

        return [
            'cart_id' => $cart->id,
            'total' => $cart->grand_total,
            'base_total' => $cart->base_grand_total,
            'negotiated_total' => $cart->grand_total,
            'base_negotiated_total' => $cart->base_grand_total,
        ];
    }

    /**
     * Calculate expiration date based on configuration.
     */
    private function calculateExpirationDate(): Carbon
    {
        $period = (int) (core()->getConfigData('b2b.quotes.settings.default_expiration_period') ?: 30);

        $unit = core()->getConfigData('b2b.quotes.settings.expiration_period_unit') ?: 'days';

        return match ($unit) {
            'weeks' => now()->addWeeks($period),
            'months' => now()->addMonths($period),
            default => now()->addDays($period),
        };
    }
}
