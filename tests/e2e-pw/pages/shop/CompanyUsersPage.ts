import { expect, type Locator } from "@playwright/test";
import { ShopPage } from "./ShopPage";

export interface CompanyMember {
    firstName: string;
    lastName: string;
    email: string;
    phone: string;
    role: string;
}

export class CompanyUsersPage extends ShopPage {
    private get agreeButton(): Locator {
        return this.page.getByRole("button", { name: "Agree", exact: true });
    }

    private pendingInvitationRow(email: string): Locator {
        return this.page.locator("div.row:not(.datagrid-head)").filter({ hasText: email });
    }

    async createMember(member: CompanyMember): Promise<void> {
        await this.visit("customer/account/users/create");
        await this.waitForVueMount();

        await this.page.locator('input[name="first_name"]').fill(member.firstName);
        await this.page.locator('input[name="last_name"]').fill(member.lastName);
        await this.page.locator('input[name="user_email"]').fill(member.email);
        await this.page.locator('input[name="phone"]').fill(member.phone);
        await this.page.locator('select[name="gender"]').selectOption("Male");
        await this.page.locator('select[name="company_role_id"]').selectOption({ label: member.role });
        await this.page.getByRole("button", { name: "Save User", exact: true }).click();

        await expect(this.flash("User created successfully.")).toBeVisible();
    }

    async inviteCustomer(email: string, role: string): Promise<void> {
        await this.visit("customer/account/users/add-existing");
        await this.waitForVueMount();

        await this.page.locator('input[name="user_email"]').fill(email);
        await this.page.locator('select[name="company_role_id"]').selectOption({ label: role });
        await this.page.getByRole("button", { name: "Send Invitation", exact: true }).click();

        await expect(this.flash(`Invitation sent to ${email}.`)).toBeVisible();
    }

    async revokeInvitation(email: string): Promise<void> {
        await this.visit("customer/account/users/add-existing");
        await this.waitForVueMount();

        await expect(this.pendingInvitationRow(email)).toHaveCount(1);

        await this.pendingInvitationRow(email).locator("span.icon-bin").click();
        await this.agreeButton.click();

        await expect(this.flash("Invitation revoked.")).toBeVisible();
    }
}
