{{--
    B2B Suite override of the storefront sign-in page (shop::customers.sign-in).

    Registered (prepended to the `shop` view namespace) only while the B2B Suite is
    active — see B2BSuiteServiceProvider. Lays the page out in two columns: the login
    form on the left and the customer / company account creation options on the right.
--}}

<!-- SEO Meta Content -->
@push('meta')
    <meta name="description" content="@lang('shop::app.customers.login-form.page-title')"/>

    <meta name="keywords" content="@lang('shop::app.customers.login-form.page-title')"/>
@endPush

<x-shop::layouts
    :has-header="false"
    :has-feature="false"
    :has-footer="false"
>
    <!-- Page Title -->
    <x-slot:title>
        @lang('shop::app.customers.login-form.page-title')
    </x-slot>

    <div class="container mt-20 max-1180:px-5 max-md:mt-12">
        {!! view_render_event('bagisto.shop.customers.login.logo.before') !!}

        <!-- Company Logo -->
        <div class="flex items-center gap-x-14 max-[1180px]:gap-x-9">
            <a
                href="{{ route('shop.home.index') }}"
                class="m-[0_auto_20px_auto]"
                aria-label="@lang('shop::app.customers.login-form.bagisto')"
            >
                <img
                    src="{{ core()->getCurrentChannel()->logo_url ?? bagisto_asset('images/logo.svg') }}"
                    alt="{{ config('app.name') }}"
                    width="131"
                    height="29"
                >
            </a>
        </div>

        {!! view_render_event('bagisto.shop.customers.login.logo.after') !!}

        <!-- Page Heading -->
        <h1 class="font-dmserif text-4xl text-navyBlue max-md:text-3xl max-sm:text-2xl">
            @lang('shop::app.customers.login-form.page-title')
        </h1>

        <!-- Two-column container -->
        <div class="b2b-account-grid mt-10 grid grid-cols-2 max-md:grid-cols-1">
            {{--
                ====================================================
                LEFT — Registered customers (login form)
                ====================================================
            --}}
            <div>
                <h2 class="border-b border-zinc-200 pb-3 text-xl font-semibold text-navyBlue">
                    @lang('b2b::app.shop.sign-in.registered-customers')
                </h2>

                <p class="mt-5 text-base text-zinc-500 max-sm:text-sm">
                    @lang('b2b::app.shop.sign-in.registered-customers-text')
                </p>

                {!! view_render_event('bagisto.shop.customers.login.before') !!}

                <div class="mt-8 rounded">
                    <x-shop::form :action="route('shop.customer.session.create')">
                        {!! view_render_event('bagisto.shop.customers.login_form_controls.before') !!}

                        <!-- Email -->
                        <x-shop::form.control-group>
                            <x-shop::form.control-group.label class="required">
                                @lang('shop::app.customers.login-form.email')
                            </x-shop::form.control-group.label>

                            <x-shop::form.control-group.control
                                type="email"
                                class="px-6 py-4 max-md:py-3 max-sm:py-2"
                                name="email"
                                rules="required|email"
                                value=""
                                :label="trans('shop::app.customers.login-form.email')"
                                placeholder="email@example.com"
                                :aria-label="trans('shop::app.customers.login-form.email')"
                                aria-required="true"
                            />

                            <x-shop::form.control-group.error control-name="email" />
                        </x-shop::form.control-group>

                        <!-- Password -->
                        <x-shop::form.control-group>
                            <x-shop::form.control-group.label class="required">
                                @lang('shop::app.customers.login-form.password')
                            </x-shop::form.control-group.label>

                            <x-shop::form.control-group.control
                                type="password"
                                class="px-6 py-4 max-md:py-3 max-sm:py-2"
                                id="password"
                                name="password"
                                rules="required|min:6"
                                value=""
                                :label="trans('shop::app.customers.login-form.password')"
                                :placeholder="trans('shop::app.customers.login-form.password')"
                                :aria-label="trans('shop::app.customers.login-form.password')"
                                aria-required="true"
                            />

                            <x-shop::form.control-group.error control-name="password" />
                        </x-shop::form.control-group>

                        <div class="flex justify-between">
                            <div class="flex select-none items-center gap-1.5">
                                <input
                                    type="checkbox"
                                    id="show-password"
                                    class="peer hidden"
                                    onchange="switchVisibility()"
                                />

                                <label
                                    class="icon-uncheck peer-checked:icon-check-box cursor-pointer text-2xl text-navyBlue peer-checked:text-navyBlue max-sm:text-xl"
                                    for="show-password"
                                ></label>

                                <label
                                    class="cursor-pointer select-none text-base text-zinc-500 max-sm:text-sm ltr:pl-0 rtl:pr-0"
                                    for="show-password"
                                >
                                    @lang('shop::app.components.form.control-group.control.show-password')
                                </label>
                            </div>

                            <div class="block">
                                <a
                                    href="{{ route('shop.customers.forgot_password.create') }}"
                                    class="cursor-pointer text-base text-black max-sm:text-sm"
                                >
                                    <span>
                                        @lang('shop::app.customers.login-form.forgot-pass')
                                    </span>
                                </a>
                            </div>
                        </div>

                        <!-- Captcha -->
                        @if (core()->getConfigData('customer.captcha.credentials.status'))
                            <x-shop::form.control-group class="mt-5">
                                {!! \Webkul\Customer\Facades\Captcha::render() !!}

                                <x-shop::form.control-group.error control-name="recaptcha_token" />
                            </x-shop::form.control-group>
                        @endif

                        <!-- Submit Button -->
                        <div class="mt-8">
                            <button
                                class="primary-button m-0 block w-full max-w-[374px] rounded-2xl px-11 py-4 text-center text-base max-md:max-w-full max-md:rounded-lg max-md:py-3 max-sm:py-2.5 ltr:ml-0 rtl:mr-0"
                                type="submit"
                            >
                                @lang('shop::app.customers.login-form.button-title')
                            </button>
                        </div>

                        <!-- Social login icons (and other extensions) -->
                        <div class="b2b-social-slot">
                            {!! view_render_event('bagisto.shop.customers.login_form_controls.after') !!}
                        </div>
                    </x-shop::form>
                </div>

                {!! view_render_event('bagisto.shop.customers.login.after') !!}

                @if (
                    request()->cookie('enable-resend')
                    && request()->cookie('email-for-resend')
                )
                    <p class="mt-5 font-medium text-zinc-500 max-sm:text-sm">
                        <a
                            class="text-navyBlue"
                            href="{{ route('shop.customers.resend.verification_email', urlencode(request()->cookie('email-for-resend'))) }}"
                        >
                            @lang('shop::app.customers.login-form.resend-verification')
                        </a>
                    </p>
                @endif

                <p class="mt-8 text-sm font-medium text-red-600">
                    @lang('b2b::app.shop.sign-in.required-fields')
                </p>
            </div>

            {{--
                ====================================================
                RIGHT — New customer & new company account options
                ====================================================
            --}}
            <div class="b2b-account-options">
                <!-- New Customers -->
                <section>
                    <h2 class="border-b border-zinc-200 pb-3 text-xl font-semibold text-navyBlue">
                        @lang('b2b::app.shop.sign-in.new-customers')
                    </h2>

                    <p class="mt-5 text-base text-zinc-500 max-sm:text-sm">
                        @lang('b2b::app.shop.sign-in.new-customers-text')
                    </p>

                    <a
                        href="{{ route('shop.customers.register.index') }}"
                        class="primary-button mt-6 inline-flex rounded-2xl px-8 py-4 text-center text-base max-md:rounded-lg max-md:py-3"
                    >
                        @lang('b2b::app.shop.sign-in.create-an-account')
                    </a>
                </section>

                <!-- New Company Account (only when the B2B Suite is active) -->
                @if (core()->getConfigData('b2b.general.settings.active'))
                    <section class="mt-12">
                        <h2 class="border-b border-zinc-200 pb-3 text-xl font-semibold text-navyBlue">
                            @lang('b2b::app.shop.sign-in.new-company-account')
                        </h2>

                        <p class="mt-5 text-base text-zinc-500 max-sm:text-sm">
                            @lang('b2b::app.shop.sign-in.new-company-account-text')
                        </p>

                        <a
                            href="{{ route('shop.customers.register.index', ['type' => 'company']) }}"
                            class="primary-button mt-6 inline-flex rounded-2xl px-8 py-4 text-center text-base max-md:rounded-lg max-md:py-3"
                        >
                            @lang('b2b::app.shop.sign-in.create-a-company-account')
                        </a>
                    </section>
                @endif
            </div>
        </div>

        <p class="mb-4 mt-12 text-center text-xs text-zinc-500">
            @lang('shop::app.customers.login-form.footer', ['current_year'=> date('Y') ])
        </p>
    </div>

    @push('styles')
        <style>
            /* Two-column layout helpers (classes not present in the core shop stylesheet). */
            .b2b-account-grid {
                gap: 4rem;
            }

            .b2b-social-slot {
                margin-top: 1.5rem;
            }

            .b2b-social-slot:empty {
                margin-top: 0;
            }

            @media (min-width: 1024px) {
                .b2b-account-options {
                    border-inline-start: 1px solid #e4e4e7;
                    padding-inline-start: 4rem;
                }
            }

            @media (max-width: 1023px) {
                .b2b-account-grid {
                    gap: 2.5rem;
                }

                .b2b-account-options {
                    border-top: 1px solid #e4e4e7;
                    padding-top: 2.5rem;
                }
            }
        </style>
    @endpush

    @push('scripts')
        {!! \Webkul\Customer\Facades\Captcha::renderJS() !!}

        <script>
            function switchVisibility() {
                let passwordField = document.getElementById("password");

                passwordField.type = passwordField.type === "password"
                    ? "text"
                    : "password";
            }
        </script>
    @endpush
</x-shop::layouts>
