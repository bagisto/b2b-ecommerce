import { test } from "../../setup";
import { CompanyProfilePage } from "../../pages/shop/CompanyProfilePage";
import { CompanyRolesPage } from "../../pages/shop/CompanyRolesPage";
import { CompanyUsersPage } from "../../pages/shop/CompanyUsersPage";
import { CustomerSessionPage } from "../../pages/shop/CustomerSessionPage";
import { signInAsApprovedCompany } from "../../utils/company";
import { readNewMemberPassword } from "../../utils/company-mail";
import { generateEmail, generateFirstName, generateLastName, generatePhoneNumber, uniqueStamp } from "../../utils/faker";

test.describe("company profile", () => {
    test("should show the company's edited details on its profile", async ({ shopPage, adminPage }) => {
        const company = await signInAsApprovedCompany(shopPage, adminPage);
        const profile = new CompanyProfilePage(shopPage);
        const stamp = uniqueStamp();

        await profile.editProfile({
            address: `${stamp} Harbour Road`,
            city: "Pune",
            state: "Maharashtra",
            country: "India",
            postcode: "411001",
            website_url: `https://company-${stamp}.example.com`,
        });

        await profile.expectProfileValues({
            "Business Name": company.businessName,
            Address: `${stamp} Harbour Road`,
            City: "Pune",
            State: "Maharashtra",
            Country: "India",
            Postcode: "411001",
            "Website URL": `https://company-${stamp}.example.com`,
        });
    });

    test("should let a member without the edit permission read the profile but not change it", async ({ shopPage, adminPage, browser }) => {
        const company = await signInAsApprovedCompany(shopPage, adminPage);

        const role = `Profile Viewer ${uniqueStamp()}`;
        const member = {
            firstName: generateFirstName(),
            lastName: generateLastName(),
            email: generateEmail("viewer"),
            phone: generatePhoneNumber(),
            role,
        };

        await new CompanyRolesPage(shopPage).createCustomRole(role, ["company_profile"], ["company_profile.edit"]);
        await new CompanyUsersPage(shopPage).createMember(member);

        const password = await readNewMemberPassword(member.email);
        const memberContext = await browser.newContext();

        try {
            const memberPage = await memberContext.newPage();

            await new CustomerSessionPage(memberPage).signIn(member.email, password);
            await new CompanyProfilePage(memberPage).expectReadOnly(company.businessName);
        } finally {
            await memberContext.close();
        }
    });
});
