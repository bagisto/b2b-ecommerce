{{--
    Request-for-Quote modal (B2B Suite) — opened from the cart summary button via the
    `open-request-quote-modal` emitter event. Built on the core Bagisto modal/form
    components so it stays on-theme. Included by the overridden cart summary for
    company-affiliated customers.
--}}
@php
    $supportedFormats = core()->getConfigData('b2b.quotes.settings.supported_file_formats') ?? 'doc,docx,xls,xlsx,pdf,txt,jpg,png,jpeg';
    $maxFileSize = (int) (core()->getConfigData('b2b.quotes.settings.maximum_file_size') ?: 10);
@endphp

<v-request-quote-modal :cart="cart"></v-request-quote-modal>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-request-quote-modal-template"
    >
        <x-shop::form
            v-slot="{ meta, errors, handleSubmit }"
            as="div"
        >
            <form @submit="handleSubmit($event, submitQuote)">
                <x-shop::modal ref="requestQuoteModal">
                    <!-- Toggler handled via emitter -->
                    <x-slot:toggle></x-slot>

                    <!-- Header -->
                    <x-slot:header>
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-lightOrange text-navyBlue">
                                <span class="icon-dollar-sign text-2xl"></span>
                            </span>

                            <div>
                                <p class="text-xl font-medium text-navyBlue max-sm:text-base">
                                    @lang('b2b::app.shop.checkout.cart.request-quote.title')
                                </p>

                                <p class="text-sm text-zinc-500 max-sm:text-xs">
                                    @lang('b2b::app.shop.checkout.cart.request-quote.subtitle')
                                </p>
                            </div>
                        </div>
                    </x-slot>

                    <!-- Content (capped height so the modal never outgrows the viewport; the body scrolls) -->
                    <x-slot:content class="!px-6 !py-6 max-sm:!p-4" style="max-height: 60vh; overflow-y: auto;">
                        <div class="grid gap-6">
                            <!-- Quote Name -->
                            <x-shop::form.control-group class="!mb-0">
                                <x-shop::form.control-group.label class="required">
                                    @lang('b2b::app.shop.checkout.cart.quote-name')
                                </x-shop::form.control-group.label>

                                <x-shop::form.control-group.control
                                    ref="quoteNameInput"
                                    type="text"
                                    class="px-4 py-3 max-sm:!p-2.5"
                                    name="name"
                                    rules="required|min:3|max:255"
                                    :placeholder="trans('b2b::app.shop.checkout.cart.enter-quote-name')"
                                />

                                <x-shop::form.control-group.error class="flex" control-name="name" />
                            </x-shop::form.control-group>

                            <!-- Description -->
                            <x-shop::form.control-group class="!mb-0">
                                <x-shop::form.control-group.label class="required">
                                    @lang('b2b::app.shop.checkout.cart.add-your-description')
                                </x-shop::form.control-group.label>

                                <x-shop::form.control-group.control
                                    ref="descriptionInput"
                                    type="textarea"
                                    class="px-4 py-3 max-sm:!p-2.5"
                                    name="description"
                                    rows="4"
                                    rules="required|min:10|max:1000"
                                    :placeholder="trans('b2b::app.shop.checkout.cart.enter-brief-description')"
                                />

                                <x-shop::form.control-group.error class="flex" control-name="description" />
                            </x-shop::form.control-group>

                            <!-- Attachments -->
                            <x-shop::form.control-group class="!mb-0">
                                <x-shop::form.control-group.label>
                                    @lang('b2b::app.shop.checkout.cart.attach-file')
                                </x-shop::form.control-group.label>

                                <div class="relative max-h-[140px] overflow-auto rounded-xl border-2 border-dashed border-zinc-300 p-6 text-center transition-colors hover:border-navyBlue">
                                    <input
                                        v-if="selectedFiles.length === 0"
                                        type="file"
                                        ref="fileInput"
                                        @change="handleFileSelect"
                                        class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                                        :accept="acceptedFileTypes"
                                        multiple
                                    />

                                    <div v-if="selectedFiles.length === 0">
                                        <span class="icon-upload mb-2 block text-3xl text-zinc-400"></span>

                                        <p class="mb-1 text-sm text-zinc-600">
                                            @lang('b2b::app.shop.checkout.cart.click-to-select-file')
                                        </p>

                                        <p class="text-xs text-zinc-500">
                                            @lang('b2b::app.shop.checkout.cart.file-requirements', [
                                                'formats' => strtoupper($supportedFormats),
                                                'size' => $maxFileSize . 'MB',
                                            ])
                                        </p>
                                    </div>

                                    <div v-else class="space-y-2">
                                        <div
                                            v-for="(file, idx) in selectedFiles"
                                            :key="file.name + file.size"
                                            class="flex items-center justify-between rounded-lg bg-zinc-50 p-2.5"
                                        >
                                            <div class="flex items-center">
                                                <span class="icon-file ltr:mr-2 rtl:ml-2 text-xl text-navyBlue"></span>

                                                <div class="ltr:text-left rtl:text-right">
                                                    <p class="text-sm font-medium text-gray-900">@{{ file.name }}</p>
                                                    <p class="text-xs text-gray-500">@{{ formatFileSize(file.size) }}</p>
                                                </div>
                                            </div>

                                            <button type="button" @click="removeFile(idx)" class="ltr:ml-3 rtl:mr-3 text-red-500 hover:text-red-700">
                                                <span class="icon-cancel text-lg"></span>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <p v-if="fileError" class="mt-2 text-sm text-red-500">@{{ fileError }}</p>
                            </x-shop::form.control-group>
                        </div>
                    </x-slot>

                    <!-- Footer -->
                    <x-slot:footer>
                        <div class="flex w-full justify-end gap-3 max-sm:flex-col">
                            <x-shop::button
                                type="button"
                                class="secondary-button rounded-2xl px-6 py-3 max-md:rounded-lg max-sm:w-full"
                                :title="trans('b2b::app.shop.checkout.cart.request-quote.save-as-draft')"
                                ::loading="isDraftSaving"
                                ::disabled="isDraftSaving || isSubmitting"
                                @click="saveAsDraft"
                            />

                            <x-shop::button
                                class="primary-button rounded-2xl px-6 py-3 max-md:rounded-lg max-sm:w-full"
                                :title="trans('b2b::app.shop.checkout.cart.request-quote.request-quote-submit')"
                                ::loading="isSubmitting"
                                ::disabled="isSubmitting || isDraftSaving"
                            />
                        </div>
                    </x-slot>
                </x-shop::modal>
            </form>
        </x-shop::form>
    </script>

    <script type="module">
        app.component('v-request-quote-modal', {
            template: '#v-request-quote-modal-template',

            props: ['cart'],

            data() {
                return {
                    isSubmitting: false,
                    isDraftSaving: false,
                    selectedFiles: [],
                    fileError: null,
                    supportedFormats: '{{ $supportedFormats }}',
                    maxFileSize: {{ $maxFileSize }},
                };
            },

            computed: {
                acceptedFileTypes() {
                    return this.supportedFormats
                        .split(',')
                        .map(format => '.' + format.trim())
                        .join(',');
                },
            },

            mounted() {
                this.$emitter.on('open-request-quote-modal', this.openModal);
            },

            methods: {
                openModal() {
                    this.$refs.requestQuoteModal.toggle();
                },

                handleFileSelect(event) {
                    const files = Array.from(event.target.files);
                    this.fileError = null;

                    const allowedFormats = this.supportedFormats.toLowerCase().split(',').map(f => f.trim());
                    const maxSizeInBytes = this.maxFileSize * 1024 * 1024;
                    let validFiles = [];

                    for (const file of files) {
                        const fileExtension = file.name.split('.').pop().toLowerCase();

                        if (! allowedFormats.includes(fileExtension)) {
                            this.fileError = `@lang('b2b::app.shop.checkout.cart.invalid-file-format', ['formats' => '${this.supportedFormats.toUpperCase()}'])`;
                            continue;
                        }

                        if (file.size > maxSizeInBytes) {
                            this.fileError = `@lang('b2b::app.shop.checkout.cart.file-too-large', ['size' => '${this.maxFileSize}MB'])`;
                            continue;
                        }

                        validFiles.push(file);
                    }

                    this.selectedFiles = this.selectedFiles.concat(validFiles);

                    if (this.$refs.fileInput) {
                        this.$refs.fileInput.value = '';
                    }
                },

                removeFile(idx) {
                    this.selectedFiles.splice(idx, 1);
                    this.fileError = null;

                    if (this.selectedFiles.length === 0 && this.$refs.fileInput) {
                        this.$refs.fileInput.value = '';
                    }
                },

                formatFileSize(bytes) {
                    if (bytes === 0) return '0 Bytes';

                    const k = 1024;
                    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                    const i = Math.floor(Math.log(bytes) / Math.log(k));

                    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
                },

                submitQuote(params, { resetForm }) {
                    this.isSubmitting = true;

                    this.submitQuoteRequest(params, resetForm, 'open');
                },

                saveAsDraft() {
                    const quoteName = this.$refs.quoteNameInput?.value?.trim() || '';
                    const description = this.$refs.descriptionInput?.value?.trim() || '';

                    if (! quoteName || ! description) {
                        this.$emitter.emit('add-flash', {
                            type: 'warning',
                            message: '@lang("b2b::app.shop.checkout.cart.request-quote.required-fields-draft")',
                        });

                        return;
                    }

                    this.isDraftSaving = true;

                    this.submitQuoteRequest({ name: quoteName, description }, () => {}, 'draft');
                },

                submitQuoteRequest(params, resetForm, status) {
                    const formData = new FormData();

                    formData.append('name', params.name);
                    formData.append('description', params.description);
                    formData.append('status', status);

                    if (this.selectedFiles.length > 0) {
                        this.selectedFiles.forEach((file) => {
                            formData.append('attachments[]', file);
                        });
                    }

                    if (this.cart) {
                        formData.append('cart_id', this.cart.id);
                    }

                    this.$axios.post('{{ route("b2b.shop.quotes.store") }}', formData, {
                        headers: { 'Content-Type': 'multipart/form-data' },
                    })
                        .then((response) => {
                            this.isSubmitting = false;
                            this.isDraftSaving = false;

                            if (response.data.redirect_url) {
                                window.location.href = response.data.redirect_url;

                                return;
                            }

                            this.selectedFiles = [];
                            this.fileError = null;
                            resetForm();
                            this.$refs.requestQuoteModal.toggle();
                        })
                        .catch((error) => {
                            this.isSubmitting = false;
                            this.isDraftSaving = false;

                            if ([400, 422].includes(error.response?.status)) {
                                this.$emitter.emit('add-flash', {
                                    type: 'warning',
                                    message: error.response.data.message,
                                });

                                return;
                            }

                            this.$emitter.emit('add-flash', {
                                type: 'error',
                                message: error.response?.data?.message || '@lang("b2b::app.shop.checkout.cart.request-failed")',
                            });
                        });
                },
            },
        });
    </script>
@endPushOnce
