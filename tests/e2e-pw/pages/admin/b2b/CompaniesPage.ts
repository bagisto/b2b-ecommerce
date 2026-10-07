import { expect, type Locator } from "@playwright/test";
import { AdminPage } from "../AdminPage";

const GRID_PATH = "admin/b2b/companies";

export class CompaniesPage extends AdminPage {
    private companyRow(email: string): Locator {
        return this.page.locator("div.b2b-company-grid").filter({ hasText: email });
    }

    private selectToggle(email: string): Locator {
        return this.companyRow(email).locator('label[for^="mass_action_select_record_"]');
    }

    private massActionGroup(label: string): Locator {
        return this.page.getByRole("link", { name: new RegExp(`^\\s*${label}`) });
    }

    private massActionOption(label: string): Locator {
        return this.page.getByRole("link", { name: label, exact: true });
    }

    private async findCompany(email: string): Promise<void> {
        await this.visit(GRID_PATH);
        await this.waitForVueMount();
        await this.searchGrid(GRID_PATH, email);

        await expect(this.companyRow(email)).toHaveCount(1);
    }

    async approveCompany(email: string): Promise<void> {
        await this.findCompany(email);

        await this.selectToggle(email).click();
        await this.selectActionButton.click();
        await this.massActionGroup("Update Status").hover();
        await this.massActionOption("Approve").click();
        await this.agreeButton.click();

        await expect(this.flash("Selected companies status updated successfully.")).toBeVisible();
    }

    async assignSalesRepresentative(email: string, representative: string): Promise<void> {
        await this.findCompany(email);

        await this.selectToggle(email).click();
        await this.selectActionButton.click();
        await this.massActionGroup("Assign Sales Representative").hover();
        await this.massActionOption(representative).click();
        await this.agreeButton.click();

        await expect(this.flash("Sales representative assigned to the selected companies successfully.")).toBeVisible();
    }

    async companyId(email: string): Promise<string> {
        await this.findCompany(email);

        const target = await this.selectToggle(email).getAttribute("for");
        const id = target?.replace("mass_action_select_record_", "");

        if (!id) {
            throw new Error(`No company row id found for ${email}`);
        }

        return id;
    }

    async expectCompanyStatus(email: string, status: "Active" | "Pending"): Promise<void> {
        await this.findCompany(email);

        await expect(this.companyRow(email).getByText(status, { exact: true })).toBeVisible();
    }
}
