<?php

namespace Webkul\B2BSuite\Http\Controllers\Shop;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Webkul\B2BSuite\DataGrids\Shop\CompanyInvitationDataGrid;
use Webkul\B2BSuite\DataGrids\Shop\UserDataGrid;
use Webkul\B2BSuite\Models\CompanyInvitation;
use Webkul\B2BSuite\Notifications\Notifier;
use Webkul\B2BSuite\Repositories\CompanyInvitationRepository;
use Webkul\B2BSuite\Repositories\CompanyRoleRepository;
use Webkul\B2BSuite\Repositories\CustomerQuoteRepository;
use Webkul\B2BSuite\Repositories\CustomerRequisitionRepository;
use Webkul\Core\Rules\PhoneNumber;
use Webkul\Customer\Repositories\CustomerGroupRepository;
use Webkul\Customer\Repositories\CustomerRepository;
use Webkul\Shop\Http\Controllers\Controller;

class UserController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected CustomerRepository $customerRepository,
        protected CustomerGroupRepository $customerGroupRepository,
        protected CompanyRoleRepository $companyRoleRepository,
        protected CompanyInvitationRepository $companyInvitationRepository,
        protected CustomerQuoteRepository $customerQuoteRepository,
        protected CustomerRequisitionRepository $customerRequisitionRepository,
    ) {}

    /**
     * Display a listing of the resource.
     *
     * @return View
     */
    public function index()
    {
        if (request()->ajax()) {
            return datagrid(UserDataGrid::class)->process();
        }

        return view('b2b::shop.customers.account.users.index');
    }

    /**
     * Show the user create form.
     *
     * @return View
     */
    public function create()
    {
        $customer = auth()->guard('customer')->user();

        $currentRole = $this->companyRoleRepository->find($customer->company_role_id);

        $companyAdminId = $currentRole->customer_id;

        $roles = $this->companyRoleRepository->findWhere([
            'customer_id' => $companyAdminId,
        ]);

        return view('b2b::shop.customers.account.users.create', compact('roles'));
    }

    /**
     * Method to store user's sign up form data to DB.
     *
     * @return Response
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'first_name' => ['required'],
            'last_name' => ['required'],
            'user_email' => 'email|unique:customers,email',
            'gender' => 'required|in:Other,Male,Female',
            'date_of_birth' => 'date|before:today',
            'image' => 'array',
            'image.*' => 'mimes:bmp,jpeg,jpg,png,webp',
            'phone' => ['required', new PhoneNumber, 'unique:customers,phone'],
            'company_role_id' => ['required'],
        ]);

        $companyId = $this->currentCompanyId();

        if (! $this->isCompanyRole($request->input('company_role_id'), $companyId)) {
            session()->flash('error', trans('b2b::app.shop.customers.account.users.existing.invalid-role'));

            return redirect()->back()->withInput();
        }

        $customerGroup = core()->getConfigData('customer.settings.create_new_account_options.default_group');

        $password = rand(100000, 10000000);

        $data = array_merge($request->only([
            'first_name',
            'last_name',
            'gender',
            'date_of_birth',
            'phone',
            'image',
            'company_role_id',
        ]), [
            'email' => $request->input('user_email'),
            'status' => $request->has('status') ? 1 : 0,
            'is_suspended' => $request->has('is_suspended') ? 1 : 0,
            'type' => 'user',
            'password' => bcrypt($password),
            'api_token' => Str::random(80),
            'is_verified' => ! core()->getConfigData('customer.settings.email.verification'),
            'customer_group_id' => $this->customerGroupRepository->findOneWhere(['code' => $customerGroup])->id,
            'channel_id' => core()->getCurrentChannel()->id,
            'token' => md5(uniqid(rand(), true)),
        ]);

        if (
            core()->getCurrentChannel()->theme === 'default'
            && ! isset($data['image'])
        ) {
            $data['image']['image_0'] = '';
        }

        if (empty($data['date_of_birth'])) {
            unset($data['date_of_birth']);
        }

        Event::dispatch('customer.registration.before');

        $customer = $this->customerRepository->create($data);

        $customer->companies()->sync([$companyId]);

        Event::dispatch('customer.create.after', $customer);

        Event::dispatch('customer.registration.after', $customer);

        /**
         * Welcome the newly created company sub-user. The generated password is passed through
         * so the welcome email can carry their login credentials (replacing the separate core
         * NewCustomerNotification credentials mail); it is gated by the "New Sub-User Welcome"
         * toggle inside Notifier::user().
         */
        Notifier::user($customer, $password);

        if (request()->hasFile('image')) {
            $this->customerRepository->uploadImages($data, $customer);
        }

        if (core()->getConfigData('customer.settings.email.verification')) {
            session()->flash('success', trans('shop::app.customers.signup-form.success-verify'));
        } else {
            session()->flash('success', trans('b2b::app.shop.customers.account.users.create-success'));
        }

        return redirect()->route('shop.customers.account.users.index');
    }

    /**
     * For loading the edit form page.
     *
     * @return View
     */
    public function edit($id)
    {
        $companyId = $this->currentCompanyId();

        /**
         * Only the company's own sub-users are editable; the owner is listed for context only.
         */
        if (! $user = $this->findCompanyUser($id, $companyId)) {
            session()->flash('error', trans('b2b::app.shop.customers.account.users.un-auth-access'));

            return redirect()->route('shop.customers.account.users.index');
        }

        $roles = $this->companyRoleRepository->findWhere([
            'customer_id' => $companyId,
        ]);

        return view('b2b::shop.customers.account.users.edit', compact('user', 'roles'));
    }

    /**
     * Edit function for editing customer profile.
     *
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $companyId = $this->currentCompanyId();

        /**
         * Only the company's own sub-users are editable; the owner is listed for context only.
         */
        if (! $this->findCompanyUser($id, $companyId)) {
            session()->flash('error', trans('b2b::app.shop.customers.account.users.un-auth-access'));

            return redirect()->route('shop.customers.account.users.index');
        }

        $this->validate($request, [
            'first_name' => ['required'],
            'last_name' => ['required'],
            'user_email' => 'email|unique:customers,email,'.$id,
            'gender' => 'required|in:Other,Male,Female',
            'date_of_birth' => 'date|before:today',
            'image' => 'array',
            'image.*' => 'mimes:bmp,jpeg,jpg,png,webp',
            'phone' => ['required', new PhoneNumber, 'unique:customers,phone,'.$id],
            'company_role_id' => ['required'],
        ]);

        if (! $this->isCompanyRole($request->input('company_role_id'), $companyId)) {
            session()->flash('error', trans('b2b::app.shop.customers.account.users.existing.invalid-role'));

            return redirect()->back()->withInput();
        }

        $data = array_merge($request->only([
            'first_name',
            'last_name',
            'gender',
            'date_of_birth',
            'phone',
            'image',
            'company_role_id',
            'status',
            'is_suspended',
        ]), [
            'email' => $request->input('user_email'),
            'type' => 'user',
            'status' => $request->has('status') ? 1 : 0,
            'is_suspended' => $request->has('is_suspended') ? 1 : 0,
        ]);

        /**
         * A user cannot change their own role or deactivate / suspend themselves.
         */
        if ((int) $id === (int) auth()->guard('customer')->id()) {
            unset($data['company_role_id'], $data['status'], $data['is_suspended']);
        }

        if (
            core()->getCurrentChannel()->theme === 'default'
            && ! isset($data['image'])
        ) {
            $data['image']['image_0'] = '';
        }

        if (empty($data['date_of_birth'])) {
            unset($data['date_of_birth']);
        }

        Event::dispatch('customer.update.before');

        if ($customer = $this->customerRepository->update($data, $id)) {
            Event::dispatch('customer.update.after', $customer);

            if (request()->hasFile('image')) {
                $this->customerRepository->uploadImages($data, $customer);
            } else {
                if (
                    isset($data['image'])
                    && ! empty($data['image'])
                    && ! isset($data['image']['image_0'])
                ) {
                    Storage::delete((string) $customer->image);

                    $customer->image = null;

                    $customer->save();
                }
            }

            session()->flash('success', trans('b2b::app.shop.customers.account.users.update-success'));

            return redirect()->route('shop.customers.account.users.index');
        }

        session()->flash('error', trans('b2b::app.shop.customers.account.users.edit-fail'));

        return redirect()->route('shop.customers.account.users.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        /**
         * Resolve via the repository to get the B2B customer model (the auth guard returns
         * the core Customer, which lacks the companies() relation).
         */
        $currentAdmin = $this->customerRepository->find(auth()->guard('customer')->user()->id);

        /**
         * A member cannot remove themselves from the company.
         */
        if ((int) $id === (int) $currentAdmin->id) {
            return new JsonResponse([
                'message' => trans('b2b::app.shop.customers.account.users.cannot-remove-self'),
            ], 422);
        }

        $companyId = $currentAdmin->type === 'company'
            ? $currentAdmin->id
            : ($currentAdmin->companies()->first()?->id ?? $currentAdmin->id);

        $user = $this->customerRepository->findOneWhere([
            'id' => $id,
            'type' => 'user',
        ]);

        if (
            ! $user
            || ! $user->companies->contains($companyId)
        ) {
            return new JsonResponse([
                'message' => trans('b2b::app.shop.customers.account.users.un-auth-access'),
            ], 401);
        }

        try {
            /**
             * Removing a user from the company does not delete their account — it detaches
             * them from the company and reverts them to a normal customer (clearing the
             * company role and any inherited company-catalog group), keeping their account
             * and order history intact.
             */
            $user->companies()->detach($companyId);

            /**
             * The member's quotes / purchase orders stay with the company. Snapshot the
             * creator's name + email for display and null the customer link so they no longer
             * belong to (or reach) the now-removed customer.
             */
            $this->customerQuoteRepository
                ->findWhere(['customer_id' => $user->id, 'company_id' => $companyId])
                ->each(fn ($quote) => $this->customerQuoteRepository->update([
                    'customer_name' => $user->name,
                    'customer_email' => $user->email,
                    'customer_id' => null,
                ], $quote->id));

            /**
             * Requisition lists are personal B2B lists the member can no longer reach once
             * detached, and they are not shared with the company — so they would only linger
             * as orphaned rows. Delete the member's lists for this company; their items are
             * removed automatically via the requisition_list_id foreign key cascade.
             */
            $this->customerRequisitionRepository->deleteWhere([
                'customer_id' => $user->id,
                'company_id' => $companyId,
            ]);

            $data = ['company_role_id' => null];

            if ($generalGroupId = $this->generalGroupId()) {
                $data['customer_group_id'] = $generalGroupId;
            }

            $this->customerRepository->update($data, $id);

            /**
             * Let the customer know they have been detached and are now a standard customer.
             */
            Notifier::userRemoved($user);

            return new JsonResponse([
                'message' => trans('b2b::app.shop.customers.account.users.remove-success'),
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'message' => trans('b2b::app.shop.customers.account.users.delete-failed'),
            ], 500);
        }
    }

    /**
     * Show the "invite an existing platform customer" form.
     *
     * @return View
     */
    public function addExisting()
    {
        if (request()->ajax()) {
            return datagrid(CompanyInvitationDataGrid::class)->process();
        }

        $customer = auth()->guard('customer')->user();

        $currentRole = $this->companyRoleRepository->find($customer->company_role_id);

        $roles = $this->companyRoleRepository->findWhere([
            'customer_id' => $currentRole->customer_id,
        ]);

        return view('b2b::shop.customers.account.users.add-existing', compact('roles'));
    }

    /**
     * Invite an existing platform customer to join the company. Instead of adding them
     * directly we create a pending invitation and email them a link to accept — they only
     * become a company user once they accept.
     *
     * @return Response
     */
    public function invite(Request $request)
    {
        $this->validate($request, [
            'user_email' => ['required', 'email'],
            'company_role_id' => ['required'],
        ]);

        /**
         * Resolve via the repository to get the B2B customer model (the auth guard returns
         * the core Customer, which lacks the companies() relation).
         */
        $currentAdmin = $this->customerRepository->find(auth()->guard('customer')->user()->id);

        $companyId = $currentAdmin->type === 'company'
            ? $currentAdmin->id
            : ($currentAdmin->companies()->first()?->id ?? $currentAdmin->id);

        $email = $request->input('user_email');

        $customer = $this->customerRepository->findOneWhere(['email' => $email]);

        if (! $customer) {
            return redirect()->back()->withInput()
                ->with('error', trans('b2b::app.shop.customers.account.users.existing.not-found'));
        }

        /**
         * A company account, or the viewer's own company, cannot be invited as a sub-user.
         */
        if ($customer->type === 'company' || (int) $customer->id === (int) $companyId) {
            return redirect()->back()->withInput()
                ->with('error', trans('b2b::app.shop.customers.account.users.existing.invalid'));
        }

        /**
         * Already belongs to a company (members carry a company role / company link). Note a
         * plain customer is also type "user" here, so membership must not be inferred from type.
         */
        if ($customer->company_role_id || $customer->companies()->exists()) {
            return redirect()->back()->withInput()
                ->with('error', trans('b2b::app.shop.customers.account.users.existing.already-member'));
        }

        /**
         * The chosen role must belong to this company.
         */
        $role = $this->companyRoleRepository->findOneWhere([
            'id' => $request->input('company_role_id'),
            'customer_id' => $companyId,
        ]);

        if (! $role) {
            return redirect()->back()->withInput()
                ->with('error', trans('b2b::app.shop.customers.account.users.existing.invalid-role'));
        }

        /**
         * Reuse an existing pending invitation for this email (re-send) so duplicates can't pile up.
         */
        $existing = $this->companyInvitationRepository->findOneWhere([
            'company_id' => $companyId,
            'email' => $email,
            'status' => CompanyInvitation::STATUS_PENDING,
        ]);

        $data = [
            'company_id' => $companyId,
            'email' => $email,
            'company_role_id' => $request->input('company_role_id'),
            'invited_by' => $currentAdmin->id,
            'token' => Str::random(48),
            'status' => CompanyInvitation::STATUS_PENDING,
            'expires_at' => now()->addDays(7),
        ];

        $invitation = $existing
            ? $this->companyInvitationRepository->update($data, $existing->id)
            : $this->companyInvitationRepository->create($data);

        $company = $this->customerRepository->find($companyId);

        Notifier::invitation(
            $invitation,
            $company->businessName() ?: $company->name,
            $role->name,
            route('shop.customers.account.invitations.show', $invitation->token),
        );

        session()->flash('success', trans('b2b::app.shop.customers.account.users.existing.invite-success', ['email' => $email]));

        return redirect()->route('shop.customers.account.users.index');
    }

    /**
     * Revoke a pending invitation sent by this company (datagrid action — returns JSON).
     */
    public function revokeInvitation(int $id): JsonResponse
    {
        /**
         * Resolve via the repository to get the B2B customer model (the auth guard returns
         * the core Customer, which lacks the companies() relation).
         */
        $currentAdmin = $this->customerRepository->find(auth()->guard('customer')->user()->id);

        $companyId = $currentAdmin->type === 'company'
            ? $currentAdmin->id
            : ($currentAdmin->companies()->first()?->id ?? $currentAdmin->id);

        $invitation = $this->companyInvitationRepository->findOneWhere([
            'id' => $id,
            'company_id' => $companyId,
            'status' => CompanyInvitation::STATUS_PENDING,
        ]);

        if (! $invitation) {
            return new JsonResponse([
                'message' => trans('b2b::app.shop.customers.account.users.un-auth-access'),
            ], 401);
        }

        $this->companyInvitationRepository->update(['status' => CompanyInvitation::STATUS_REVOKED], $invitation->id);

        return new JsonResponse([
            'message' => trans('b2b::app.shop.customers.account.users.existing.revoke-success'),
        ]);
    }

    /**
     * Resolve the company the logged-in customer acts for.
     */
    protected function currentCompanyId(): int
    {
        $customer = $this->customerRepository->find(auth()->guard('customer')->user()->id);

        if ($customer->type === 'company') {
            return $customer->id;
        }

        return $customer->companies()->first()?->id ?? $customer->id;
    }

    /**
     * Find a sub-user that belongs to the given company.
     */
    protected function findCompanyUser($id, int $companyId)
    {
        $user = $this->customerRepository->findOneWhere([
            'id' => $id,
            'type' => 'user',
        ]);

        return $user?->companies->contains($companyId)
            ? $user
            : null;
    }

    /**
     * Whether the role belongs to the given company.
     */
    protected function isCompanyRole($roleId, int $companyId): bool
    {
        return (bool) $this->companyRoleRepository->findOneWhere([
            'id' => $roleId,
            'customer_id' => $companyId,
        ]);
    }

    /**
     * Resolve the store's general customer group id (used when reverting a removed user to a
     * normal customer).
     */
    protected function generalGroupId(): ?int
    {
        return optional($this->customerGroupRepository->findOneWhere([
            'code' => core()->getConfigData('customer.settings.create_new_account_options.default_group') ?: 'general',
        ]))->id;
    }
}
