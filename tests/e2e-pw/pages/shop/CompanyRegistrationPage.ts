import { expect, type Locator } from "@playwright/test";
import { ShopPage } from "./ShopPage";

export interface CompanyAccount {
    firstName: string;
    lastName: string;
    email: string;
    phone: string;
    businessName: string;
    password: string;
}

export class CompanyRegistrationPage extends ShopPage {
    private get companyPanel(): Locator {
        return this.page.locator("#panel-company");
    }

    private get registerButton(): Locator {
        return this.companyPanel.getByRole("button", { name: "Register", exact: true });
    }

    private get agreementToggle(): Locator {
        return this.companyPanel.locator('label[for="agreement-company"]').filter({ hasText: /\S/ });
    }

    private field(name: string): Locator {
        return this.companyPanel.locator(`input[name="${name}"]`);
    }

    private fieldError(message: string): Locator {
        return this.companyPanel.locator("p.text-red-500", { hasText: message });
    }

    private async fillForm(company: CompanyAccount): Promise<void> {
        await this.visit("customer/register?type=company");
        await this.waitForVueMount();

        await expect(this.companyPanel).toBeVisible();

        await this.field("first_name").fill(company.firstName);
        await this.field("last_name").fill(company.lastName);
        await this.field("email").fill(company.email);
        await this.field("phone").fill(company.phone);
        await this.field("business_name").fill(company.businessName);
        await this.field("password").fill(company.password);
        await this.field("password_confirmation").fill(company.password);

        if (await this.agreementToggle.count()) {
            await this.agreementToggle.click();
        }
    }

    async register(company: CompanyAccount): Promise<void> {
        await this.fillForm(company);
        await this.registerButton.click();

        await expect(this.page).toHaveURL(/customer\/login/);
    }

    async attemptRegister(company: CompanyAccount): Promise<void> {
        await this.fillForm(company);
        await this.registerButton.click();

        await expect(this.page).toHaveURL(/customer\/register/);
    }

    async expectPendingApprovalNotice(): Promise<void> {
        await expect(this.flash("Your company account has been registered and is pending approval.")).toBeVisible();
    }

    async expectFieldError(message: string): Promise<void> {
        await expect(this.fieldError(message)).toBeVisible();
    }
}
