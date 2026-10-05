import { test, expect, type APIRequestContext, type Page } from '@playwright/test'
import { readFileSync } from 'node:fs'
import { hash } from '../../lib/utils.js'

const api = process.env.PLAYWRIGHT_API_BASE_URL || 'http://127.0.0.1:8089'
let headers: Record<string, string>
let workspaceId: number

test.beforeAll(async ({ request }) => {
  const login = await request.post(`${api}/login`, { data: {
    email: 'e2e@example.test', password: 'Abcd@1234', remember: false,
  } })
  expect(login.ok()).toBeTruthy()
  const { token } = await login.json()
  headers = { Accept: 'application/json', Authorization: `Bearer ${token}` }
  const response = await request.get(`${api}/open/workspaces`, { headers })
  expect(response.ok()).toBeTruthy()
  workspaceId = (await response.json())[0].id
})

async function createForm(request: APIRequestContext, properties: unknown[], overrides = {}) {
  const response = await request.post(`${api}/open/forms`, { headers, data: {
    title: `SSR visibility ${Date.now()} ${Math.random().toString(36).slice(2, 6)}`,
    workspace_id: workspaceId, visibility: 'public', properties,
    language: 'en', theme: 'default', presentation_style: 'classic', width: 'centered',
    size: 'md', border_radius: 'small', no_branding: false,
    dark_mode: 'light', color: '#3B82F6', uppercase_labels: false, transparent_background: false,
    auto_save: false, auto_focus: false, use_captcha: false,
    settings: { auto_next: false },
    ...overrides,
  } })
  expect(response.ok(), await response.text()).toBeTruthy()
  return (await response.json()).form
}

function collectHydrationWarnings(page: Page) {
  const warnings: string[] = []
  page.on('console', message => {
    if (/hydration.*mismatch/i.test(message.text())) warnings.push(message.text())
  })
  return warnings
}

async function nextFocusedStep(page: Page) {
  const next = page.getByRole('button', { name: /next/i })
  // The outgoing step remains in the DOM for the slide transition.
  await expect(next).toHaveCount(1)
  await next.click()
}

async function holdRatingInput(page: Page) {
  const component = 'components/forms/heavy/RatingInput.vue'
  let pattern = `**/${component}*`
  if (process.env.PLAYWRIGHT_DEV_SERVER !== '1') {
    const manifest = readFileSync(new URL('../../.output/server/chunks/build/client.manifest.mjs', import.meta.url), 'utf8')
    const file = manifest.match(/"components\/forms\/heavy\/RatingInput.vue":\s*\{[\s\S]*?"file":\s*"([^"]+)"/)?.[1]
    expect(file, 'RatingInput must have an async client bundle').toBeTruthy()
    pattern = `**/_nuxt/${file}`
  }
  let held = false
  let release: () => void
  const blocked = new Promise<void>(resolve => { release = resolve })
  await page.route(pattern, route => {
    held = true
    return blocked.then(() => route.continue())
  })
  return { isHeld: () => held, release: () => release() }
}

const numericCases = [
  { name: 'empty rating', type: 'rating', field: { rating_max_value: 5 }, value: 0 },
  { name: 'empty slider', type: 'slider', field: { slider_min_value: 0, slider_max_value: 50, slider_step_value: 5 }, value: 0 },
  { name: 'prefilled scale', type: 'scale', field: { prefill: '3', scale_min_value: 1, scale_max_value: 5, scale_step_value: 1 }, value: 3 },
  { name: 'decimal scale', type: 'scale', field: { prefill: '2.5', scale_min_value: 1, scale_max_value: 5, scale_step_value: 0.5 }, value: 2.5 },
  { name: 'URL scale', type: 'scale', field: { scale_min_value: 1, scale_max_value: 5, scale_step_value: 0.5 }, value: 2.5, query: '?control=2.5' },
  { name: 'computed prefill', type: 'number', field: { prefill: 5 }, value: 10, computed: true },
  { name: 'computed URL', type: 'number', field: {}, value: 10, query: '?control=5', computed: true },
]

