<?php

namespace Webkul\B2BSuite\Http\Controllers\Shop;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\View\View;
use Webkul\B2BSuite\DataGrids\Shop\RoleDataGrid;
use Webkul\B2BSuite\Repositories\CompanyRoleRepository;
use Webkul\Customer\Repositories\CustomerRepository;
use Webkul\Shop\Http\Controllers\Controller;

class RoleController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected CompanyRoleRepository $companyRoleRepository,
        protected CustomerRepository $customerRepository,
    ) {}

    /**
     * Display a listing of the resource.
     *
     * @return View
     */
    public function index()
    {
        if (request()->ajax()) {
            return datagrid(RoleDataGrid::class)->process();
        }

        return view('b2b::shop.customers.account.roles.index');
    }

    /**
     * Show the user create form.
     *
     * @return View
     */
    public function create()
    {
        return view('b2b::shop.customers.account.roles.create');
    }

    /**
     * Method to store user's sign up form data to DB.
     *
     * @return Response
     */
    public function store()
    {
        $this->validate(request(), [
            'name' => 'required',
            'permission_type' => 'required|in:all,custom',
            'description' => 'required',
        ]);

        if (request('permission_type') == 'custom') {
            $this->validate(request(), [
                'permissions' => 'required',
            ]);
        }

        Event::dispatch('customer.role.create.before');

        $companyId = $this->currentCompanyId();

        $data = array_merge(request()->only([
            'name',
            'description',
            'permission_type',
            'permissions',
        ]), [
            'customer_id' => $companyId,
        ]);

        $role = $this->companyRoleRepository->create($data);

        Event::dispatch('customer.role.create.after', $role);

        session()->flash('success', trans('b2b::app.shop.customers.account.roles.create-success'));

        return redirect()->route('shop.customers.account.roles.index');
    }

    /**
     * For loading the edit form page.
     *
     * @return View
     */
    public function edit($id)
    {
        $role = $this->companyRoleRepository->findOneWhere([
            'id' => $id,
            'customer_id' => $this->currentCompanyId(),
        ]);

        if (! $role) {
            session()->flash('error', trans('b2b::app.shop.customers.account.users.un-auth-access'));

            return redirect()->route('shop.customers.account.roles.index');
        }

        return view('b2b::shop.customers.account.roles.edit', compact('role'));
    }

    /**
     * Edit function for editing customer profile.
     *
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $this->validate(request(), [
            'name' => 'required',
            'permission_type' => 'required|in:all,custom',
            'description' => 'required',
        ]);

        $companyId = $this->currentCompanyId();

        $role = $this->companyRoleRepository->findOneWhere([
            'id' => $id,
            'customer_id' => $companyId,
        ]);

        if (! $role) {
            session()->flash('error', trans('b2b::app.shop.customers.account.users.un-auth-access'));

            return redirect()->route('shop.customers.account.roles.index');
        }

        /**
         * Keep at least one company user with full access when a role moves from all to custom.
         */
        if (
            request('permission_type') == 'custom'
            && $role->permission_type == 'all'
            && $this->companyRoleRepository->countCustomersWithAllAccess($companyId, $role->id) === 0
        ) {
            session()->flash('error', trans('b2b::app.shop.customers.account.roles.being-used'));

            return redirect()->route('shop.customers.account.roles.index');
        }

        $data = array_merge(request()->only([
            'name',
            'description',
            'permission_type',
        ]), [
            'permissions' => request()->has('permissions') ? request('permissions') : [],
            'customer_id' => $companyId,
        ]);

        Event::dispatch('customer.role.update.before', $id);

        $role = $this->companyRoleRepository->update($data, $id);

        Event::dispatch('customer.role.update.after', $role);

        session()->flash('success', trans('b2b::app.shop.customers.account.roles.update-success'));

        return redirect()->route('shop.customers.account.roles.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $companyId = $this->currentCompanyId();

        $role = $this->companyRoleRepository->findOneWhere([
            'id' => $id,
            'customer_id' => $companyId,
        ]);

        if (! $role) {
            return new JsonResponse([
                'message' => trans('b2b::app.shop.customers.account.users.un-auth-access'),
            ], 401);
        }

        if ($role->customers->count() >= 1) {
            return new JsonResponse([
                'message' => trans('b2b::app.shop.customers.account.roles.being-used'),
            ], 400);
        }

        if ($this->companyRoleRepository->count(['customer_id' => $companyId]) == 1) {
            return new JsonResponse([
                'message' => trans(
                    'admin::app.settings.roles.last-delete-error'
                ),
            ], 400);
        }

        try {
            Event::dispatch('customer.role.delete.before', $id);

            $this->companyRoleRepository->delete($id);

            Event::dispatch('customer.role.delete.after', $id);

            return new JsonResponse(['message' => trans('b2b::app.shop.customers.account.roles.delete-success')]);
        } catch (\Exception $e) {
        }

        return new JsonResponse([
            'message' => trans('b2b::app.shop.customers.account.roles.delete-failed'),
        ], 500);
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
}
