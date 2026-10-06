<p align="center">
    <img src="https://bagisto.com/wp-content/themes/bagisto/images/logo.png" />
    <h2 align="center">Open Source B2B Ecommerce Platform</h2>
</p>


<p align="center">
    <img alt="Packagist Downloads" src="https://img.shields.io/packagist/dt/bagisto/b2b-suite"> 
    <a href="https://packagist.org/packages/bagisto/b2b-suite"><img src="https://poser.pugx.org/bagisto/b2b-suite/license.svg" alt="License"></a>
</p>

---

### 1. Introduction:

**Bagisto B2B Ecommerce** is an open-source package that extends the Bagisto eCommerce
platform with powerful B2B features.

It allows companies to register their organization, submit their registration for **Admin Approval**, manage multiple users, and complete their purchasing process from a single dashboard.

The package includes features such as **Quick Order, Requisition Lists**, and **Request for Quote (RFQ)** to make ordering faster and easier.

**Role-based permissions** let companies assign roles and control what each user can access.

Administrators can manage each company's buying experience with **Company Catalogs, Company Credit, Sales Representatives**, and **Company Attributes**.

These features help customize product visibility, pricing, credit limits, company information, and customer management.

Bagisto B2B Ecommerce is built for wholesalers, manufacturers, distributors, and other
businesses that need a flexible and scalable B2B eCommerce solution.

