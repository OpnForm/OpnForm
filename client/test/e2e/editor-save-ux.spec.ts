import { expect, test } from '@playwright/test'
import { waitForAnonymousApiBudget, waitForAuthenticatedApiBudget } from './api-budget'

test.setTimeout(60_000)

const api = process.env.PLAYWRIGHT_API_BASE_URL || 'http://127.0.0.1:8089'
let headers: Record<string, string>
let form: { id: number, slug: string }

test.beforeEach(async ({ page, request }, testInfo) => {
  await waitForAnonymousApiBudget(request, testInfo)
  const login = await request.post(`${api}/login`, { data: {
    email: 'e2e@example.test', password: 'Abcd@1234', remember: false,
  } })
  expect(login.ok(), await login.text()).toBeTruthy()
  const token = (await login.json()).token
  headers = { Authorization: `Bearer ${token}`, Accept: 'application/json' }
  await waitForAuthenticatedApiBudget(request, testInfo, headers)
  const workspaces = await request.get(`${api}/open/workspaces`, { headers })
  expect(workspaces.ok()).toBeTruthy()
  const workspaceId = (await workspaces.json())[0].id
  const response = await request.post(`${api}/open/forms`, { headers, data: {
    title: `Save UX ${Date.now()}`, workspace_id: workspaceId, visibility: 'public',
    properties: [{ id: 'answer', name: 'Answer', type: 'text' }],
    language: 'en', theme: 'default', presentation_style: 'classic', width: 'centered',
    size: 'md', border_radius: 'small', no_branding: false, dark_mode: 'light', color: '#3B82F6',
    uppercase_labels: false, transparent_background: false, auto_save: false, auto_focus: false,
    use_captcha: false, settings: {},
  } })
  expect(response.ok(), await response.text()).toBeTruthy()
  form = (await response.json()).form
  await page.context().addCookies([{ name: 'opnform_token', value: token, url: testInfo.project.use.baseURL as string }])
})

test.afterEach(async ({ request }) => {
  if (form) {
    const removed = await request.delete(`${api}/open/forms/${form.id}`, { headers })
    expect(removed.ok(), await removed.text()).toBeTruthy()
  }
})

for (const [visibility, label] of [
  ['draft', 'Draft - not publicly accessible'],
  ['closed', "Closed - won't accept new submissions"],
  ['public', 'Published'],
]) {
  test(`settings save ${visibility} in one click and persist after reload`, async ({ page, request }) => {
    await page.goto(`/forms/${form.slug}/edit`)
    await expect(page.getByTestId('form-save-status')).toHaveText('All changes saved', { timeout: 15_000 })
    await expect(page.getByTestId('save-form-button')).toHaveText('Save Changes')
    await page.getByRole('button', { name: 'Settings', exact: true }).click()
    const settings = page.getByRole('dialog', { name: 'Form settings', exact: true })
    await expect(settings).toBeVisible()
    const title = `Saved ${visibility} ${Date.now()}`
    await settings.getByRole('textbox', { name: 'Form Name', exact: true }).fill(title)
    await settings.getByRole('radio', { name: label, exact: true }).check()
    let saves = 0
    page.on('request', request => {
      if (request.method() === 'PUT' && request.url() === `${api}/open/forms/${form.id}`) saves++
    })
    await settings.getByRole('button', { name: 'Save and close', exact: true }).click()
    await expect(page).toHaveURL(`/forms/${form.slug}/show/share`)
    expect(saves).toBe(1)
    await page.reload()
    await expect(page.getByRole('heading', { name: title, exact: true })).toBeVisible()
    const stored = await request.get(`${api}/open/forms/${form.id}`, { headers })
    expect(stored.ok()).toBeTruthy()
    expect(await stored.json()).toMatchObject({ title, visibility })
  })
}

