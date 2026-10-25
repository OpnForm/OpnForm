import { test, expect, type APIRequestContext, type Page } from '@playwright/test'
import { readFileSync } from 'node:fs'
import { waitForAnonymousApiBudget, waitForAuthenticatedApiBudget } from './api-budget'

const api = process.env.PLAYWRIGHT_API_BASE_URL || 'http://127.0.0.1:8089'
let headers: Record<string, string>
let workspaceId: number

test.beforeAll(async ({ request }) => {
  const login = await request.post(`${api}/login`, { data: {
    email: 'e2e@example.test', password: 'Abcd@1234', remember: false,
  } })
  expect(login.ok()).toBeTruthy()
  headers = { Authorization: `Bearer ${(await login.json()).token}`, Accept: 'application/json' }
  const response = await request.get(`${api}/open/workspaces`, { headers })
  expect(response.ok()).toBeTruthy()
  workspaceId = (await response.json())[0].id
})

test.beforeEach(async ({ request }, testInfo) => {
  await waitForAnonymousApiBudget(request, testInfo)
  await waitForAuthenticatedApiBudget(request, testInfo, headers)
})

async function waitForHydration(page: Page) {
  await page.waitForFunction(() => {
    const root = document.querySelector('#__nuxt') as Element & {
      __vue_app__?: { $nuxt?: { isHydrating: boolean } }
    }
    return root?.__vue_app__?.$nuxt?.isHydrating === false
  })
}

async function createForm(request: APIRequestContext, properties: unknown[], language = 'en') {
  const response = await request.post(`${api}/open/forms`, { headers, data: {
    title: `Security dependency QA ${language} ${Date.now()}`,
    workspace_id: workspaceId, visibility: 'public', properties, language,
    presentation_style: 'classic', theme: 'default', width: 'centered', size: 'md',
    dark_mode: 'light', border_radius: 'small', color: '#3B82F6',
    no_branding: false, uppercase_labels: false, transparent_background: false,
    auto_save: false, auto_focus: false, use_captcha: false,
    submitted_text: 'Security QA submission received.',
  } })
  expect(response.ok(), await response.text()).toBeTruthy()
  return (await response.json()).form
}

for (const language of ['fr', 'ar']) {
  test(`lazy UI locale preserves ${language} public rendering and hydration`, async ({ page, request }) => {
    const messages = JSON.parse(readFileSync(new URL(`../../i18n/lang/${language}.json`, import.meta.url), 'utf8'))
    const form = await createForm(request, [{ id: 'name', name: 'Name', type: 'text' }], language)
    const warnings: string[] = []
    page.on('console', message => {
      if (/hydration.*mismatch/i.test(message.text())) warnings.push(message.text())
    })
    await page.goto(`/forms/${form.slug}`)
    await waitForHydration(page)
    await expect(page.getByRole('button', { name: messages.forms.buttons.submit, exact: true })).toBeVisible()
    await expect.poll(() => page.evaluate(() => {
      const root = document.querySelector('#__nuxt') as Element & {
        __vue_app__: { _context: { provides: Record<symbol, { value: { code: string } }> } }
      }
      return root.__vue_app__._context.provides[Symbol.for('nuxt-ui.locale-context')]?.value.code
    })).toBe(language)
    expect(warnings).toEqual([])
  })
}

test('local file upload survives a public submission on the production client', async ({ page, request }) => {
  const form = await createForm(request, [{ id: 'attachment', name: 'Attachment', type: 'files', required: true }])
  await page.goto(`/forms/${form.slug}`)
  await waitForHydration(page)
  await page.locator('input[type="file"]').setInputFiles({
    name: 'security-qa.txt', mimeType: 'text/plain', buffer: Buffer.from('Dependency migration upload QA\n'),
  })
  await expect(page.getByText('security-qa.txt', { exact: true })).toBeVisible()
  await page.getByRole('button', { name: 'Submit', exact: true }).click()
  await expect(page.getByText('Security QA submission received.', { exact: true })).toBeVisible()
  const response = await request.get(`${api}/open/forms/${form.slug}/submissions`, { headers })
  expect(response.ok()).toBeTruthy()
  expect(JSON.stringify(await response.json())).toContain('security-qa')
})
