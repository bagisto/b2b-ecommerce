import type { Page } from "@playwright/test";
import { CompaniesPage } from "../pages/admin/b2b/CompaniesPage";
import { CompanyRegistrationPage, type CompanyAccount } from "../pages/shop/CompanyRegistrationPage";
import { CustomerSessionPage } from "../pages/shop/CustomerSessionPage";
import { generateEmail, generateFirstName, generateLastName, generatePhoneNumber, uniqueStamp } from "./faker";

export function buildCompany(): CompanyAccount {
    return {
        firstName: generateFirstName(),
        lastName: generateLastName(),
        email: generateEmail("company"),
        phone: generatePhoneNumber(),
        businessName: `Company ${uniqueStamp()}`,
        password: "admin123",
    };
}

export async function signInAsApprovedCompany(shopPage: Page, adminPage: Page): Promise<CompanyAccount> {
    const company = buildCompany();

    await new CompanyRegistrationPage(shopPage).register(company);
    await new CompaniesPage(adminPage).approveCompany(company.email);
    await new CustomerSessionPage(shopPage).signIn(company.email, company.password);

    return company;
}
