import { expect, type Locator } from "@playwright/test";
import { ShopPage } from "./ShopPage";

export class CompanyAccountPage extends ShopPage {
    private get profileHeading(): Locator {
        return this.page.locator("h2", { hasText: /^\s*Company Profile\s*$/ });
    }

    private navigationLink(name: string): Locator {
        return this.page.locator("a", {
            has: this.page.locator("p", { hasText: new RegExp(`^\\s*${name}\\s*$`) }),
        });
    }

    async expectCompanyProfile(businessName: string): Promise<void> {
        await this.visit("customer/account/company-profile");

        await expect(this.profileHeading).toBeVisible();
        await expect(this.page.getByText(businessName, { exact: true })).toBeVisible();
    }

    async expectCompanyNavigation(links: string[]): Promise<void> {
        for (const link of links) {
            await expect(this.navigationLink(link).filter({ visible: true })).toHaveCount(1);
        }
    }
}
