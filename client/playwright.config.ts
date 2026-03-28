import { readFileSync } from "node:fs"
import { resolve } from "node:path"
import { defineConfig, devices } from "@playwright/test"

function loadEnvFile(path: string) {
  const values: Record<string, string> = {}
  const contents = readFileSync(path, "utf8")

  for (const rawLine of contents.split("\n")) {
    const line = rawLine.trim()
    if (!line || line.startsWith("#")) {
      continue
    }

    const separatorIndex = line.indexOf("=")
    if (separatorIndex === -1) {
      continue
    }

    const key = line.slice(0, separatorIndex).trim()
    let value = line.slice(separatorIndex + 1).trim()
    if (
      (value.startsWith('"') && value.endsWith('"')) ||
      (value.startsWith("'") && value.endsWith("'"))
    ) {
      value = value.slice(1, -1)
    }

    values[key] = value
  }

  return values
}

const e2eEnv = loadEnvFile(resolve(process.cwd(), ".env.e2e"))
const baseURL = process.env.PLAYWRIGHT_BASE_URL || e2eEnv.NUXT_PUBLIC_APP_URL || "http://127.0.0.1:3100"
const basePort = Number(new URL(baseURL).port || "80")
const shouldManageWebServer = process.env.PLAYWRIGHT_NO_WEB_SERVER !== "1"
const artifactsDir = resolve(process.cwd(), "../.playwright")
const workerCount = Number(process.env.PLAYWRIGHT_WORKERS || "1")

export default defineConfig({
  testDir: "./test/e2e",
  outputDir: resolve(artifactsDir, "test-results"),
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: Number.isFinite(workerCount) && workerCount > 0 ? workerCount : 1,
  reporter: [
    ["list"],
    ["html", { open: "never", outputFolder: resolve(artifactsDir, "playwright-report") }],
  ],
  use: {
    baseURL,
    trace: "retain-on-failure",
    screenshot: "only-on-failure",
    video: "retain-on-failure",
    headless: true,
  },
  projects: [
    {
      name: "chromium",
      use: { ...devices["Desktop Chrome"] },
    },
  ],
  webServer: shouldManageWebServer
    ? {
        command: "node --require ./scripts/e2e-runtime-guard.cjs .output/server/index.mjs",
        env: {
          ...process.env,
          ...e2eEnv,
          NODE_ENV: "production",
          NITRO_HOST: "0.0.0.0",
          NITRO_PORT: String(basePort),
        },
        port: basePort,
        reuseExistingServer: !process.env.CI,
        stdout: "pipe",
        stderr: "pipe",
        timeout: 180_000,
      }
    : undefined,
})
