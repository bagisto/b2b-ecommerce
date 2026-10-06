<?php

use Illuminate\Support\Facades\Route;
use Webkul\B2BSuite\Http\Controllers\Shop\InvitationController;
use Webkul\B2BSuite\Http\Controllers\Shop\PurchaseOrderController;
use Webkul\B2BSuite\Http\Controllers\Shop\QuickOrderController;
use Webkul\B2BSuite\Http\Controllers\Shop\QuoteController;
use Webkul\B2BSuite\Http\Controllers\Shop\RequisitionListController;
use Webkul\B2BSuite\Http\Controllers\Shop\RoleController;
use Webkul\B2BSuite\Http\Controllers\Shop\UserController;
use Webkul\Core\Http\Middleware\NoCacheMiddleware;
use Webkul\Shop\Http\Controllers\Customer\CustomerController;
use Webkul\Shop\Http\Controllers\Customer\RegistrationController;

/**
 * Company registration route (public, pre-authentication).
 */
Route::post('companies/register', [RegistrationController::class, 'save'])->name('shop.companies.register.store');

Route::group(['middleware' => ['theme', 'locale', 'currency', 'customer', 'customer_bouncer', NoCacheMiddleware::class], 'prefix' => 'customer/account'], function () {
    /**
     * Company Profile Routes.
     */
    Route::controller(CustomerController::class)->prefix('company-profile')->group(function () {
        Route::get('', 'companyProfile')->name('shop.customers.account.company_profile.index');

        Route::get('edit', 'companyProfileEdit')->name('shop.customers.account.company_profile.edit');

        Route::put('{id}', 'modify')->name('shop.customers.account.company_profile.update');
    });

    /**
     * Company Credit Routes.
     */
    Route::controller(CustomerController::class)->prefix('company-credit')->group(function () {
        Route::get('', 'companyCredit')->name('shop.customers.account.credit.index');
    });

    /**
     * Quotes Routes.
     */
    Route::controller(QuoteController::class)->prefix('quotes')->group(function () {
        Route::get('', 'index')->name('shop.customers.account.quotes.index');

        Route::post('', 'store')->name('b2b.shop.quotes.store');

        Route::get('{id}', 'view')->name('shop.customers.account.quotes.view');

        Route::put('edit/{id}', 'update')->name('shop.customers.account.quotes.update');

        Route::post('{id}/submit', 'submitQuote')->name('shop.customers.account.quotes.submit_quote');

        Route::get('{id}/messages', 'getMessages')->name('shop.customers.account.quotes.messages');

        Route::put('{id}', 'updateCart')->name('shop.customers.account.quotes.add_to_cart');

        Route::get('{id}/attachments/{attachment}', 'download')->name('shop.customers.account.quotes.download');

        Route::post('{id}/delete', 'deleteQuote')->name('shop.customers.account.quotes.delete_quote');

        Route::post('{id}/send-message', 'sendMessage')->name('shop.customers.account.quotes.send_message');

        Route::post('{id}/reject-quote', 'rejectQuote')->name('shop.customers.account.quotes.reject_quote');
    });

    /**
     * Purchase Orders Routes.
     */
    Route::controller(PurchaseOrderController::class)->prefix('purchase-orders')->group(function () {
        Route::get('', 'index')->name('shop.customers.account.purchase_orders.index');

        Route::get('{id}', 'view')->name('shop.customers.account.purchase_orders.view');
    });

    /**
     * Requisitions List Routes.
     */
    Route::controller(RequisitionListController::class)->prefix('requisitions')->group(function () {
        Route::get('', 'index')->name('shop.customers.account.requisitions.index');

        Route::get('get', 'list')->name('shop.customers.account.requisitions.list');

        Route::post('create', 'store')->name('shop.customers.account.requisitions.store');

        Route::get('edit/{id}', 'edit')->name('shop.customers.account.requisitions.edit');

        Route::put('edit/{id}', 'update')->name('shop.customers.account.requisitions.update');

        Route::get('get-product', 'getProduct')->name('shop.customers.account.requisitions.get_product');

        Route::post('add-product', 'addProduct')->name('shop.customers.account.requisitions.add_product');

        Route::get('get-items', 'getItems')->name('shop.customers.account.requisitions.items');

        Route::post('move-to-cart', 'moveToCart')->name('shop.customers.account.requisitions.move_to_cart');

        Route::put('update-items', 'updateItems')->name('shop.customers.account.requisitions.update_items');

        Route::delete('delete-items', 'destroyItems')->name('shop.customers.account.requisitions.delete_items');

        Route::post('delete/{id}', 'destroy')->name('shop.customers.account.requisitions.delete');
    });

    /**
     * Quick Order Routes.
     */
    Route::controller(QuickOrderController::class)->prefix('quick-orders')->group(function () {
        Route::get('', 'index')->name('shop.customers.account.quick_orders.index');

        Route::post('', 'store')->name('shop.customers.account.quick_orders.store');

        Route::get('search', 'search')->name('shop.customers.account.quick_orders.search');

        Route::post('fetch-by-skus', 'fetchBySkus')->name('shop.customers.account.quick_orders.fetchBySkus');

        Route::get('download-sample', 'downloadSample')->name('shop.customers.account.quick_orders.downloadSample');
    });

    /**
     * Users Routes.
     */
    Route::controller(UserController::class)->prefix('users')->group(function () {
        Route::get('', 'index')->name('shop.customers.account.users.index');

        Route::get('create', 'create')->name('shop.customers.account.users.create');

        Route::post('create', 'store')->name('shop.customers.account.users.store');

        Route::get('add-existing', 'addExisting')->name('shop.customers.account.users.add_existing');

        Route::post('add-existing', 'invite')->name('shop.customers.account.users.invite');

        Route::post('invitations/{id}/revoke', 'revokeInvitation')->name('shop.customers.account.users.revoke_invitation');

        Route::get('edit/{id}', 'edit')->name('shop.customers.account.users.edit');

        Route::put('edit/{id}', 'update')->name('shop.customers.account.users.update');

        Route::post('delete/{id}', 'destroy')->name('shop.customers.account.users.delete');
    });

    /**
     * Roles routes.
     */
    Route::controller(RoleController::class)->prefix('roles')->group(function () {
        Route::get('', 'index')->name('shop.customers.account.roles.index');

        Route::get('create', 'create')->name('shop.customers.account.roles.create');

        Route::post('create', 'store')->name('shop.customers.account.roles.store');

        Route::get('edit/{id}', 'edit')->name('shop.customers.account.roles.edit');

        Route::put('edit/{id}', 'update')->name('shop.customers.account.roles.update');

        Route::post('edit/{id}', 'destroy')->name('shop.customers.account.roles.delete');
    });

    /**
     * Company invitation accept / decline (invitee side).
     */
    Route::controller(InvitationController::class)->prefix('invitations')->group(function () {
        Route::get('{token}', 'show')->name('shop.customers.account.invitations.show');

        Route::post('{token}/accept', 'accept')->name('shop.customers.account.invitations.accept');

        Route::post('{token}/decline', 'decline')->name('shop.customers.account.invitations.decline');
    });
});
