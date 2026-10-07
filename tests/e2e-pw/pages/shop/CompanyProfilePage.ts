import { expect, type Locator } from "@playwright/test";
import { ShopPage } from "./ShopPage";

export type CompanyProfileFields = Record<string, string>;

export class CompanyProfilePage extends ShopPage {
    private get editLink(): Locator {
        return this.page.getByRole("link", { name: "Edit Profile", exact: true });
    }

    private profileValue(label: string): Locator {
        return this.page
            .locator(`div:has(> span:text-is("${label}"))`)
            .locator(`:scope > span:not(:text-is("${label}"))`);
    }

    private async open(): Promise<void> {
        const response = await this.page.goto("customer/account/company-profile");

        expect(response?.status()).toBe(200);

        await this.waitForVueMount();
    }

    async editProfile(fields: CompanyProfileFields): Promise<void> {
        await this.open();
        await this.editLink.click();

        await expect(this.page).toHaveURL(/customer\/account\/company-profile\/edit$/);
        await this.waitForVueMount();

        for (const [code, value] of Object.entries(fields)) {
            await this.page.locator(`input[name="${code}"]`).fill(value);
        }

        await this.page.getByRole("button", { name: "Save", exact: true }).click();

        await expect(this.page).toHaveURL(/customer\/account\/company-profile$/);
        await expect(this.flash("Profile Updated Successfully")).toBeVisible();
    }

    async expectProfileValues(values: Record<string, string>): Promise<void> {
        await this.open();

        for (const [label, value] of Object.entries(values)) {
            await expect(this.profileValue(label)).toHaveText(value);
        }
    }

    async expectReadOnly(businessName: string): Promise<void> {
        await this.expectProfileValues({ "Business Name": businessName });

        await expect(this.editLink).toHaveCount(0);

        const response = await this.page.goto("customer/account/company-profile/edit");

        expect(response?.status()).toBe(401);
    }
}
