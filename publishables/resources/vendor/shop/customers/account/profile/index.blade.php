{{--
    B2B override of the storefront "Account / Profile" page. Wraps the personal profile in a
    card (matching the Company Information card), moves the Delete action to the header on the
    right, and renders the Company Information card directly underneath. Core render events are
    preserved so other extensions keep working.
--}}
<x-shop::layouts.account>
    <!-- Page Title -->
    <x-slot:title>
        @lang('shop::app.customers.account.profile.index.title')
    </x-slot>

    <!-- Breadcrumbs -->
    @if ((core()->getConfigData('general.general.breadcrumbs.shop')))
        @section('breadcrumbs')
            <x-shop::breadcrumbs name="profile" />
        @endSection
    @endif

    <div class="max-md:hidden">
        <x-shop::layouts.account.navigation />
    </div>

    <div class="mx-4 flex-auto max-md:mx-6 max-sm:mx-4">
        <div class="flex flex-col gap-6">
            <!-- ============ Company Information (B2B) ============ -->
            @if ((bool) core()->getConfigData('b2b.general.settings.active'))
                @include('b2b::shop.customers.account.profile.company-details')
            @endif

            <!-- ============ Profile Information ============ -->
            <div class="rounded-xl border border-zinc-200 bg-white">
                <!-- Header: title (+ mobile back) and actions on the right -->
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-200 px-6 py-4 max-sm:px-4">
                    <div class="flex items-center gap-2.5">
                        <a
                            class="grid md:hidden"
                            href="{{ route('shop.customers.account.index') }}"
                        >
                            <span class="text-2xl icon-arrow-left rtl:icon-arrow-right"></span>
                        </a>

                        <h3 class="text-lg font-medium text-zinc-800">
                            @lang('b2b::app.shop.customers.account.profile.profile-information')
                        </h3>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        {!! view_render_event('bagisto.shop.customers.account.profile.edit_button.before') !!}

                        <a
                            href="{{ route('shop.customers.account.profile.edit') }}"
                            class="secondary-button border-zinc-200 px-5 py-2 text-sm font-normal"
                        >
                            @lang('shop::app.customers.account.profile.index.edit')
                        </a>

                        {!! view_render_event('bagisto.shop.customers.account.profile.edit_button.after') !!}

                        <!-- Delete profile (right side) -->
                        {!! view_render_event('bagisto.shop.customers.account.profile.delete.before') !!}

                        <x-shop::form action="{{ route('shop.customers.account.profile.destroy') }}">
                            <x-shop::modal>
                                <x-slot:toggle>
                                    <button
                                        type="button"
                                        class="secondary-button border-zinc-200 px-5 py-2 text-sm font-normal text-red-600 hover:bg-red-50"
                                    >
                                        @lang('shop::app.customers.account.profile.index.delete-profile')
                                    </button>
                                </x-slot>

                                <x-slot:header>
                                    <h2 class="text-2xl font-medium max-md:text-base">
                                        @lang('shop::app.customers.account.profile.index.enter-password')
                                    </h2>
                                </x-slot>

                                <x-slot:content>
                                    <x-shop::form.control-group class="mb-0!">
                                        <x-shop::form.control-group.control
                                            type="password"
                                            name="password"
                                            class="px-6 py-4"
                                            rules="required"
                                            :placeholder="trans('shop::app.customers.account.profile.index.enter-password')"
                                        />

                                        <x-shop::form.control-group.error
                                            class="ltr:text-left rtl:text-right"
                                            control-name="password"
                                        />
                                    </x-shop::form.control-group>
                                </x-slot>

                                <x-slot:footer>
                                    <button
                                        type="submit"
                                        class="flex rounded-2xl primary-button px-11 py-3 max-md:rounded-lg max-md:px-6 max-md:text-sm"
                                    >
                                        @lang('shop::app.customers.account.profile.index.delete')
                                    </button>
                                </x-slot>
                            </x-shop::modal>
                        </x-shop::form>

                        {!! view_render_event('bagisto.shop.customers.account.profile.delete.after') !!}
                    </div>
                </div>

                <!-- Fields -->
                <div class="px-6 max-sm:px-4">
                    {!! view_render_event('bagisto.shop.customers.account.profile.first_name.before') !!}

                    <div class="grid w-full grid-cols-[2fr_3fr] border-b border-zinc-100 py-3">
                        <p class="text-sm font-medium">@lang('shop::app.customers.account.profile.index.first-name')</p>
                        <p class="text-sm font-medium text-zinc-500" v-pre>{{ $customer->first_name }}</p>
                    </div>

                    {!! view_render_event('bagisto.shop.customers.account.profile.first_name.after') !!}

                    {!! view_render_event('bagisto.shop.customers.account.profile.last_name.before') !!}

                    <div class="grid w-full grid-cols-[2fr_3fr] border-b border-zinc-100 py-3">
                        <p class="text-sm font-medium">@lang('shop::app.customers.account.profile.index.last-name')</p>
                        <p class="text-sm font-medium text-zinc-500" v-pre>{{ $customer->last_name }}</p>
                    </div>

                    {!! view_render_event('bagisto.shop.customers.account.profile.last_name.after') !!}

                    {!! view_render_event('bagisto.shop.customers.account.profile.gender.before') !!}

                    <div class="grid w-full grid-cols-[2fr_3fr] border-b border-zinc-100 py-3">
                        <p class="text-sm font-medium">@lang('shop::app.customers.account.profile.index.gender')</p>
                        <p class="text-sm font-medium text-zinc-500" v-pre>{{ $customer->gender ?? '—' }}</p>
                    </div>

                    {!! view_render_event('bagisto.shop.customers.account.profile.gender.after') !!}

                    {!! view_render_event('bagisto.shop.customers.account.profile.date_of_birth.before') !!}

                    <div class="grid w-full grid-cols-[2fr_3fr] border-b border-zinc-100 py-3">
                        <p class="text-sm font-medium">@lang('shop::app.customers.account.profile.index.dob')</p>
                        <p class="text-sm font-medium text-zinc-500" v-pre>{{ $customer->date_of_birth?->format('Y-m-d') ?? '—' }}</p>
                    </div>

                    {!! view_render_event('bagisto.shop.customers.account.profile.date_of_birth.after') !!}

                    {!! view_render_event('bagisto.shop.customers.account.profile.email.before') !!}

                    <div class="grid w-full grid-cols-[2fr_3fr] py-3">
                        <p class="text-sm font-medium">@lang('shop::app.customers.account.profile.index.email')</p>
                        <p class="text-sm font-medium text-zinc-500" v-pre>{{ $customer->email }}</p>
                    </div>

                    {!! view_render_event('bagisto.shop.customers.account.profile.email.after') !!}
                </div>
            </div>
        </div>
    </div>
</x-shop::layouts.account>
