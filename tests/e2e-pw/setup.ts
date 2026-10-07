import { test as base, expect, type BrowserContext, type Page } from "@playwright/test";
import fs from "fs";
import { loginAsAdmin } from "./utils/admin";
import { ADMIN_AUTH_STATE_PATH, ensureStateDir } from "./utils/paths";

type Fixtures = {
    adminPage: Page;
    shopPage: Page;
};

async function saveAdminAuth(context: BrowserContext): Promise<void> {
    ensureStateDir();

    await context.storageState({ path: ADMIN_AUTH_STATE_PATH });
}

export const test = base.extend<Fixtures>({
    adminPage: async ({ browser }, use) => {
        const authExists = fs.existsSync(ADMIN_AUTH_STATE_PATH);

        const context = await browser.newContext(
            authExists ? { storageState: ADMIN_AUTH_STATE_PATH } : {},
        );

        await context.addInitScript(() => {
            window.localStorage.removeItem("datagrids");
        });

        const page = await context.newPage();

        if (authExists) {
            await page.goto("admin/dashboard");
        }

        if (
            !authExists
            || page.url().includes("admin/login")
        ) {
            await loginAsAdmin(page);
            await saveAdminAuth(context);
        }

        await use(page);
        await context.close();
    },

    shopPage: async ({ browser }, use) => {
        const context = await browser.newContext();

        await context.addInitScript(() => {
            window.localStorage.removeItem("datagrids");
        });

        const page = await context.newPage();

        await use(page);
        await context.close();
    },
});

export { expect };
