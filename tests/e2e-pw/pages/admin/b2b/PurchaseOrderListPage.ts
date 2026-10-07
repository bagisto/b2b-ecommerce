import { expect, type Locator } from "@playwright/test";
import { AdminPage } from "../AdminPage";

const GRID_PATH = "admin/b2b/purchase-orders";

export class PurchaseOrderListPage extends AdminPage {
    private purchaseOrderRow(poNumber: string): Locator {
        return this.page
            .locator("div.b2b-dg-grid")
            .filter({ has: this.page.getByRole("link", { name: `#${poNumber}`, exact: true }) });
    }

    async expectPurchaseOrderListed(poNumber: string, status: string): Promise<void> {
        await this.visit(GRID_PATH);
        await this.waitForVueMount();
        await this.searchGrid(GRID_PATH, poNumber);

        await expect(this.purchaseOrderRow(poNumber)).toHaveCount(1);
        await expect(this.purchaseOrderRow(poNumber).getByText(status, { exact: true })).toBeVisible();
    }
}
