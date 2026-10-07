import { test } from "../../setup";
import type { SimpleProduct } from "../../pages/admin/catalog/ProductCreatePage";
import { CompanyCatalogPage } from "../../pages/admin/b2b/CompanyCatalogPage";
import { CartPage } from "../../pages/shop/CartPage";
import { RequisitionListsPage } from "../../pages/shop/RequisitionListsPage";
import { signInAsApprovedCompany } from "../../utils/company";
import { uniqueStamp } from "../../utils/faker";
import { createQuotableProduct } from "../../utils/product";

test.describe("requisition lists", () => {
    let fastener: SimpleProduct;
    let bracket: SimpleProduct;

    test.beforeEach(async ({ adminPage }) => {
        fastener = await createQuotableProduct(adminPage, 100);
        bracket = await createQuotableProduct(adminPage, 25);
    });

    test("should save the cart into a requisition list with its quantities", async ({ shopPage, adminPage }) => {
        await signInAsApprovedCompany(shopPage, adminPage);

        const cart = new CartPage(shopPage);
        const requisitions = new RequisitionListsPage(shopPage);
        const list = `Monthly Restock ${uniqueStamp()}`;

        await cart.addProduct(fastener.urlKey!, 3);
        await cart.addProduct(bracket.urlKey!, 2);
        await requisitions.saveCartToNewList(list);
        await requisitions.openList(list);

        await requisitions.expectItemQuantity(fastener.name, 3);
        await requisitions.expectItemQuantity(bracket.name, 2);
    });

    test("should move a requisition list into the cart at the quantities saved on it", async ({ shopPage, adminPage }) => {
        await signInAsApprovedCompany(shopPage, adminPage);

        const requisitions = new RequisitionListsPage(shopPage);
        const list = `Site Supplies ${uniqueStamp()}`;

        await requisitions.createList(list);
        await requisitions.addProductToList(fastener.urlKey!, list);
        await requisitions.addProductToList(bracket.urlKey!, list);
        await requisitions.openList(list);
        await requisitions.raiseItemQuantity(fastener.name, 3);

        await requisitions.openList(list);
        await requisitions.expectItemQuantity(fastener.name, 3);
        await requisitions.moveAllItemsToCart();

        await new CartPage(shopPage).expectSubtotal("$325.00");
    });

    test("should leave the products outside a company's catalog behind when its requisition list is moved to the cart", async ({ shopPage, adminPage }) => {
        test.setTimeout(240 * 1000);

        const company = await signInAsApprovedCompany(shopPage, adminPage);
        const requisitions = new RequisitionListsPage(shopPage);
        const catalogs = new CompanyCatalogPage(adminPage);
        const list = `Standing Order ${uniqueStamp()}`;

        await requisitions.createList(list);
        await requisitions.addProductToList(fastener.urlKey!, list);
        await requisitions.addProductToList(bracket.urlKey!, list);

        await catalogs.createCatalog(`Catalog ${uniqueStamp()}`);
        await catalogs.addProductWithFlatPrice(fastener.name, 80);
        await catalogs.assignCompany(company.businessName);
        await catalogs.saveCatalog();

        await requisitions.openList(list);
        await requisitions.moveAllItemsToCart();
        await requisitions.expectOutsideCatalogNotice();

        await new CartPage(shopPage).expectSubtotal("$80.00");
    });

    test("should keep a requisition list private to the company that made it", async ({ shopPage, adminPage, browser }) => {
        await signInAsApprovedCompany(shopPage, adminPage);

        const requisitions = new RequisitionListsPage(shopPage);
        const list = `Private Supplies ${uniqueStamp()}`;

        await requisitions.createList(list);

        const listId = await requisitions.openList(list);
        const otherContext = await browser.newContext();

        try {
            const otherPage = await otherContext.newPage();

            await signInAsApprovedCompany(otherPage, adminPage);
            await new RequisitionListsPage(otherPage).expectListRefused(listId);
        } finally {
            await otherContext.close();
        }
    });
});
