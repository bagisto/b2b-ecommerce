import type { Locator } from "@playwright/test";
import { BasePage } from "../BasePage";

export abstract class AdminPage extends BasePage {
    protected get flashContainer(): Locator {
        return this.page.locator("div.fixed.top-5.z-\\[10003\\]");
    }

    protected get errorCode(): Locator {
        return this.page.locator("div.text-\\[38px\\]");
    }

    protected get searchInput(): Locator {
        return this.page.locator('input[name="search"]:visible');
    }

    protected get selectActionButton(): Locator {
        return this.page.getByRole("button", { name: "Select Action" });
    }

    protected get agreeButton(): Locator {
        return this.page.getByRole("button", { name: "Agree", exact: true });
    }

    protected flash(text: string): Locator {
        return this.flashContainer.getByText(text);
    }

    protected async searchGrid(path: string, term: string): Promise<void> {
        await this.searchInput.fill(term);

        await Promise.all([
            this.page.waitForResponse((response) => {
                const url = decodeURIComponent(response.url()).replace(/\+/g, " ");

                return url.includes(path) && url.includes("filters[all]") && url.includes(term);
            }),
            this.searchInput.press("Enter"),
        ]);
    }
}
