import type { Page } from "@playwright/test";
import { ProductCreatePage, type SimpleProduct } from "../pages/admin/catalog/ProductCreatePage";
import { uniqueStamp } from "./faker";

export async function createQuotableProduct(adminPage: Page, price: number): Promise<SimpleProduct> {
    const stamp = uniqueStamp();

    return new ProductCreatePage(adminPage).createSimpleProduct({
        sku: `B2B-${stamp}`,
        name: `B2B Product ${stamp}`,
        price,
        inventory: 500,
    });
}
