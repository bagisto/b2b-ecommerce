@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-create-customer-form-template"
    >
        <x-admin::form
            v-slot="{ meta, errors, handleSubmit }"
            as="div"
        >
            <form @submit="handleSubmit($event, create)">
                <!-- Customer Create Modal -->
                <x-admin::modal ref="customerCreateModal">
                    <!-- Modal Header -->
                    <x-slot:header>
                        <p class="text-lg font-bold text-gray-800 dark:text-white">
                            @lang('admin::app.customers.customers.index.create.title')
                        </p>
                    </x-slot>

                    <!-- Modal Content -->
                    <x-slot:content>
                        {!! view_render_event('bagisto.admin.customers.create.before') !!}

                        @if ((bool) core()->getConfigData('b2b.general.settings.active'))
                            <!-- Companies Multi-Select -->
                            <x-admin::form.control-group class="mb-2.5">
                                <x-admin::form.control-group.label>
                                    @lang('b2b::app.admin.companies.index.title')
                                </x-admin::form.control-group.label>

                                <div class="relative">
                                    <x-admin::form.control-group.control
                                        type="text"
                                        v-model="companySearchTerm"
                                        @input="searchCompanies"
                                        @focus="showCompanyDropdown = true"
                                        id="company_list"
                                        name="company_list"
                                        :label="trans('b2b::app.admin.companies.index.companies-placeholder')"
                                        :placeholder="trans('b2b::app.admin.companies.index.companies-placeholder')"
                                    />

                                    <!-- Selected Companies Tags -->
                                    <div v-if="selectedCompanies.length > 0" class="mt-2 flex flex-wrap gap-2">
                                        <span
                                            v-for="company in selectedCompanies"
                                            :key="company.id"
                                            class="inline-flex items-center rounded-full bg-blue-100 px-2 py-1 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-300"
                                        >
                                            @{{ company.name }}
                                            
                                            <button
                                                type="button"
                                                @click="removeCompany(company)"
                                                class="icon-cross ltr:ml-2 rtl:mr-2 cursor-pointer"
                                            >
                                            </button>
                                        </span>
                                    </div>

                                    <!-- Dropdown -->
                                    <div
                                        v-show="showCompanyDropdown && filteredCompanies.length > 0"
                                        class="absolute z-10 mt-1 w-full rounded-md border border-gray-300 bg-white shadow-lg dark:border-gray-600 dark:bg-gray-800"
                                    >
                                        <div
                                            v-for="company in filteredCompanies"
                                            :key="company.id"
                                            @click="selectCompany(company)"
                                            class="cursor-pointer px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700"
                                        >
                                            <div class="font-medium text-gray-900 dark:text-white">@{{ company.name }}</div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">@{{ company.email }}</div>
                                        </div>
                                    </div>

                                    <!-- Hidden inputs for form submission -->
                                    <input
                                        v-for="company in selectedCompanies"
                                        :key="company.id"
                                        type="hidden"
                                        name="company_ids[]"
                                        :value="company.id"
                                    />
                                </div>
                            </x-admin::form.control-group>
                        @endif

                        <div class="flex gap-4 max-sm:flex-wrap">
                            <!-- First Name -->
                            <x-admin::form.control-group class="mb-2.5 w-full">
                                <x-admin::form.control-group.label class="required">
                                    @lang('admin::app.customers.customers.index.create.first-name')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="text"
                                    id="first_name"
                                    name="first_name"
                                    rules="required"
                                    :label="trans('admin::app.customers.customers.index.create.first-name')"
                                    :placeholder="trans('admin::app.customers.customers.index.create.first-name')"
                                />

                                <x-admin::form.control-group.error control-name="first_name" />
                            </x-admin::form.control-group>

                            <!-- Last Name -->
                            <x-admin::form.control-group class="mb-2.5 w-full">
                                <x-admin::form.control-group.label class="required">
                                    @lang('admin::app.customers.customers.index.create.last-name')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="text"
                                    id="last_name"
                                    name="last_name"
                                    rules="required"
                                    :label="trans('admin::app.customers.customers.index.create.last-name')"
                                    :placeholder="trans('admin::app.customers.customers.index.create.last-name')"
                                />

                                <x-admin::form.control-group.error control-name="last_name" />
                            </x-admin::form.control-group>
                        </div>

                        <!-- Email -->
                        <x-admin::form.control-group>
                            <x-admin::form.control-group.label class="required">
                                @lang('admin::app.customers.customers.index.create.email')
                            </x-admin::form.control-group.label>

                            <x-admin::form.control-group.control
                                type="email"
                                id="email"
                                name="email"
                                rules="required|email"
                                :label="trans('admin::app.customers.customers.index.create.email')"
                                placeholder="email@example.com"
                            />

                            <x-admin::form.control-group.error control-name="email" />
                        </x-admin::form.control-group>

                        <!-- Contact Number -->
                        <x-admin::form.control-group>
                            <x-admin::form.control-group.label>
                                @lang('admin::app.customers.customers.index.create.phone')
                            </x-admin::form.control-group.label>

                            <x-admin::form.control-group.control
                                type="text"
                                id="phone"
                                name="phone"
                                rules="phone"
                                :label="trans('admin::app.customers.customers.index.create.phone')"
                                :placeholder="trans('admin::app.customers.customers.index.create.phone')"
                            />

                            <x-admin::form.control-group.error control-name="phone" />
                        </x-admin::form.control-group>

                        <x-admin::form.control-group>
                            <x-admin::form.control-group.label>
                                @lang('admin::app.customers.customers.index.create.date-of-birth')
                            </x-admin::form.control-group.label>

                            <x-admin::form.control-group.control
                                type="date"
                                id="dob"
                                name="date_of_birth"
                                :label="trans('admin::app.customers.customers.index.create.date-of-birth')"
                                :placeholder="trans('admin::app.customers.customers.index.create.date-of-birth')"
                            />

                            <x-admin::form.control-group.error control-name="date_of_birth" />
                        </x-admin::form.control-group>

                        <div class="flex gap-4 max-sm:flex-wrap">
                            <!-- Gender -->
                            <x-admin::form.control-group class="w-full">
                                <x-admin::form.control-group.label class="required">
                                    @lang('admin::app.customers.customers.index.create.gender')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="select"
                                    id="gender"
                                    name="gender"
                                    rules="required"
                                    :label="trans('admin::app.customers.customers.index.create.gender')"
                                >
                                    <option value="">
                                        @lang('admin::app.customers.customers.index.create.select-gender')
                                    </option>

                                    <option value="Male">
                                        @lang('admin::app.customers.customers.index.create.male')
                                    </option>

                                    <option value="Female">
                                        @lang('admin::app.customers.customers.index.create.female')
                                    </option>

                                    <option value="Other">
                                        @lang('admin::app.customers.customers.index.create.other')
                                    </option>
                                </x-admin::form.control-group.control>

                                <x-admin::form.control-group.error control-name="gender" />
                            </x-admin::form.control-group>

                            <!-- Customer Group -->
                            <x-admin::form.control-group class="w-full">
                                <x-admin::form.control-group.label>
                                    @lang('admin::app.customers.customers.index.create.customer-group')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="select"
                                    id="customerGroup"
                                    name="customer_group_id"
                                    :label="trans('admin::app.customers.customers.index.create.customer-group')"
                                    ::value="groups[0]?.id"
                                >
                                    <option 
                                        v-for="group in groups" 
                                        :value="group.id"
                                        selected
                                    > 
                                        @{{ group.name }} 
                                    </option>
                                </x-admin::form.control-group.control>

                                <x-admin::form.control-group.error control-name="customer_group_id" />
                            </x-admin::form.control-group>
                        </div>

                        {!! view_render_event('bagisto.admin.customers.create.after') !!}
                    </x-slot>

                    <!-- Modal Footer -->
                    <x-slot:footer>
                        <!-- Save Button -->
                        <x-admin::button
                            button-type="submit"
                            class="primary-button justify-center"
                            :title="trans('admin::app.customers.customers.index.create.save-btn')"
                            ::loading="isLoading"
                            ::disabled="isLoading"
                        />
                    </x-slot>
                </x-admin::modal>
            </form>
        </x-admin::form>
    </script>

    <script type="module">
        app.component('v-create-customer-form', {
            template: '#v-create-customer-form-template',

            data() {
                return {
                    groups: @json($groups),

                    isLoading: false,

                    // Companies data
                    allCompanies: [],
                    filteredCompanies: [],
                    selectedCompanies: [],
                    companySearchTerm: '',
                    showCompanyDropdown: false,
                    isB2BEnabled: {{ core()->getConfigData('b2b.general.settings.active') ?? 0 }}
                };
            },

            mounted() {
                if (this.isB2BEnabled == 1) {
                    this.fetchCompanies();
                
                    // Close dropdown when clicking outside
                    document.addEventListener('click', this.handleClickOutside);
                }
            },

            beforeUnmount() {
                if (this.isB2BEnabled == 1) {
                    document.removeEventListener('click', this.handleClickOutside);
                }
            },

            methods: {
                openModal() {
                    this.$refs.customerCreateModal.open();
                },

                async fetchCompanies() {
                    try {
                        const response = await this.$axios.get("{{ core()->getConfigData('b2b.general.settings.active') ? route('admin.b2b.companies.get') : '' }}");
                        this.allCompanies = response.data;
                        this.filteredCompanies = response.data;
                    } catch (error) {
                        console.error('Error fetching companies:', error);
                    }
                },

                searchCompanies() {
                    if (this.companySearchTerm.trim() === '') {
                        this.filteredCompanies = this.allCompanies.filter(
                            company => !this.selectedCompanies.find(selected => selected.id === company.id)
                        );
                    } else {
                        this.filteredCompanies = this.allCompanies.filter(company => {
                            const isAlreadySelected = this.selectedCompanies.find(selected => selected.id === company.id);
                            const matchesSearch = company.name.toLowerCase().includes(this.companySearchTerm.toLowerCase()) ||
                                                company.email.toLowerCase().includes(this.companySearchTerm.toLowerCase());
                            return !isAlreadySelected && matchesSearch;
                        });
                    }
                    this.showCompanyDropdown = true;
                },

                selectCompany(company) {
                    this.selectedCompanies.push(company);
                    this.companySearchTerm = '';
                    this.showCompanyDropdown = false;
                    this.searchCompanies();
                },

                removeCompany(companyToRemove) {
                    this.selectedCompanies = this.selectedCompanies.filter(
                        company => company.id !== companyToRemove.id
                    );
                    this.searchCompanies();
                },

                handleClickOutside(event) {
                    if (!this.$el.contains(event.target)) {
                        this.showCompanyDropdown = false;
                    }
                },

                create(params, { resetForm, setErrors }) {
                    this.isLoading = true;

                    if (this.selectedCompanies.length > 0) {
                        params.company_ids = this.selectedCompanies.map(company => company.id);
                    }

                    this.$axios.post("{{ route('admin.customers.customers.store') }}", params)
                        .then((response) => {
                            this.$refs.customerCreateModal.close();

                            this.$emit('customer-created', response.data.data);

                            this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });

                            resetForm();

                            // Reset companies selection
                            this.selectedCompanies = [];
                            this.companySearchTerm = '';
                            this.showCompanyDropdown = false;
                            this.searchCompanies();

                            this.isLoading = false;
                        })
                        .catch(error => {                            
                            this.isLoading = false;

                            if (error.response.status == 422) {
                                setErrors(error.response.data.errors);
                            }
                        });
                }
            }
        })
    </script>
@endPushOnce