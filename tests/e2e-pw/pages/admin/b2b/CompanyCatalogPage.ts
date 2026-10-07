import { expect, type Locator } from "@playwright/test";
import { AdminPage } from "../AdminPage";

export interface CatalogTier {
    qty: number;
    price: number;
}

export class CompanyCatalogPage extends AdminPage {
    private get createButton(): Locator {
        return this.page.getByRole("button", { name: "Create Company Catalog" });
    }

    private get assignProductsButton(): Locator {
        return this.page.getByRole("button", { name: "Assign Products" });
    }

    private get assignCompaniesButton(): Locator {
        return this.page.getByRole("button", { name: "Assign Companies" });
    }

    private get saveCatalogButton(): Locator {
        return this.page.getByRole("button", { name: "Save Catalog" });
    }

    private get tierPanel(): Locator {
        return this.page
            .locator("div.box-shadow")
            .filter({ has: this.page.getByRole("button", { name: /Add price break/ }) })
            .filter({ visible: true });
    }

    private modalPanel(title: string): Locator {
        return this.page
            .locator("div.box-shadow")
            .filter({ has: this.page.locator("p, h2", { hasText: new RegExp(`^\\s*${title}`) }) })
            .filter({ visible: true });
    }

    private productRow(name: string): Locator {
        return this.page
            .locator("tr", { has: this.page.locator("span", { hasText: new RegExp(`^\\s*${name}\\s*$`) }) })
            .filter({ has: this.page.locator("select") });
    }

    private assignedCompanyRow(businessName: string): Locator {
        return this.page
            .locator("tr", { hasText: businessName })
            .filter({ has: this.page.locator("span.icon-delete") });
    }

    private async searchInModal(panel: Locator, placeholder: string, endpoint: string, term: string): Promise<void> {
        await Promise.all([
            this.page.waitForResponse((response) => {
                const url = decodeURIComponent(response.url()).replace(/\+/g, " ");

                return url.includes(endpoint) && url.includes(term);
            }),
            panel.getByPlaceholder(placeholder).fill(term),
        ]);
    }

    async createCatalog(name: string): Promise<void> {
        await this.visit("admin/b2b/company-catalogs");
        await this.waitForVueMount();
        await this.createButton.click();

        const panel = this.modalPanel("Create Company Catalog");

        await panel.locator('input[name="name"]').fill(name);
        await panel.getByRole("button", { name: "Save & Continue" }).click();

        await expect(this.page).toHaveURL(/company-catalogs\/edit\/\d+/);
        await expect(this.flash("Company catalog created successfully.")).toBeVisible();

        await this.waitForVueMount();
    }

    async addProductWithFlatPrice(productName: string, price: number, tier?: CatalogTier): Promise<void> {
        await this.assignProductsButton.click();

        const panel = this.modalPanel("Assign Products");

        await this.searchInModal(panel, "Search products by name or SKU", "/company-catalogs/products", productName);
        await panel.locator("tr", { hasText: productName }).click();
        await panel.getByRole("button", { name: /^Assign \(1\)$/ }).click();

        const row = this.productRow(productName);

        await expect(row).toBeVisible();

        await row.locator("select").selectOption("fixed");
        await row.locator('input[type="number"]').fill(String(price));

        if (!tier) {
            return;
        }

        await row.getByText("Tier Pricing", { exact: true }).click();
        await this.tierPanel.getByRole("button", { name: /Add price break/ }).click();

        const breakRow = this.tierPanel.locator("tr", { has: this.page.locator('input[min="2"]') });

        await breakRow.locator('input[min="2"]').fill(String(tier.qty));
        await breakRow.locator("select").selectOption("fixed");
        await breakRow.locator('input[step="0.01"]').fill(String(tier.price));
        await this.tierPanel.getByRole("button", { name: "Done", exact: true }).click();

        await expect(row.getByText(/Tier Pricing\s*\(1\)/)).toBeVisible();
    }

    async assignCompany(businessName: string): Promise<void> {
        await this.assignCompaniesButton.click();

        const panel = this.modalPanel("Assign Companies");

        await this.searchInModal(panel, "Search companies by name or email", "/company-catalogs/companies", businessName);
        await panel.locator("tr", { hasText: businessName }).click();
        await panel.getByRole("button", { name: /^Assign \(1\)$/ }).click();

        await expect(this.assignedCompanyRow(businessName)).toBeVisible();
    }

    async saveCatalog(): Promise<void> {
        await this.saveCatalogButton.click();

        await Promise.all([
            this.page.waitForURL(/admin\/b2b\/company-catalogs(\?.*)?$/),
            this.page.getByRole("button", { name: "Confirm & Save" }).click(),
        ]);

        await expect(this.flash("Company catalog updated successfully.")).toBeVisible();
    }
}
