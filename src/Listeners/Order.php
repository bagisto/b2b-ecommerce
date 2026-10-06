<?php

namespace Webkul\B2BSuite\Listeners;

use Webkul\B2BSuite\Helpers\CreditManager;
use Webkul\B2BSuite\Models\CustomerQuote;
use Webkul\B2BSuite\Notifications\Notifier;
use Webkul\B2BSuite\Repositories\CustomerQuoteRepository;
use Webkul\Sales\Contracts\Invoice as InvoiceContract;
use Webkul\Sales\Contracts\Order as OrderContract;
use Webkul\Sales\Contracts\Refund as RefundContract;
use Webkul\Sales\Contracts\Shipment as ShipmentContract;
use Webkul\Sales\Models\Order as SalesOrder;
use Webkul\Sales\Repositories\OrderRepository;
use Webkul\Shop\Listeners\Base;

class Order extends Base
{
    /**
     * Create a new listener instance.
     *
     * @return void
     */
    public function __construct(
        protected CustomerQuoteRepository $customerQuoteRepository,
        protected OrderRepository $orderRepository,
        protected CreditManager $creditManager,
    ) {}

    /**
     * After order is created.
     *
     * @return void
     */
    public function afterCreated(OrderContract $order)
    {
        if (! (bool) core()->getConfigData('b2b.general.settings.active')) {
            return;
        }

        $quoteStatus = CustomerQuote::STATUS_ORDERED;

        $isQuote = false;

        foreach ($order->items as $orderItem) {
            if (
                isset($orderItem->additional['quote_id'])
                && isset($orderItem->additional['quote_item_id'])
            ) {
                $quote = $this->updateQuoteStatus($orderItem->additional, $quoteStatus);

                if (isset($quote->id)) {
                    $isQuote = $quote;
                }
            }
        }

        if ($isQuote) {
            $isQuote->state = CustomerQuote::STATE_PURCHASE_ORDER;
            $isQuote->status = $quoteStatus;
            $isQuote->order_id = $order->id;
            $isQuote->save();

            /**
             * The quote has been converted into a purchase order: confirm to the buyer and
             * alert the sales rep.
             */
            Notifier::quote($isQuote, 'ordered', 'buyer');
            Notifier::quote($isQuote, 'po_placed', 'admin');
        }

        $this->chargeCompanyCredit($order);
    }

    /**
     * Free the uninvoiced part of a "Pay By Credit" order's charge when the order is cancelled.
     */
    public function afterCancelled(OrderContract $order): void
    {
        $amount = (float) $order->base_grand_total - (float) $order->base_grand_total_invoiced;

        if (
            $amount <= 0
            || ! $credit = $this->payByCreditAccount($order)
        ) {
            return;
        }

        $this->creditManager->revert($credit, $amount, $order->id, [
            'type' => 'system',
        ]);
    }

    /**
     * Give the refunded amount of a "Pay By Credit" order back to the company's credit.
     */
    public function afterRefunded(RefundContract $refund): void
    {
        if (
            (float) $refund->base_grand_total <= 0
            || ! $credit = $this->payByCreditAccount($refund->order)
        ) {
            return;
        }

        $this->creditManager->refund($credit, (float) $refund->base_grand_total, $refund->order_id, [
            'type' => 'system',
        ]);
    }

    /**
     * After invoice/shipment is created.
     *
     * @return void
     */
    public function afterUpdated(InvoiceContract|ShipmentContract $invoiceOrShipment)
    {
        $order = $this->orderRepository->find($invoiceOrShipment->order->id);

        if (
            ! (bool) core()->getConfigData('b2b.general.settings.active')
            || $order->status != SalesOrder::STATUS_COMPLETED
        ) {
            return;
        }

        $quoteStatus = CustomerQuote::STATUS_COMPLETED;

        $quote = $this->customerQuoteRepository->findOneByField('order_id', $order->id);

        if (! $quote || $quote->status == $quoteStatus) {
            return;
        }

        $isQuote = false;

        foreach ($order->items as $orderItem) {
            if (
                isset($orderItem->additional['quote_id'])
                && isset($orderItem->additional['quote_item_id'])
            ) {
                $quote = $this->updateQuoteStatus($orderItem->additional, $quoteStatus);

                if (isset($quote->id)) {
                    $isQuote = $quote;
                }
            }
        }

        if ($isQuote) {
            $isQuote->state = CustomerQuote::STATE_PURCHASE_ORDER;
            $isQuote->status = $quoteStatus;
            $isQuote->save();
        }
    }

    /**
     * Charge a "Pay By Credit" order to the buyer's company credit (one purchase ledger
     * entry per order). No-op for any other payment method.
     */
    public function chargeCompanyCredit(OrderContract $order): void
    {
        if ($order->payment?->method !== 'paybycredit') {
            return;
        }

        if (! $this->creditManager->isActive()) {
            return;
        }

        if (! $credit = $this->creditManager->companyCreditFor($order->customer)) {
            return;
        }

        $this->creditManager->purchase($credit, (float) $order->base_grand_total, $order->id, [
            'type' => 'customer',
            'id' => $order->customer_id,
        ]);
    }

    /**
     * Update quote status.
     */
    public function updateQuoteStatus($quoteData, $quoteStatus): ?CustomerQuote
    {
        $quote = $this->customerQuoteRepository->find($quoteData['quote_id']);

        if (! $quote) {
            return null;
        }

        $quoteItem = $quote->items->where('id', $quoteData['quote_item_id'])->first();

        if (! $quoteItem) {
            return null;
        }

        $quoteItem->status = $quoteStatus;
        $quoteItem->save();

        return $quote;
    }

    /**
     * The company credit account a "Pay By Credit" order was charged to, when credit is enabled.
     */
    protected function payByCreditAccount(OrderContract $order)
    {
        if (
            $order->payment?->method !== 'paybycredit'
            || ! $this->creditManager->isActive()
        ) {
            return null;
        }

        return $this->creditManager->companyCreditFor($order->customer);
    }
}
