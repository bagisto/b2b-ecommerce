import { expect, type Locator } from "@playwright/test";
import { AdminPage } from "../AdminPage";

const GRID_PATH = "admin/b2b/quotes";

export class QuoteListPage extends AdminPage {
    private quoteRow(name: string): Locator {
        return this.page.locator("div.b2b-dg-grid:not(:has(.shimmer))").filter({ hasText: name });
    }

    private async searchQuotes(name: string): Promise<void> {
        await this.visit(GRID_PATH);
        await this.waitForVueMount();
        await this.searchGrid(GRID_PATH, name);
    }

    async expectQuoteListed(name: string): Promise<void> {
        await this.searchQuotes(name);

        await expect(this.quoteRow(name)).toHaveCount(1);
    }

    async expectQuoteNotListed(name: string): Promise<void> {
        await this.searchQuotes(name);

        await expect(this.quoteRow(name)).toHaveCount(0);
    }
}