![Bagisto B2B Ecommerce Image](https://github.com/bagisto/temp-media/blob/master/intro-banner.webp)


## Key Features

* **Company Registration & Approval** – Companies can register from the storefront and manage their organization profile, with optional admin approval before activation.
* **Company User Management** – Companies can add new users directly or invite existing store customers to join their account.
* **Role-Based Permissions** – Create custom company roles and grant granular access control to users within the company.
* **Requisition Lists** – Save products to reusable lists for faster bulk and repeat ordering.
* **Quick Order** – Add multiple products to the cart instantly using SKUs or CSV upload.
* **Request for Quote (RFQ)** – Companies can submit quote requests directly from the cart on the storefront.
* **Quotation Handling** – End-to-end buyer–seller negotiation, with messaging, on both the storefront and admin.
* **Purchase Orders** – Track and manage procurement across the company, from both the storefront and admin.
* **Company Catalogs** – Per-company product and category visibility with custom flat, percentage, and quantity-tier pricing.
* **Company Credit (Pay By Credit)** – Per-company credit limits with an audited ledger, reimbursements, and a *Pay By Credit* checkout method.
* **Sales Representatives** – Assign admin users to manage specific companies and handle their negotiations.
* **Company Attributes** – Extend the company profile with custom fields, optionally shown on the registration form.
* **Companies Management (Admin End)** – Admins can create, approve, disable, and manage companies from the backend.
* **Localization & RTL** – Full right-to-left support and translations for all 22 supported locales.

![Bagisto B2B Ecommerce Features Image](https://github.com/bagisto/temp-media/blob/master/b2b-ecommerce-platform-banner.webp)

## Admin Configuration Options

All B2B settings are grouped together under **Admin Panel → Configure → B2B Suite**, so you can control company onboarding, quotations, company credit, and email notifications from a single place.

**General**

* **Enable B2B Suite** – Master switch for the entire suite. When disabled, all B2B routes, menus, the company registration option, and every company-specific feature are hidden.
* **Require Company Approval** – When enabled, newly registered companies stay in a **Pending** state and cannot sign in until an administrator approves them.
* **Number Of Requisition Lists** – The maximum number of requisition lists each company is allowed to create.

**Quotations & Purchase Orders**

* **Quotation Prefix / Purchase Order Prefix** – Prefixes used when generating quotation (`QO`) and purchase order (`PO`) numbers.
* **Default Padding** – Zero-padding applied to the auto-incrementing quotation / purchase order number.
* **Default Expiration Period & Unit** – How long a quotation stays valid, in Days, Weeks, or Months.
* **Minimum Cart Amount & Message** – The minimum cart total required before a customer can request a quote, and the message shown when it is not met.
* **Supported File Formats & Maximum File Size** – Allowed attachment extensions and size limit for quotation uploads.

**Company Credit**

* **Enable Company Credit** – Lets companies with an assigned credit limit pay using the **Pay By Credit** method at checkout.

**B2B Email Notifications**

* Per-notification toggles for the entire B2B lifecycle — company registration/approval, sub-user management, credit updates, quotation negotiation, and purchase orders. Buyer emails go to the company; seller emails go to the assigned sales representative (or the store email).

![Bagisto B2B Ecommerce Features Image](https://github.com/bagisto/temp-media/blob/master/b2b-suit-admin-feaures.webp)

---

## 📖 Documentation & Demo

- **User Guide:** [User Guide](https://docs.bagisto.com/b2b-ecommerce-platform/introduction.html)
- **Live Demo:** [Live Demo](https://demo.bagisto.com/b2b-suite)

### Feature Guides

| Guide | Description |
| --- | --- |
| [Configuration](https://docs.bagisto.com/b2b-ecommerce-platform/configuration.html) | Enable and configure the suite, quotations, company credit, and email notifications. |
| [Company Registration](https://docs.bagisto.com/b2b-ecommerce-platform/company-registration.html) | Storefront company sign-up, sign in, and admin-side company management. |
| [Role Based Permissions](https://docs.bagisto.com/b2b-ecommerce-platform/role-based-permissions.html) | Custom company roles, company user management, and inviting existing customers. |
| [Company Attributes](https://docs.bagisto.com/b2b-ecommerce-platform/company-attributes.html) | Extend the company profile with custom fields and attribute mapping. |
| [Company Catalog](https://docs.bagisto.com/b2b-ecommerce-platform/company-catalog.html) | Per-company product/category visibility with custom and tier pricing. |
| [Company Credit](https://docs.bagisto.com/b2b-ecommerce-platform/company-credit.html) | Credit limits, audited ledger, reimbursements, and Pay By Credit checkout. |
| [Sales Representative](https://docs.bagisto.com/b2b-ecommerce-platform/sales-representative.html) | Assign admin users to manage specific companies and their negotiations. |
| [Quick Order](https://docs.bagisto.com/b2b-ecommerce-platform/quick-order.html) | Add products to the cart in bulk via SKU or CSV upload. |
| [Requisition Lists](https://docs.bagisto.com/b2b-ecommerce-platform/requisition-lists.html) | Save products to reusable lists for faster repeat ordering. |
| [Request for Quote](https://docs.bagisto.com/b2b-ecommerce-platform/request-for-quote.html) | Request custom pricing and negotiate from the cart. |
| [Quotation Handling](https://docs.bagisto.com/b2b-ecommerce-platform/quotation-handling.html) | End-to-end buyer–seller quotation negotiation and messaging. |
| [Purchase Orders](https://docs.bagisto.com/b2b-ecommerce-platform/purchase-orders.html) | Track and manage procurement from the storefront and admin. |

### 2. Requirements:

* **Bagisto**: v2.5.x
* **PHP**: 8.4 or higher

> **Which suite version do I need?** The suite's major version tracks the Bagisto release it
> supports, and the two numbers do not match. Install the line that matches your Bagisto:
>
> | Bagisto | B2B Suite | Branch |
> |---|---|---|
> | v2.5.x | v3.0 and later | `3.0` |
> | v2.4.9 – v2.4.x | v2.0 – v2.2 | `2.0` |
>
> Installing a suite line against the wrong Bagisto is not supported — Bagisto 2.5 moved to
> Laravel 13, PHP 8.4 and Tailwind CSS 4, and renamed routes and translation keys the suite
> consumes.

#### Upgrading from v2.x

v3.0 no longer overrides the storefront cart page. `vendor:publish` only writes files, so the
copy an earlier version published stays behind and keeps overriding Bagisto's own cart — which
on 2.5 costs you the cart item's remove action. Delete it once, after upgrading:

```bash
rm -f resources/views/vendor/shop/checkout/cart/index.blade.php
php artisan optimize:clear
```

---

### 3. Installation:

#### Step 1: Install via Composer

```bash
composer require bagisto/b2b-suite
```

#### Step 2: Register the Service Provider

Add the provider to the array returned by `bootstrap/providers.php` (Bagisto v2.5 runs on Laravel 13, which registers providers here rather than in `config/app.php`):

> **Note:** Composer package auto-discovery is **not possible** for this provider. Order matters—`B2BSuiteServiceProvider` must be listed **after** the Shop package (or last in the array). Auto-discovery would load it too early, which can cause issues.

```php
return [
    // ...other service providers
    Webkul\B2BSuite\Providers\B2BSuiteServiceProvider::class,
];
```

#### Step 3: Run the installation command

```bash
php artisan b2b-suite:install
```

> That’s it! The package is now installed and ready to use in your Bagisto project.

#### Frontend assets

The package **ships prebuilt admin & shop theme bundles** (each core theme with the B2B
views folded in). They are published automatically by `b2b-suite:install`, so a normal
install **does not need Node, npm, or a Tailwind build**.

If you upgrade or customize the core **Shop/Admin** theme and notice any B2B styling
breakage, just rebuild the bundles. `npm run build` writes the regenerated bundles
straight into `public/themes/.../build`, so there's nothing else to publish. The build
resolves the core themes relative to the app, so it works from `vendor/`.

> **Prerequisite:** the B2B build reuses the **Shop** and **Admin** theme dependencies
> (Vue plugin, theme JS), so make sure `npm install` has been run in **both** core theme
> packages first:
>
> ```bash
> ( cd packages/Webkul/Shop  && npm install )
> ( cd packages/Webkul/Admin && npm install )
> ```

```bash
cd vendor/bagisto/b2b-suite
npm install
npm run build   # rebuilds admin + shop directly into public/themes/.../build

php artisan optimize:clear
```

> Individual themes can be rebuilt with `npm run build:admin` or `npm run build:shop`
> (and `npm run dev:admin` / `npm run dev:shop` for hot-reload while developing).

---

### 4. License

The B2B Ecommerce is open-sourced software licensed under the **MIT License**.

---

### 5. Support

For issues, questions, or contributions, please contact the **[Webkul Team](https://webkul.com/contacts/)**.