test('design status follows edits and reversions; cancelling departure keeps the changes', async ({ page, request }) => {
  await page.setViewportSize({ width: 1024, height: 768 })
  await page.goto(`/forms/${form.slug}/edit`)
  const status = page.getByTestId('form-save-status')
  await expect(status).toHaveText('All changes saved', { timeout: 15_000 })
  await page.getByRole('tab', { name: 'Design', exact: true }).click()
  const uppercase = page.getByRole('switch', { name: 'Uppercase Input Labels', exact: true })
  await uppercase.check()
  await expect(status).toHaveText('Unsaved changes')
  await expect(status).toBeInViewport({ ratio: 1 })
  await expect(page.getByTestId('save-form-button')).toBeInViewport({ ratio: 1 })
  await uppercase.uncheck()
  await expect(status).toHaveText('All changes saved', { timeout: 15_000 })
  await uppercase.check()
  await expect(status).toHaveText('Unsaved changes')
  let confirmations = 0
  page.once('dialog', async dialog => {
    confirmations++
    expect(dialog.message()).toBe('You have unsaved changes. Leave without saving?')
    await dialog.dismiss()
  })
  await page.locator('#form-editor a[href="#"]').first().click()
  await expect(page).toHaveURL(`/forms/${form.slug}/edit`)
  expect(confirmations).toBe(1)
  await expect(uppercase).toBeChecked()
  await expect(status).toHaveText('Unsaved changes')
  const storedBefore = await request.get(`${api}/open/forms/${form.id}`, { headers })
  expect((await storedBefore.json()).uppercase_labels).toBe(false)
  await page.getByTestId('save-form-button').click()
  await expect(page).toHaveURL(`/forms/${form.slug}/show/share`)
  // A saved form must not leave a stale beforeunload warning on its next page.
  page.on('dialog', async dialog => {
    confirmations++
    await dialog.dismiss()
  })
  await page.reload()
  expect(confirmations).toBe(1)
  await page.getByRole('link', { name: 'Edit form', exact: true }).click()
  await page.getByRole('tab', { name: 'Design', exact: true }).click()
  await expect(uppercase).toBeChecked()
  await expect(status).toHaveText('All changes saved', { timeout: 15_000 })
})

test('settings keep edits after a failed save and allow retry', async ({ page }) => {
  await page.goto(`/forms/${form.slug}/edit`)
  await expect(page.getByTestId('form-save-status')).toHaveText('All changes saved', { timeout: 15_000 })
  await page.getByRole('button', { name: 'Settings', exact: true }).click()
  const settings = page.getByRole('dialog', { name: 'Form settings', exact: true })
  await settings.getByRole('textbox', { name: 'Form Name', exact: true }).fill('Retry these settings')
  let release: () => void = () => {}
  const pending = new Promise<void>(resolve => { release = resolve })
  const endpoint = `${api}/open/forms/${form.id}`
  await page.route(endpoint, async route => {
    if (route.request().method() !== 'PUT') return route.continue()
    await pending
    await route.fulfill({ status: 500, contentType: 'application/json', body: JSON.stringify({ message: 'Local test failure' }) })
  })
  try {
    await settings.getByRole('button', { name: 'Save and close', exact: true }).click()
    await expect(page.getByTestId('form-save-status')).toHaveText('Saving changes…')
    await expect(settings.getByTestId('save-form-settings-button')).toBeDisabled()
  } finally {
    release()
  }
  await expect(page.getByTestId('form-save-status')).toHaveText('Unsaved changes')
  await expect(settings).toBeVisible()
  await expect(settings.getByRole('textbox', { name: 'Form Name', exact: true })).toHaveValue('Retry these settings')
  await expect(settings.getByRole('button', { name: 'Save and close', exact: true })).toBeEnabled()
  await page.unroute(endpoint)
  await settings.getByRole('button', { name: 'Save and close', exact: true }).click()
  await expect(page).toHaveURL(`/forms/${form.slug}/show/share`)
  await expect(page.getByRole('heading', { name: 'Retry these settings', exact: true })).toBeVisible()
})

test('new form settings apply with Done without creating a form', async ({ page }) => {
  await page.goto('/forms/create')
  await page.getByTestId('form-style-classic').click()
  await page.getByTestId('form-base-simple-contact').click()
  await expect(page.locator('#form-editor')).toBeVisible()
  let creates = 0
  page.on('request', request => {
    if (request.method() === 'POST' && request.url() === `${api}/open/forms`) creates++
  })
  await page.getByRole('button', { name: 'Settings', exact: true }).click()
  const settings = page.getByRole('dialog', { name: 'Form settings', exact: true })
  await settings.getByRole('textbox', { name: 'Form Name', exact: true }).fill('Unpublished new form')
  await settings.getByRole('button', { name: 'Done', exact: true }).click()
  await expect(settings).not.toBeVisible()
  await expect(page.locator('#form-editor-title')).toHaveText('Unpublished new form')
  await expect(page).toHaveURL('/forms/create')
  expect(creates).toBe(0)
})
