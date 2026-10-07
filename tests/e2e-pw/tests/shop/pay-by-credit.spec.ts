import { test } from "../../setup";
import type { SimpleProduct } from "../../pages/admin/catalog/ProductCreatePage";
import { CompaniesPage } from "../../pages/admin/b2b/CompaniesPage";
import { CompanyCreditSettingsPage } from "../../pages/admin/b2b/CompanyCreditSettingsPage";
import { ConfigurationPage, type SwitchValues } from "../../pages/admin/ConfigurationPage";
import { CartPage } from "../../pages/shop/CartPage";
import { CheckoutPage } from "../../pages/shop/CheckoutPage";
import { CompanyCreditPage } from "../../pages/shop/CompanyCreditPage";
import { signInAsApprovedCompany } from "../../utils/company";
import { formatPrice } from "../../utils/money";
import { createQuotableProduct } from "../../utils/product";
import {
    B2B_CREDIT_SECTION,
    COMPANY_CREDIT_SETTING,
    PAYMENT_METHODS_SECTION,
    PAY_BY_CREDIT_SETTING,
} from "../../utils/settings";

const UNIT_PRICE = 100;

test.describe("pay by credit", () => {
    let configuration: ConfigurationPage;
    let originalCreditSettings: SwitchValues;
    let originalPaymentSettings: SwitchValues;
    let product: SimpleProduct;

    test.beforeEach(async ({ adminPage }) => {
        configuration = new ConfigurationPage(adminPage);
        originalCreditSettings = await configuration.readSwitches(B2B_CREDIT_SECTION, [COMPANY_CREDIT_SETTING]);
        originalPaymentSettings = await configuration.readSwitches(PAYMENT_METHODS_SECTION, [PAY_BY_CREDIT_SETTING]);

        await configuration.applySwitches(B2B_CREDIT_SECTION, { [COMPANY_CREDIT_SETTING]: true });
        await configuration.applySwitches(PAYMENT_METHODS_SECTION, { [PAY_BY_CREDIT_SETTING]: true });

        product = await createQuotableProduct(adminPage, UNIT_PRICE);
    });

    test.afterEach(async () => {
        try {
            await configuration.applySwitches(PAYMENT_METHODS_SECTION, originalPaymentSettings);
        } finally {
            await configuration.applySwitches(B2B_CREDIT_SECTION, originalCreditSettings);
        }
    });

    test("should place an order on company credit and draw the order total from the available credit", async ({ shopPage, adminPage }) => {
        test.setTimeout(240 * 1000);

        const company = await signInAsApprovedCompany(shopPage, adminPage);
        const companyId = await new CompaniesPage(adminPage).companyId(company.email);
        const checkout = new CheckoutPage(shopPage);

        await new CompanyCreditSettingsPage(adminPage).setCreditLimit(companyId, 1000);
        await new CartPage(shopPage).addProduct(product.urlKey!, 1);

        await checkout.enterNewAddress();
        await checkout.chooseFlatRateShipping();
        await checkout.choosePayment("paybycredit");

        const orderTotal = await checkout.readGrandTotal();

        await checkout.placeOrder();

        await new CompanyCreditPage(shopPage).expectBalances({
            outstanding: formatPrice(orderTotal),
            available: formatPrice(1000 - orderTotal),
            limit: formatPrice(1000),
        });
    });

    test("should refuse a pay by credit order that exceeds the available credit", async ({ shopPage, adminPage }) => {
        test.setTimeout(240 * 1000);

        const company = await signInAsApprovedCompany(shopPage, adminPage);
        const companyId = await new CompaniesPage(adminPage).companyId(company.email);
        const checkout = new CheckoutPage(shopPage);

        await new CompanyCreditSettingsPage(adminPage).setCreditLimit(companyId, 50);
        await new CartPage(shopPage).addProduct(product.urlKey!, 1);

        await checkout.enterNewAddress();
        await checkout.chooseFlatRateShipping();
        await checkout.choosePayment("paybycredit");
        await checkout.expectPaymentDescription("paybycredit", "Exceeds available credit");
        await checkout.attemptPlaceOrder();
        await checkout.expectOrderRefused("This order exceeds your available company credit");

        await new CompanyCreditPage(shopPage).expectBalances({
            outstanding: formatPrice(0),
            available: formatPrice(50),
            limit: formatPrice(50),
        });
    });
});
