import { expect, type Locator } from "@playwright/test";
import { AdminPage } from "./AdminPage";

export class RestrictedAdminPage extends AdminPage {
    private get emailInput(): Locator {
        return this.page.getByPlaceholder("Email");
    }

    private get passwordInput(): Locator {
        return this.page.getByPlaceholder("Password");
    }

    private get signInButton(): Locator {
        return this.page.getByRole("button", { name: "Sign In" });
    }

    async login(email: string, password: string): Promise<void> {
        await this.visit("admin/login");
        await this.emailInput.fill(email);
        await this.passwordInput.fill(password);
        await this.signInButton.click();

        await expect(this.page).not.toHaveURL(/admin\/login/);
    }

    async expectRouteAllowed(path: string): Promise<void> {
        const response = await this.page.goto(path);

        expect(response?.status()).toBe(200);

        await expect(this.page).toHaveURL(new RegExp(`/${path}(\\?.*)?$`));
        await expect(this.errorCode).toHaveCount(0);
    }

    async expectRouteDenied(path: string): Promise<void> {
        const response = await this.page.goto(path);

        expect(response?.status()).toBe(401);
    }

    async expectRouteForbidden(path: string): Promise<void> {
        const response = await this.page.goto(path);

        expect(response?.status()).toBe(403);
    }
}
