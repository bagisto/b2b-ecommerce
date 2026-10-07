import { test } from "../../setup";
import { CompaniesPage } from "../../pages/admin/b2b/CompaniesPage";
import { ConfigurationPage, type SwitchValues } from "../../pages/admin/ConfigurationPage";
import { CompanyAccountPage } from "../../pages/shop/CompanyAccountPage";
import { CompanyRegistrationPage } from "../../pages/shop/CompanyRegistrationPage";
import { CustomerSessionPage } from "../../pages/shop/CustomerSessionPage";
import { buildCompany } from "../../utils/company";
import { B2B_GENERAL_SECTION, COMPANY_APPROVAL_SETTING } from "../../utils/settings";

test.describe("company registration", () => {
    let configuration: ConfigurationPage;
    let originalSettings: SwitchValues;

    test.beforeEach(async ({ adminPage }) => {
        configuration = new ConfigurationPage(adminPage);
        originalSettings = await configuration.readSwitches(B2B_GENERAL_SECTION, [COMPANY_APPROVAL_SETTING]);

        await configuration.applySwitches(B2B_GENERAL_SECTION, { [COMPANY_APPROVAL_SETTING]: true });
    });

    test.afterEach(async () => {
        await configuration.applySwitches(B2B_GENERAL_SECTION, originalSettings);
    });

    test("should hold a new company for approval and open the company account once approved", async ({ shopPage, adminPage }) => {
        const company = buildCompany();
        const registration = new CompanyRegistrationPage(shopPage);
        const session = new CustomerSessionPage(shopPage);
        const companies = new CompaniesPage(adminPage);
        const account = new CompanyAccountPage(shopPage);

        await registration.register(company);
        await registration.expectPendingApprovalNotice();
        await companies.expectCompanyStatus(company.email, "Pending");

        await session.attemptSignIn(company.email, company.password);
        await session.expectSignInHeldForApproval();

        await companies.approveCompany(company.email);
        await companies.expectCompanyStatus(company.email, "Active");

        await session.signIn(company.email, company.password);
        await account.expectCompanyProfile(company.businessName);
        await account.expectCompanyNavigation(["Quotations", "Purchase Orders", "Users", "Roles"]);
    });

    test("should refuse a company whose business name is already registered", async ({ shopPage }) => {
        const first = buildCompany();
        const duplicate = { ...buildCompany(), businessName: first.businessName };
        const registration = new CompanyRegistrationPage(shopPage);
        const session = new CustomerSessionPage(shopPage);

        await registration.register(first);
        await registration.attemptRegister(duplicate);
        await registration.expectFieldError("The business name has already been taken.");

        await session.attemptSignIn(duplicate.email, duplicate.password);
        await session.expectSignInRejected();
    });
});
