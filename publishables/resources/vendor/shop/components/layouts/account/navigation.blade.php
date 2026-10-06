{{--
    B2B Suite override of the customer account navigation
    (shop::components.layouts.account.navigation).

    Keeps the core account links in their own section and groups all B2B/company
    features (company profile, company credit, users, roles, requisitions, quick orders,
    quotes, purchase orders) under a separate "Company" heading. Menu keys are left as
    `account.*` so the existing ACL / CustomerBouncer permission logic is unaffected —
    only the rendering is split.
--}}
@php
    $customer = auth()->guard('customer')->user();

    /**
     * Menu keys that belong to the B2B/company section. Anything not listed here is
     * treated as a core account item.
     */
    $b2bKeys = [
        'account.company_profile',
        'account.company_credit',
        'account.users',
        'account.roles',
        'account.requisitions',
        'account.quick_orders',
        'account.quotes',
        'account.purchase_orders',
    ];

    /**
     * The Company Profile link is permission-gated like the other B2B items: the menu is
     * already filtered by customer_bouncer(), so it only reaches here for the company
     * account or members whose role grants `company_profile`.
     */
@endphp

<div class="panel-side journal-scroll grid max-h-330 min-w-85.5 max-w-95 grid-cols-[1fr] gap-8 overflow-y-auto overflow-x-hidden max-xl:min-w-67.5 max-md:max-w-full max-md:gap-5">
    <!-- Account Profile Hero Section -->
    <div class="grid grid-cols-[auto_1fr] items-center gap-4 rounded-xl border border-zinc-200 px-5 py-6.25 max-md:py-2.5">
        <div class="">
            <img
                src="{{ $customer->image_url ??  bagisto_asset('images/user-placeholder.png') }}"
                class="h-15 w-15 rounded-full"
                alt="{{ trans('shop::app.components.layouts.account.profile-image') }}"
            >
        </div>

        <div
            class="flex flex-col justify-between"
            v-pre
        >
            <p class="text-2xl break-all font-mediums max-md:text-xl">
                Hello! {{ $customer->first_name }}
            </p>

            <p class="no-underline break-all max-md:text-md: text-zinc-500">
                {{ $customer->email }}
            </p>
        </div>
    </div>

    <!-- Account Navigation Menus -->
    @foreach (menu()->getItems('customer') as $menuItem)
        @php
            $coreChildren = $menuItem->getChildren()->reject(fn ($child) => in_array($child->getKey(), $b2bKeys));
            $b2bChildren  = $menuItem->getChildren()
                ->filter(fn ($child) => in_array($child->getKey(), $b2bKeys));
        @endphp

        <!-- Core account section -->
        <div>
            <!-- Account Navigation Toggler -->
            <div class="select-none pb-5 max-md:pb-1.5">
                <p class="text-xl font-medium max-md:text-lg">
                    {{ $menuItem->getName() }}
                </p>
            </div>

            <!-- Account Navigation Content -->
            @if ($coreChildren->isNotEmpty())
                <div class="grid rounded-md border border-b border-l border-r border-t-0 border-zinc-200 max-md:border-none">
                    @foreach ($coreChildren as $subMenuItem)
                        <a href="{{ $subMenuItem->getUrl() }}">
                            <div class="flex justify-between px-6 py-5 border-t border-zinc-200 hover:bg-zinc-100 cursor-pointer max-md:p-4 max-md:border-0 max-md:py-3 max-md:px-0 {{ $subMenuItem->isActive() ? 'bg-zinc-100' : '' }}">
                                <p class="flex items-center text-lg font-medium gap-x-4 max-sm:text-base">
                                    <span class="{{ $subMenuItem->getIcon() }} text-2xl"></span>

                                    {{ $subMenuItem->getName() }}
                                </p>

                                <span class="text-2xl icon-arrow-right rtl:icon-arrow-left"></span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Company (B2B) section -->
        @if ($b2bChildren->isNotEmpty())
            <div>
                <!-- Company Navigation Toggler -->
                <div class="select-none pb-5 max-md:pb-1.5">
                    <p class="text-xl font-medium max-md:text-lg">
                        @lang('b2b::app.shop.layouts.company')
                    </p>
                </div>

                <!-- Company Navigation Content -->
                <div class="grid rounded-md border border-b border-l border-r border-t-0 border-zinc-200 max-md:border-none">
                    @foreach ($b2bChildren as $subMenuItem)
                        <a href="{{ $subMenuItem->getUrl() }}">
                            <div class="flex justify-between px-6 py-5 border-t border-zinc-200 hover:bg-zinc-100 cursor-pointer max-md:p-4 max-md:border-0 max-md:py-3 max-md:px-0 {{ $subMenuItem->isActive() ? 'bg-zinc-100' : '' }}">
                                <p class="flex items-center text-lg font-medium gap-x-4 max-sm:text-base">
                                    <span class="{{ $subMenuItem->getIcon() }} text-2xl"></span>

                                    {{ $subMenuItem->getName() }}
                                </p>

                                <span class="text-2xl icon-arrow-right rtl:icon-arrow-left"></span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    @endforeach
</div>
