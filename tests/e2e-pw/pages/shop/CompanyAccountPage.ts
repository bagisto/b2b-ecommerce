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

    private roleValue(): Locator {
        return this.page
            .locator('div:has(> p:text-is("Your Role"))')
            .locator(':scope > p:not(:text-is("Your Role"))');
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

    async expectCompanyNavigationHidden(links: string[]): Promise<void> {
        for (const link of links) {
            await expect(this.navigationLink(link)).toHaveCount(0);
        }
    }

    async expectCompanyPageRefused(path: string): Promise<void> {
        const response = await this.page.goto(path);

        expect(response?.status()).toBe(401);
    }

    async expectCompanyPageOpen(path: string): Promise<void> {
        const response = await this.page.goto(path);

        expect(response?.status()).toBe(200);
    }

    async expectMemberRole(role: string): Promise<void> {
        await this.visit("customer/account/profile");

        await expect(this.roleValue()).toHaveText(role);
    }
}
