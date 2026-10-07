import { expect, type Locator } from "@playwright/test";
import { ShopPage } from "./ShopPage";

export class RequisitionListsPage extends ShopPage {
    private get listDropdown(): Locator {
        return this.page.locator("#requisition-list-dropdown");
    }

    private get createListForm(): Locator {
        return this.page
            .locator("form")
            .filter({ has: this.page.locator('textarea[name="description"]') })
            .filter({ hasText: "Create Requisition List" });
    }

    private get agreeButton(): Locator {
        return this.page.getByRole("button", { name: "Agree", exact: true });
    }

    private listRow(name: string): Locator {
        return this.page
            .locator("div.row:not(.datagrid-head)")
            .filter({ hasText: name })
            .filter({ visible: true });
    }

    private listItem(productName: string): Locator {
        return this.page
            .locator('div:has(> div > input[id^="item_"])')
            .filter({ has: this.page.locator("p", { hasText: new RegExp(`^\\s*${productName}\\s*$`) }) });
    }

    private itemQuantity(productName: string): Locator {
        return this.listItem(productName).locator('div:has(> button[aria-label="Increase Quantity"]) > p');
    }

    private async fillAndSaveNewList(name: string): Promise<void> {
        await expect(this.createListForm.locator('input[name="name"]')).toBeVisible();

        await this.createListForm.locator('input[name="name"]').fill(name);
        await this.createListForm.locator('textarea[name="description"]').fill(`${name} for repeat orders.`);
        await this.createListForm.getByRole("button", { name: "Save", exact: true }).click();
    }

    private async pickList(name: string): Promise<void> {
        await this.listDropdown.getByText("Add To Requisition List", { exact: true }).click();
        await this.listDropdown.locator("li", { hasText: new RegExp(`^\\s*${name}\\s*$`) }).click();

        await expect(this.flash("Product added to requisition list successfully.")).toBeVisible();
    }

    async createList(name: string): Promise<void> {
        await this.visit("customer/account/requisitions");
        await this.waitForVueMount();

        await this.page.getByRole("button", { name: "Create", exact: true }).click();
        await this.fillAndSaveNewList(name);

        await expect(this.flash("Requisition list created successfully.")).toBeVisible();
    }

    async saveCartToNewList(name: string): Promise<void> {
        await this.visit("checkout/cart");
        await this.waitForVueMount();

        await this.listDropdown.getByText("Add To Requisition List", { exact: true }).click();
        await this.listDropdown.getByText("Create New Requisition List", { exact: true }).click();
        await this.fillAndSaveNewList(name);

        await expect(this.page).toHaveURL(/customer\/account\/requisitions$/);
        await expect(this.flash("Requisition list created successfully.")).toBeVisible();

        await this.visit("checkout/cart");
        await this.waitForVueMount();
        await this.pickList(name);
    }

    async addProductToList(urlKey: string, name: string): Promise<void> {
        await this.visit(urlKey);
        await this.waitForVueMount();

        await this.pickList(name);
    }

    async openList(name: string): Promise<string> {
        await this.visit("customer/account/requisitions");
        await this.waitForVueMount();

        await this.listRow(name).locator("span.icon-edit").click();

        await expect(this.page).toHaveURL(/customer\/account\/requisitions\/edit\/\d+$/);
        await this.waitForVueMount();

        return this.page.url().match(/edit\/(\d+)$/)![1];
    }

    async raiseItemQuantity(productName: string, quantity: number): Promise<void> {
        const increase = this.listItem(productName).getByRole("button", { name: "Increase Quantity" });

        for (let current = Number(await this.itemQuantity(productName).innerText()); current < quantity; current++) {
            await increase.click();
        }

        await expect(this.itemQuantity(productName)).toHaveText(String(quantity));

        await this.page.getByRole("button", { name: "Update Items", exact: true }).click();

        await expect(this.flash("Requisition list items updated successfully.")).toBeVisible();
    }

    async expectItemQuantity(productName: string, quantity: number): Promise<void> {
        await expect(this.itemQuantity(productName)).toHaveText(String(quantity));
    }

    async moveAllItemsToCart(): Promise<void> {
        await this.page.locator('label[for="select-all"]').click();
        await this.page.getByRole("button", { name: "Move Selected To Cart", exact: true }).click();
        await this.agreeButton.click();

        await expect(this.page).toHaveURL(/checkout\/cart$/);
        await expect(this.flash("Product moved to cart successfully.")).toBeVisible();
    }

    async expectOutsideCatalogNotice(): Promise<void> {
        await expect(this.flash("This product is not available in your company catalog.")).toBeVisible();
    }

    async expectListRefused(listId: string): Promise<void> {
        const response = await this.page.goto(`customer/account/requisitions/edit/${listId}`);

        expect(response?.status()).toBe(404);
    }
}
