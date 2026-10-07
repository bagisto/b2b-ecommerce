import { expect, type Locator } from "@playwright/test";
import { AdminPage } from "../AdminPage";

export class OrderViewPage extends AdminPage {
    private get statusBadge(): Locator {
        return this.page
            .locator("p", { hasText: /^\s*Order #\s*\S+\s*$/ })
            .locator("xpath=following-sibling::span[1]");
    }

    async cancelOrder(orderId: string): Promise<void> {
        await this.visit(`admin/sales/orders/view/${orderId}`);
        await this.waitForVueMount();

        await this.page.getByRole("link", { name: "Cancel", exact: true }).click();
        await this.agreeButton.click();

        await expect(this.flash("Order cancelled successfully")).toBeVisible();
        await expect(this.statusBadge).toHaveText("Canceled");
    }
}
