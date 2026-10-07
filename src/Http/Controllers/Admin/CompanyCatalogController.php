<?php

namespace Webkul\B2BSuite\Http\Controllers\Admin;

use Illuminate\Support\Collection;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\B2BSuite\DataGrids\Admin\CompanyCatalogDataGrid;
use Webkul\B2BSuite\Helpers\CompanyCatalog as CompanyCatalogHelper;
use Webkul\B2BSuite\Models\Customer;
use Webkul\B2BSuite\Repositories\CompanyCatalogRepository;
use Webkul\B2BSuite\Repositories\CompanyFlatRepository;
use Webkul\Product\Repositories\ProductCustomerGroupPriceRepository;
use Webkul\Product\Repositories\ProductRepository;

class CompanyCatalogController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected CompanyCatalogRepository $companyCatalogRepository,
        protected CompanyFlatRepository $companyFlatRepository,
        protected ProductCustomerGroupPriceRepository $productCustomerGroupPriceRepository,
        protected CompanyCatalogHelper $companyCatalogHelper,
    ) {}

    /**
     * Display a listing of the company catalogs.
     */
    public function index()
    {
        if (request()->ajax()) {
            return app(CompanyCatalogDataGrid::class)->process();
        }

        return view('b2b::admin.company-catalogs.index');
    }

    /**
     * Show the form for creating a new company catalog.
     */
    public function create()
    {
        return to_route('admin.b2b.company_catalogs.index');
    }

    /**
     * Store a newly created company catalog.
     */
    public function store()
    {
        $this->validateSettings();

        $catalog = $this->companyCatalogRepository->create([
            'name' => request('name'),
            'description' => request('description'),
            'status' => request('status') !== null ? (int) (bool) request('status') : 1,
            'created_by' => auth()->guard('admin')->user()->id,
        ]);

        $this->companyCatalogHelper->provisionGroup($catalog);

        session()->flash('success', trans('b2b::app.admin.company-catalogs.create-success'));

        return to_route('admin.b2b.company_catalogs.edit', $catalog->id);
    }

    /**
     * Show the form for editing the specified company catalog.
     */
    public function edit($id)
    {
        $catalog = $this->companyCatalogRepository->findOrFail($id);

        abort_unless(Customer::repCanAccessCatalog((int) $id), 403);

        $products = $this->prepareProducts($catalog);

        $companyIds = $catalog->companies()->pluck('id')->toArray();

        $flats = $this->companyFlatRepository
            ->findWhereIn('customer_id', $companyIds)
            ->keyBy('customer_id');

        $companies = collect($companyIds)->map(function ($id) use ($flats) {
            $flat = $flats->get($id);

            return [
                'id' => $id,
                'name' => ($flat->business_name ?? null) ?: ($flat->email ?? '#'.$id),
                'email' => $flat->email ?? '',
            ];
        })->values();

        /**
         * Viewers who are not the creator (and not super-admin) get a read-only form.
         */
        $canEdit = Customer::repCanEditCatalog((int) $id);

        return view('b2b::admin.company-catalogs.edit', compact('catalog', 'products', 'companies', 'canEdit'));
    }

    /**
     * Update the specified company catalog.
     */
    public function update($id)
    {
        abort_unless(Customer::repCanEditCatalog((int) $id), 403, trans('b2b::app.admin.company-catalogs.not-owner'));

        $this->validateRelations();

        $catalog = $this->companyCatalogRepository->findOrFail($id);

        /**
         * The edit screen owns products, prices and companies; the general settings
         * (name/description/status) are managed separately via the settings modal.
         */
        $this->persistRelations($catalog);

        session()->flash('success', trans('b2b::app.admin.company-catalogs.update-success'));

        return to_route('admin.b2b.company_catalogs.index');
    }

    /**
     * Update only the catalog's general settings (name, description, status) — posted from
     * the settings modal on the listing.
     */
    public function updateSettings($id)
    {
        abort_unless(Customer::repCanEditCatalog((int) $id), 403, trans('b2b::app.admin.company-catalogs.not-owner'));

        $this->validateSettings();

        $this->companyCatalogRepository->update([
            'name' => request('name'),
            'description' => request('description'),
            'status' => request('status') !== null ? (int) (bool) request('status') : 1,
        ], $id);

        session()->flash('success', trans('b2b::app.admin.company-catalogs.update-success'));

        return to_route('admin.b2b.company_catalogs.index');
    }

    /**
     * Remove the specified company catalog.
     */
    public function destroy($id)
    {
        abort_unless(Customer::repCanEditCatalog((int) $id), 403, trans('b2b::app.admin.company-catalogs.not-owner'));

        $catalog = $this->companyCatalogRepository->findOrFail($id);

        try {
            $this->companyCatalogHelper->cleanup($catalog);

            $this->companyCatalogRepository->delete($id);

            return response()->json([
                'message' => trans('b2b::app.admin.company-catalogs.delete-success'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => trans('b2b::app.admin.company-catalogs.delete-failed'),
            ], 500);
        }
    }

    /**
     * Search companies by company (business) name for the picker.
     */
    public function companies()
    {
        $query = trim((string) request('query'));

        $sort = request('sort') === 'email' ? 'b2b_company_flat.email' : 'b2b_company_flat.business_name';

        $order = request('order') === 'desc' ? 'desc' : 'asc';

        $repId = Customer::salesRepScopeId();

        $companies = $this->companyFlatRepository->searchCompaniesForPicker($query, $repId, $sort, $order);

        return response()->json([
            'data' => collect($companies->items())->map(fn ($company) => [
                'id' => $company->id,
                'name' => $company->business_name ?: $company->email,
                'email' => $company->email,
                'current_catalog' => $company->current_catalog,
            ])->values(),
            'meta' => [
                'current_page' => $companies->currentPage(),
                'last_page' => $companies->lastPage(),
                'total' => $companies->total(),
            ],
        ]);
    }

    /**
     * Paginated, searchable product list for the "Assign Products" modal.
     */
    public function products()
    {
        $query = trim((string) request('query'));

        /**
         * Only storefront-listable products are assignable. Restricting to
         * visible_individually keeps variants (and other non-listable children) out of
         * the picker, so a variant can never be assigned on its own — its catalog price
         * is owned solely by its configurable parent's expansion. Children of grouped /
         * bundle products are reached the same way (through their parent), not here.
         */
        $products = app(ProductRepository::class)
            ->searchFromDatabase(array_filter([
                'query' => $query !== '' ? $query : null,
                'type' => request('type') ?: null,
                'status' => 1,
                'visible_individually' => 1,
                'channel_id' => core()->getCurrentChannel()->id,
                'sort' => 'created_at-desc',
                'limit' => 10,
            ]));

        return response()->json([
            'data' => collect($products->items())->map(fn ($product) => [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'type' => $product->type,
                'price' => (float) $product->price,
                'formatted_price' => core()->formatPrice($product->price),
                'image' => $product->images->first()?->url,
            ])->values(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    /**
     * Return a single product shaped as a catalog node (type + priceable leaves with
     * base prices, no overrides) so the assign-products picker can render its rows.
     */
    public function productChildren($id)
    {
        $product = app(ProductRepository::class)->find($id);

        if (! $product) {
            return response()->json(['data' => null], 404);
        }

        return response()->json(['data' => $this->buildProductNode($product, collect())]);
    }

    /**
     * Category tree (with rolled-up product counts) for the save-confirmation dialog,
     * computed from the products currently assigned in the form.
     */
    public function categoryPreview()
    {
        $this->validate(request(), [
            'products' => 'array',
            'products.*' => 'integer',
        ]);

        return response()->json([
            'tree' => $this->companyCatalogHelper->categoryTreeForProducts(request('products', [])),
        ]);
    }

    /**
     * Validate the catalog request.
     */
    protected function validateSettings(): void
    {
        $this->validate(request(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|nullable|boolean',
        ]);
    }

    /**
     * Validate the product / price / company payload posted from the edit screen.
     */
    protected function validateRelations(): void
    {
        $this->validate(request(), [
            'products' => 'array',
            'products.*' => 'integer',
            'prices' => 'array',
            'prices.*.type' => 'nullable|in:fixed,discount',
            'prices.*.value' => 'nullable|numeric|min:0',
            'prices.*.breaks' => 'array',
            'prices.*.breaks.*.qty' => 'nullable|integer|min:2',
            'prices.*.breaks.*.type' => 'nullable|in:fixed,discount',
            'prices.*.breaks.*.value' => 'nullable|numeric|min:0',
            'companies' => 'array',
            'companies.*' => 'integer',
        ]);
    }

    /**
     * Persist products, prices and company assignments for a catalog.
     */
    protected function persistRelations($catalog): void
    {
        $this->companyCatalogHelper->provisionGroup($catalog);

        $catalog = $this->companyCatalogRepository->find($catalog->id);

        $this->companyCatalogHelper->syncProducts($catalog, request('products', []));

        $this->companyCatalogHelper->setPrices($catalog, request('prices', []));

        $this->companyCatalogHelper->assignCompanies($catalog, request('companies', []));

        /**
         * The visible categories are always derived from the assigned products (their
         * categories + ancestors), so the storefront tree stays in sync with what was
         * just confirmed in the save dialog.
         */
        $this->companyCatalogHelper->deriveCategories($catalog);
    }

    /**
     * Build the product tree (each assigned product + its priceable leaves and their
     * catalog prices) for the edit screen.
     */
    protected function prepareProducts($catalog): Collection
    {
        $priceRows = collect();

        if ($catalog->customer_group_id) {
            $priceRows = $this->productCustomerGroupPriceRepository
                ->findWhere(['customer_group_id' => $catalog->customer_group_id])
                ->sortBy('qty')
                ->groupBy('product_id');
        }

        $assigned = $catalog->products()->with('images')->get();

        $assignedIds = $assigned->pluck('id')->all();

        /**
         * A variant whose configurable parent is also assigned must not appear as its own
         * top-level row — it is already shown (and priced) under the parent's expansion.
         * Dropping it here de-duplicates the listing and removes it from the allowlist on
         * the next save, while its price is still submitted via the parent.
         */
        return $assigned
            ->reject(fn ($product) => $product->parent_id && in_array($product->parent_id, $assignedIds))
            ->map(fn ($product) => $this->buildProductNode($product, $priceRows))
            ->values();
    }

    /**
     * Shape a single assigned product into a node: type metadata + the price-bearing
     * leaves (variants / associated / bundle products, or the product itself), each
     * carrying any existing catalog price for this group.
     */
    protected function buildProductNode($product, $priceRows): array
    {
        $leaves = $this->companyCatalogHelper->leafProducts($product)->map(function ($leaf) use ($priceRows) {
            $rows = collect($priceRows->get($leaf->id) ?? []);

            $base = $rows->firstWhere('qty', 1);

            /**
             * qty > 1 rows are volume breaks, edited in the per-product tier modal. The
             * qty = 1 row stays the inline base catalog price.
             */
            $breaks = $rows
                ->filter(fn ($row) => (int) $row->qty > 1)
                ->sortBy('qty')
                ->map(fn ($row) => [
                    'qty' => (int) $row->qty,
                    'type' => $row->value_type,
                    'value' => (float) $row->value,
                ])
                ->values();

            return [
                'id' => $leaf->id,
                'sku' => $leaf->sku,
                'name' => $leaf->name,
                'price' => (float) $leaf->price,
                'formatted_price' => core()->formatPrice($leaf->price),
                'price_type' => $base->value_type ?? 'fixed',
                'price_value' => $base ? (float) $base->value : '',
                'breaks' => $breaks,
            ];
        })->values();

        return [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'type' => $product->type,
            'image' => $product->images->first()?->url,
            'priceable' => $product->type !== 'booking',
            'is_composite' => in_array($product->type, ['configurable', 'grouped', 'bundle']),
            'leaves' => $leaves,
        ];
    }
}
