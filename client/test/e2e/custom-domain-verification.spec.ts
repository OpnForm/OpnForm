import { expect, test } from '@playwright/test'
import { waitForAnonymousApiBudget, waitForAuthenticatedApiBudget } from './api-budget'

const api = process.env.PLAYWRIGHT_API_BASE_URL || 'http://127.0.0.1:8089'

test('custom domain settings show the real TXT proof and reject saving without DNS ownership', async ({ page, request, context, baseURL }, testInfo) => {
  test.setTimeout(120_000)
  await waitForAnonymousApiBudget(request, testInfo)

  const userAgent = await page.evaluate(() => navigator.userAgent)
  const login = await request.post(`${api}/login`, {
    headers: { 'User-Agent': userAgent },
    data: { email: 'e2e@example.test', password: 'Abcd@1234', remember: false },
  })
  expect(login.ok(), await login.text()).toBeTruthy()
  const token = (await login.json()).token
  const headers = { Authorization: `Bearer ${token}`, Accept: 'application/json', 'User-Agent': userAgent }
  await waitForAuthenticatedApiBudget(request, testInfo, headers)

  const initial = await request.get(`${api}/open/workspaces`, { headers })
  expect(initial.ok(), await initial.text()).toBeTruthy()
  const workspace = (await initial.json()).find(workspace => workspace.is_admin && workspace.features.includes('custom_domain'))
  expect(workspace, 'The seeded admin must have a workspace with custom domains enabled').toBeTruthy()
  const originalDomains = workspace.custom_domains ?? []
  const domain = `unverified-qa-${Date.now()}.example.com`
  expect(originalDomains).not.toContain(domain)

  if (!baseURL) throw new Error('Playwright baseURL is required')
  await context.addCookies([{ name: 'opnform_token', value: token, url: baseURL }])
  await page.goto(`/home?workspace_id=${workspace.id}&workspace-settings=domains`)
  await page.waitForFunction(() => {
    const root = document.querySelector('#__nuxt') as Element & {
      __vue_app__?: { $nuxt?: { isHydrating: boolean } }
    }
    return root?.__vue_app__?.$nuxt?.isHydrating === false
  })
  await expect(page.getByRole('heading', { name: 'Custom Domains Settings', exact: true })).toBeVisible()
  await page.getByPlaceholder('yourdomain.com').fill(domain)

  const proofResponse = page.waitForResponse(response => {
    const url = new URL(response.url())
    return url.pathname === `/open/workspaces/${workspace.id}/custom-domains/verification`
      && url.searchParams.get('domain') === domain
      && response.request().method() === 'GET'
  })
  await page.getByRole('button', { name: 'Add', exact: true }).click()
  const proof = await proofResponse
  expect(proof.ok(), await proof.text()).toBeTruthy()
  const record = await proof.json()
  expect(record).toMatchObject({ type: 'TXT', host: `_opnform-verification.${domain}` })
  expect(record.value).toMatch(/^opnform-verification=[a-f0-9]{64}$/)
  await expect(page.getByText(`Verify ownership of ${domain}`, { exact: true })).toBeVisible()
  await expect(page.getByText(record.host, { exact: true })).toBeVisible()
  await expect(page.getByText(record.value, { exact: true })).toBeVisible()

  const saveResponse = page.waitForResponse(response => response.url() === `${api}/open/workspaces/${workspace.id}/custom-domains`
    && response.request().method() === 'PUT')
  await page.getByRole('button', { name: 'Save Domain(s)', exact: true }).click()
  const save = await saveResponse
  expect(save.status()).toBe(422)
  const validation = await save.json()
  expect(validation.errors.custom_domains).toHaveLength(1)
  await expect(page.getByText(validation.errors.custom_domains[0], { exact: true })).toBeVisible()

  // Check persistence through the real API; a rejected request must preserve all existing domains.
  const refreshed = await request.get(`${api}/open/workspaces`, { headers })
  expect(refreshed.ok(), await refreshed.text()).toBeTruthy()
  const savedWorkspace = (await refreshed.json()).find(current => current.id === workspace.id)
  expect(savedWorkspace.custom_domains ?? []).toEqual(originalDomains)
})
