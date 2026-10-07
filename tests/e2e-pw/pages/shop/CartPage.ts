import { expect, type Locator } from "@playwright/test";
import { ShopPage } from "./ShopPage";

export interface QuoteRequest {
    name: string;
    description: string;
    attachments: string[];
}

export class CartPage extends ShopPage {
    private get productForm(): Locator {
        return this.page.locator('form:has(input[name="product_id"])');
    }

    private get requestQuoteButton(): Locator {
        return this.page.getByRole("button", { name: "Request For Quote", exact: true });
    }

    private get quoteModal(): Locator {
        return this.page
            .locator("form")
            .filter({ has: this.page.locator('textarea[name="description"]') })
            .filter({ hasText: "Request For Quote" });
    }

    private get submitQuoteButton(): Locator {
        return this.page.getByRole("button", { name: "Request A Quotation", exact: true });
    }

    private get subtotal(): Locator {
        return this.page.locator('div.flex.justify-between:has(> p:text-is("Subtotal"))').filter({ visible: true });
    }

    async addProduct(urlKey: string, quantity: number): Promise<void> {
        await this.visit(urlKey);
        await this.waitForVueMount();

        for (let added = 1; added < quantity; added++) {
            await this.productForm.getByRole("button", { name: "Increase Quantity" }).click();
        }

        await this.productForm.getByRole("button", { name: "Add To Cart" }).click();

        await expect(this.flash("Item Added Successfully")).toBeVisible();
    }

    async requestQuote(request: QuoteRequest): Promise<string> {
        await this.visit("checkout/cart");
        await this.waitForVueMount();
        await this.requestQuoteButton.click();

        await expect(this.quoteModal.locator('textarea[name="description"]')).toBeVisible();

        await this.quoteModal.locator('input[name="name"]').fill(request.name);
        await this.quoteModal.locator('textarea[name="description"]').fill(request.description);

        if (request.attachments.length) {
            await this.quoteModal.locator('input[type="file"]').setInputFiles(request.attachments);
        }

        await this.submitQuoteButton.click();

        await expect(this.page).toHaveURL(/customer\/account\/quotes\/\d+$/);
        await expect(this.flash("Your quotation request has been created successfully.")).toBeVisible();

        return this.page.url().match(/quotes\/(\d+)$/)![1];
    }

    async expectSubtotal(amount: string): Promise<void> {
        await this.visit("checkout/cart");
        await this.waitForVueMount();

        await expect(this.subtotal).toContainText(amount);
    }
}