for (const style of ['classic', 'focused']) {
  for (const item of numericCases) {
    test(`${style} SSR visibility stays stable for ${item.name}`, async ({ page, request, browser }) => {
      const warnings = collectHydrationWarnings(page)
      const form = await createForm(request, [
        // Put the dependent first so Focused also exposes an incorrect first question.
        { id: 'dependent', name: 'Dependent', type: 'text', hidden: false, required: true,
          logic: { conditions: { identifier: item.computed ? 'cv_total' : 'control', value: {
            operator: item.computed ? 'greater_than' : 'equals', property_meta: item.computed
              ? { id: 'cv_total', type: 'computed', result_type: 'number' }
              : { id: 'control', type: item.type }, value: item.value,
          } }, actions: ['hide-block'] } },
        { id: 'control', name: 'Control', type: item.type, hidden: false, required: false, ...item.field },
        { id: 'feedback', name: 'Feedback', type: 'text', hidden: false, prefill: 'Kept answer' },
        { id: 'static_hidden', name: 'Static hidden', type: 'text', hidden: true },
      ], { presentation_style: style, computed_variables: item.computed ? [
        { id: 'cv_total', name: 'Total', formula: '{cv_double} + 1', result_type: 'number' },
        { id: 'cv_double', name: 'Double', formula: '{control} * 2', result_type: 'number' },
      ] : [] })
      const path = `/forms/${form.slug}${item.query || ''}`
      const response = await request.get(path)
      expect(response.ok()).toBeTruthy()
      const html = await response.text()
      expect(html).not.toContain('data-testid="open-form-field-dependent"')
      expect(html).not.toContain('data-testid="open-form-field-static_hidden"')
      expect(html).not.toMatch(/<label[^>]*for="dependent"/)

      const context = await browser.newContext({ javaScriptEnabled: false })
      try {
        const firstRender = await context.newPage()
        await firstRender.goto(new URL(path, response.url()).href)
        await expect(firstRender.locator('label[for="control"]')).toBeVisible()
        await expect(firstRender.locator('label[for="dependent"]')).toHaveCount(0)
        if (item.type === 'scale') {
          await expect(firstRender.getByRole('radio', { name: `Scale value ${item.value}`, exact: true }))
            .toHaveAttribute('aria-checked', 'true')
        }
      } finally {
        await context.close()
      }

      await page.goto(path)
      await page.waitForLoadState('networkidle')
      await expect(page.locator('label[for="control"]')).toBeVisible()
      await expect(page.locator('label[for="dependent"]')).toHaveCount(0)
      await expect(page.locator('label[for="static_hidden"]')).toHaveCount(0)
      if (item.type === 'scale') {
        await expect(page.getByRole('radio', { name: `Scale value ${item.value}`, exact: true }))
          .toHaveAttribute('aria-checked', 'true')
      }
      expect(warnings).toEqual([])

      if (item.computed) {
        await page.getByLabel('Control', { exact: true }).fill('1')
        await expect(page.getByLabel('Dependent', { exact: true })).toBeVisible()
        await page.getByLabel('Dependent', { exact: true }).fill('Recomputed answer')
      }
      if (style === 'classic') {
        const submission = page.waitForRequest(request => request.method() === 'POST' &&
          request.url().endsWith(`/forms/${form.slug}/answer`))
        await page.getByRole('button', { name: /submit/i }).click()
        const payload = (await submission).postDataJSON()
        expect(Number(payload.control)).toBe(item.computed ? 1 : item.value)
        expect(payload.feedback).toBe('Kept answer')
        if (item.computed) expect(payload.dependent).toBe('Recomputed answer')
        await expect.poll(async () => {
          const response = await request.get(`${api}/open/forms/${form.slug}/submissions`, { headers })
          expect(response.ok()).toBeTruthy()
          return (await response.json()).data[0]?.data
        }).toMatchObject({ control: payload.control, feedback: 'Kept answer' })
      }
    })
  }
}

