import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const APP_ROOT_MARKERS = ["artisan", "composer.json"];

export const E2E_ROOT_PATH = path.resolve(
    path.dirname(fileURLToPath(import.meta.url)),
    "..",
);

export const DATA_PATH = path.join(E2E_ROOT_PATH, "data");

export const STATE_DIR_PATH = path.join(E2E_ROOT_PATH, ".state");

export const ADMIN_AUTH_STATE_PATH = path.join(
    STATE_DIR_PATH,
    "admin-auth.json",
);

function findAppRoot(startPath: string): string | null {
    let current = startPath;

    while (true) {
        const isAppRoot = APP_ROOT_MARKERS.every((marker) =>
            fs.existsSync(path.join(current, marker)),
        );

        if (isAppRoot) {
            return current;
        }

        const parent = path.dirname(current);

        if (parent === current) {
            return null;
        }

        current = parent;
    }
}

export function resolveEnvPath(): string | null {
    const localEnvPath = path.join(E2E_ROOT_PATH, ".env");

    if (fs.existsSync(localEnvPath)) {
        return localEnvPath;
    }

    const appRoot = findAppRoot(E2E_ROOT_PATH);

    if (!appRoot) {
        return null;
    }

    const appEnvPath = path.join(appRoot, ".env");

    return fs.existsSync(appEnvPath) ? appEnvPath : null;
}

export function ensureStateDir(): void {
    fs.mkdirSync(STATE_DIR_PATH, { recursive: true });
}

export function dataFile(name: string): string {
    return path.join(DATA_PATH, name);
}
