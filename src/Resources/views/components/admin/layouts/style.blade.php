{{--
    Injected into the core admin layout head by `Providers/EventServiceProvider`.

    It must stay on `head.before`, not `head.after`: loading a utility sheet after
    the core one lets its plain utilities override core's responsive variants,
    which previously broke the responsive flash toasts and the admin sidebar.

    From Bagisto 2.5 the core bundle is built with Tailwind 4, which puts every rule
    in a cascade layer. An unlayered sheet beats all layered rules whatever their
    order, so there this sheet is imported into its own `b2b-suite` layer, ordered
    above core's reset and below its components and utilities — the same outcome
    the load order gives on the unlayered Tailwind 3 bundle of Bagisto 2.4.
--}}
@if (version_compare(core()->version(), '2.5.0-dev', '>='))
    <style>
        @layer properties, theme, base, b2b-suite, components, utilities;

        @import url("{{ themes()->setBagistoVite('src/Resources/assets/css/admin.css', 'b2b-suite-admin')->asset('src/Resources/assets/css/admin.css') }}") layer(b2b-suite);
    </style>
@else
    @bagistoVite(['src/Resources/assets/css/admin.css'], 'b2b-suite-admin')
@endif
