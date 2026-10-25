import { expect, type APIRequestContext, type TestInfo } from '@playwright/test'

// Production builds can exhaust the real per-minute buckets faster than dev.
// Reserve capacity for a whole browser flow without disabling either limiter.
async function waitForBudget(request: APIRequestContext, testInfo: TestInfo, path: string, headers = {}) {
  const base = process.env.PLAYWRIGHT_API_BASE_URL || 'http://127.0.0.1:8089'
  const response = await request.get(`${base}${path}`, { headers })
  const remaining = Number(response.headers()['x-ratelimit-remaining'] ?? Infinity)
  if (response.ok() && remaining >= 35) return
  if (response.status() !== 429) expect(response.ok(), await response.text()).toBeTruthy()

  const seconds = response.status() === 429 ? Number(response.headers()['retry-after']) : 60
  expect(seconds).toBeGreaterThan(0)
  expect(seconds).toBeLessThanOrEqual(60)
  testInfo.setTimeout(testInfo.timeout + 61_000)
  await new Promise(resolve => setTimeout(resolve, seconds * 1000 + 250))
  const ready = await request.get(`${base}${path}`, { headers })
  expect(ready.ok(), await ready.text()).toBeTruthy()
}

export function waitForAnonymousApiBudget(request: APIRequestContext, testInfo: TestInfo) {
  return waitForBudget(request, testInfo, '/content/feature-flags')
}

export function waitForAuthenticatedApiBudget(request: APIRequestContext, testInfo: TestInfo, headers: Record<string, string>) {
  return waitForBudget(request, testInfo, '/open/workspaces', headers)
}
