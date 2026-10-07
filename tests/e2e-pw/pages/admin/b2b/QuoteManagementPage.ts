import { expect, type Locator } from "@playwright/test";
import { AdminPage } from "../AdminPage";

export class QuoteManagementPage extends AdminPage {
    private get offerTrigger(): Locator {
        return this.page
            .locator("button.primary-button")
            .filter({ hasText: /^\s*(Send|Revise) Quotation\s*$/ });
    }

    private get offerForm(): Locator {
        return this.page.locator("form.b2b-quote-modal");
    }

    private get itemDiscountRow(): Locator {
        return this.offerForm.locator("tr", { has: this.page.locator('input[name$="[discount_value]"]') });
    }

    private get nextButton(): Locator {
        return this.page.getByRole("button", { name: "Next", exact: true });
    }

    private get confirmOfferButton(): Locator {
        return this.page.getByRole("button", { name: /^Confirm & (Send|Revise) Quotation$/ });
    }

    private get statusLabel(): Locator {
        return this.page
            .locator("p", { hasText: /^\s*Quotation Id:/ })
            .locator("xpath=following-sibling::span[1]");
    }

    private async open(quoteId: string): Promise<void> {
        await this.visit(`admin/b2b/quotes/${quoteId}`);
        await this.waitForVueMount();
    }

    async sendPercentOffer(quoteId: string, percent: number, message: string): Promise<void> {
        await this.open(quoteId);

        await this.offerTrigger.click();
        await expect(this.offerForm).toBeVisible();

        await this.itemDiscountRow.getByRole("button", { name: "%", exact: true }).click();
        await this.itemDiscountRow.locator('input[name$="[discount_value]"]').fill(String(percent));
        await this.offerForm.locator('textarea[name="message"]').fill(message);

        await this.nextButton.click();
        await this.confirmOfferButton.click();

        await expect(this.flash("Quotation submitted successfully.")).toBeVisible();
    }

    async expectStatus(quoteId: string, status: string): Promise<void> {
        await this.open(quoteId);

        await expect(this.statusLabel).toHaveText(status);
    }
}
