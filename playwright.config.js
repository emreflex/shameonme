import { defineConfig } from "@playwright/test";
export default defineConfig({
    testDir: "tests/Browser",
    workers: 1,
    reporter: "list",
    use: {
        baseURL: process.env.BROWSER_TEST_URL || "http://127.0.0.1:8001",
        headless: true,
        launchOptions: {
            executablePath: process.env.CHROMIUM_PATH || undefined,
            args: ["--no-sandbox"],
        },
    },
});
