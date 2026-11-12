import type { Locator } from "@playwright/test";
import { BasePage } from "../BasePage";

export abstract class ShopPage extends BasePage {
    protected get flashContainer(): Locator {
        return this.page.locator("div.fixed.top-5.grid.justify-items-end");
    }

    protected flash(text: string): Locator {
        return this.flashContainer.getByText(text);
    }
}
