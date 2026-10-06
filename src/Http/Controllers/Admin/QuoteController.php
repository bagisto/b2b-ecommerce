<?php

namespace Webkul\B2BSuite\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Requests\MassDestroyRequest;
use Webkul\Admin\Http\Requests\MassUpdateRequest;
use Webkul\Admin\Http\Resources\CartResource;
use Webkul\B2BSuite\DataGrids\Admin\CustomerQuoteDataGrid;
use Webkul\B2BSuite\Http\Requests\QuoteRequest;
use Webkul\B2BSuite\Models\Customer;
use Webkul\B2BSuite\Models\CustomerQuote;
use Webkul\B2BSuite\Notifications\Notifier;
use Webkul\B2BSuite\Repositories\CustomerQuoteMessageRepository;
use Webkul\B2BSuite\Repositories\CustomerQuoteRepository;
use Webkul\Checkout\Repositories\CartRepository;
use Webkul\Customer\Repositories\CustomerGroupRepository;
use Webkul\Customer\Repositories\CustomerRepository;

class QuoteController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected CustomerQuoteRepository $customerQuoteRepository,
        protected CustomerQuoteMessageRepository $customerQuoteMessageRepository,
        protected CartRepository $cartRepository,
        protected CustomerRepository $customerRepository,
        protected CustomerGroupRepository $customerGroupRepository
    ) {}

    /**
     * Display a listing of the resource.
     *
     * @return View
     */
    public function index()
    {
        if (request()->ajax()) {
            return datagrid(CustomerQuoteDataGrid::class)->process();
        }

        $channels = core()->getAllChannels();

        $groups = $this->customerGroupRepository->findWhere([['code', '<>', 'guest']]);

        return view('b2b::admin.quotes.index', compact('channels', 'groups'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return View
     */
    public function create(int $cartId)
    {
        $cart = $this->cartRepository->find($cartId);

        if (! $cart) {
            return redirect()->route('admin.b2b.quotes.index');
        }

        $cart = new CartResource($cart);

        $statusLabels = collect(CustomerQuote::statusLabel())->only([
            CustomerQuote::STATUS_OPEN,
            CustomerQuote::STATUS_NEGOTIATION,
            CustomerQuote::STATUS_ACCEPTED,
        ])->toArray();

        $company = $cart->customer->companies->first();

        if ($cart?->company_id) {
            $company = $this->customerRepository->findOneWhere([
                'id' => $cart->company_id,
                'type' => 'company',
            ]);
        }

        $user = auth()->guard('admin')->user();

        return view('b2b::admin.quotes.create', compact('cart', 'statusLabels', 'company', 'user'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(QuoteRequest $quoteRequest)
    {
        Event::dispatch('b2b.quote.create.before');

        $cart = $this->cartRepository->find((int) $quoteRequest->cart_id);

        $company = $this->customerRepository->findOneWhere([
            'id' => $cart->company_id,
            'type' => 'company',
        ]);

        $customer = $cart->customer;

        $company = $company ?: $customer->companies->first();

        $quoteNumber = $this->customerQuoteRepository->generateQuotationNumber(null);

        $data = array_merge([
            'quotation_number' => $quoteNumber['quotation_number'],
            'po_number' => $quoteNumber['po_number'],
            'customer_id' => $customer->id,
            'company_id' => $company?->id,
            'agent_id' => $company?->sales_rep_id ?? auth()->guard('admin')->user()->id,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'cart' => $cart,
        ], $quoteRequest->only([
            'name',
            'description',
            'status',
            'attachments',
            'order_date',
            'expected_arrival_date',
            'expiration_date',
            'created_at',
        ]));

        $quote = $this->customerQuoteRepository->create($data);

        Event::dispatch('b2b.quote.create.after', $quote);

        session()->flash('success', __('b2b::app.admin.quotes.index.create-success'));

        return redirect()->route('admin.b2b.quotes.index');
    }

    /**
     * Show the form for viewing the specified resource.
     *
     * @return View
     */
    public function view(int $id)
    {
        $quote = $this->customerQuoteRepository
            ->with(['company', 'company.salesRep', 'company.company_flats'])
            ->findOrFail($id);

        $this->authorizeQuoteAccess($quote);

        /**
         * The admin may accept the buyer's offer only when the buyer made the latest quotation.
         */
        $lastQuotation = $quote->messages()->whereHas('quotations')->orderByDesc('id')->first();

        $adminIsLastQuotation = $lastQuotation && $lastQuotation->user_type === 'admin';

        return view('b2b::admin.quotes.view', compact('quote', 'adminIsLastQuotation'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @return Response
     */
    public function update(QuoteRequest $request, int $id)
    {
        $this->authorizeQuoteAccess($this->customerQuoteRepository->findOrFail($id));

        Event::dispatch('b2b.quote.update.before', $id);

        $data = $request->only([
            'name',
            'description',
            'company_id',
            'customer_id',
            'agent_id',
            'base_total',
            'total',
            'base_negotiated_total',
            'negotiated_total',
            'expiration_date',
            'status',
        ]);

        $quote = $this->customerQuoteRepository->update($data, $id);

        Event::dispatch('b2b.quote.update.after', $quote);

        session()->flash('success', __('b2b::app.admin.quotes.index.update-success'));

        return redirect()->route('admin.b2b.quotes.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $quote = $this->customerQuoteRepository->findOrFail($id);

        $this->authorizeQuoteAccess($quote);

        try {
            Event::dispatch('b2b.quote.delete.before', $id);

            $this->customerQuoteRepository->delete($id);

            Event::dispatch('b2b.quote.delete.after', $id);

            return new JsonResponse([
                'message' => trans('b2b::app.admin.quotes.index.delete-success'),
            ]);
        } catch (\Exception $e) {
        }

        return new JsonResponse([
            'message' => trans('b2b::app.admin.quotes.index.delete-failed'),
        ], 500);
    }

    /**
     * Mass update quote.
     *
     * @return JsonResponse
     */
    public function massUpdate(MassUpdateRequest $massUpdateRequest)
    {
        try {
            $quoteIds = $massUpdateRequest->input('indices');

            foreach ($quoteIds as $quoteId) {
                Event::dispatch('b2b.quotes.mass-update.before', $quoteId);

                $quote = $this->customerQuoteRepository->find($quoteId);

                $this->authorizeQuoteAccess($quote);

                $quote->status = $massUpdateRequest->input('value');

                $quote->save();

                Event::dispatch('b2b.quotes.mass-update.after', $quote);
            }

            return new JsonResponse([
                'message' => trans('b2b::app.admin.quotes.index.update-success'),
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resources from database.
     */
    public function massDestroy(MassDestroyRequest $massDestroyRequest): JsonResponse
    {
        $indices = $massDestroyRequest->input('indices');

        try {
            foreach ($indices as $index) {
                $this->authorizeQuoteAccess($this->customerQuoteRepository->findOrFail($index));

                Event::dispatch('b2b.quote.delete.before', $index);

                $this->customerQuoteRepository->delete($index);

                Event::dispatch('b2b.quote.delete.after', $index);
            }

            return new JsonResponse([
                'message' => trans('b2b::app.admin.quotes.index.mass-delete-success'),
            ]);
        } catch (\Exception $exception) {
            return new JsonResponse([
                'message' => trans('b2b::app.admin.quotes.index.delete-failed'),
            ], 500);
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
        $quote = $this->customerQuoteRepository->findOrFail($id);

        $this->authorizeQuoteAccess($quote);

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
     * Send a message for this quote.
     */
    public function sendMessage(Request $request, $id)
    {
        try {
            $request->validate([
                'message' => 'required|string|max:1000',
            ]);

            $quote = $this->customerQuoteRepository->findOrFail($id);

            $this->authorizeQuoteAccess($quote);

            if ($quote->status === CustomerQuote::STATUS_OPEN) {
                $quote->status = CustomerQuote::STATUS_NEGOTIATION;

                $quote->save();
            }

            $message = $quote->messages()->create([
                'message' => $request->message,
                'user_type' => 'admin',
                'user_id' => auth()->guard('admin')->user()->id,
                'created_at' => now(),
            ]);

            /**
             * Notify the buyer that the seller sent a message in the quote thread.
             */
            Notifier::quote($quote, 'message', 'buyer');

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => trans('b2b::app.admin.quotes.view.success-message'),
                    'data' => $message,
                ]);
            }

            return redirect()->route('admin.b2b.quotes.view', $id)
                ->with('success', trans('b2b::app.admin.quotes.view.success-message'));
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => trans('b2b::app.admin.quotes.view.error-message'),
                ], 500);
            }

            return redirect()->back()
                ->withErrors(['error' => trans('b2b::app.admin.quotes.view.error-message')]);
        }
    }

    /**
     * Submit a quote (Draft to Open).
     */
    public function submitQuote(Request $request, $id)
    {
        try {
            $request->validate([
                'items' => ['required', 'array', 'min:1'],
                'items.*.discount_type' => ['nullable', 'in:percent,fixed'],
                'items.*.discount_value' => ['nullable', 'numeric', 'min:0'],
                'items.*.negotiated_qty' => ['required', 'integer', 'min:1'],
                'total_discount_type' => ['nullable', 'in:percent,fixed'],
                'total_discount_value' => ['nullable', 'numeric', 'min:0'],
                'removed_items' => ['nullable', 'array'],
                'removed_items.*' => ['integer'],
                'message' => 'required|string|max:1000',
            ]);

            $quote = $this->customerQuoteRepository->findOrFail($id);

            $this->authorizeQuoteAccess($quote);

            $message = $quote->messages()->create([
                'message' => $request->message,
                'user_type' => 'admin',
                'user_id' => auth()->guard('admin')->user()->id,
                'created_at' => now(),
            ]);

            /**
             * Sending a quotation is a counter-offer, not a final sale: the quote moves to
             * "negotiation" and the thread stays open. The buyer reviews the offer and either
             * keeps negotiating, rejects it, or accepts it — accepting adds the negotiated
             * items straight to their cart.
             */
            $data = array_merge([
                'status' => CustomerQuote::STATUS_NEGOTIATION,
                'message_id' => $message->id,
            ], $request->only([
                'items',
                'message',
                'total_discount_type',
                'total_discount_value',
                'removed_items',
            ]));

            $this->customerQuoteRepository->createOrUpdateMessageQuotation($data, $id);

            /**
             * Notify the buyer that the seller sent/revised a quotation.
             */
            Notifier::quote($quote->refresh(), 'sent', 'buyer');

            return redirect()->route('admin.b2b.quotes.view', $id)
                ->with('success', trans('b2b::app.admin.quotes.view.quote-submitted'));

        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());

            return redirect()->back();
        }
    }

    /**
     * Reject a quote (Open to Rejected) by admin.
     */
    public function rejectQuote(Request $request, $id)
    {
        try {
            $request->validate([
                'message' => 'required|string|max:1000',
            ]);

            $quote = $this->customerQuoteRepository->findOrFail($id);

            $this->authorizeQuoteAccess($quote);

            if (in_array($quote->status, [CustomerQuote::STATUS_COMPLETED, CustomerQuote::STATUS_REJECTED])) {
                session()->flash('error', trans('b2b::app.admin.quotes.view.un-authorized-quote'));

                return redirect()->back();
            }

            $quote->status = CustomerQuote::STATUS_REJECTED;

            $quote->save();

            $quote->messages()->create([
                'message' => $request->message,
                'status' => trans('b2b::app.admin.quotes.view.'.$quote->status),
                'user_type' => 'admin',
                'user_id' => auth()->guard('admin')->user()->id,
                'created_at' => now(),
            ]);

            /**
             * Notify the buyer that the seller rejected the quote.
             */
            Notifier::quote($quote, 'rejected', 'buyer');

            return redirect()->route('admin.b2b.quotes.view', $id)
                ->with('success', trans('b2b::app.admin.quotes.view.quote-rejected'));

        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());

            return redirect()->back();
        }
    }

    /**
     * Accept the buyer's quotation (the buyer's last offer becomes the agreed quote).
     */
    public function acceptQuote(Request $request, $id)
    {
        try {
            $request->validate([
                'message' => 'required|string|max:1000',
            ]);

            $quote = $this->customerQuoteRepository->findOrFail($id);

            $this->authorizeQuoteAccess($quote);

            if (in_array($quote->status, [
                CustomerQuote::STATUS_COMPLETED,
                CustomerQuote::STATUS_ORDERED,
                CustomerQuote::STATUS_REJECTED,
            ])) {
                session()->flash('error', trans('b2b::app.admin.quotes.view.un-authorized-quote'));

                return redirect()->back();
            }

            $quote->status = CustomerQuote::STATUS_ACCEPTED;

            $quote->save();

            $quote->messages()->create([
                'message' => $request->message,
                'status' => trans('b2b::app.admin.quotes.view.'.$quote->status),
                'user_type' => 'admin',
                'user_id' => auth()->guard('admin')->user()->id,
                'created_at' => now(),
            ]);

            /**
             * Notify the buyer that the seller accepted the quote.
             */
            Notifier::quote($quote, 'accepted', 'buyer');

            return redirect()->route('admin.b2b.quotes.view', $id)
                ->with('success', trans('b2b::app.admin.quotes.view.quote-accepted'));

        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());

            return redirect()->back();
        }
    }

    /**
     * Abort with 403 when the current admin (a sales rep) may not access this quote's
     * company. Guards direct-URL access that datagrid scoping does not cover.
     */
    protected function authorizeQuoteAccess($quote): void
    {
        abort_unless(Customer::repCanAccessCompany($quote?->company_id), 403);
    }
}
