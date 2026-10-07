import { expect, type Locator } from "@playwright/test";
import { ShopPage } from "./ShopPage";

export class PurchaseOrdersPage extends ShopPage {
    private get purchaseOrderHeading(): Locator {
        return this.page.locator("h2", { hasText: /Purchase Order Id: #/ });
    }

    private purchaseOrderLink(poNumber: string): Locator {
        return this.page.getByRole("link", { name: `#${poNumber}`, exact: true });
    }

    private statusBadge(status: string): Locator {
        return this.page.locator("span.rounded-full", { hasText: new RegExp(`^\\s*${status}\\s*$`) });
    }

    async readPurchaseOrderNumber(quoteId: string): Promise<string> {
        await this.visit(`customer/account/purchase-orders/${quoteId}`);

        await expect(this.purchaseOrderHeading).toBeVisible();

        return (await this.purchaseOrderHeading.innerText()).match(/#\s*(\S+)/)![1];
    }

    async expectPurchaseOrder(quoteId: string, poNumber: string, status: string): Promise<void> {
        await this.visit(`customer/account/purchase-orders/${quoteId}`);

        await expect(this.purchaseOrderHeading).toContainText(`#${poNumber}`);
        await expect(this.statusBadge(status)).toBeVisible();

        await this.visit("customer/account/purchase-orders");
        await this.waitForVueMount();

        await expect(this.purchaseOrderLink(poNumber)).toBeVisible();
    }
}
