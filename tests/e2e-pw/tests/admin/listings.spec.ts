import { test } from "../../setup";
import { ListingPage } from "../../pages/admin/b2b/ListingPage";

const listings = [
    { path: "admin/b2b/companies", title: "Company" },
    { path: "admin/b2b/quotes", title: "Quotations" },
    { path: "admin/b2b/purchase-orders", title: "Purchase Orders" },
    { path: "admin/b2b/company-credits", title: "Company Credit" },
    { path: "admin/b2b/company-catalogs", title: "Company Catalogs" },
    { path: "admin/b2b/attributes", title: "Company Attributes" },
];

test.describe("b2b admin listings", () => {
    for (const { path, title } of listings) {
        test(`should render the ${title.toLowerCase()} listing and load its records`, async ({ adminPage }) => {
            await new ListingPage(adminPage).expectListingRenders(path, title);
        });
    }
});
