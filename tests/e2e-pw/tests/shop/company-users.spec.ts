import { test } from "../../setup";
import { CompanyAccountPage } from "../../pages/shop/CompanyAccountPage";
import { CompanyRolesPage } from "../../pages/shop/CompanyRolesPage";
import { CompanyUsersPage } from "../../pages/shop/CompanyUsersPage";
import { CustomerRegistrationPage } from "../../pages/shop/CustomerRegistrationPage";
import { CustomerSessionPage } from "../../pages/shop/CustomerSessionPage";
import { InvitationPage } from "../../pages/shop/InvitationPage";
import { signInAsApprovedCompany } from "../../utils/company";
import { readInvitationPath, readNewMemberPassword } from "../../utils/company-mail";
import { generateEmail, generateFirstName, generateLastName, generatePhoneNumber, uniqueStamp } from "../../utils/faker";

test.describe("company users", () => {
    test("should limit a new member to the company features their role grants", async ({ shopPage, adminPage, browser }) => {
        await signInAsApprovedCompany(shopPage, adminPage);

        const role = `Quotes Only ${uniqueStamp()}`;
        const member = {
            firstName: generateFirstName(),
            lastName: generateLastName(),
            email: generateEmail("member"),
            phone: generatePhoneNumber(),
            role,
        };

        await new CompanyRolesPage(shopPage).createCustomRole(role, ["quotes"]);
        await new CompanyUsersPage(shopPage).createMember(member);

        const password = await readNewMemberPassword(member.email);
        const memberContext = await browser.newContext();

        try {
            const memberPage = await memberContext.newPage();
            const account = new CompanyAccountPage(memberPage);

            await new CustomerSessionPage(memberPage).signIn(member.email, password);

            await account.expectCompanyPageOpen("customer/account/quotes");
            await account.expectCompanyNavigation(["Quotations"]);
            await account.expectCompanyNavigationHidden(["Company Profile", "Purchase Orders", "Users", "Roles"]);
            await account.expectCompanyPageRefused("customer/account/users");
            await account.expectCompanyPageRefused("customer/account/roles");
        } finally {
            await memberContext.close();
        }
    });

    test("should make an invited customer a member with the role they were invited to", async ({ shopPage, adminPage, browser }) => {
        await signInAsApprovedCompany(shopPage, adminPage);

        const role = `Buyer ${uniqueStamp()}`;
        const customer = {
            firstName: generateFirstName(),
            lastName: generateLastName(),
            email: generateEmail("invitee"),
            password: "admin123",
        };

        await new CompanyRolesPage(shopPage).createCustomRole(role, ["quotes"]);

        const customerContext = await browser.newContext();

        try {
            const customerPage = await customerContext.newPage();
            const account = new CompanyAccountPage(customerPage);

            await new CustomerRegistrationPage(customerPage).register(customer);
            await new CustomerSessionPage(customerPage).signIn(customer.email, customer.password);

            await new CompanyUsersPage(shopPage).inviteCustomer(customer.email, role);
            await new InvitationPage(customerPage).accept(await readInvitationPath(customer.email));

            await account.expectMemberRole(role);
            await account.expectCompanyPageOpen("customer/account/quotes");
            await account.expectCompanyNavigation(["Quotations"]);
        } finally {
            await customerContext.close();
        }
    });

    test("should refuse an invitation the company has revoked", async ({ shopPage, adminPage, browser }) => {
        await signInAsApprovedCompany(shopPage, adminPage);

        const customer = {
            firstName: generateFirstName(),
            lastName: generateLastName(),
            email: generateEmail("revoked"),
            password: "admin123",
        };

        const customerContext = await browser.newContext();

        try {
            const customerPage = await customerContext.newPage();
            const companyUsers = new CompanyUsersPage(shopPage);

            await new CustomerRegistrationPage(customerPage).register(customer);
            await new CustomerSessionPage(customerPage).signIn(customer.email, customer.password);

            await companyUsers.inviteCustomer(customer.email, "Administrator");

            const invitationPath = await readInvitationPath(customer.email);

            await companyUsers.revokeInvitation(customer.email);
            await new InvitationPage(customerPage).expectRefused(invitationPath);

            await new CompanyAccountPage(customerPage).expectCompanyNavigationHidden(["Quotations"]);
        } finally {
            await customerContext.close();
        }
    });
});
