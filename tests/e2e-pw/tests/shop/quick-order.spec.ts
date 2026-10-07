import { test } from "../../setup";
import type { SimpleProduct } from "../../pages/admin/catalog/ProductCreatePage";
import { CompanyCatalogPage } from "../../pages/admin/b2b/CompanyCatalogPage";
import { CartPage } from "../../pages/shop/CartPage";
import { QuickOrderPage } from "../../pages/shop/QuickOrderPage";
import { signInAsApprovedCompany } from "../../utils/company";
import { uniqueStamp } from "../../utils/faker";
import { createQuotableProduct } from "../../utils/product";

test.describe("quick order", () => {
    let fastener: SimpleProduct;
    let bracket: SimpleProduct;

    test.beforeEach(async ({ adminPage }) => {
        fastener = await createQuotableProduct(adminPage, 100);
        bracket = await createQuotableProduct(adminPage, 25);
    });

    test("should add the products found by name and sku to the cart at the chosen quantities", async ({ shopPage, adminPage }) => {
        await signInAsApprovedCompany(shopPage, adminPage);

        const quickOrder = new QuickOrderPage(shopPage);

        await quickOrder.open();
        await quickOrder.selectProduct(fastener.name, fastener.name);
        await quickOrder.selectProduct(bracket.sku, bracket.name);
        await quickOrder.expectRemovable(bracket.name);
        await quickOrder.setQuantity(fastener.name, 2);
        await quickOrder.addSelectedToCart();

        await new CartPage(shopPage).expectSubtotal("$225.00");
    });

    test("should add the products listed in an uploaded csv to the cart", async ({ shopPage, adminPage }) => {
        await signInAsApprovedCompany(shopPage, adminPage);

        const quickOrder = new QuickOrderPage(shopPage);

        await quickOrder.open();
        await quickOrder.uploadCsv([
            { sku: fastener.sku, quantity: 3 },
            { sku: bracket.sku, quantity: 4 },
        ]);

        await quickOrder.expectSelectedQuantity(fastener.name, 3);
        await quickOrder.expectSelectedQuantity(bracket.name, 4);
        await quickOrder.addSelectedToCart();

        await new CartPage(shopPage).expectSubtotal("$400.00");
    });

    test("should keep the products outside a company's catalog out of its quick order", async ({ shopPage, adminPage }) => {
        test.setTimeout(240 * 1000);

        const company = await signInAsApprovedCompany(shopPage, adminPage);
        const catalogs = new CompanyCatalogPage(adminPage);
        const quickOrder = new QuickOrderPage(shopPage);

        await catalogs.createCatalog(`Catalog ${uniqueStamp()}`);
        await catalogs.addProductWithFlatPrice(fastener.name, 80);
        await catalogs.assignCompany(company.businessName);
        await catalogs.saveCatalog();

        await quickOrder.open();
        await quickOrder.selectProduct(fastener.name, fastener.name);
        await quickOrder.expectNoSearchResults(bracket.name);
        await quickOrder.uploadCsv([{ sku: bracket.sku, quantity: 2 }]);
        await quickOrder.expectNotSelected(bracket.name);
        await quickOrder.addSelectedToCart();

        await new CartPage(shopPage).expectSubtotal("$80.00");
    });
});
