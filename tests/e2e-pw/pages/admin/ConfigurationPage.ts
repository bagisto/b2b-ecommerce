import { expect, type Locator } from "@playwright/test";
import { AdminPage } from "./AdminPage";

export type SwitchValues = Record<string, boolean>;

export class ConfigurationPage extends AdminPage {
    private get saveButton(): Locator {
        return this.page.getByRole("button", { name: "Save Configuration" });
    }

    private switchInput(name: string): Locator {
        return this.page.locator(`input[type="checkbox"][name="${name}"]`);
    }

    private switchToggle(name: string): Locator {
        return this.page.locator(`label:has(> input[type="checkbox"][name="${name}"])`);
    }

    private async open(section: string): Promise<void> {
        await this.visit(`admin/configuration/${section}`);
        await this.waitForVueMount();
    }

    async readSwitches(section: string, names: string[]): Promise<SwitchValues> {
        await this.open(section);

        const values: SwitchValues = {};

        for (const name of names) {
            await this.switchInput(name).waitFor({ state: "attached" });

            values[name] = await this.switchInput(name).isChecked();
        }

        return values;
    }

    async applySwitches(section: string, values: SwitchValues): Promise<void> {
        await this.open(section);

        for (const [name, enabled] of Object.entries(values)) {
            await this.switchInput(name).waitFor({ state: "attached" });

            if ((await this.switchInput(name).isChecked()) !== enabled) {
                await this.switchToggle(name).click();
            }

            await expect(this.switchInput(name)).toBeChecked({ checked: enabled });
        }

        await this.saveButton.click();

        await expect(this.flash("Configuration saved successfully")).toBeVisible();

        await this.expectSwitches(section, values);
    }

    async expectSwitches(section: string, values: SwitchValues): Promise<void> {
        await this.open(section);

        for (const [name, enabled] of Object.entries(values)) {
            await expect(this.switchInput(name)).toBeChecked({ checked: enabled });
        }
    }
}