for (const toggle of [false, true]) {
  for (const checked of [true, false]) {
    test(`${toggle ? 'switch' : 'checkbox'} restores a ${checked ? 'checked' : 'unchecked'} draft after hydration and keeps autosave consistent`, async ({ page, request }, testInfo) => {
      const warnings = collectHydrationWarnings(page)
      const form = await createForm(request, [
        { id: 'choice', name: 'Choice', type: 'checkbox', use_toggle_switch: toggle, prefill: !checked, hidden: false },
        { id: 'feedback', name: 'Feedback', type: 'text', prefill: 'Configured answer', hidden: false },
        { id: 'scale', name: 'Scale', type: 'scale', scale_min_value: 1, scale_max_value: 5, scale_step_value: 0.5 },
        { id: 'details', name: 'Details', type: 'text', logic: {
          conditions: { identifier: 'choice', value: {
            operator: 'is_not_checked', property_meta: { id: 'choice', type: 'checkbox' },
          } }, actions: ['hide-block'],
        } },
      ], { auto_save: true })
      const definition = await request.get(`${api}/forms/${form.slug}`)
      const publicForm = await definition.json()
      const path = `/forms/${form.slug}`
      const key = `${publicForm.form_pending_submission_key}-${hash(new URL(path, testInfo.project.use.baseURL as string).href)}`
      await page.addInitScript(({ key, checked }) => {
        if (localStorage.getItem(key) === null) {
          localStorage.setItem(key, JSON.stringify({ choice: checked, feedback: 'Saved answer', scale: '2.5' }))
        }
      }, { key, checked })
      await page.goto(path)
      await page.waitForLoadState('networkidle')
      const control = page.getByRole(toggle ? 'switch' : 'checkbox', { name: 'Choice', exact: true })
      await expect(control).toBeChecked({ checked })
      await expect(page.getByLabel('Feedback', { exact: true })).toHaveValue('Saved answer')
      await expect(page.getByRole('radio', { name: 'Scale value 2.5', exact: true })).toHaveAttribute('aria-checked', 'true')
      await expect(page.getByTestId('open-form-field-details')).toHaveCount(checked ? 1 : 0)
      expect(warnings).toEqual([])

      await page.getByLabel('Feedback', { exact: true }).fill('Updated answer')
      await control.press('Space')
      await expect.poll(async () => page.evaluate(key => JSON.parse(localStorage.getItem(key) || '{}'), key))
        .toMatchObject({ choice: !checked, feedback: 'Updated answer', scale: 2.5 })
      await page.reload()
      await page.waitForLoadState('networkidle')
      await expect(control).toBeChecked({ checked: !checked })
      await expect(page.getByLabel('Feedback', { exact: true })).toHaveValue('Updated answer')
      await expect(page.getByTestId('open-form-field-details')).toHaveCount(checked ? 0 : 1)

      const submission = page.waitForRequest(request => request.method() === 'POST' &&
        request.url().endsWith(`/forms/${form.slug}/answer`))
      await page.getByRole('button', { name: /submit/i }).click()
      expect((await submission).postDataJSON()).toMatchObject({ choice: !checked, feedback: 'Updated answer', scale: 2.5 })
      await expect.poll(async () => page.evaluate(key => localStorage.getItem(key), key)).toBeNull()
      // Check after the trailing autosave timer, not only immediately after clearing.
      await page.waitForTimeout(1100)
      expect(await page.evaluate(key => localStorage.getItem(key), key)).toBeNull()
      expect(warnings).toEqual([])
    })
  }
}

test('focused drafts restore No and lazy numeric controls before stepping and submitting', async ({ page, request }, testInfo) => {
  const warnings = collectHydrationWarnings(page)
  const form = await createForm(request, [
    { id: 'choice', name: 'Choice', type: 'checkbox', prefill: true },
    { id: 'rating', name: 'Rating', type: 'rating', rating_max_value: 5 },
    { id: 'scale', name: 'Scale', type: 'scale', scale_min_value: 1, scale_max_value: 5, scale_step_value: 0.5 },
    { id: 'feedback', name: 'Feedback', type: 'text', prefill: 'Configured answer' },
  ], { auto_save: true, presentation_style: 'focused' })
  const publicForm = await (await request.get(`${api}/forms/${form.slug}`)).json()
  const path = `/forms/${form.slug}`
  const key = `${publicForm.form_pending_submission_key}-${hash(new URL(path, testInfo.project.use.baseURL as string).href)}`
  await page.addInitScript(key => {
    localStorage.setItem(key, JSON.stringify({ choice: false, rating: 4, scale: '2.5', feedback: 'Saved answer' }))
  }, key)
  await page.goto(path)
  await page.waitForLoadState('networkidle')
  await expect(page.getByRole('option', { name: /No/i })).toHaveAttribute('aria-selected', 'true')
  await nextFocusedStep(page)
  await expect(page.getByRole('slider')).toHaveAttribute('aria-valuenow', '4')
  await nextFocusedStep(page)
  await expect(page.getByRole('radio', { name: 'Scale value 2.5', exact: true })).toHaveAttribute('aria-checked', 'true')
  await nextFocusedStep(page)
  await expect(page.getByLabel('Feedback', { exact: true })).toHaveValue('Saved answer')
  const submission = page.waitForRequest(request => request.method() === 'POST' && request.url().endsWith(`/forms/${form.slug}/answer`))
  await page.getByRole('button', { name: /submit/i }).click()
  expect((await submission).postDataJSON()).toMatchObject({ choice: false, rating: 4, scale: 2.5, feedback: 'Saved answer' })
  await expect.poll(async () => page.evaluate(key => localStorage.getItem(key), key)).toBeNull()
  expect(warnings).toEqual([])
})

