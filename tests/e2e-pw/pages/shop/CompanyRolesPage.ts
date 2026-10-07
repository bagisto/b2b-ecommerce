import { expect, type Locator } from "@playwright/test";
import { ShopPage } from "./ShopPage";

export class CompanyRolesPage extends ShopPage {
    private get saveButton(): Locator {
        return this.page.getByRole("button", { name: "Save Role", exact: true });
    }

    private permissionOption(key: string): Locator {
        return this.page.locator("label", {
            has: this.page.locator(`input[type="checkbox"][value="${key}"]`),
        });
    }

    async createCustomRole(name: string, permissionKeys: string[]): Promise<void> {
        await this.visit("customer/account/roles/create");
        await this.waitForVueMount();

        await this.page.locator('input[name="name"]').fill(name);
        await this.page.locator('textarea[name="description"]').fill(`${name} role`);
        await this.page.locator('select[name="permission_type"]').selectOption("custom");

        for (const key of permissionKeys) {
            await this.permissionOption(key).click();
        }

        await this.saveButton.click();

        await expect(this.flash("Role created successfully.")).toBeVisible();
    }
}
