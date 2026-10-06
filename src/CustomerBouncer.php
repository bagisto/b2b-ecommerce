<?php

namespace Webkul\B2BSuite;

use Webkul\B2BSuite\Repositories\CompanyRoleRepository;
use Webkul\Customer\Repositories\CustomerRepository;

class CustomerBouncer
{
    /**
     * Full-access permission type — grants every company feature.
     */
    public const PERMISSION_TYPE_ALL = 'all';

    /**
     * Custom permission type allows selecting specific permissions from the list of company features.
     */
    public const PERMISSION_TYPE_CUSTOM = 'custom';

    /**
     * Company (B2B) feature ACL keys. Everything else — profile, address, orders,
     * downloadables, reviews, wishlist, GDPR — is a personal account feature that a company
     * role must never be able to revoke: a member always controls their own account.
     */
    public const B2B_KEYS = [
        'account.company_profile',
        'account.company_credit',
        'account.quotes',
        'account.purchase_orders',
        'account.requisitions',
        'account.quick_orders',
        'account.users',
        'account.roles',
    ];

    /**
     * Create a new bouncer instance.
     *
     * @return void
     */
    public function __construct(
        protected CompanyRoleRepository $roleRepo,
        protected CustomerRepository $customerRepository,
    ) {}

    /**
     * Check if the current logged-in customer has permission for a given key.
     */
    public function hasPermission(string $permission): bool
    {
        $customer = auth()->guard('customer')->user();

        if (! $customer) {
            return false;
        }

        /**
         * Personal account features are always allowed — only company features are gated by
         * the company role.
         */
        if (! $this->isCompanyPermission($permission)) {
            return true;
        }

        /**
         * A plain customer (no company role) cannot reach company features.
         */
        if (! $customer->company_role_id && $customer->type !== 'company') {
            return false;
        }

        $role = $customer->company_role_id
            ? $this->roleRepo->find($customer->company_role_id)
            : $this->roleRepo->findWhere(['customer_id' => $customer->id])->first();

        /**
         * A disabled company's users lose every company feature.
         */
        if (
            ! $role
            || ! $this->customerRepository->find($role->customer_id)?->status
        ) {
            return false;
        }

        if ($role->permission_type === self::PERMISSION_TYPE_ALL) {
            return true;
        }

        if ($role->permission_type === self::PERMISSION_TYPE_CUSTOM) {
            $permissions = $role->permissions;

            if (is_string($permissions)) {
                $permissions = json_decode($permissions, true) ?: [];
            }

            if (! is_array($permissions)) {
                $permissions = ['account'];
            }

            $permissions = array_map(fn ($perm) => 'account.'.$perm, $permissions);
            array_unshift($permissions, 'account');

            return in_array($permission, $permissions);
        }

        return false;
    }

    /**
     * Abort unauthorized actions.
     */
    public static function allow(string $permission): void
    {
        $instance = app(self::class);

        if (! $instance->hasPermission($permission)) {
            abort(401, 'Unauthorized action.');
        }
    }

    /**
     * Whether the given ACL key belongs to a company (B2B) feature.
     */
    protected function isCompanyPermission(string $permission): bool
    {
        foreach (self::B2B_KEYS as $key) {
            if (str_contains($permission, $key)) {
                return true;
            }
        }

        return false;
    }
}
