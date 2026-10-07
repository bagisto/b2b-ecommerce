import { test } from "../../setup";
import type { SimpleProduct } from "../../pages/admin/catalog/ProductCreatePage";
import { CompanyCatalogPage } from "../../pages/admin/b2b/CompanyCatalogPage";
import { ProductPage } from "../../pages/shop/ProductPage";
import { signInAsApprovedCompany } from "../../utils/company";
import { uniqueStamp } from "../../utils/faker";
import { createQuotableProduct } from "../../utils/product";

test.describe("company catalogs", () => {
    let catalogProduct: SimpleProduct;
    let excludedProduct: SimpleProduct;

    test.beforeEach(async ({ adminPage }) => {
        catalogProduct = await createQuotableProduct(adminPage, 100);
        excludedProduct = await createQuotableProduct(adminPage, 100);
    });

    test("should show a catalog company only its catalog products at the catalog price", async ({ shopPage, adminPage }) => {
        test.setTimeout(240 * 1000);

        const company = await signInAsApprovedCompany(shopPage, adminPage);
        const catalogs = new CompanyCatalogPage(adminPage);
        const productPage = new ProductPage(shopPage);

        await catalogs.createCatalog(`Catalog ${uniqueStamp()}`);
        await catalogs.addProductWithFlatPrice(catalogProduct.name, 80, { qty: 10, price: 70 });
        await catalogs.assignCompany(company.businessName);
        await catalogs.saveCatalog();

        await productPage.expectDiscountedPrice(catalogProduct.urlKey!, "$100.00", "$80.00");
        await productPage.expectTierOffer(/Buy 10 for \$70\.00 each/);
        await productPage.expectUnavailable(excludedProduct.urlKey!);
    });

    test("should leave a company outside the catalog on the regular storefront", async ({ shopPage, adminPage, browser }) => {
        test.setTimeout(240 * 1000);

        const catalogCompanyContext = await browser.newContext();

        try {
            const catalogCompany = await signInAsApprovedCompany(await catalogCompanyContext.newPage(), adminPage);
            const catalogs = new CompanyCatalogPage(adminPage);

            await catalogs.createCatalog(`Catalog ${uniqueStamp()}`);
            await catalogs.addProductWithFlatPrice(catalogProduct.name, 80);
            await catalogs.assignCompany(catalogCompany.businessName);
            await catalogs.saveCatalog();
        } finally {
            await catalogCompanyContext.close();
        }

        await signInAsApprovedCompany(shopPage, adminPage);

        const productPage = new ProductPage(shopPage);

        await productPage.expectPrice(catalogProduct.urlKey!, "$100.00");
        await productPage.expectPrice(excludedProduct.urlKey!, "$100.00");
    });
});
