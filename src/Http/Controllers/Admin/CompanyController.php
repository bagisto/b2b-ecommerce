<?php

namespace Webkul\B2BSuite\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Event;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Requests\MassDestroyRequest;
use Webkul\Admin\Http\Requests\MassUpdateRequest;
use Webkul\B2BSuite\DataGrids\Admin\CompanyDataGrid;
use Webkul\B2BSuite\Http\Requests\CompanyRequest;
use Webkul\B2BSuite\Http\Resources\CustomerResource;
use Webkul\B2BSuite\Models\Customer;
use Webkul\B2BSuite\Repositories\CompanyAttributeGroupRepository;
use Webkul\B2BSuite\Repositories\CompanyAttributeValueRepository;
use Webkul\B2BSuite\Repositories\CompanyFlatRepository;
use Webkul\B2BSuite\Repositories\CompanyRoleRepository;
use Webkul\Customer\Repositories\CustomerGroupRepository;
use Webkul\Customer\Repositories\CustomerRepository;
use Webkul\User\Repositories\AdminRepository;

class CompanyController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected CustomerRepository $customerRepository,
        protected CustomerGroupRepository $customerGroupRepository,
        protected CompanyFlatRepository $customerFlatRepository,
        protected CompanyAttributeGroupRepository $companyAttributeGroupRepository,
        protected CompanyAttributeValueRepository $companyAttributeValueRepository,
        protected CompanyRoleRepository $companyRoleRepository,
        protected AdminRepository $adminRepository
    ) {}

    /**
     * Display a listing of the companies.
     */
    public function index()
    {
        if (request()->ajax()) {
            return app(CompanyDataGrid::class)->process();
        }

        return view('b2b::admin.companies.index');
    }

    /**
     * Show the form for creating a new company.
     */
    public function create()
    {
        $attributeGroups = $this->companyAttributeGroupRepository->with([
            'custom_attributes' => function ($query) {
                $query->orderBy('pivot_position', 'asc');
            },
        ])->orderBy('column', 'asc')->orderBy('position', 'asc')->get();

        $admins = $this->adminRepository->all();

        return view('b2b::admin.companies.create', compact('attributeGroups', 'admins'));
    }

    /**
     * Store a newly created company in storage.
     */
    public function store(CompanyRequest $request)
    {
        Event::dispatch('customer.registration.before');

        $password = rand(100000, 10000000);

        $data = array_merge([
            'password' => bcrypt($password),
            'is_verified' => 1,
            /**
             * Companies created from the admin are active by default; the
             * create form may still override this via the status control.
             */
            'status' => $request->input('status') !== null ? (int) (bool) $request->input('status') : 1,
            'channel_id' => core()->getCurrentChannel()->id,
        ], $request->only([
            'first_name',
            'last_name',
            'gender',
            'email',
            'date_of_birth',
            'phone',
            'customer_group_id',
            'channel_id',
            'type',
            'company_role_id',
        ]));

        /**
         * A company defaults to the store's general customer group (not guest), unless the
         * form explicitly picked one.
         */
        if (empty($data['customer_group_id'])) {
            $data['customer_group_id'] = $this->defaultCompanyGroupId();
        }

        /**
         * Always a company customer — never rely on the request, otherwise the record
         * falls back to the default 'user' type and disappears from the company listing.
         */
        $data['type'] = 'company';

        /**
         * Assigned sales rep (empty select = unassigned).
         */
        $data['sales_rep_id'] = $request->input('sales_rep_id') ?: null;

        $customer = $this->customerRepository->create($data);

        $this->companyAttributeValueRepository->saveValues(
            $request->all(),
            $customer,
            $customer->custom_attributes
        );

        $role = $this->companyRoleRepository->create([
            'name' => 'Administrator',
            'description' => 'All permissions',
            'permission_type' => 'all',
            'permissions' => null,
            'customer_id' => $customer->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $customer->update(['company_role_id' => $role->id]);

        $customer->companies()->attach($customer->id);

        Event::dispatch('customer.registration.after', $customer);

        return to_route('admin.b2b.companies.index')
            ->withSuccess(trans('b2b::app.admin.companies.create-success'));
    }

    /**
     * Show the form for editing the specified company.
     */
    public function edit($id)
    {
        $company = $this->customerRepository->findOrFail($id);

        abort_unless(Customer::repCanAccessCompany((int) $id), 403);

        $attributeGroups = $this->companyAttributeGroupRepository->with([
            'custom_attributes' => function ($query) {
                $query->orderBy('pivot_position', 'asc');
            },
        ])->orderBy('column', 'asc')->orderBy('position', 'asc')->get();

        $admins = $this->adminRepository->all();

        return view('b2b::admin.companies.edit', compact('company', 'attributeGroups', 'admins'));
    }

    /**
     * Update the specified company in storage.
     */
    public function update(CompanyRequest $request, $id)
    {
        abort_unless(Customer::repCanAccessCompany((int) $id), 403);

        Event::dispatch('customer.update.before', $id);

        $customer = $this->customerRepository->findOrFail($id);

        $data = array_merge([
            'is_verified' => 1,
            'status' => (int) (bool) $request->input('status'),
            'channel_id' => core()->getCurrentChannel()->id,
        ], $request->only([
            'first_name',
            'last_name',
            'gender',
            'email',
            'date_of_birth',
            'phone',
            'customer_group_id',
            'channel_id',
            'type',
            'company_role_id',
        ]));

        /**
         * Don't touch the customer group when the edit form doesn't include it — otherwise a
         * routine edit (status, sales rep, …) would wipe the company's general / company-catalog
         * group back to guest.
         */
        if (empty($data['customer_group_id'])) {
            unset($data['customer_group_id']);
        }

        /**
         * Keep the record a company on every save (re-asserts type for any record that
         * was previously stored with the wrong type).
         */
        $data['type'] = 'company';

        /**
         * Assigned sales rep (empty select = unassigned).
         */
        $data['sales_rep_id'] = $request->input('sales_rep_id') ?: null;

        $wasPending = ! $customer->status;

        $customer->update($data);

        if ($wasPending && $customer->status) {
            Event::dispatch('b2b.company.approved', $customer);
        }

        $this->companyAttributeValueRepository->saveValues(
            $request->all(),
            $customer,
            $customer->custom_attributes
        );

        $customer = $customer->refresh();

        Event::dispatch('customer.update.after', $customer);

        return to_route('admin.b2b.companies.index')
            ->withSuccess(trans('b2b::app.admin.companies.update-success'));
    }

    /**
     * Approve (activate) or disable a single company.
     *
     * A pending company has `status = 0`, which Bagisto uses to block login,
     * checkout and protected routes. Approving sets `status = 1`.
     */
    public function updateStatus($id): JsonResponse
    {
        $company = $this->customerRepository->findOrFail($id);

        abort_unless(Customer::repCanAccessCompany((int) $id), 403);

        $status = (int) (bool) request()->input('status', 1);

        $company->update(['status' => $status]);

        Event::dispatch('b2b.company.'.($status ? 'approved' : 'disabled'), $company);

        return new JsonResponse([
            'message' => trans('b2b::app.admin.companies.'.($status ? 'approve-success' : 'disable-success')),
        ]);
    }

    /**
     * Remove the specified company from storage.
     */
    public function destroy($id)
    {
        abort_unless(Customer::repCanAccessCompany((int) $id), 403);

        try {
            Event::dispatch('customer.delete.before', $id);

            $this->customerRepository->delete($id);

            Event::dispatch('customer.delete.after', $id);

            return response()->json([
                'message' => trans('b2b::app.admin.companies.delete-success'),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => trans('b2b::app.admin.companies.delete-failed'),
            ], 500);
        }
    }

    /**
     * Mass approve/disable companies.
     */
    public function massUpdateStatus(MassUpdateRequest $massUpdateRequest): JsonResponse
    {
        $status = (int) (bool) $massUpdateRequest->input('value');

        $companies = $this->customerRepository->findWhereIn('id', $massUpdateRequest->input('indices'));

        foreach ($companies as $company) {
            abort_unless(Customer::repCanAccessCompany($company->id), 403);

            $company->update(['status' => $status]);

            Event::dispatch('b2b.company.'.($status ? 'approved' : 'disabled'), $company);
        }

        return new JsonResponse([
            'message' => trans('b2b::app.admin.companies.mass-update-status-success'),
        ]);
    }

    /**
     * Mass-assign a sales representative to the selected companies.
     */
    public function massAssignSalesRep(MassUpdateRequest $massUpdateRequest): JsonResponse
    {
        $repId = (int) $massUpdateRequest->input('value');

        $companies = $this->customerRepository->findWhereIn('id', $massUpdateRequest->input('indices'));

        foreach ($companies as $company) {
            abort_unless(Customer::repCanAccessCompany($company->id), 403);

            $company->update(['sales_rep_id' => $repId ?: null]);
        }

        return new JsonResponse([
            'message' => trans('b2b::app.admin.companies.mass-assign-sales-rep-success'),
        ]);
    }

    /**
     * Remove the specified resources from database.
     */
    public function massDestroy(MassDestroyRequest $massDestroyRequest): JsonResponse
    {
        $customers = $this->customerRepository->findWhereIn('id', $massDestroyRequest->input('indices'));

        try {
            /**
             * Ensure that customers do not have any active orders before performing deletion.
             */
            foreach ($customers as $customer) {
                abort_unless(Customer::repCanAccessCompany($customer->id), 403);

                if ($this->customerRepository->haveActiveOrders($customer)) {
                    throw new \Exception(trans('admin::app.customers.customers.index.datagrid.order-pending'));
                }
            }

            /**
             * After ensuring that they have no active orders delete the corresponding customer.
             */
            foreach ($customers as $customer) {
                Event::dispatch('customer.delete.before', $customer);

                $this->customerRepository->delete($customer->id);

                Event::dispatch('customer.delete.after', $customer);
            }

            return new JsonResponse([
                'message' => trans('b2b::app.admin.companies.mass-delete-success'),
            ]);
        } catch (\Exception $exception) {
            return new JsonResponse([
                'message' => trans('b2b::app.admin.companies.delete-failed'),
            ], 500);
        }
    }

    /**
     * Get companies for autocomplete.
     */
    public function get(): JsonResponse
    {
        $repId = Customer::salesRepScopeId();

        $companies = $this->customerRepository
            ->findWhere(['type' => 'company'])
            ->when($repId, fn ($companies) => $companies->where('sales_rep_id', $repId))
            ->map(function ($company) {
                return [
                    'id' => $company->id,
                    'name' => $company->first_name.' '.$company->last_name,
                    'email' => $company->email,
                ];
            })
            ->values();

        return new JsonResponse($companies);
    }

    /**
     * Result of search companies/customers.
     */
    public function search(): JsonResource
    {
        $data = request()->all();

        $repId = Customer::salesRepScopeId();

        $term = '%'.urldecode($data['query'] ?? '').'%';

        $customers = $this->customerRepository->scopeQuery(function ($query) use ($data, $repId, $term) {
            return $query->whereIn('type', [$data['type'] ?? 'company', 'company'])
                ->when($repId, fn ($q) => $q->where('sales_rep_id', $repId))
                ->where(function ($q) use ($term) {
                    $q->where('email', db_grammar()->caseInsensitiveLike(), $term)
                        ->orWhereRaw(db_grammar()->concatWs(' ', 'first_name', 'last_name').' '.db_grammar()->caseInsensitiveLike().' ?', [$term]);
                })
                ->orderBy('created_at', 'desc');
        })->get();

        return CustomerResource::collection($customers);
    }

    /**
     * The customer group a new company is created with — the store's configured default
     * ("general"), falling back to guest only if that group is missing.
     */
    protected function defaultCompanyGroupId(): int
    {
        $code = core()->getConfigData('customer.settings.create_new_account_options.default_group') ?: 'general';

        return optional($this->customerGroupRepository->findOneWhere(['code' => $code]))->id
            ?? core()->getGuestCustomerGroup()->id;
    }
}
