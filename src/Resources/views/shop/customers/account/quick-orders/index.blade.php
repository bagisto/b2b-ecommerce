<x-shop::layouts.account>
    <!-- Page Title -->
    <x-slot:title>
        @lang('b2b::app.shop.customers.account.quick-orders.title')
    </x-slot>

    <!-- Breadcrumbs -->
    @if ((core()->getConfigData('general.general.breadcrumbs.shop')))
        @section('breadcrumbs')
            <x-shop::breadcrumbs name="quick-orders" />
        @endSection
    @endif

    <div class="max-md:hidden">
        <x-shop::layouts.account.navigation />
    </div>

    <div class="flex-auto max-md:px-4">
        {!! view_render_event('bagisto.shop.customers.account.quick_orders.before') !!}

        <!-- Page Header -->
        <div class="flex items-center justify-between gap-4 max-sm:flex-wrap">
            <div class="flex items-center gap-3">
                <!-- Back Button (mobile) -->
                <a
                    class="grid md:hidden"
                    href="{{ route('shop.customers.account.profile.index') }}"
                >
                    <span class="icon-arrow-left rtl:icon-arrow-right text-2xl"></span>
                </a>

                <h2 class="text-2xl font-medium max-md:text-xl max-sm:text-base">
                    @lang('b2b::app.shop.customers.account.quick-orders.title')
                </h2>
            </div>

            <!-- Back Button (desktop) -->
            <a
                href="{{ route('shop.customers.account.profile.index') }}"
                class="transparent-button px-5 py-2.5 hover:bg-gray-100 max-md:hidden"
            >
                @lang('b2b::app.shop.customers.account.quick-orders.btn-back')
            </a>
        </div>

        <!-- Quick Order Component -->
        <div class="mt-6 max-md:mt-4">
            <v-quick-order ref="vQuickOrder"></v-quick-order>
        </div>

        {!! view_render_event('bagisto.shop.customers.account.quick_orders.after') !!}
    </div>

    @pushOnce('styles')
        <style>
            /**
             * Selected-products filter box. Padding / icon offset / width live here because the
             * arbitrary + ltr/rtl utility variants are purged out of the B2B shop bundle.
             */
            .b2b-qo-filter {
                position: relative;
                width: 18rem;
            }

            @media (max-width: 767px) {
                .b2b-qo-filter {
                    width: 100%;
                }
            }

            .b2b-qo-filter > input {
                width: 100%;
                padding: 0.5rem 0.75rem 0.5rem 2.25rem;
            }

            .b2b-qo-filter > input:focus {
                border-color: #2563eb;
            }

            .b2b-qo-filter > .icon-search {
                position: absolute;
                top: 50%;
                inset-inline-start: 0.65rem;
                transform: translateY(-50%);
            }
        </style>
    @endPushOnce

    @pushOnce('scripts')
        @php
            $maxFileSize = (int) (core()->getConfigData('b2b.quotes.settings.maximum_file_size') ?: 2);
        @endphp

        <!-- Vue Template -->
        <script type="text/x-template" id="v-quick-order-template">
            <div class="grid gap-6 max-md:gap-4">
                <!-- Add Products By Search -->
                <div class="rounded-xl border border-zinc-200 bg-white p-6 max-md:p-4">
                    <div class="mb-5 flex items-center gap-3">
                        <span class="icon-search grid h-10 w-10 shrink-0 place-items-center rounded-full bg-blue-50 text-xl text-blue-600"></span>

                        <h3 class="text-base font-semibold text-gray-900">
                            @lang('b2b::app.shop.customers.account.quick-orders.search-by-sku-name')
                        </h3>
                    </div>

                    <!-- Search Input -->
                    <div class="relative w-full">
                        <input
                            type="text"
                            class="w-full rounded-lg border border-zinc-300 px-5 py-3 text-base text-gray-700 outline-none transition-all focus:border-navyBlue ltr:pr-12 rtl:pl-12"
                            placeholder="@lang('b2b::app.shop.customers.account.quick-orders.search-by-sku-name')"
                            v-model="searchTerm"
                        />

                        <template v-if="isSearching">
                            <img
                                class="absolute top-3.5 h-5 w-5 animate-spin ltr:right-4 rtl:left-4"
                                src="{{ bagisto_asset('images/spinner.svg') }}"
                            />
                        </template>

                        <template v-else>
                            <span class="icon-search absolute top-2.5 text-2xl text-gray-400 ltr:right-3 rtl:left-3"></span>
                        </template>
                    </div>

                    <!-- Search Results -->
                    <div v-if="searchedProducts.length" class="mt-4">
                        <div
                            ref="resultsBox"
                            class="grid gap-3 overflow-y-auto ltr:pr-1 rtl:pl-1"
                            style="max-height: 26rem;"
                            @scroll="onResultsScroll"
                        >
                            <div
                                v-for="product in searchedProducts"
                                :key="product.id"
                                class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 p-3 transition-all hover:border-zinc-300 hover:shadow-sm"
                            >
                                <!-- Information -->
                                <div class="flex items-center gap-3">
                                    <!-- Image -->
                                    <div class="h-16 w-16 shrink-0 overflow-hidden rounded-lg border border-zinc-100">
                                        <template v-if="! product.images.length">
                                            <img
                                                class="h-16 w-16 object-cover"
                                                src="{{ bagisto_asset('images/small-product-placeholder.webp') }}"
                                            >
                                        </template>

                                        <template v-else>
                                            <img
                                                class="h-16 w-16 object-cover"
                                                :src="product.images[0].url"
                                            >
                                        </template>
                                    </div>

                                    <!-- Details -->
                                    <div class="grid gap-0.5">
                                        <p class="break-all text-sm font-medium text-gray-900">
                                            @{{ product.name }}
                                        </p>

                                        <p class="text-xs text-gray-500">
                                            @lang('b2b::app.shop.customers.account.quick-orders.sku') @{{ product.sku }}
                                        </p>

                                        <p class="text-sm font-semibold text-gray-900">
                                            @{{ product.formatted_price }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Action -->
                                <button
                                    type="button"
                                    class="flex shrink-0 items-center gap-1 rounded-lg border border-navyBlue px-4 py-2 text-sm font-medium text-navyBlue transition-all hover:bg-navyBlue hover:text-white max-sm:px-3"
                                    @click="addProduct(product)"
                                >
                                    <span class="icon-plus text-lg"></span>

                                    <span class="max-sm:hidden">
                                        @lang('b2b::app.shop.customers.account.quick-orders.btn-add')
                                    </span>
                                </button>
                            </div>

                            <!-- Auto load-more spinner (triggered on scroll) -->
                            <div
                                v-if="loadingMore"
                                class="flex items-center justify-center gap-2 py-2 text-sm text-gray-500"
                            >
                                <img
                                    class="h-4 w-4 animate-spin"
                                    src="{{ bagisto_asset('images/spinner.svg') }}"
                                >

                                @lang('b2b::app.shop.customers.account.quick-orders.loading')
                            </div>
                        </div>
                    </div>

                    <!-- No results -->
                    <div
                        v-else-if="searchTerm.trim().length > 1 && ! isSearching"
                        class="mt-4 rounded-lg border border-dashed border-zinc-200 p-6 text-center text-sm text-gray-500"
                    >
                        @lang('b2b::app.shop.customers.account.quick-orders.no-products-found')
                    </div>
                </div>

                <!-- Bulk Add: Multiple SKUs & Upload File -->
                <div class="grid grid-cols-2 gap-6 max-md:grid-cols-1 max-md:gap-4">
                    <!-- Multiple SKUs -->
                    <div class="flex flex-col rounded-xl border border-zinc-200 bg-white p-6 max-md:p-4">
                        <div class="mb-4 flex items-center gap-3">
                            <span class="icon-listing grid h-10 w-10 shrink-0 place-items-center rounded-full bg-blue-50 text-xl text-blue-600"></span>

                            <h3 class="text-base font-semibold text-gray-900">
                                @lang('b2b::app.shop.customers.account.quick-orders.multiple-skus')
                            </h3>
                        </div>

                        <textarea
                            rows="4"
                            v-model="multipleSKUs"
                            class="mb-3 w-full rounded-lg border border-zinc-300 px-4 py-3 text-sm text-gray-700 outline-none transition-all focus:border-navyBlue"
                            placeholder="@lang('b2b::app.shop.customers.account.quick-orders.enter-multiple-skus')"
                        ></textarea>

                        <button
                            type="button"
                            class="secondary-button mt-auto self-end rounded-lg px-6 py-2.5 text-sm"
                            :disabled="! multipleSKUs.trim().length"
                            :class="{ 'cursor-not-allowed': ! multipleSKUs.trim().length }"
                            :style="! multipleSKUs.trim().length ? 'opacity: 0.5;' : ''"
                            @click="addMultipleSKUs()"
                        >
                            @lang('b2b::app.shop.customers.account.quick-orders.btn-add-to-list')
                        </button>
                    </div>

                    <!-- Upload A File -->
                    <div class="flex flex-col rounded-xl border border-zinc-200 bg-white p-6 max-md:p-4">
                        <div class="mb-4 flex items-center gap-3">
                            <span class="icon-download grid h-10 w-10 shrink-0 place-items-center rounded-full bg-blue-50 text-xl text-blue-600"></span>

                            <h3 class="text-base font-semibold text-gray-900">
                                @lang('b2b::app.shop.customers.account.quick-orders.upload-file')
                            </h3>
                        </div>

                        <input
                            type="file"
                            accept=".csv"
                            class="w-full cursor-pointer rounded-lg border border-zinc-300 px-4 py-2.5 text-sm text-gray-600 outline-none transition-all focus:border-navyBlue"
                            @change="handleFileUpload"
                        />

                        <div class="mt-2 flex flex-wrap items-center gap-1 text-sm text-gray-500">
                            <span>@lang('b2b::app.shop.customers.account.quick-orders.add-from-file')</span>

                            <a
                                href="{{ route('shop.customers.account.quick_orders.downloadSample') }}"
                                class="inline-flex items-center gap-1 font-medium text-blue-600"
                            >
                                <span class="icon-download text-base"></span>

                                @lang('b2b::app.shop.customers.account.quick-orders.download-sample')
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Selected Products -->
                <div
                    v-if="selectedProducts.length"
                    class="overflow-hidden rounded-xl border border-zinc-200 bg-white"
                >
                    <!-- Header -->
                    <div class="flex items-center justify-between gap-3 border-b border-zinc-200 px-6 py-4 max-md:flex-wrap max-md:px-4">
                        <div class="flex items-center gap-2">
                            <span class="icon-cart text-xl text-gray-700"></span>

                            <h3 class="text-base font-semibold text-gray-900">
                                @lang('b2b::app.shop.customers.account.quick-orders.selected-products') (@{{ selectedProducts.length }})
                            </h3>
                        </div>

                        <!-- Filter selected products -->
                        <div class="b2b-qo-filter">
                            <span class="icon-search text-lg text-gray-400"></span>

                            <input
                                type="text"
                                v-model="selectedSearch"
                                class="rounded-lg border border-zinc-300 text-sm text-gray-700 outline-none transition-all"
                                placeholder="@lang('b2b::app.shop.customers.account.quick-orders.filter-selected')"
                            >
                        </div>
                    </div>

                    <!-- Mass actions -->
                    <div
                        v-if="paginatedSelected.length"
                        class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-200 bg-gray-50 px-6 py-3 max-md:px-4"
                    >
                        <label class="flex cursor-pointer items-center gap-3 text-sm font-medium text-gray-700">
                            <input
                                type="checkbox"
                                class="h-4 w-4 cursor-pointer"
                                style="accent-color: #2563eb;"
                                :checked="isPageAllChecked"
                                @change="togglePage"
                            >

                            <span v-if="checkedIds.length">
                                @{{ checkedIds.length }} @lang('b2b::app.shop.customers.account.quick-orders.selected-count')
                            </span>

                            <span v-else>
                                @lang('b2b::app.shop.customers.account.quick-orders.select-all-page')
                            </span>
                        </label>

                        <div v-if="checkedIds.length" class="flex items-center gap-3">
                            <!-- Extend selection to all filtered products (every page) -->
                            <button
                                v-if="checkedIds.length < filteredSelected.length"
                                type="button"
                                class="secondary-button rounded-lg px-4 py-2 text-sm font-medium"
                                @click="selectAllFiltered"
                            >
                                @lang('b2b::app.shop.customers.account.quick-orders.select-all') (@{{ filteredSelected.length }})
                            </button>

                            <!-- Remove the checked products -->
                            <button
                                type="button"
                                class="rounded-lg px-4 py-2 text-sm font-medium text-white transition-all"
                                style="background-color: #dc2626;"
                                @click="removeChecked"
                            >
                                @lang('b2b::app.shop.customers.account.quick-orders.remove-selected')
                            </button>
                        </div>
                    </div>

                    <!-- Items -->
                    <div
                        v-for="product in paginatedSelected"
                        :key="product.id"
                        class="flex items-center justify-between gap-3 border-b border-zinc-100 px-6 py-4 transition-all last:border-b-0 hover:bg-gray-50/60 max-md:flex-wrap max-md:px-4"
                    >
                        <!-- Information -->
                        <div class="flex items-center gap-3">
                            <input
                                type="checkbox"
                                class="h-4 w-4 shrink-0 cursor-pointer"
                                style="accent-color: #2563eb;"
                                :checked="checkedIds.includes(product.id)"
                                @change="toggleCheck(product.id)"
                            >

                            <div class="h-16 w-16 shrink-0 overflow-hidden rounded-lg border border-zinc-100">
                                <template v-if="! product.images.length">
                                    <img
                                        class="h-16 w-16 object-cover"
                                        src="{{ bagisto_asset('images/small-product-placeholder.webp') }}"
                                    >
                                </template>

                                <template v-else>
                                    <img
                                        class="h-16 w-16 object-cover"
                                        :src="product.images[0].url"
                                    >
                                </template>
                            </div>

                            <!-- Details -->
                            <div class="grid gap-0.5">
                                <p class="break-all text-sm font-medium text-gray-900">
                                    @{{ product.name }}
                                </p>

                                <p class="text-xs text-gray-500">
                                    @lang('b2b::app.shop.customers.account.quick-orders.sku') @{{ product.sku }}
                                </p>

                                <p class="text-sm font-semibold text-gray-900">
                                    @{{ product.formatted_price }}
                                </p>
                            </div>
                        </div>

                        <!-- Qty + Remove -->
                        <div class="flex items-center gap-3">
                            <x-shop::quantity-changer
                                class="flex max-w-max items-center gap-x-2.5 rounded-lg border border-zinc-300 px-3 py-1.5"
                                name="quantity"
                                ::value="product.qty"
                                @change="product.qty = $event"
                            />

                            <button
                                type="button"
                                class="grid h-9 w-9 place-items-center rounded-lg text-lg text-gray-400 transition-all hover:bg-red-50 hover:text-red-600"
                                @click="removeProduct(product.id)"
                                aria-label="@lang('b2b::app.shop.customers.account.quick-orders.remove')"
                            >
                                <span class="icon-cancel"></span>
                            </button>
                        </div>
                    </div>

                    <!-- No matches for the filter -->
                    <div
                        v-if="! paginatedSelected.length"
                        class="px-6 py-10 text-center text-sm text-gray-500 max-md:px-4"
                    >
                        @lang('b2b::app.shop.customers.account.quick-orders.no-matching-products')
                    </div>

                    <!-- Pagination -->
                    <div
                        v-if="selectedLastPage > 1"
                        class="flex items-center justify-between gap-3 border-t border-zinc-200 px-6 py-3 text-sm max-md:px-4"
                    >
                        <span class="text-gray-500">
                            @lang('b2b::app.shop.customers.account.quick-orders.page') @{{ selectedPage }} / @{{ selectedLastPage }}
                        </span>

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="grid h-8 w-8 place-items-center rounded-lg border border-zinc-200 text-gray-500 transition-all hover:bg-zinc-100"
                                :disabled="selectedPage <= 1"
                                :style="selectedPage <= 1 ? 'opacity: 0.4; cursor: not-allowed;' : ''"
                                @click="selectedPage > 1 && selectedPage--"
                            >
                                <span class="icon-arrow-left rtl:icon-arrow-right"></span>
                            </button>

                            <button
                                type="button"
                                class="grid h-8 w-8 place-items-center rounded-lg border border-zinc-200 text-gray-500 transition-all hover:bg-zinc-100"
                                :disabled="selectedPage >= selectedLastPage"
                                :style="selectedPage >= selectedLastPage ? 'opacity: 0.4; cursor: not-allowed;' : ''"
                                @click="selectedPage < selectedLastPage && selectedPage++"
                            >
                                <span class="icon-arrow-right rtl:icon-arrow-left"></span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div
                    class="flex justify-end max-md:justify-stretch"
                    v-if="selectedProducts.length"
                >
                    <button
                        type="button"
                        class="primary-button rounded-lg px-10 py-3 max-md:w-full max-md:place-content-center"
                        @click="submitList()"
                    >
                        @lang('b2b::app.shop.customers.account.quick-orders.btn-add-to-cart')
                    </button>
                </div>
            </div>
        </script>

        <!-- Vue Component -->
        <script type="module">
            app.component("v-quick-order", {
                template: '#v-quick-order-template',

                data() {
                    return {
                        searchTerm: '',
                        multipleSKUs: '',
                        searchedProducts: [],
                        selectedProducts: [],
                        isSearching: false,
                        loadingMore: false,
                        page: 1,
                        lastPage: 1,
                        searchTimer: null,
                        selectedSearch: '',
                        selectedPage: 1,
                        selectedPerPage: 5,
                        checkedIds: [],
                        maxFileSizeMB: '{{ $maxFileSize }}',
                    }
                },

                watch: {
                    searchTerm() {
                        // Real-time, debounced search as the user types.
                        clearTimeout(this.searchTimer);

                        this.searchTimer = setTimeout(() => this.search(true), 350);
                    },

                    selectedSearch() {
                        this.selectedPage = 1;
                    },
                },

                computed: {
                    /**
                     * Selected products filtered by the "filter selected" box (name or SKU).
                     */
                    filteredSelected() {
                        const term = this.selectedSearch.trim().toLowerCase();

                        if (! term) {
                            return this.selectedProducts;
                        }

                        return this.selectedProducts.filter(p =>
                            (p.name || '').toLowerCase().includes(term)
                            || (p.sku || '').toLowerCase().includes(term)
                        );
                    },

                    selectedLastPage() {
                        return Math.max(1, Math.ceil(this.filteredSelected.length / this.selectedPerPage));
                    },

                    paginatedSelected() {
                        const start = (this.selectedPage - 1) * this.selectedPerPage;

                        return this.filteredSelected.slice(start, start + this.selectedPerPage);
                    },

                    /**
                     * Whether every product on the current page is checked (drives the header checkbox).
                     */
                    isPageAllChecked() {
                        return this.paginatedSelected.length > 0
                            && this.paginatedSelected.every(p => this.checkedIds.includes(p.id));
                    },
                },

                methods: {
                    /**
                     * Parse an uploaded CSV (sku,quantity) on the client and feed its products
                     * into the same "Selected Products" list as search / multiple SKUs, so the
                     * user reviews them before adding to cart.
                     */
                    handleFileUpload($event) {
                        const file = $event.target.files[0];

                        if (! file) return;

                        const extension = file.name.split('.').pop().toLowerCase();
                        const allowedTypes = ['text/csv', 'application/csv', 'application/vnd.ms-excel'];

                        if (! allowedTypes.includes(file.type) && extension !== 'csv') {
                            this.$emitter.emit('add-flash', { type: 'error', message: "@lang('b2b::app.shop.checkout.cart.invalid-file-type')" });
                            $event.target.value = '';
                            return;
                        }

                        if (file.size > this.maxFileSizeMB * 1024 * 1024) {
                            this.$emitter.emit('add-flash', {
                                type: 'error',
                                message: "@lang('b2b::app.shop.checkout.cart.file-size-exceeds')".replace(':size', this.maxFileSizeMB),
                            });
                            $event.target.value = '';
                            return;
                        }

                        const reader = new FileReader();

                        reader.onload = (e) => {
                            const lines = e.target.result.split(/\r?\n/).map(l => l.trim()).filter(l => l);

                            // Drop the header row when present.
                            const rows = (lines[0] && lines[0].toLowerCase().includes('sku')) ? lines.slice(1) : lines;

                            const quantities = {};
                            const skus = [];

                            rows.forEach(line => {
                                const [sku, qty] = line.split(',').map(c => (c || '').trim());

                                if (sku) {
                                    skus.push(sku);
                                    quantities[sku] = parseInt(qty) || 1;
                                }
                            });

                            $event.target.value = '';

                            if (! skus.length) {
                                this.$emitter.emit('add-flash', { type: 'warning', message: "@lang('b2b::app.shop.customers.account.quick-orders.no-products-found')" });
                                return;
                            }

                            this.addSkusToList(skus, quantities);
                        };

                        reader.readAsText(file);
                    },

                    /**
                     * Auto-load the next page of search results when the user scrolls near the
                     * bottom of the results box.
                     */
                    onResultsScroll($event) {
                        const el = $event.target;

                        if (el.scrollTop + el.clientHeight >= el.scrollHeight - 80) {
                            this.loadMore();
                        }
                    },

                    /**
                     * Resolve a list of SKUs to catalog products and append them to the selected
                     * list (shared by the multiple-SKUs box and the file upload).
                     */
                    addSkusToList(skus, quantities = {}) {
                        return this.$axios.post("{{ route('shop.customers.account.quick_orders.fetchBySkus') }}", { skus })
                        .then((response) => {
                            let added = 0;

                            response.data.data.forEach(product => {
                                if (! this.selectedProducts.find(p => p.id === product.id)) {
                                    this.selectedProducts.push({ ...product, qty: quantities[product.sku] || 1 });
                                    added++;
                                }
                            });

                            // Drop anything just added from the search results.
                            const ids = this.selectedProducts.map(p => p.id);
                            this.searchedProducts = this.searchedProducts.filter(p => ! ids.includes(p.id));

                            this.$emitter.emit('add-flash', {
                                type: added ? 'success' : 'warning',
                                message: added
                                    ? "@lang('b2b::app.shop.customers.account.quick-orders.skus-added')"
                                    : "@lang('b2b::app.shop.customers.account.quick-orders.no-products-found')",
                            });

                            return added;
                        })
                        .catch(() => {
                            this.$emitter.emit('add-flash', { type: 'error', message: "@lang('b2b::app.shop.checkout.cart.request-failed')" });
                        });
                    },

                    search(reset = true) {
                        const term = this.searchTerm.trim();

                        if (term.length <= 1) {
                            this.searchedProducts = [];
                            this.page = 1;
                            this.lastPage = 1;
                            return;
                        }

                        if (reset) {
                            this.page = 1;
                            this.isSearching = true;
                        } else {
                            this.loadingMore = true;
                        }

                        this.$axios.get("{{ route('shop.customers.account.quick_orders.search') }}", {
                            params: { query: term, page: this.page }
                        })
                        .then((response) => {
                            this.isSearching = false;
                            this.loadingMore = false;

                            this.lastPage = response.data.meta?.last_page ?? this.page;

                            // Filter out already selected products and append (or replace on a fresh search).
                            const selectedIds = this.selectedProducts.map(p => p.id);
                            const fresh = response.data.data.filter(p => !selectedIds.includes(p.id));

                            this.searchedProducts = reset ? fresh : this.searchedProducts.concat(fresh);
                        })
                        .catch(() => {
                            this.isSearching = false;
                            this.loadingMore = false;
                        });
                    },

                    loadMore() {
                        if (this.loadingMore || this.page >= this.lastPage) {
                            return;
                        }

                        this.page++;

                        this.search(false);
                    },

                    addProduct(product) {
                        if (!this.selectedProducts.find(p => p.id === product.id)) {
                            this.selectedProducts.push({ ...product, qty: 1 });
                        }

                        // Remove from search results.
                        this.searchedProducts = this.searchedProducts.filter(
                            p => p.id !== product.id
                        );
                    },

                    addMultipleSKUs() {
                        const skus = this.multipleSKUs.split(",")
                                     .map(s => s.trim()).filter(s => s);

                        if (! skus.length) return;

                        this.addSkusToList(skus).then(() => { this.multipleSKUs = ''; });
                    },

                    removeProduct(productId) {
                        this.selectedProducts = this.selectedProducts.filter(p => p.id !== productId);
                        this.checkedIds = this.checkedIds.filter(id => id !== productId);

                        this.clampSelectedPage();

                        if (this.searchTerm.length > 1) {
                            this.search();
                        }
                    },

                    /* ---- Selected-products mass actions ---- */

                    toggleCheck(id) {
                        const index = this.checkedIds.indexOf(id);

                        index === -1 ? this.checkedIds.push(id) : this.checkedIds.splice(index, 1);
                    },

                    togglePage() {
                        const pageIds = this.paginatedSelected.map(p => p.id);

                        this.checkedIds = this.isPageAllChecked
                            ? this.checkedIds.filter(id => ! pageIds.includes(id))
                            : [...new Set([...this.checkedIds, ...pageIds])];
                    },

                    selectAllFiltered() {
                        this.checkedIds = this.filteredSelected.map(p => p.id);
                    },

                    removeChecked() {
                        if (! this.checkedIds.length) return;

                        this.selectedProducts = this.selectedProducts.filter(p => ! this.checkedIds.includes(p.id));
                        this.checkedIds = [];

                        this.clampSelectedPage();
                    },

                    clampSelectedPage() {
                        if (this.selectedPage > this.selectedLastPage) {
                            this.selectedPage = this.selectedLastPage;
                        }
                    },

                    submitList() {
                        if (! this.selectedProducts.length) {
                            this.$emitter.emit('add-flash', {
                                type: 'error',
                                message: '@lang("b2b::app.shop.checkout.cart.request-failed")',
                            });

                            return;
                        }

                        this.$axios.post("{{ route('shop.customers.account.quick_orders.store') }}", {
                            products: this.selectedProducts.map(product => ({ sku: product.sku, quantity: product.qty })),
                        })
                        .then((response) => {
                            if (response.data.status) {
                                if (response.data.message) {
                                    this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                                }

                                if (response.data.redirect_url) {
                                    window.location.href = response.data.redirect_url;
                                }
                            } else if (response.data.message) {
                                this.$emitter.emit('add-flash', { type: 'error', message: response.data.message });
                            }
                        })
                        .catch((error) => {
                            this.$emitter.emit('add-flash', {
                                type: 'error',
                                message: error.response?.data?.message || '@lang("b2b::app.shop.checkout.cart.request-failed")',
                            });
                        });
                    }
                }
            });
        </script>
    @endPushOnce
</x-shop::layouts.account>
