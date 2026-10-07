import dotenv from "dotenv";
import { resolveEnvPath } from "./paths";

export interface E2eEnv {
    baseUrl: string;
    adminEmail: string;
    adminPassword: string;
    timezone: string;
    headed: boolean;
    mailpitUrl: string;
}

const envPath = resolveEnvPath();

if (envPath) {
    dotenv.config({ path: envPath });
}

function required(names: string[]): string {
    for (const name of names) {
        const value = process.env[name];

        if (value && value.trim()) {
            return value.trim();
        }
    }

    throw new Error(
        `[e2e-pw] Set one of ${names.join(" or ")} in the application .env, tests/e2e-pw/.env, or the shell.`,
    );
}

function optional(name: string, defaultValue: string): string {
    const value = process.env[name];

    return value && value.trim() ? value.trim() : defaultValue;
}

function optionalBoolean(name: string, defaultValue: boolean): boolean {
    const value = process.env[name];

    if (!value || !value.trim()) {
        return defaultValue;
    }

    return ["1", "true", "yes", "on"].includes(value.trim().toLowerCase());
}

export const env: E2eEnv = {
    baseUrl: required(["APP_URL", "BASE_URL"]).replace(/\/+$/, ""),

    adminEmail: optional("BAGISTO_ADMIN_EMAIL", "admin@example.com"),

    adminPassword: optional("BAGISTO_ADMIN_PASSWORD", "admin123"),

    timezone: optional("APP_TIMEZONE", "UTC"),

    headed: optionalBoolean("HEADED", false),

    mailpitUrl: optional("MAILPIT_URL", "http://127.0.0.1:8025").replace(/\/+$/, ""),
};