for (const partialSync of [true, false]) {
  test(`slow draft hydration protects ${partialSync ? 'partial synchronization' : 'manual editing and submission'}`, async ({ page, request }, testInfo) => {
    const warnings = collectHydrationWarnings(page)
    const form = await createForm(request, [
      { id: 'rating', name: 'Rating', type: 'rating', rating_max_value: 5 },
      { id: 'choice', name: 'Choice', type: 'checkbox', use_toggle_switch: true },
      { id: 'feedback', name: 'Feedback', type: 'text', prefill: 'Configured answer' },
    ], { auto_save: true, enable_partial_submissions: partialSync })
    const publicForm = await (await request.get(`${api}/forms/${form.slug}`)).json()
    const path = `/forms/${form.slug}`
    const key = `${publicForm.form_pending_submission_key}-${hash(new URL(path, testInfo.project.use.baseURL as string).href)}`
    const draft = { rating: 4, choice: true, feedback: 'Saved answer', ...(partialSync ? { submission_hash: 'saved-partial-hash' } : {}) }
    await page.addInitScript(({ key, draft }) => localStorage.setItem(key, JSON.stringify(draft)), { key, draft })
    const submissions: Record<string, unknown>[] = []
    await page.route(`**/forms/${form.slug}/answer`, route => {
      submissions.push(route.request().postDataJSON())
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({
        submission_id: 'saved-submission', submission_hash: 'saved-partial-hash',
      }) })
    })
    const input = await holdRatingInput(page)
    try {
      await page.goto(path, { waitUntil: 'domcontentloaded' })
      await expect.poll(input.isHeld).toBe(true)
      await expect(page.locator('.open-complete-form')).toHaveAttribute('inert', '')
      if (!partialSync) {
        // Native events must not change or submit the SSR answers while a draft is pending.
        await page.getByLabel('Feedback', { exact: true }).click({ force: true })
        await page.keyboard.type('Premature answer')
        await page.getByRole('button', { name: /submit/i }).click({ force: true })
      }
      // Cross the partial-sync debounce while the component is deliberately held.
      await page.waitForTimeout(2500)
      expect(submissions).toEqual([])
      expect(await page.evaluate(key => JSON.parse(localStorage.getItem(key) || '{}'), key)).toEqual(draft)

      input.release()
      await expect(page.locator('.open-complete-form')).not.toHaveAttribute('inert')
      await expect(page.getByLabel('Feedback', { exact: true })).toHaveValue('Saved answer')
      await expect(page.getByRole('switch', { name: 'Choice', exact: true })).toBeChecked()
      if (partialSync) {
        await expect.poll(() => submissions.length).toBe(1)
        expect(submissions[0]).toMatchObject({ ...draft, is_partial: true })
      } else {
        await page.getByLabel('Feedback', { exact: true }).fill('Updated answer')
        await page.getByRole('button', { name: /submit/i }).click()
        await expect.poll(() => submissions.length).toBe(1)
        expect(submissions[0]).toMatchObject({ rating: 4, choice: true, feedback: 'Updated answer' })
        await expect.poll(async () => page.evaluate(key => localStorage.getItem(key), key)).toBeNull()
        await page.waitForTimeout(1100)
        expect(await page.evaluate(key => localStorage.getItem(key), key)).toBeNull()
      }
      expect(warnings).toEqual([])
    } finally {
      input.release()
    }
  })
}

