import { expect, type Locator } from "@playwright/test";
import { AdminPage } from "../AdminPage";

export class CompanyCreditSettingsPage extends AdminPage {
    private get creditSettingsButton(): Locator {
        return this.page.getByRole("button", { name: "Credit Settings" });
    }

    private get creditForm(): Locator {
        return this.page.locator('form:has(input[name="credit_limit"])');
    }

    private summaryValue(label: string): Locator {
        return this.page
            .locator("span", { hasText: new RegExp(`^\\s*${label}\\s*$`, "i") })
            .locator("xpath=following-sibling::p[1]");
    }

    async setCreditLimit(companyId: string, limit: number): Promise<void> {
        await this.visit(`admin/b2b/company-credits/${companyId}`);
        await this.waitForVueMount();

        await this.creditSettingsButton.click();
        await this.creditForm.locator('input[name="credit_limit"]').fill(String(limit));

        const status = this.creditForm.locator('input[type="checkbox"][name="status"]');

        if (!(await status.isChecked())) {
            await status.check();
        }

        await this.creditForm.getByRole("button", { name: "Save", exact: true }).click();

        await expect(this.flash("Credit settings updated successfully.")).toBeVisible();
        await expect(this.summaryValue("Credit Limit")).toContainText(limit.toLocaleString("en-US"));
    }
}
