<?php

namespace Webkul\B2BSuite\Http\Controllers\Shop\Customer;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Webkul\B2BSuite\Http\Requests\CompanyRequest;
use Webkul\B2BSuite\Repositories\CompanyAttributeRepository;
use Webkul\B2BSuite\Repositories\CompanyAttributeValueRepository;
use Webkul\B2BSuite\Repositories\CompanyRoleRepository;
use Webkul\Core\Repositories\SubscribersListRepository;
use Webkul\Customer\Repositories\CustomerGroupRepository;
use Webkul\Customer\Repositories\CustomerRepository;
use Webkul\Shop\Http\Controllers\Customer\RegistrationController as BaseRegistrationController;

class RegistrationController extends BaseRegistrationController
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected CustomerRepository $customerRepository,
        protected CustomerGroupRepository $customerGroupRepository,
        protected SubscribersListRepository $subscriptionRepository,
        protected CompanyAttributeRepository $companyAttributeRepository,
        protected CompanyAttributeValueRepository $companyAttributeValueRepository,
        protected CompanyRoleRepository $companyRoleRepository
    ) {}

    /**
     * Opens up the user's sign up form.
     *
     * @return View
     */
    public function index()
    {
        if (! (bool) core()->getConfigData('b2b.general.settings.active')) {
            return view('shop::customers.sign-up');
        }

        return view('b2b::shop.companies.sign-up')
            ->with('attributes', $this->companyAttributeRepository->getSignUpAttributes());
    }

    /**
     * Method to store user's sign up form data to DB.
     *
     * @return Response
     */
    public function save(CompanyRequest $request)
    {
        $customerGroup = core()->getConfigData('customer.settings.create_new_account_options.default_group');

        /**
         * When company approval is required, the company registers in a pending state
         * (status = 0). Bagisto blocks login for inactive customers, so the company
         * cannot log in or transact until an admin approves (activates) the account.
         */
        $requireApproval = (bool) core()->getConfigData('b2b.general.settings.require_company_approval');

        $data = array_merge($request->only([
            'first_name',
            'last_name',
            'email',
            'phone',
            'password_confirmation',
            'is_subscribed',
        ]), [
            'type' => 'company',
            'password' => bcrypt(request()->input('password')),
            'api_token' => Str::random(80),
            'status' => $requireApproval ? 0 : 1,
            'is_verified' => ! core()->getConfigData('customer.settings.email.verification'),
            'customer_group_id' => $this->customerGroupRepository->findOneWhere(['code' => $customerGroup])->id,
            'channel_id' => core()->getCurrentChannel()->id,
            'token' => md5(uniqid(rand(), true)),
            'subscribed_to_news_letter' => (bool) request()->input('is_subscribed'),
        ]);

        Event::dispatch('customer.registration.before');

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

        if (isset($data['is_subscribed'])) {
            $subscription = $this->subscriptionRepository->findOneWhere(['email' => $data['email']]);

            if ($subscription) {
                $this->subscriptionRepository->update([
                    'customer_id' => $customer->id,
                ], $subscription->id);
            } else {
                Event::dispatch('customer.subscription.before');

                $subscription = $this->subscriptionRepository->create([
                    'email' => $data['email'],
                    'customer_id' => $customer->id,
                    'channel_id' => core()->getCurrentChannel()->id,
                    'is_subscribed' => 1,
                    'token' => uniqid(),
                ]);

                Event::dispatch('customer.subscription.after', $subscription);
            }
        }

        Event::dispatch('customer.create.after', $customer);

        Event::dispatch('customer.registration.after', $customer);

        Event::dispatch('b2b.company.registered', $customer);

        if ($requireApproval) {
            session()->flash('success', trans('b2b::app.shop.companies.signup-form.success-pending-approval'));
        } elseif (core()->getConfigData('customer.settings.email.verification')) {
            session()->flash('success', trans('shop::app.customers.signup-form.success-verify'));
        } else {
            session()->flash('success', trans('shop::app.customers.signup-form.success'));
        }

        return redirect()->route('shop.customer.session.index');
    }
}
