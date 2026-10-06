<x-shop::layouts.account>
    <!-- Page Title -->
    <x-slot:title>
        @lang('b2b::app.shop.customers.account.requisitions.title')
    </x-slot>

    <!-- Breadcrumbs -->
    @if ((core()->getConfigData('general.general.breadcrumbs.shop')))
        @section('breadcrumbs')
            <x-shop::breadcrumbs name="requisitions" />
        @endSection
    @endif

    <div class="max-md:hidden">
        <x-shop::layouts.account.navigation />
    </div>
    
    <v-requisitions></v-requisitions>

    @pushOnce('scripts')
        <script
            type="text/x-template"
            id="v-requisitions-template"
        >
            <div class="mx-4 flex-auto max-md:mx-6 max-sm:mx-4">
                <div class="mb-8 flex justify-between max-sm:mb-5">
                    <div class="flex items-center">
                        <!-- Back Button -->
                        <a
                            class="grid md:hidden"
                            href="{{ route('shop.customers.account.profile.index') }}"
                        >
                            <span class="icon-arrow-left rtl:icon-arrow-right text-2xl"></span>
                        </a>
            
                        <h2 class="text-2xl font-medium max-md:text-xl max-sm:text-base ltr:ml-2.5 md:ltr:ml-0 rtl:mr-2.5 md:rtl:mr-0">
                            @lang('b2b::app.shop.customers.account.requisitions.title')
                        </h2>
                    </div>
                    
                    <!-- Requisition Create Button -->
                    @if ((int) core()->getConfigData('b2b.general.settings.no_requisition_list') > $totalRequisition)
                        <button
                            type="button"
                            class="secondary-button border-zinc-200 px-5 py-3 font-normal max-md:rounded-lg max-md:py-2 max-sm:py-1.5 max-sm:text-sm"
                            @click="selectedRequisitions=0;resetForm();$refs.requisitionCreateModal.toggle()"
                        >
                            @lang('b2b::app.shop.customers.account.requisitions.btn-create') 
                        </button>
                    @endif
                </div>
                
                {!! view_render_event('bagisto.shop.customers.account.requisitions.list.before') !!}

                <!-- For Desktop View -->
                <div class="max-md:hidden">
                    <x-shop::datagrid :src="route('shop.customers.account.requisitions.index')" />
                </div>

                <!-- For Mobile View -->
                <div class="md:hidden">
                    <x-shop::datagrid :src="route('shop.customers.account.requisitions.index')">
                        <!-- Datagrid Header -->
                        <template #header="{
                            isLoading,
                            available,
                            applied,
                            selectAll,
                            sort,
                            performAction
                        }">
                            <div class="hidden"></div>
                        </template>

                        <template #body="{
                            isLoading,
                            available,
                            applied,
                            selectAll,
                            sort,
                            performAction
                        }">
                            <template v-if="isLoading">
                                <x-b2b::shimmer.datagrid.card.body />
                            </template>
            
                            <template v-else>
                                <template v-for="record in available.records">
                                    <div class="w-full p-4 border rounded-md transition-all hover:bg-gray-50 [&>*]:border-0 mb-4 last:mb-0">
                                        <a :href="record.actions[0].url">
                                            <div class="flex justify-between">
                                                <div class="text-sm font-semibold">
                                                    @lang('b2b::app.shop.customers.account.requisitions.index.datagrid.order-id'): #@{{ record.id }}
            
                                                    <p class="text-xs font-normal text-neutral-500">
                                                        @{{ record.created_at }}
                                                    </p>
                                                </div>
            
                                                <p v-html="record.status"></p>
                                            </div>
                
                                            <div class="mt-2.5 text-xs font-normal text-neutral-500">
                                                @lang('b2b::app.shop.customers.account.quotes.subtotal')
            
                                                <p class="text-xl font-semibold text-black">
                                                    @{{ record.name }}
                                                </p>
                                            </div>
                                        </a>
                                    </div>
                                </template>
                            </template>
                        </template>
                    </x-shop::datagrid>
                </div>
            
                {!! view_render_event('bagisto.shop.customers.account.requisitions.list.after') !!}

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
                                    name="id"
                                    v-model="requisition.id"
                                />

                                <x-shop::form.control-group>
                                    <x-shop::form.control-group.label class="required">
                                        @lang('b2b::app.shop.customers.account.requisitions.index.create.name')
                                    </x-shop::form.control-group.label>

                                    <x-shop::form.control-group.control
                                        type="text"
                                        name="name"
                                        rules="required"
                                        v-model="requisition.name"
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
                                        v-model="requisition.description"
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
            </div>
        </script>

        <script type="module">
            app.component('v-requisitions', {
                template: '#v-requisitions-template',

                data() {
                    return {
                        requisition: {
                            image: [],
                        },

                        isLoading: false,

                        selectedRequisitions: 0,
                    }
                },

                methods: {
                    createRequisition(params, { resetForm, setErrors  }) {
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
                    }
                },
            });
        </script>
    @endPushOnce
</x-shop::layouts.account>
