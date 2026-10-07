import { expect, type Locator, type Response } from "@playwright/test";
import { ShopPage } from "./ShopPage";

export type PaymentMethod = "cashondelivery" | "moneytransfer" | "paybycredit";

export class CheckoutPage extends ShopPage {
    private get proceedToCheckoutLink(): Locator {
        return this.page.getByRole("link", { name: "Proceed To Checkout" });
    }

    private get cookieAcceptButton(): Locator {
        return this.page.locator(".js-cookie-consent").getByRole("button", { name: "Accept" });
    }

    private get addNewAddressOption(): Locator {
        return this.page.getByText("Add new address");
    }

    private get saveAddressButton(): Locator {
        return this.page.getByRole("button", { name: "Save", exact: true });
    }

    private get proceedButton(): Locator {
        return this.page.getByRole("button", { name: "Proceed", exact: true });
    }

    private get placeOrderButton(): Locator {
        return this.page.getByRole("button", { name: "Place Order" });
    }

    private get grandTotal(): Locator {
        return this.page
            .locator('div.flex.justify-between:has(> p:text-is("Grand Total"))')
            .filter({ visible: true })
            .locator(':scope > p:not(:text-is("Grand Total"))');
    }

    private get orderIdHeading(): Locator {
        return this.page.locator("p.text-xl").filter({ hasText: /#\s*\d+/ });
    }

    private optionLabel(id: string): Locator {
        return this.page.locator(`label[for="${id}"]`).filter({ hasText: /\S/ });
    }

    private async openCheckout(): Promise<void> {
        await this.visit("checkout/cart");
        await this.waitForVueMount();

        if (await this.cookieAcceptButton.isVisible()) {
            await this.cookieAcceptButton.click();
        }

        await this.proceedToCheckoutLink.click();

        await expect(this.page).toHaveURL(/checkout\/onepage/);

        await this.waitForVueMount();
    }

    private async fillAddress(): Promise<void> {
        await expect(this.page.getByRole("textbox", { name: "First Name" })).toBeVisible();

        await this.page.getByRole("textbox", { name: "Company Name" }).fill("Webkul");
        await this.page.getByRole("textbox", { name: "First Name" }).fill("Demo");
        await this.page.getByRole("textbox", { name: "Last Name" }).fill("Buyer");
        await this.page.locator('input[name="billing\\.email"]').fill("buyer@example.com");
        await this.page.getByRole("textbox", { name: "Street Address" }).fill("North Street");
        await this.page.locator('select[name="billing\\.country"]').selectOption("IN");
        await this.page.locator('select[name="billing\\.state"]').selectOption("UP");
        await this.page.getByRole("textbox", { name: "City" }).fill("Test City");
        await this.page.getByRole("textbox", { name: "Zip/Postcode" }).fill("123456");
        await this.page.getByRole("textbox", { name: "Phone" }).fill("2365432789");
    }

    private async waitForPaymentMethodSaved(): Promise<void> {
        await expect
            .poll(
                async () => {
                    const response = await this.page.request.get("api/checkout/onepage/summary").catch(() => null);

                    if (!response?.ok()) {
                        return null;
                    }

                    return (await response.json().catch(() => null))?.data?.payment_method ?? null;
                },
                { message: "the selected payment method was never saved on the cart" },
            )
            .not.toBeNull();
    }

    private async submitOrder(): Promise<Response> {
        await this.waitForPaymentMethodSaved();

        await expect(this.placeOrderButton).toBeEnabled({ timeout: 60 * 1000 });

        const orderResponse = this.page.waitForResponse(
            (response) =>
                response.url().includes("/api/checkout/onepage/orders")
                && response.request().method() === "POST",
            { timeout: 90 * 1000 },
        );

        await this.placeOrderButton.click();

        return orderResponse;
    }

    async enterNewAddress(): Promise<void> {
        await this.openCheckout();

        if (await this.addNewAddressOption.isVisible()) {
            await this.addNewAddressOption.click();
        }

        await this.fillAddress();

        if (await this.saveAddressButton.isVisible()) {
            await this.saveAddressButton.click();
        }

        await this.proceedButton.click();
    }

    async chooseFlatRateShipping(): Promise<void> {
        await this.optionLabel("flatrate_flatrate").click();

        await expect(this.page.locator("input#flatrate_flatrate")).toBeChecked();
    }

    async choosePayment(method: PaymentMethod): Promise<void> {
        await Promise.all([
            this.page.waitForResponse((response) => response.url().includes("checkout/onepage/payment-methods")),
            this.optionLabel(method).click(),
        ]);

        await expect(this.page.locator(`input#${method}`)).toBeChecked();
    }

    async readGrandTotal(): Promise<number> {
        await expect(this.grandTotal).toContainText("$");

        return Number((await this.grandTotal.innerText()).replace(/[^0-9.]/g, ""));
    }

    async placeOrder(): Promise<string> {
        const response = await this.submitOrder();

        if (!response.ok()) {
            throw new Error(`checkout failed (${response.status()}): ${await response.text()}`);
        }

        await expect(this.page).toHaveURL(/checkout\/onepage\/success/, { timeout: 30 * 1000 });

        return (await this.orderIdHeading.innerText()).match(/#\s*(\d+)/)![1];
    }

    async attemptPlaceOrder(): Promise<number> {
        return (await this.submitOrder()).status();
    }

    async expectOrderRefused(message: string): Promise<void> {
        await expect(this.flash(message)).toBeVisible();
        await expect(this.page).toHaveURL(/checkout\/onepage$/);
    }

    async expectPaymentDescription(method: PaymentMethod, text: string): Promise<void> {
        await expect(this.optionLabel(method)).toContainText(text);
    }
}
