import { expect, type Locator } from "@playwright/test";
import { ShopPage } from "./ShopPage";

export interface QuickOrderLine {
    sku: string;
    quantity: number;
}

export class QuickOrderPage extends ShopPage {
    private get searchInput(): Locator {
        return this.page.getByPlaceholder("Search Product by SKU or Name");
    }

    private get searchSection(): Locator {
        return this.page.locator("div.rounded-xl", { has: this.searchInput });
    }

    private get selectedSection(): Locator {
        return this.page.locator("div.rounded-xl", {
            has: this.page.locator("h3", { hasText: /^\s*Selected Products/ }),
        });
    }

    private searchResult(name: string): Locator {
        return this.searchSection.locator("div:has(> button)", {
            has: this.page.locator("p", { hasText: new RegExp(`^\\s*${name}\\s*$`) }),
        });
    }

    private selectedLine(name: string): Locator {
        return this.selectedSection.locator("div", {
            has: this.page.locator("p", { hasText: new RegExp(`^\\s*${name}\\s*$`) }),
        }).filter({ has: this.page.getByRole("button", { name: "Increase Quantity" }) });
    }

    private selectedQuantity(name: string): Locator {
        return this.selectedLine(name).locator('div:has(> button[aria-label="Increase Quantity"]) > p');
    }

    private async search(term: string): Promise<void> {
        await Promise.all([
            this.page.waitForResponse((response) => {
                const url = decodeURIComponent(response.url()).replace(/\+/g, " ");

                return url.includes("/quick-orders/search") && url.includes(term);
            }),
            this.searchInput.fill(term),
        ]);
    }

    async open(): Promise<void> {
        await this.visit("customer/account/quick-orders");
        await this.waitForVueMount();
    }

    async selectProduct(term: string, name: string): Promise<void> {
        await this.search(term);

        await this.searchResult(name).getByRole("button", { name: /^\W*Add$/ }).click();

        await expect(this.selectedLine(name)).toHaveCount(1);
    }

    async expectNoSearchResults(term: string): Promise<void> {
        await this.search(term);

        await expect(this.searchSection.getByText("No products found.", { exact: true })).toBeVisible();
    }

    async setQuantity(name: string, quantity: number): Promise<void> {
        const increase = this.selectedLine(name).getByRole("button", { name: "Increase Quantity" });

        for (let current = Number(await this.selectedQuantity(name).innerText()); current < quantity; current++) {
            await increase.click();
        }

        await expect(this.selectedQuantity(name)).toHaveText(String(quantity));
    }

    async uploadCsv(lines: QuickOrderLine[]): Promise<void> {
        const csv = ["sku,quantity", ...lines.map((line) => `${line.sku},${line.quantity}`)].join("\n");

        await Promise.all([
            this.page.waitForResponse((response) => response.url().includes("/quick-orders/fetch-by-skus")),
            this.page.locator('input[type="file"][accept=".csv"]').setInputFiles({
                name: "quick-order.csv",
                mimeType: "text/csv",
                buffer: Buffer.from(csv),
            }),
        ]);
    }

    async expectSelectedQuantity(name: string, quantity: number): Promise<void> {
        await expect(this.selectedQuantity(name)).toHaveText(String(quantity));
    }

    async expectRemovable(name: string): Promise<void> {
        await expect(this.selectedLine(name).getByRole("button", { name: "Remove", exact: true })).toBeVisible();
    }

    async expectNotSelected(name: string): Promise<void> {
        await expect(this.selectedLine(name)).toHaveCount(0);
    }

    async addSelectedToCart(): Promise<void> {
        await Promise.all([
            this.page.waitForResponse((response) =>
                response.url().endsWith("/customer/account/quick-orders")
                && response.request().resourceType() === "document",
            ),
            this.page.getByRole("button", { name: "Add To Cart", exact: true }).click(),
        ]);

        await this.waitForVueMount();
    }
}
