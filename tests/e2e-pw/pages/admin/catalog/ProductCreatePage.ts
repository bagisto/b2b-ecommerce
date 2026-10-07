import { expect, type Locator } from "@playwright/test";
import { AdminPage } from "../AdminPage";
import { fillTinymce } from "../../../utils/tinymce";

export interface SimpleProduct {
    sku: string;
    name: string;
    price: number;
    inventory: number;
    urlKey?: string;
}

export class ProductCreatePage extends AdminPage {
    private get createProductButton(): Locator {
        return this.page.getByRole("button", { name: "Create Product" });
    }

    private get typeSelect(): Locator {
        return this.page.locator('select[name="type"]');
    }

    private get familySelect(): Locator {
        return this.page.locator('select[name="attribute_family_id"]');
    }

    private get skuInput(): Locator {
        return this.page.locator('input[name="sku"]');
    }

    private get saveProductButton(): Locator {
        return this.page.getByRole("button", { name: "Save Product" });
    }

    private get nameInput(): Locator {
        return this.page.locator("#name");
    }

    private get urlKeyInput(): Locator {
        return this.page.locator('input[name="url_key"]');
    }

    private get priceInput(): Locator {
        return this.page.locator('input[name="price"]');
    }

    private get weightInput(): Locator {
        return this.page.locator('input[name="weight"]');
    }

    private get inventoryInput(): Locator {
        return this.page.locator('input[name="inventories[1]"]');
    }

    private async fillAndConfirm(field: Locator, value: string): Promise<void> {
        await expect(async () => {
            await field.fill(value);
            await expect(field).toHaveValue(value, { timeout: 2000 });
        }).toPass({ timeout: 15000 });
    }

    async createSimpleProduct(product: SimpleProduct): Promise<SimpleProduct> {
        await this.visit("admin/catalog/products");
        await this.createProductButton.click();
        await this.typeSelect.selectOption("simple");
        await this.familySelect.selectOption("1");
        await this.skuInput.fill(product.sku);
        await this.saveProductButton.click();

        await expect(this.page).toHaveURL(/\/admin\/catalog\/products\/edit\/\d+/);

        await this.waitForVueMount();
        await this.nameInput.fill(product.name);

        await expect(this.urlKeyInput).toHaveValue(/.+/);

        const urlKey = await this.urlKeyInput.inputValue();

        await fillTinymce(this.page, "#short_description_ifr", `${product.name} short description`);
        await fillTinymce(this.page, "#description_ifr", `${product.name} description`);
        await this.fillAndConfirm(this.priceInput, String(product.price));
        await this.fillAndConfirm(this.weightInput, "1");
        await this.fillAndConfirm(this.inventoryInput, String(product.inventory));
        await this.saveProductButton.click();

        await expect(this.flash("Product updated successfully")).toBeVisible({ timeout: 60 * 1000 });

        return { ...product, urlKey };
    }
}
