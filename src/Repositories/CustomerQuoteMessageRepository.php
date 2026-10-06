<?php

namespace Webkul\B2BSuite\Repositories;

use Webkul\B2BSuite\Contracts\CustomerQuoteMessage;
use Webkul\Core\Eloquent\Repository;

class CustomerQuoteMessageRepository extends Repository
{
    /**
     * Specify model class name.
     */
    public function model()
    {
        return CustomerQuoteMessage::class;
    }

    /**
     * Get the quote's latest quotation message, only when it was sent by the given user type.
     *
     * @param  int  $quoteId
     * @param  string  $userType
     * @return \Webkul\B2BSuite\Models\CustomerQuoteMessage|null
     */
    public function getLastQuotationMessage($quoteId, $userType)
    {
        $message = $this->model->where('quote_id', $quoteId)
            ->whereHas('quotations')
            ->with('quotations')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return $message?->user_type === $userType
            ? $message
            : null;
    }
}