for (const fragment of ['', '#section']) {
  test(`auto-submit uses restored draft answers after hydration without flashing the form${fragment}`, async ({ page, request }, testInfo) => {
    const warnings = collectHydrationWarnings(page)
    const form = await createForm(request, [
      { id: 'choice', name: 'Choice', type: 'checkbox', use_toggle_switch: true, prefill: false, required: true },
      { id: 'feedback', name: 'Feedback', type: 'text', required: true },
      { id: 'scale', name: 'Scale', type: 'scale', scale_min_value: 1, scale_max_value: 5, scale_step_value: 0.5 },
    ], { auto_save: true })
    const publicForm = await (await request.get(`${api}/forms/${form.slug}`)).json()
    const path = `/forms/${form.slug}?auto_submit=true${fragment}`
    const response = await request.get(path)
    expect(response.ok()).toBeTruthy()
    expect(await response.text()).not.toContain('data-testid="open-form-field-choice"')
    const key = `${publicForm.form_pending_submission_key}-${hash(new URL(path, testInfo.project.use.baseURL as string).href)}`
    await page.addInitScript(key => {
      localStorage.setItem(key, JSON.stringify({ choice: true, feedback: 'Saved auto-submit answer', scale: '2.5' }))
    }, key)
    const submission = page.waitForResponse(response => response.request().method() === 'POST' &&
      response.url().endsWith(`/forms/${form.slug}/answer`))
    await page.goto(path)
    expect((await submission).ok()).toBeTruthy()
    expect((await submission).request().postDataJSON()).toMatchObject({ choice: true, feedback: 'Saved auto-submit answer', scale: 2.5 })
    await page.waitForLoadState('networkidle')
    expect(warnings).toEqual([])
  })
}

for (const autoSubmit of [false, true]) {
  test(`encoded and repeated query values survive SSR and ${autoSubmit ? 'auto-submission' : 'refilling'}`, async ({ page, request, browser }, testInfo) => {
    const warnings = collectHydrationWarnings(page)
    const prefill = 'Why? #section & A+B 100%'
    const form = await createForm(request, [
      { id: 'feedback', name: 'Feedback', type: 'text', required: true },
      { id: 'choices', name: 'Choices', type: 'multi_select', hidden: true,
        multi_select: { options: [{ id: 'one', name: 'One' }, { id: 'two', name: 'Two' }] } },
    ], { auto_save: true, re_fillable: true, re_fill_button_text: 'Fill again' })
    const publicForm = await (await request.get(`${api}/forms/${form.slug}`)).json()
    const query = new URLSearchParams({ feedback: prefill })
    query.append('choices[]', 'one')
    query.append('choices[]', 'two')
    if (autoSubmit) query.append('auto_submit', 'true')
    const path = `/forms/${form.slug}?${query}#section`

    const ssr = await browser.newContext({ javaScriptEnabled: false, baseURL: testInfo.project.use.baseURL as string })
    try {
      const serverPage = await ssr.newPage()
      await serverPage.goto(path)
      if (autoSubmit) {
        await expect(serverPage.getByLabel('Feedback', { exact: true })).toHaveCount(0)
      } else {
        await expect(serverPage.getByLabel('Feedback', { exact: true })).toHaveValue(prefill)
      }
    } finally {
      await ssr.close()
    }

    const submissions: Record<string, unknown>[] = []
    await page.route(`**/forms/${form.slug}/answer`, route => {
      submissions.push(route.request().postDataJSON())
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ submission_id: 'saved-submission' }) })
    })
    await page.goto(path)
    await page.waitForLoadState('networkidle')
    // Vue Router canonicalizes escaped query characters; drafts use the browser URL.
    const key = `${publicForm.form_pending_submission_key}-${hash(page.url())}`
    if (!autoSubmit) {
      await expect(page.getByLabel('Feedback', { exact: true })).toHaveValue(prefill)
      await page.getByRole('button', { name: /submit/i }).click()
    }
    await expect(page.getByRole('button', { name: 'Fill again', exact: true })).toBeVisible()
    expect(submissions).toHaveLength(1)
    expect(submissions[0]).toMatchObject({ feedback: prefill, choices: ['One', 'Two'] })
    await page.waitForTimeout(1100)
    expect(await page.evaluate(key => localStorage.getItem(key), key)).toBeNull()

    if (!autoSubmit) {
      await page.getByRole('button', { name: 'Fill again', exact: true }).click()
      await expect(page.getByLabel('Feedback', { exact: true })).toHaveValue(prefill)
      await page.getByLabel('Feedback', { exact: true }).fill('New draft after refilling')
      await expect.poll(async () => page.evaluate(key => JSON.parse(localStorage.getItem(key) || '{}'), key))
        .toMatchObject({ feedback: 'New draft after refilling', choices: ['One', 'Two'] })
      await page.reload()
      await expect(page.getByLabel('Feedback', { exact: true })).toHaveValue('New draft after refilling')
    }
    expect(warnings).toEqual([])
  })
}
