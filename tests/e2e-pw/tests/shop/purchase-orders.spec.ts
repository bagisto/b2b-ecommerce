import { test } from "../../setup";
import type { SimpleProduct } from "../../pages/admin/catalog/ProductCreatePage";
import { PurchaseOrderListPage } from "../../pages/admin/b2b/PurchaseOrderListPage";
import { QuoteManagementPage } from "../../pages/admin/b2b/QuoteManagementPage";
import { CartPage } from "../../pages/shop/CartPage";
import { CheckoutPage } from "../../pages/shop/CheckoutPage";
import { PurchaseOrdersPage } from "../../pages/shop/PurchaseOrdersPage";
import { QuoteViewPage } from "../../pages/shop/QuoteViewPage";
import { signInAsApprovedCompany } from "../../utils/company";
import { uniqueStamp } from "../../utils/faker";
import { createQuotableProduct } from "../../utils/product";

test.describe("purchase orders", () => {
    let product: SimpleProduct;

    test.beforeEach(async ({ adminPage }) => {
        product = await createQuotableProduct(adminPage, 100);
    });

    test("should turn an accepted quotation into a purchase order on both sides once it is ordered", async ({ shopPage, adminPage }) => {
        test.setTimeout(240 * 1000);

        await signInAsApprovedCompany(shopPage, adminPage);

        const cart = new CartPage(shopPage);
        const checkout = new CheckoutPage(shopPage);
        const purchaseOrders = new PurchaseOrdersPage(shopPage);

        await cart.addProduct(product.urlKey!, 3);

        const quoteId = await cart.requestQuote({
            name: `Quote ${uniqueStamp()}`,
            description: "Three units for our warehouse.",
            attachments: [],
        });

        await new QuoteManagementPage(adminPage).sendPercentOffer(quoteId, 10, "Ten percent off for three units.");
        await new QuoteViewPage(shopPage).acceptOffer(quoteId);

        await checkout.enterNewAddress();
        await checkout.chooseFlatRateShipping();
        await checkout.choosePayment("cashondelivery");
        await checkout.placeOrder();

        const poNumber = await purchaseOrders.readPurchaseOrderNumber(quoteId);

        await purchaseOrders.expectPurchaseOrder(quoteId, poNumber, "Ordered");
        await new PurchaseOrderListPage(adminPage).expectPurchaseOrderListed(poNumber, "Ordered");
        await new QuoteManagementPage(adminPage).expectStatus(quoteId, "Ordered");
    });
});
