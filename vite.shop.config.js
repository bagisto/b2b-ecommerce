import { defineConfig, loadEnv } from "vite";
import laravel from "laravel-vite-plugin";
import path from "path";
import { createRequire } from "module";
import tailwindcss from "@tailwindcss/vite";

/**
 * B2B Suite — storefront build.
 *
 * Builds this package's own stylesheet into its own build directory. It must not
 * write to `themes/shop/default`: the core ships its own bundle there and
 * replaces it on upgrade.
 */

/**
 * Loaded with `createRequire`, not a static `import`. Vite pre-bundles this
 * config with esbuild in ESM format; esbuild inlines a statically imported
 * CommonJS file, and `paths.cjs`'s own `require("fs")` then fails with
 * `Dynamic require of "fs" is not supported`.
 */
const paths = createRequire(import.meta.url)("./paths.cjs");

export default defineConfig(({ mode }) => {
    Object.assign(process.env, loadEnv(mode, paths.appRoot));

    return {
        build: {
            emptyOutDir: true,
        },

        envDir: paths.appRoot,

        server: {
            host: process.env.VITE_HOST || "localhost",
            port: process.env.VITE_PORT || 5173,
            cors: true,
        },

        /**
         * `@core-shop-css` lets `shop.css` reference the core theme's entry without
         * hardcoding a `../` depth — the package is built from several depths, which
         * is why `paths.cjs` resolves the application root by walking up.
         */
        resolve: {
            alias: {
                "@core-shop-css": path.join(
                    paths.shopDir,
                    "src/Resources/assets/css/app.css"
                ),
            },
        },

        plugins: [
            tailwindcss(),

            laravel({
                /**
                 * Written verbatim, so an absolute path is safe. The filename must
                 * match `hot_file` in `src/Config/bagisto-vite.php`, which Laravel
                 * resolves relative to `public/`.
                 */
                hotFile: path.join(paths.appPublic, "b2b-suite-shop-vite.hot"),

                /**
                 * Must stay relative to this package: the plugin strips a leading
                 * slash and joins this with `buildDirectory` to form
                 * `build.outDir`, so an absolute path resolves inside the package
                 * and leaks a stray `public/` into it.
                 */
                publicDirectory: path.relative(paths.packageDir, paths.appPublic),

                buildDirectory: paths.shopBuildDirectory,

                input: ["src/Resources/assets/css/shop.css"],

                refresh: true,
            }),
        ],

        experimental: {
            /**
             * Matches the core themes: assets referenced from CSS are emitted
             * alongside the stylesheet, so a bare filename resolves.
             */
            renderBuiltUrl(filename, { hostType }) {
                if (hostType === "css") {
                    return path.basename(filename);
                }
            },
        },
    };
});
