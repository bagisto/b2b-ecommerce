import { test } from "../../setup";
import type { SimpleProduct } from "../../pages/admin/catalog/ProductCreatePage";
import { QuoteManagementPage } from "../../pages/admin/b2b/QuoteManagementPage";
import { CartPage } from "../../pages/shop/CartPage";
import { QuoteViewPage } from "../../pages/shop/QuoteViewPage";
import { signInAsApprovedCompany } from "../../utils/company";
import { uniqueStamp } from "../../utils/faker";
import { formatPrice } from "../../utils/money";
import { dataFile } from "../../utils/paths";
import { createQuotableProduct } from "../../utils/product";

const UNIT_PRICE = 100;

test.describe("quotation negotiation", () => {
    let product: SimpleProduct;

    test.beforeEach(async ({ adminPage }) => {
        product = await createQuotableProduct(adminPage, UNIT_PRICE);
    });

    test("should carry the seller's offer into the cart when the buyer accepts it", async ({ shopPage, adminPage }) => {
        await signInAsApprovedCompany(shopPage, adminPage);

        const cart = new CartPage(shopPage);
        const quote = new QuoteViewPage(shopPage);

        await cart.addProduct(product.urlKey!, 2);

        const quoteId = await cart.requestQuote({
            name: `Quote ${uniqueStamp()}`,
            description: "Two units at a trade price, please.",
            attachments: [dataFile("drawing.png"), dataFile("specification.pdf")],
        });

        await quote.expectStatus(quoteId, "Open");
        await quote.expectAttachments(quoteId, ["drawing.png"], ["specification.pdf"]);

        await new QuoteManagementPage(adminPage).sendPercentOffer(quoteId, 10, "We can offer ten percent off.");

        await quote.expectStatus(quoteId, "Negotiation");
        await quote.acceptOffer(quoteId);

        await cart.expectSubtotal(formatPrice(UNIT_PRICE * 0.9 * 2));
        await quote.expectStatus(quoteId, "Accepted");
    });

    test("should only let the buyer accept the seller's answer to a counter-offer", async ({ shopPage, adminPage }) => {
        await signInAsApprovedCompany(shopPage, adminPage);

        const cart = new CartPage(shopPage);
        const quote = new QuoteViewPage(shopPage);

        await cart.addProduct(product.urlKey!, 1);

        const quoteId = await cart.requestQuote({
            name: `Quote ${uniqueStamp()}`,
            description: "One unit, please send your best price.",
            attachments: [],
        });

        await quote.sendPercentCounterOffer(quoteId, 50, "We would like half price.");

        await quote.expectStatus(quoteId, "Negotiation");
        await quote.expectAcceptNotOffered(quoteId);

        await new QuoteManagementPage(adminPage).sendPercentOffer(quoteId, 5, "Five percent is our best price.");

        await quote.expectAcceptOffered(quoteId);
        await quote.acceptOffer(quoteId);

        await cart.expectSubtotal(formatPrice(UNIT_PRICE * 0.95));
    });
});
