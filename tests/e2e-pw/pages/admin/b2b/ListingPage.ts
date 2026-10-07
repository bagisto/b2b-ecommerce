import { expect, type Locator } from "@playwright/test";
import { AdminPage } from "../AdminPage";

export class ListingPage extends AdminPage {
    private heading(title: string): Locator {
        return this.page.locator("p.text-xl", { hasText: new RegExp(`^\\s*${title}\\s*$`) });
    }

    async expectListingRenders(path: string, title: string): Promise<void> {
        const gridResponse = this.page.waitForResponse(
            (response) =>
                response.url().includes(`/${path}?`)
                && response.request().headers()["x-requested-with"] === "XMLHttpRequest",
        );

        const pageResponse = await this.page.goto(path);

        expect(pageResponse?.status()).toBe(200);

        await expect(this.errorCode).toHaveCount(0);
        await expect(this.heading(title)).toBeVisible();

        expect((await gridResponse).status()).toBe(200);
    }
}
