import { defineConfig, devices } from '@playwright/test'
import config from './playwright.config'

// Optional cross-browser matrix; the standard suite keeps its Chromium project.
export default defineConfig({
  ...config,
  testMatch: 'form-focus.spec.ts',
  fullyParallel: true,
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    { name: 'firefox', use: { ...devices['Desktop Firefox'] } },
    { name: 'webkit', use: { ...devices['Desktop Safari'] } },
    { name: 'mobile-chromium', use: { ...devices['Pixel 7'] } },
  ],
})
