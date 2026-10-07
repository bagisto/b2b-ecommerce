import { expect, type Locator } from "@playwright/test";
import { ShopPage } from "./ShopPage";

export interface CreditBalances {
    outstanding: string;
    available: string;
    limit: string;
}

export class CompanyCreditPage extends ShopPage {
    private balance(label: string): Locator {
        return this.page
            .locator("span", { hasText: new RegExp(`^\\s*${label}\\s*$`, "i") })
            .locator("xpath=following-sibling::p[1]");
    }

    async expectBalances(balances: CreditBalances): Promise<void> {
        await this.visit("customer/account/company-credit");

        await expect(this.balance("Outstanding Balance")).toHaveText(balances.outstanding);
        await expect(this.balance("Available Credit")).toHaveText(balances.available);
        await expect(this.balance("Credit Limit")).toHaveText(balances.limit);
    }
}
