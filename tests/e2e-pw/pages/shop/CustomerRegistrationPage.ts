import { expect, type Locator } from "@playwright/test";
import { ShopPage } from "./ShopPage";

export interface CustomerAccount {
    firstName: string;
    lastName: string;
    email: string;
    password: string;
}

export class CustomerRegistrationPage extends ShopPage {
    private get personalPanel(): Locator {
        return this.page.locator("#panel-personal");
    }

    private get agreementToggle(): Locator {
        return this.personalPanel.locator('label[for="agreement"]').filter({ hasText: /\S/ });
    }

    private field(name: string): Locator {
        return this.personalPanel.locator(`input[name="${name}"]`);
    }

    async register(customer: CustomerAccount): Promise<void> {
        await this.visit("customer/register");
        await this.waitForVueMount();

        await expect(this.personalPanel).toBeVisible();

        await this.field("first_name").fill(customer.firstName);
        await this.field("last_name").fill(customer.lastName);
        await this.field("email").fill(customer.email);
        await this.field("password").fill(customer.password);
        await this.field("password_confirmation").fill(customer.password);

        if (await this.agreementToggle.count()) {
            await this.agreementToggle.click();
        }

        await this.personalPanel.getByRole("button", { name: "Register", exact: true }).click();

        await expect(this.page).toHaveURL(/customer\/login/);
    }
}
