import type { Page } from "@playwright/test";
import { env } from "./env";

export async function loginAsAdmin(page: Page): Promise<void> {
    await page.goto("admin/login");
    await page.fill('input[name="email"]', env.adminEmail);
    await page.fill('input[name="password"]', env.adminPassword);
    await page.press('input[name="password"]', "Enter");

    await page.waitForURL("**/admin/dashboard");
}
