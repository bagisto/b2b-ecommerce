<?php

namespace Webkul\B2BSuite\Http\Controllers\Shop;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Webkul\B2BSuite\DataGrids\Shop\CustomerQuoteDataGrid;
use Webkul\B2BSuite\Http\Requests\QuoteRequest;
use Webkul\B2BSuite\Models\CustomerQuote;
use Webkul\B2BSuite\Notifications\Notifier;
use Webkul\B2BSuite\Repositories\CustomerQuoteAttachmentRepository;
use Webkul\B2BSuite\Repositories\CustomerQuoteMessageRepository;
use Webkul\B2BSuite\Repositories\CustomerQuoteRepository;
use Webkul\Checkout\Facades\Cart;
use Webkul\Customer\Repositories\CustomerRepository;
use Webkul\Shop\Http\Controllers\Controller;
use Webkul\User\Repositories\AdminRepository;

class QuoteController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected CustomerQuoteRepository $customerQuoteRepository,
        protected CustomerQuoteMessageRepository $customerQuoteMessageRepository,
        protected CustomerQuoteAttachmentRepository $customerQuoteAttachmentRepository,
        protected CustomerRepository $customerRepository,
        protected AdminRepository $adminRepository,
    ) {}

    /**
     * Populate the request for quote page.
     *
     * @return View
     */
    public function index()
    {
        if (request()->ajax()) {
            return datagrid(CustomerQuoteDataGrid::class)->process();
        }

        return view('b2b::shop.customers.account.quotes.index');
    }

    /**
     * Store a new quote request.
     */
    public function store(QuoteRequest $quoteRequest): JsonResponse
    {
        $customer = $this->customerRepository->find(auth()->guard('customer')->user()->id);

        $customerCompany = $customer->companies->first();

        $cart = Cart::getCart();

        if (
            ! $customerCompany
            || ! $cart
        ) {
            return new JsonResponse([
                'message' => trans('b2b::app.shop.customers.account.quotes.view.un-authorized-quote'),
            ], 400);
        }

        if ($cart->base_grand_total < (float) core()->getConfigData('b2b.quotes.settings.minimum_amount')) {
            return new JsonResponse([
                'message' => core()->getConfigData('b2b.quotes.settings.minimum_amount_message')
                    ?: trans('b2b::app.shop.checkout.cart.minimum-amount-required'),
            ], 422);
        }

        Event::dispatch('b2b.quote.create.before');

        $quoteNumber = $this->customerQuoteRepository->generateQuotationNumber(null);

        $data = array_merge([
            'quotation_number' => $quoteNumber['quotation_number'],
            'po_number' => $quoteNumber['po_number'],
            'customer_id' => $customer->id,
            'company_id' => $customerCompany->id,
            'agent_id' => $customerCompany->sales_rep_id ?? $this->adminRepository->first()?->id ?? null,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
        ], $quoteRequest->only([
            'name',
            'description',
            'status',
            'attachments',
        ]));

        $quote = $this->customerQuoteRepository->create($data);

        Event::dispatch('b2b.quote.create.after', $quote);

        /**
         * Notify the sales rep of a new quote request (drafts notify on submit instead).
         */
        if ($quote->status !== CustomerQuote::STATUS_DRAFT) {
            Notifier::quote($quote, 'requested', 'admin');
        }

        session()->flash('success', trans('b2b::app.shop.checkout.cart.request-quote.create-success'));

        return new JsonResponse([
            'data' => $quote,
            'redirect_url' => route('shop.customers.account.quotes.view', $quote->id),
        ]);
    }

    /**
     * For loading the edit form page.
     *
     * @return View
     */
    public function view($id)
    {
        $currentAdmin = $this->customerRepository->find(auth()->guard('customer')->user()->id);

        $quote = $this->customerQuoteRepository
            ->with(['company', 'company.salesRep', 'company.company_flats', 'agent', 'attachments'])
            ->findOneWhere($this->quoteConditions($currentAdmin, $id));

        if (! $quote) {
            session()->flash('error', trans('b2b::app.shop.customers.account.quotes.not-found'));

            return redirect()->route('shop.customers.account.quotes.index');
        }

        $isAdminLastQuotation = $this->customerQuoteMessageRepository->getLastQuotationMessage($quote->id, 'admin');

        return view('b2b::shop.customers.account.quotes.view', compact('currentAdmin', 'quote', 'isAdminLastQuotation'));
    }

    /**
     * Update quote details.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'order_date' => 'date|after_or_equal:today',
            'expected_arrival_date' => 'date|after_or_equal:order_date',
        ]);

        $currentAdmin = $this->customerRepository->find(auth()->guard('customer')->user()->id);

        if (! $this->customerQuoteRepository->findOneWhere($this->quoteConditions($currentAdmin, $id))) {
            session()->flash('error', trans('b2b::app.shop.customers.account.quotes.view.un-authorized-quote'));

            return redirect()->back();
        }

        Event::dispatch('b2b.quote.update.before', $id);

        $data = $request->only([
            'order_date',
            'expected_arrival_date',
        ]);

        $quote = $this->customerQuoteRepository->update($data, $id);

        Event::dispatch('b2b.quote.update.after', $quote);

        session()->flash('success', trans('b2b::app.shop.customers.account.quotes.view.quote-updated'));

        return redirect()->back();
    }

    /**
     * UpdateCart quote details.
     */
    public function updateCart($id)
    {
        $currentAdmin = $this->customerRepository->find(auth()->guard('customer')->user()->id);

        try {
            $quote = $this->customerQuoteRepository->findOneWhere($this->quoteConditions($currentAdmin, $id));

            if (! $quote) {
                session()->flash('error', trans('b2b::app.shop.customers.account.quotes.view.un-authorized-quote'));

                return redirect()->back();
            }

            if (
                $quote->expiration_date
                && now()->startOfDay()->gt($quote->expiration_date)
            ) {
                session()->flash('error', trans('b2b::app.shop.customers.account.quotes.view.quote-expired'));

                return redirect()->back();
            }

            /**
             * "Accept & Add to Cart" is the buyer's final confirmation of the admin's offer,
             * so it is only possible while the latest offer on the quote is the admin's: a
             * buyer counter-offer must be answered by the admin before it can be accepted.
             * An already accepted quote can simply be re-added to the cart.
             */
            $adminOfferIsLatest = (bool) $this->customerQuoteMessageRepository->getLastQuotationMessage($quote->id, 'admin');

            $canAccept = $quote->status === CustomerQuote::STATUS_NEGOTIATION && $adminOfferIsLatest;

            if (
                ! $canAccept
                && $quote->status !== CustomerQuote::STATUS_ACCEPTED
            ) {
                session()->flash('error', trans('b2b::app.shop.customers.account.quotes.view.un-authorized-quote'));

                return redirect()->back();
            }

            if ($canAccept) {
                $quote->update(['status' => CustomerQuote::STATUS_ACCEPTED]);

                /**
                 * Notify the sales rep that the buyer accepted the offer.
                 */
                Notifier::quote($quote, 'accepted', 'admin');
            }

            $this->customerQuoteRepository->updateCart($id);

            return redirect()->route('shop.checkout.cart.index')
                ->with('success', trans('b2b::app.shop.customers.account.quotes.view.quote-item-updated'));

        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());

            return redirect()->back();
        }
    }

    /**
     * Delete a quote (soft_delete).
     */
    public function deleteQuote(Request $request, $id)
    {
        $customerId = auth()->guard('customer')->user()->id;

        try {
            $request->validate([
                'message' => 'required|string|max:1000',
            ]);

            $quote = $this->customerQuoteRepository->findOneWhere([
                'id' => $id,
                'customer_id' => $customerId,
            ]);

            if (! $quote) {
                session()->flash('error', trans('b2b::app.shop.customers.account.quotes.view.un-authorized-quote'));

                return redirect()->back();
            }

            $quote->soft_deleted = 1;

            $quote->save();

            $quote->messages()->create([
                'message' => $request->message,
                'user_type' => 'customer',
                'user_id' => $customerId,
                'created_at' => now(),
            ]);

            return redirect()->route('shop.customers.account.quotes.index')
                ->with('success', trans('b2b::app.shop.customers.account.quotes.view.quote-deleted'));

        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());

            return redirect()->back();
        }
    }

    /**
     * AJAX endpoint for loading messages with pagination and filters.
     *
     * @param  int  $id  Quote ID
     * @return JsonResponse
     */
    public function getMessages($id, Request $request)
    {
        $currentAdmin = $this->customerRepository->find(auth()->guard('customer')->user()->id);

        $quote = $this->customerQuoteRepository->findOneWhere($this->quoteConditions($currentAdmin, $id));

        if (! $quote) {
            return response()->json([
                'message' => trans('b2b::app.shop.customers.account.quotes.view.un-authorized-quote'),
            ], 403);
        }

        $query = $quote->messages()
            ->with('quotations', 'quotations.item');

        if ($request->get('has_quotations') === 'true') {
            $query->has('quotations');
        }

        if ($request->get('user_type')) {
            $query->where('user_type', $request->get('user_type'));
        }

        $messages = $query->orderBy('created_at', 'asc')
            ->paginate(10);

        return response()->json($messages);
    }

    /**
     * Download a quote attachment.
     */
    public function download($id, $attachmentId)
    {
        $currentAdmin = $this->customerRepository->find(auth()->guard('customer')->user()->id);

        $quote = $this->customerQuoteRepository->findOneWhere($this->quoteConditions($currentAdmin, $id));

        $attachment = $quote
            ? $this->customerQuoteAttachmentRepository->findOneWhere([
                'id' => $attachmentId,
                'customer_quote_id' => $quote->id,
            ])
            : null;

        if (! $attachment) {
            session()->flash('error', trans('b2b::app.shop.customers.account.quotes.view.un-authorized-quote'));

            return redirect()->back();
        }

        if (! Storage::disk('public')->exists($attachment->path)) {
            session()->flash('error', trans('b2b::app.shop.customers.account.quotes.view.no-attachments'));

            return redirect()->back();
        }

        $fileName = substr($attachment->path, strrpos($attachment->path, '/') + 1);

        return Storage::disk('public')->download($attachment->path, $fileName);
    }

    /**
     * Submit a quote (Draft to Open).
     */
    public function submitQuote(Request $request, $id)
    {
        $currentAdmin = $this->customerRepository->find(auth()->guard('customer')->user()->id);

        try {
            $quote = $this->customerQuoteRepository->findOneWhere($this->quoteConditions($currentAdmin, $id));

            if (! $quote) {
                session()->flash('error', trans('b2b::app.shop.customers.account.quotes.view.un-authorized-quote'));

                return redirect()->back();
            }

            /**
             * Initial draft submission: the buyer edits the name + description they filled at
             * creation and proposes a per-item discount (the seller decides the dates). Sending
             * moves the draft to "open" for the seller to review.
             */
            if ($quote->status === CustomerQuote::STATUS_DRAFT) {
                $request->validate([
                    'name' => ['required', 'string', 'max:255'],
                    'description' => ['nullable', 'string', 'max:1000'],
                    'items' => ['sometimes', 'array', 'min:1'],
                    'items.*.discount_type' => ['nullable', 'in:percent,fixed'],
                    'items.*.discount_value' => ['nullable', 'numeric', 'min:0'],
                    'items.*.negotiated_qty' => ['nullable', 'integer', 'min:1'],
                ]);

                $note = $request->description ?: trans('b2b::app.shop.customers.account.quotes.view.draft-submitted-note');

                $quote->update([
                    'name' => $request->name,
                    'description' => $request->description,
                ]);

                $message = $quote->messages()->create([
                    'message' => $note,
                    'user_type' => 'customer',
                    'user_id' => $currentAdmin->id,
                    'created_at' => now(),
                ]);

                if ($request->filled('items')) {
                    $data = array_merge([
                        'status' => CustomerQuote::STATUS_OPEN,
                        'message_id' => $message->id,
                        'message' => $note,
                    ], $request->only(['items']));

                    $this->customerQuoteRepository->createOrUpdateMessageQuotation($data, $id);
                } else {
                    $quote->update(['status' => CustomerQuote::STATUS_OPEN]);
                }

                /**
                 * Notify the sales rep that the buyer submitted their draft request.
                 */
                Notifier::quote($quote->refresh(), 'requested', 'admin');

                return redirect()
                    ->route('shop.customers.account.quotes.view', $id)
                    ->with('success', trans('b2b::app.shop.customers.account.quotes.view.quote-submitted'));
            }

            /**
             * Re-submit / counter-offer, only while the quote is still open for negotiation.
             */
            if (! in_array($quote->status, [CustomerQuote::STATUS_OPEN, CustomerQuote::STATUS_NEGOTIATION])) {
                session()->flash('error', trans('b2b::app.shop.customers.account.quotes.view.un-authorized-quote'));

                return redirect()->back();
            }

            $request->validate([
                'items' => ['sometimes', 'array', 'min:1'],
                'items.*.discount_type' => ['nullable', 'in:percent,fixed'],
                'items.*.discount_value' => ['nullable', 'numeric', 'min:0'],
                'items.*.negotiated_qty' => ['nullable', 'integer', 'min:1'],
                'message' => 'required|string|max:1000',
            ]);

            $message = $quote->messages()->create([
                'message' => $request->message,
                'user_type' => 'customer',
                'user_id' => $currentAdmin->id,
                'created_at' => now(),
            ]);

            if ($request->filled('items')) {
                $data = array_merge([
                    'status' => CustomerQuote::STATUS_NEGOTIATION,
                    'message_id' => $message->id,
                ], $request->only(['items', 'message']));

                $this->customerQuoteRepository->createOrUpdateMessageQuotation($data, $id);
            } else {
                $quote->update(['status' => CustomerQuote::STATUS_NEGOTIATION]);
            }

            /**
             * Notify the sales rep that the buyer sent a counter-offer.
             */
            Notifier::quote($quote->refresh(), 'countered', 'admin');

            return redirect()
                ->route('shop.customers.account.quotes.view', $id)
                ->with('success', trans('b2b::app.shop.customers.account.quotes.view.quote-submitted'));

        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());

            return redirect()->back();
        }
    }

    /**
     * Send a message for this quote.
     */
    public function sendMessage(Request $request, $id)
    {
        $currentAdmin = $this->customerRepository->find(auth()->guard('customer')->user()->id);

        try {
            $request->validate([
                'message' => 'required|string|max:1000',
            ]);

            $quote = $this->customerQuoteRepository->findOneWhere($this->quoteConditions($currentAdmin, $id));

            if (! $quote) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => trans('b2b::app.shop.customers.account.quotes.view.un-authorized-quote'),
                    ], 403);
                }

                session()->flash('error', trans('b2b::app.shop.customers.account.quotes.view.un-authorized-quote'));

                return redirect()->back();
            }

            $message = $quote->messages()->create([
                'message' => $request->message,
                'user_type' => 'customer',
                'user_id' => $currentAdmin->id,
                'created_at' => now(),
            ]);

            /**
             * Notify the sales rep that the buyer posted a new message.
             */
            Notifier::quote($quote, 'message', 'admin');

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => trans('b2b::app.shop.customers.account.quotes.view.success-message'),
                    'data' => $message,
                ]);
            }

            return redirect()->route('shop.customers.account.quotes.view', $id)
                ->with('success', trans('b2b::app.shop.customers.account.quotes.view.success-message'));
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => trans('b2b::app.shop.customers.account.quotes.view.error-message'),
                ], 500);
            }

            return redirect()->back()
                ->withErrors(['error' => trans('b2b::app.shop.customers.account.quotes.view.error-message')]);
        }
    }

    /**
     * Reject a quote (Open to Rejected) by customer.
     */
    public function rejectQuote(Request $request, $id)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        $currentAdmin = $this->customerRepository->find(auth()->guard('customer')->user()->id);

        try {
            $quote = $this->customerQuoteRepository->findOneWhere($this->quoteConditions($currentAdmin, $id));

            if (
                ! $quote
                || in_array($quote->status, [
                    CustomerQuote::STATUS_DRAFT,
                    CustomerQuote::STATUS_ORDERED,
                    CustomerQuote::STATUS_COMPLETED,
                    CustomerQuote::STATUS_REJECTED,
                ])
            ) {
                session()->flash('error', trans('b2b::app.shop.customers.account.quotes.view.un-authorized-quote'));

                return redirect()->back();
            }

            $quote->update(['status' => CustomerQuote::STATUS_REJECTED]);

            $quote->messages()->create([
                'message' => $request->message,
                'status' => trans('b2b::app.shop.customers.account.quotes.view.'.$quote->status),
                'user_type' => 'customer',
                'user_id' => $currentAdmin->id,
                'created_at' => now(),
            ]);

            /**
             * Notify the sales rep that the buyer rejected the quote.
             */
            Notifier::quote($quote, 'rejected', 'admin');

            return redirect()
                ->route('shop.customers.account.quotes.view', $id)
                ->with('success', trans('b2b::app.shop.customers.account.quotes.view.quote-rejected'));

        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());

            return redirect()->back();
        }
    }

    /**
     * Conditions that scope a quote to the customer's company, or to the customer when they have none.
     */
    protected function quoteConditions($customer, $id): array
    {
        $conditions = [
            'id' => $id,
            'soft_deleted' => 0,
        ];

        if ($customer->type === 'company') {
            $conditions['company_id'] = $customer->id;

            return $conditions;
        }

        if ($company = $customer->companies()->first()) {
            $conditions['company_id'] = $company->id;

            return $conditions;
        }

        $conditions['customer_id'] = $customer->id;

        return $conditions;
    }
}
