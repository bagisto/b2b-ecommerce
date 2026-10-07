import { expect, type Locator } from "@playwright/test";
import { ShopPage } from "./ShopPage";

export class CustomerSessionPage extends ShopPage {
    private get emailInput(): Locator {
        return this.page.locator('input[name="email"]');
    }

    private get passwordInput(): Locator {
        return this.page.locator('input[name="password"]');
    }

    private get signInButton(): Locator {
        return this.page.getByRole("button", { name: "Sign In", exact: true });
    }

    private async submitCredentials(email: string, password: string): Promise<void> {
        await this.visit("customer/login");
        await this.waitForVueMount();
        await this.emailInput.fill(email);
        await this.passwordInput.fill(password);
        await this.signInButton.click();
    }

    async signIn(email: string, password: string): Promise<void> {
        await this.submitCredentials(email, password);

        await expect(this.page).not.toHaveURL(/customer\/login/);
    }

    async attemptSignIn(email: string, password: string): Promise<void> {
        await this.submitCredentials(email, password);
    }

    async expectSignInHeldForApproval(): Promise<void> {
        await expect(this.flash("Your activation seeks admin approval")).toBeVisible();
        await expect(this.page).toHaveURL(/customer\/login/);
    }

    async expectSignInRejected(): Promise<void> {
        await expect(this.flash("Please check your credentials and try again.")).toBeVisible();
        await expect(this.page).toHaveURL(/customer\/login/);
    }
}
