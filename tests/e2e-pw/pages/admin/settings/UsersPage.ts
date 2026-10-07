import { expect, type Locator } from "@playwright/test";
import { AdminPage } from "../AdminPage";

const GRID_PATH = "admin/settings/users";

export interface AdminUser {
    name: string;
    email: string;
    password: string;
    role: string;
}

export class UsersPage extends AdminPage {
    private get createButton(): Locator {
        return this.page.getByRole("button", { name: "Create User" });
    }

    private get statusInput(): Locator {
        return this.page.locator('input[type="checkbox"][name="status"]');
    }

    private get statusToggle(): Locator {
        return this.page.locator('label[for="status"]');
    }

    private get saveButton(): Locator {
        return this.page.getByRole("button", { name: "Save User" });
    }

    private field(name: string): Locator {
        return this.page.locator(`input[name="${name}"]`);
    }

    private userRow(email: string): Locator {
        return this.page.locator("div.row:not(.datagrid-head)").filter({ hasText: email });
    }

    private async openGrid(): Promise<void> {
        await this.visit(GRID_PATH);
        await this.waitForVueMount();
    }

    async createUser(user: AdminUser): Promise<void> {
        await this.openGrid();
        await this.createButton.click();

        await expect(this.field("name")).toBeVisible();

        await this.field("name").fill(user.name);
        await this.field("email").fill(user.email);
        await this.field("password").fill(user.password);
        await this.field("password_confirmation").fill(user.password);
        await this.page.locator('select[name="role_id"]').selectOption({ label: user.role });

        if (!(await this.statusInput.isChecked())) {
            await this.statusToggle.click();
        }

        await expect(this.statusInput).toBeChecked();

        await this.saveButton.click();

        await expect(this.flash("User created successfully.")).toBeVisible();
    }

    async deleteUserIfPresent(email: string): Promise<void> {
        await this.openGrid();
        await this.searchGrid(GRID_PATH, email);

        if (!(await this.userRow(email).count())) {
            return;
        }

        await this.userRow(email).locator("span.icon-delete").click();
        await this.agreeButton.click();

        await expect(this.flash("User deleted successfully.")).toBeVisible();
    }
}
