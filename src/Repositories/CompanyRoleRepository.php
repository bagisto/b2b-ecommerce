<?php

namespace Webkul\B2BSuite\Repositories;

use Illuminate\Container\Container;
use Webkul\B2BSuite\Contracts\CompanyRole;
use Webkul\Core\Eloquent\Repository;
use Webkul\Customer\Repositories\CustomerRepository;

class CompanyRoleRepository extends Repository
{
    /**
     * Create a new repository instance.
     */
    public function __construct(
        protected CustomerRepository $customerRepository,
        protected Container $container,
    ) {
        parent::__construct($container);
    }

    /**
     * Specify model class name.
     */
    public function model()
    {
        return CompanyRole::class;
    }

    /**
     * Count the company's customers holding a full-access role, optionally ignoring one role.
     */
    public function countCustomersWithAllAccess(int $companyId, ?int $exceptRoleId = null): int
    {
        return $this->customerRepository->getModel()::query()
            ->join('b2b_company_roles', 'customers.company_role_id', '=', 'b2b_company_roles.id')
            ->where('b2b_company_roles.customer_id', $companyId)
            ->where('b2b_company_roles.permission_type', 'all')
            ->when($exceptRoleId, fn ($query) => $query->where('b2b_company_roles.id', '!=', $exceptRoleId))
            ->count();
    }
}
