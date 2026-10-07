import { test } from "../../setup";
import { CompaniesPage } from "../../pages/admin/b2b/CompaniesPage";
import { QuoteListPage } from "../../pages/admin/b2b/QuoteListPage";
import { ConfigurationPage, type SwitchValues } from "../../pages/admin/ConfigurationPage";
import { RestrictedAdminPage } from "../../pages/admin/RestrictedAdminPage";
import { RolesPage } from "../../pages/admin/settings/RolesPage";
import { UsersPage, type AdminUser } from "../../pages/admin/settings/UsersPage";
import { CartPage } from "../../pages/shop/CartPage";
import { CompanyRegistrationPage } from "../../pages/shop/CompanyRegistrationPage";
import { buildCompany, signInAsApprovedCompany } from "../../utils/company";
import { generateEmail, uniqueStamp } from "../../utils/faker";
import { createQuotableProduct } from "../../utils/product";
import { B2B_GENERAL_SECTION, COMPANY_APPROVAL_SETTING } from "../../utils/settings";

test.describe("b2b admin permissions", () => {
    let configuration: ConfigurationPage;
    let originalSettings: SwitchValues;
    let salesRepresentative: AdminUser;

    test.beforeEach(async ({ adminPage }) => {
        configuration = new ConfigurationPage(adminPage);
        originalSettings = await configuration.readSwitches(B2B_GENERAL_SECTION, [COMPANY_APPROVAL_SETTING]);

        await configuration.applySwitches(B2B_GENERAL_SECTION, { [COMPANY_APPROVAL_SETTING]: true });

        salesRepresentative = {
            name: `Sales Rep ${uniqueStamp()}`,
            email: generateEmail("sales-rep"),
            password: "admin12345",
            role: `B2B Sales Rep ${uniqueStamp()}`,
        };
    });

    test.afterEach(async ({ adminPage }) => {
        try {
            await new UsersPage(adminPage).deleteUserIfPresent(salesRepresentative.email);
        } finally {
            try {
                await new RolesPage(adminPage).deleteRoleIfPresent(salesRepresentative.role);
            } finally {
                await configuration.applySwitches(B2B_GENERAL_SECTION, originalSettings);
            }
        }
    });

    test("should let a sales representative approve their company but not manage company credit", async ({ adminPage, shopPage, browser }) => {
        const company = buildCompany();
        const companies = new CompaniesPage(adminPage);

        await new CompanyRegistrationPage(shopPage).register(company);
        await companies.expectCompanyStatus(company.email, "Pending");

        await new RolesPage(adminPage).createRole(salesRepresentative.role, ["b2b.companies"]);
        await new UsersPage(adminPage).createUser(salesRepresentative);
        await companies.assignSalesRepresentative(company.email, salesRepresentative.name);

        const context = await browser.newContext();

        try {
            const restrictedPage = await context.newPage();
            const restrictedAdmin = new RestrictedAdminPage(restrictedPage);

            await restrictedAdmin.login(salesRepresentative.email, salesRepresentative.password);
            await restrictedAdmin.expectRouteAllowed("admin/b2b/companies");
            await restrictedAdmin.expectRouteDenied("admin/b2b/company-credits");

            await new CompaniesPage(restrictedPage).approveCompany(company.email);
        } finally {
            await context.close();
        }

        await companies.expectCompanyStatus(company.email, "Active");
    });

    test("should only show a sales representative the quotations of their own companies", async ({ adminPage, shopPage, browser }) => {
        test.setTimeout(240 * 1000);

        const product = await createQuotableProduct(adminPage, 100);
        const ownQuote = `Rep Quote ${uniqueStamp()}`;
        const otherQuote = `Other Quote ${uniqueStamp()}`;

        const ownCompany = await signInAsApprovedCompany(shopPage, adminPage);
        const ownCart = new CartPage(shopPage);

        await ownCart.addProduct(product.urlKey!, 1);
        await ownCart.requestQuote({ name: ownQuote, description: "Quote for the assigned company.", attachments: [] });

        const otherContext = await browser.newContext();
        let otherQuoteId: string;

        try {
            const otherPage = await otherContext.newPage();
            const otherCart = new CartPage(otherPage);

            await signInAsApprovedCompany(otherPage, adminPage);
            await otherCart.addProduct(product.urlKey!, 1);

            otherQuoteId = await otherCart.requestQuote({ name: otherQuote, description: "Quote for another company.", attachments: [] });
        } finally {
            await otherContext.close();
        }

        await new RolesPage(adminPage).createRole(salesRepresentative.role, ["b2b.quotes"]);
        await new UsersPage(adminPage).createUser(salesRepresentative);
        await new CompaniesPage(adminPage).assignSalesRepresentative(ownCompany.email, salesRepresentative.name);

        const repContext = await browser.newContext();

        try {
            const repPage = await repContext.newPage();
            const quotes = new QuoteListPage(repPage);

            await new RestrictedAdminPage(repPage).login(salesRepresentative.email, salesRepresentative.password);

            await quotes.expectQuoteListed(ownQuote);
            await quotes.expectQuoteNotListed(otherQuote);
            await new RestrictedAdminPage(repPage).expectRouteForbidden(`admin/b2b/quotes/${otherQuoteId!}`);
        } finally {
            await repContext.close();
        }
    });
});
