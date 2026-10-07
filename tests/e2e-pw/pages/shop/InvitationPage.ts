import { expect } from "@playwright/test";
import { ShopPage } from "./ShopPage";

export class InvitationPage extends ShopPage {
    async accept(invitationPath: string): Promise<void> {
        await this.visit(invitationPath);
        await this.page.getByRole("button", { name: "Accept Invitation", exact: true }).click();

        await expect(this.page).toHaveURL(/customer\/account\/profile/);
        await expect(this.flash("You have joined the company successfully.")).toBeVisible();
    }

    async expectRefused(invitationPath: string): Promise<void> {
        await this.visit(invitationPath);

        await expect(this.page).toHaveURL(/customer\/account\/profile/);
        await expect(this.flash("This invitation is no longer valid or is not for your account.")).toBeVisible();
    }
}
