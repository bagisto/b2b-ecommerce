import { expect, type Locator } from "@playwright/test";
import { AdminPage } from "../AdminPage";

const GRID_PATH = "admin/settings/roles";

export class RolesPage extends AdminPage {
    private get createLink(): Locator {
        return this.page.getByRole("link", { name: "Create Role" });
    }

    private get nameInput(): Locator {
        return this.page.locator('input[name="name"]');
    }

    private get descriptionInput(): Locator {
        return this.page.locator('textarea[name="description"]');
    }

    private get permissionTypeSelect(): Locator {
        return this.page.locator('select[name="permission_type"]');
    }

    private get saveButton(): Locator {
        return this.page.getByRole("button", { name: "Save Role" });
    }

    private permissionInput(key: string): Locator {
        return this.page.locator(`input[name="permissions[]"][value="${key}"]`);
    }

    private permissionOption(key: string): Locator {
        return this.page.locator(`label:has(> input[name="permissions[]"][value="${key}"])`);
    }

    private roleRow(name: string): Locator {
        return this.page.locator("div.row:not(.datagrid-head)").filter({ hasText: name });
    }

    private async openGrid(): Promise<void> {
        await this.visit(GRID_PATH);
        await this.waitForVueMount();
    }

    async createRole(name: string, permissions: string[]): Promise<void> {
        await this.openGrid();
        await this.createLink.click();
        await this.waitForVueMount();

        await this.permissionTypeSelect.selectOption("custom");
        await this.nameInput.fill(name);
        await this.descriptionInput.fill(`${name} description`);

        for (const key of permissions) {
            await expect(this.permissionInput(key)).toBeAttached();

            if (!(await this.permissionInput(key).isChecked())) {
                await this.permissionOption(key).click();
            }

            await expect(this.permissionInput(key)).toBeChecked();
        }

        await this.saveButton.click();

        await expect(this.flash("Roles Created Successfully")).toBeVisible();
    }

    async deleteRoleIfPresent(name: string): Promise<void> {
        await this.openGrid();
        await this.searchGrid(GRID_PATH, name);

        if (!(await this.roleRow(name).count())) {
            return;
        }

        await this.roleRow(name).locator("span.icon-delete").click();
        await this.agreeButton.click();

        await expect(this.flash("Roles is deleted successfully")).toBeVisible();
    }
}
