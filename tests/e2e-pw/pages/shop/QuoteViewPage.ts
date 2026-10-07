import { expect, type Locator } from "@playwright/test";
import { ShopPage } from "./ShopPage";

export class QuoteViewPage extends ShopPage {
    private get acceptButton(): Locator {
        return this.page.getByRole("button", { name: "Accept & Add To Cart", exact: true });
    }

    private get counterOfferTrigger(): Locator {
        return this.page.getByText("Quote Again", { exact: true });
    }

    private get counterOfferEditor(): Locator {
        return this.page.locator("form.b2b-shop-quote-editor");
    }

    private get itemDiscountRow(): Locator {
        return this.counterOfferEditor.locator("tr", { has: this.page.locator('input[name$="[discount_value]"]') });
    }

    private get confirmCounterOfferButton(): Locator {
        return this.page.getByRole("button", { name: "Confirm & Send Quotation", exact: true });
    }

    private get attachmentLinks(): Locator {
        return this.page.locator('a[title="Download"]');
    }

    private statusBadge(status: string): Locator {
        return this.page.locator("span.rounded-full", { hasText: new RegExp(`^\\s*${status}\\s*$`) });
    }

    private async open(quoteId: string): Promise<void> {
        await this.visit(`customer/account/quotes/${quoteId}`);
        await this.waitForVueMount();
    }

    async acceptOffer(quoteId: string): Promise<void> {
        await this.open(quoteId);
        await this.acceptButton.click();

        await expect(this.page).toHaveURL(/checkout\/cart/);
        await expect(this.flash("Quotation item(s) updated successfully.")).toBeVisible();
    }

    async sendPercentCounterOffer(quoteId: string, percent: number, message: string): Promise<void> {
        await this.open(quoteId);
        await this.counterOfferTrigger.click();

        await expect(this.counterOfferEditor.locator('textarea[name="message"]')).toBeVisible();

        await this.itemDiscountRow.getByRole("button", { name: "%", exact: true }).click();
        await this.itemDiscountRow.locator('input[name$="[discount_value]"]').fill(String(percent));
        await this.counterOfferEditor.locator('textarea[name="message"]').fill(message);
        await this.confirmCounterOfferButton.click();

        await expect(this.flash("Quotation submitted successfully.")).toBeVisible();
    }

    async expectStatus(quoteId: string, status: string): Promise<void> {
        await this.open(quoteId);

        await expect(this.statusBadge(status)).toBeVisible();
    }

    async expectAcceptOffered(quoteId: string): Promise<void> {
        await this.open(quoteId);

        await expect(this.acceptButton).toBeVisible();
    }

    async expectAcceptNotOffered(quoteId: string): Promise<void> {
        await this.open(quoteId);

        await expect(this.acceptButton).toHaveCount(0);
    }

    async expectAttachments(quoteId: string, imageNames: string[], fileNames: string[]): Promise<void> {
        await this.open(quoteId);

        await expect(this.attachmentLinks).toHaveCount(imageNames.length + fileNames.length);

        for (const name of imageNames) {
            await expect(this.page.getByAltText(name)).toBeVisible();
        }

        for (const name of fileNames) {
            await expect(this.attachmentLinks.filter({ hasText: name })).toHaveCount(1);
        }
    }
}
