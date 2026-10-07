import { expect, type Locator } from "@playwright/test";
import { ShopPage } from "./ShopPage";

export class ProductPage extends ShopPage {
    private get productDetails(): Locator {
        return this.page.locator('form:has(input[name="product_id"])');
    }

    private get regularPrice(): Locator {
        return this.productDetails.locator("p.final-price.line-through");
    }

    private get sellingPrice(): Locator {
        return this.productDetails.locator("p.final-price:not(.line-through), p.final-price.line-through + p");
    }

    private async open(urlKey: string): Promise<void> {
        const response = await this.page.goto(urlKey);

        expect(response?.status()).toBe(200);

        await this.waitForVueMount();
    }

    async expectDiscountedPrice(urlKey: string, regular: string, price: string): Promise<void> {
        await this.open(urlKey);

        await expect(this.regularPrice).toHaveText(regular);
        await expect(this.sellingPrice).toHaveText(price);
    }

    async expectPrice(urlKey: string, price: string): Promise<void> {
        await this.open(urlKey);

        await expect(this.regularPrice).toHaveCount(0);
        await expect(this.sellingPrice).toHaveText(price);
    }

    async expectTierOffer(text: RegExp): Promise<void> {
        await expect(this.productDetails.getByText(text)).toBeVisible();
    }

    async expectUnavailable(urlKey: string): Promise<void> {
        const response = await this.page.goto(urlKey);

        expect(response?.status()).toBe(404);
    }
}
