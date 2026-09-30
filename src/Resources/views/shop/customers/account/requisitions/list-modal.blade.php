<!-- Requisition List Button -->
@if (
    (bool) core()->getConfigData('b2b.general.settings.active')
    && auth()->guard('customer')->check()
    && customer_bouncer()->hasPermission('account.requisitions')
)
    @php
        $currentRoute = request()->route()?->getName();
        $currentCart = \Webkul\Checkout\Facades\Cart::getCart();
        $productDetails = isset($product) ? json_encode($product) : 'null';
        $buttonTitle = trans('b2b::app.shop.customers.account.requisitions.list-view.title');
    @endphp

    <v-list-modal
        :cart="{{ json_encode($currentCart ?? []) }}"
        :product-data="{{ $productDetails }}"
        :product-list="{{ isset($product) ? 'product' : 'null' }}"
        :button-caption="'{{ $buttonTitle }}'"
    ></v-list-modal>

    @push('scripts')
        <script
            type="text/x-template"
            id="v-list-modal-template"
        >
            <div
                v-if="showRequisitionDropdown"
                class="{{ $currentRoute !== 'shop.checkout.cart.index' ? 'mt-4' : '' }} relative  grid max-w-[470px] max-sm:mt-4"
                id="requisition-list-dropdown"
            >
                {!! view_render_event('bagisto.shop.products.view.requisition_list.before') !!}

                @if (
                    $productDetails 
                    || $currentRoute === 'shop.checkout.cart.index'
                )
                    <div
                        class="secondary-button max-h-14 rounded-2xl max-md:rounded-lg max-md:px-6 max-md:py-3 max-md:text-sm max-sm:py-2" 
                        @click="toggleDropdown"
                    >
                        <span class="icon-listing text-2xl"></span>
                        
                        @{{ buttonCaption }}
                    </div>
                @endif

                <div 
                    v-if="showDropdown"
                    class="absolute top-16 z-10 w-full rounded-2xl border border-gray-200 bg-white shadow-lg hover:rounded-2xl max-md:rounded-lg"
                >
                    <ul>
                        <li 
                            v-for="item in requisitions"
                            :key="item.id"
                            class="cursor-pointer p-4 hover:bg-gray-100"
                            @click="addProductToRequisition(item.id)"
                        >
                            @{{ item.name }}
                        </li>
                    </ul>

                    <div 
                        v-if="allowNewList"
                        class="border-t p-4"
                    >
                        <a 
                            href="#"
                            class="flex items-center text-blue-600"
                            @click.prevent="selectedRequisitions=0;resetForm();$refs.requisitionCreateModal.toggle()"
                        >
                            <span class="icon-plus text-2xl"></span>
                            @lang('b2b::app.shop.customers.account.requisitions.list-view.create-new')
                        </a>
                    </div>
                </div>

                <!-- Create Requisition Modal -->
                <x-shop::form
                    v-slot="{ meta, errors, handleSubmit }"
                    as="div"
                    ref="modalForm"
                >
                    <form
                        @submit="handleSubmit($event, createRequisition)"
                        ref="createRequisitionForm"
                    >

                        {!! view_render_event('bagisto.shop.customers.account.requisitions.create_form_controls.before') !!}

                        <x-shop::modal ref="requisitionCreateModal">
                            <!-- Modal Header -->
                            <x-slot:header>
                                <p class="text-lg font-bold text-gray-800">
                                    <span v-if="selectedRequisitions">
                                        @lang('b2b::app.shop.customers.account.requisitions.edit-title')
                                    </span>

                                    <span v-else>
                                        @lang('b2b::app.shop.customers.account.requisitions.add-title')
                                    </span>
                                </p>
                            </x-slot>

                            <!-- Modal Content -->
                            <x-slot:content>
                                {!! view_render_event('bagisto.shop.customers.account.requisitions.create.before') !!}

                                <x-shop::form.control-group.control
                                    type="hidden"
                                    name="product_id"
                                    v-model="product.id"
                                />

                                <x-shop::form.control-group>
                                    <x-shop::form.control-group.label class="required">
                                        @lang('b2b::app.shop.customers.account.requisitions.index.create.name')
                                    </x-shop::form.control-group.label>

                                    <x-shop::form.control-group.control
                                        type="text"
                                        name="name"
                                        rules="required"
                                        :label="trans('b2b::app.shop.customers.account.requisitions.index.create.name')"
                                        :placeholder="trans('b2b::app.shop.customers.account.requisitions.index.create.name')"
                                    />

                                    <x-shop::form.control-group.error control-name="name" />
                                </x-shop::form.control-group>

                                <x-shop::form.control-group>
                                    <x-shop::form.control-group.label class="required">
                                        @lang('b2b::app.shop.customers.account.requisitions.index.create.description')
                                    </x-shop::form.control-group.label>

                                    <x-shop::form.control-group.control
                                        type="textarea"
                                        name="description"
                                        rows="4"
                                        rules="required"
                                        :label="trans('b2b::app.shop.customers.account.requisitions.index.create.description')"
                                        :placeholder="trans('b2b::app.shop.customers.account.requisitions.index.create.description')"
                                    />

                                    <x-shop::form.control-group.error control-name="description" />
                                </x-shop::form.control-group>

                                <!-- Is Default -->
                                <x-shop::form.control-group>
                                    <x-shop::form.control-group.label>
                                        @lang('b2b::app.shop.customers.account.requisitions.index.create.is-default')
                                    </x-shop::form.control-group.label>

                                    <x-shop::form.control-group.control
                                        type="switch"
                                        name="is_default"
                                        value="1"
                                        :label="trans('b2b::app.shop.customers.account.requisitions.index.create.is-default')"
                                    />
                                </x-shop::form.control-group>

                                {!! view_render_event('bagisto.shop.customers.account.requisitions.create.after') !!}
                            </x-slot>

                            <!-- Modal Footer -->
                            <x-slot:footer>
                                <!-- Save Button -->
                                <x-shop::button
                                    button-type="button"
                                    class="primary-button"
                                    :title="trans('b2b::app.shop.customers.account.requisitions.index.create.btn-save')"
                                    ::loading="isLoading"
                                    ::disabled="isLoading"
                                />
                            </x-slot>
                        </x-shop::modal>

                        {!! view_render_event('bagisto.shop.customers.account.requisitions.create_form_controls.after') !!}

                    </form>
                </x-shop::form>

                {!! view_render_event('bagisto.shop.products.view.requisition_list.after') !!}
            </div>
        </script>
        
        <script type="module">
            app.component('v-list-modal', {
                template: '#v-list-modal-template',

                props: ['cart', 'productData', 'productList', 'buttonCaption'],

                data() {
                    return {
                        requisitions: [],

                        allowNewList: false,

                        showDropdown: false,

                        showRequisitionDropdown: false,

                        product: {},

                        variantId: false,

                        showCreateModal: false,

                        currentRouteName: '{{ $currentRoute }}',

                        newRequisition: {
                            name: '',
                            description: '',
                            is_default: false,
                        },
                    };
                },

                mounted() {
                    this.$emitter.on('configurable-variant-selected-event', (variantId) => {
                        this.variantId = variantId;
                        this.loadRequisitions();
                    });
                },
                
                created() {
                    if (this.productData && this.productData.id) {
                        this.product = this.productData;
                    }
                    
                    if (this.productList && this.productList.id) {
                        this.loadProduct(this.productList.id);
                    }
                    
                    this.loadRequisitions();
                },
                
                methods: {
                    loadRequisitions() {
                        this.validateRequisitionCondition();

                        if (this.showRequisitionDropdown) {
                            this.$axios.get("{{ route('shop.customers.account.requisitions.list') }}")
                                .then(response => {
                                    this.requisitions = response.data.requisitions;
                                    
                                    this.allowNewList = response.data.allow_new_list;
                                })
                                .catch(error => {
                                    console.error("Error loading requisitions:", error);
                                });
                        }
                    },
                    
                    loadProduct(productId) {
                        this.$axios.get("{{ route('shop.customers.account.requisitions.get_product') }}", {
                                params: {id: productId}
                            })
                            .then(response => {
                                this.product = response.data.data;
                            })
                            .catch(error => {
                                console.error("Error loading requisitions:", error);
                            });
                    },
                    
                    toggleDropdown() {
                        this.showDropdown = !this.showDropdown;
                    },
                    
                    addProductToRequisition(requisitionId) {
                        this.$axios.post("{{ route('shop.customers.account.requisitions.add_product') }}", {
                            requisition_id: requisitionId,
                            cart_id: (this.cart && this.cart.id) ? this.cart.id : null,
                            product_id: (this.product && this.product.id) ? this.product.id : null,
                            selected_configurable_option: this.variantId || null,
                            quantity: 1,
                        })
                        .then(response => {
                            this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                            this.showDropdown = false;
                        })
                        .catch(error => {
                            this.$emitter.emit('add-flash', { type: 'error', message: error.response.data.message });
                        });
                    },
                    
                    createRequisition(params, { resetForm, setErrors }) {
                        this.isLoading = true;

                        let formData = new FormData(this.$refs.createRequisitionForm);

                        this.$axios.post("{{ route('shop.customers.account.requisitions.store') }}", formData, {
                            headers: {
                                'Content-Type': 'multipart/form-data'
                            }
                        })
                        .then((response) => {
                            this.isLoading = false;

                            this.$refs.requisitionCreateModal.close();
                            
                            if (response.data.redirect_url) {
                                window.location.href = response.data.redirect_url;
                            } else {
                                window.location.reload();
                            }

                            resetForm();
                        })
                        .catch(error => {
                            this.isLoading = false;

                            if (error.response.status == 422) {
                                setErrors(error.response.data.errors);
                            }
                        });
                    },

                    resetForm() {
                        this.requisition = {
                            image: [],
                        };
                    },
                    
                    validateRequisitionCondition() {
                        const types = ["simple", "virtual", "grouped"];
                        this.showRequisitionDropdown = false;

                        if (types.includes(this.product.type)) {
                            this.showRequisitionDropdown = true;
                        }
                        
                        if (
                            this.product.type === 'configurable'
                            && this.variantId
                        ) {
                            this.showRequisitionDropdown = true;
                        }
                        
                        if (this.currentRouteName === 'shop.checkout.cart.index') {
                            this.showRequisitionDropdown = true;
                        }
                    }
                },
            });
        </script>
    @endpush
@endif
