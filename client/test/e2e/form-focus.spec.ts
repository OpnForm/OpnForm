import { expect, test, type APIRequestContext, type Locator, type Page } from '@playwright/test'

const API_BASE_URL = process.env.PLAYWRIGHT_API_BASE_URL || 'http://127.0.0.1:8089'
const color = '#7c3aed'
const themes = ['default', 'simple', 'notion', 'minimal', 'transparent']
const options = [{ id: 'Alpha', name: 'Alpha' }, { id: 'Beta', name: 'Beta' }]

function field(id: string, type: string, extra = {}) {
  return { id, name: `Focus ${id}`, type, hidden: false, required: false, ...extra }
}

const properties = [
  field('text', 'text', { required: true }),
  field('textarea', 'text', { multi_lines: true }),
  field('dropdown', 'select', { select: { options }, use_focused_selector: false }),
  field('flat', 'select', { select: { options }, without_dropdown: true, use_focused_selector: false }),
  field('choice', 'select', { select: { options }, without_dropdown: true }),
  field('multichoice', 'multi_select', { multi_select: { options }, without_dropdown: true }),
  field('checkbox', 'checkbox', { use_focused_toggle: false }),
  field('toggle', 'checkbox', { use_toggle_switch: true, use_focused_toggle: false }),
  field('yesno', 'checkbox'),
  field('phone', 'phone_number'),
  field('date', 'date'),
  field('rating', 'rating', { rating_max_value: 5 }),
  field('scale', 'scale', { scale_min_value: 1, scale_max_value: 5, scale_step_value: 1 }),
  field('slider', 'slider', { slider_min_value: 0, slider_max_value: 10, slider_step_value: 1 }),
  field('matrix', 'matrix', { rows: ['Quality'], columns: ['Good', 'Great'] }),
  field('richtext', 'rich_text'),
  field('signature', 'signature'),
  field('files', 'files'),
]

async function readStyle(control: Locator) {
  return control.evaluate(element => {
    const style = getComputedStyle(element)
    return {
      shadow: style.boxShadow,
      duration: style.transitionDuration,
      transition: style.transitionProperty,
      color: style.getPropertyValue('--form-focus-color').trim(),
    }
  })
}

async function focusWithKeyboard(page: Page, control: Locator) {
  await page.keyboard.press('Tab')
  await control.focus()
  await page.keyboard.press('Escape')
  await expect(control).toBeFocused()
}

function controls(scope: Locator, id: string, presentation: string) {
  let target: Locator
  let decoration: Locator | undefined
  switch (id) {
    case 'dropdown':
      target = scope.locator('button[aria-haspopup="listbox"]')
      decoration = target.locator('..')
      break
    case 'choice':
    case 'multichoice':
      target = scope.locator(presentation === 'focused' ? 'button[role="option"]' : `[role="${id === 'multichoice' ? 'checkbox' : 'radio'}"]`).first()
      decoration = presentation === 'focused' ? target.locator('..') : target
      break
    case 'flat':
    case 'scale':
    case 'matrix':
      target = scope.locator('[role="radio"]').first()
      break
    case 'checkbox':
      target = scope.locator('input[type="checkbox"]')
      decoration = target.locator('..')
      break
    case 'yesno':
      target = scope.locator(presentation === 'focused' ? 'button[role="option"]' : 'input[type="checkbox"]').first()
      decoration = target.locator('..')
      break
    case 'toggle':
      target = scope.locator('button[role="switch"]')
      break
    case 'phone':
      target = scope.locator('input').first()
      break
    case 'date':
      target = scope.getByRole('button', { name: 'Select date', exact: true })
      break
    case 'rating':
      target = scope.getByRole('button', { name: '1 star', exact: true })
      break
    case 'slider':
      target = scope.locator('input[type="range"]')
      decoration = target.locator('..')
      break
    case 'richtext':
      target = scope.locator('.ql-editor')
      decoration = scope.locator('.rich-editor').first()
      break
    case 'signature':
      target = scope.locator('[name="signature"]')
      break
    case 'files':
      target = scope.getByRole('button', { name: 'Choose a file or drag here', exact: true })
      break
    default:
      target = scope.locator('input, textarea').first()
  }
  return { target, decoration: decoration || target }
}

async function createFocusForm(request: APIRequestContext, settings: Record<string, unknown>) {
  const login = await request.post(`${API_BASE_URL}/login`, {
    data: { email: 'e2e@example.test', password: 'Abcd@1234', remember: false },
  })
  expect(login.ok()).toBeTruthy()
  const token = (await login.json()).token
  const headers = { Authorization: `Bearer ${token}`, Accept: 'application/json' }
  const workspaces = await request.get(`${API_BASE_URL}/open/workspaces`, { headers })
  const workspaceId = (await workspaces.json())[0].id
  const response = await request.post(`${API_BASE_URL}/open/forms`, {
    headers,
    data: {
      workspace_id: workspaceId,
      visibility: 'public', language: 'en', theme: 'default',
      presentation_style: 'classic', width: 'centered', size: 'md', border_radius: 'small',
      dark_mode: 'light', color,
      uppercase_labels: false, no_branding: false, transparent_background: false,
      submitted_text: 'Focus submission saved.', auto_focus: false, auto_save: false,
      settings: { auto_next: false }, properties,
      ...settings,
    },
  })
  expect(response.ok(), await response.text()).toBeTruthy()
  return { form: (await response.json()).form, token }
}

for (const presentation of ['classic', 'focused']) {
  for (const theme of themes) {
    for (const mode of ['light', 'dark']) {
      test(`${presentation} ${theme} ${mode}: animated focus across form controls`, async ({ page, request }, testInfo) => {
        test.setTimeout(120_000)
        const errors: string[] = []
        page.on('pageerror', error => errors.push(error.message))
        const { form } = await createFocusForm(request, {
          title: `Focus ${presentation} ${theme} ${mode} ${Date.now()}`,
          theme, presentation_style: presentation, dark_mode: mode,
          size: presentation === 'focused' ? 'lg' : (testInfo.project.name === 'mobile-chromium' ? 'sm' : 'md'),
          border_radius: testInfo.project.name === 'mobile-chromium' ? 'full' : (mode === 'dark' ? 'none' : 'small'),
        })
        await page.goto(`/forms/${form.slug}`)
        await expect(page.locator('input[name="text"]')).toBeVisible({ timeout: 30_000 })
        await page.waitForLoadState('networkidle')
        await page.keyboard.press('Tab')

        for (const property of properties) {
          await test.step(property.id, async () => {
            const scope = presentation === 'classic'
              ? page.getByTestId(`open-form-field-${property.id}`)
              : page.locator('form').filter({ has: page.getByText(property.name, { exact: true }) })
            const { target, decoration } = controls(scope, property.id, presentation)
            await expect(target).toBeVisible()
            if (presentation === 'focused') {
              await expect(page.locator('[class*="slide-vertical-"][class*="-active"]')).toHaveCount(0)
            }
            if (['choice', 'multichoice', 'yesno'].includes(property.id) && presentation === 'focused') {
              const list = scope.getByRole('listbox')
              await focusWithKeyboard(page, list)
              await expect.poll(async () => (await readStyle(list)).shadow).toContain('3px')
              await list.blur()
            }
            await target.blur()
            // Sample the resting state after the 200ms blur animation has finished.
            await page.waitForTimeout(220)
            const idle = await readStyle(decoration)
            await focusWithKeyboard(page, target)
            await expect.poll(async () => (await readStyle(decoration)).shadow,
              { message: `${property.id} has a visible focus indicator` }).not.toBe(idle.shadow)
            await page.waitForTimeout(220)
            const focused = await readStyle(decoration)
            expect(focused.color).toBe(color)
            expect(focused.duration).toBe('0.2s')
            if (property.id === 'dropdown' && testInfo.project.name === 'chromium') {
              await testInfo.attach('Focus appearance', { body: await page.screenshot(), contentType: 'image/png' })
            }
            if (theme === 'transparent' && ['text', 'textarea', 'phone', 'date', 'richtext'].includes(property.id)) {
              expect(focused.shadow).toContain('inset')
              expect(focused.shadow).toContain('2px')
            }
            await target.blur()
            await expect.poll(async () => (await readStyle(decoration)).shadow).toBe(idle.shadow)

            if (['flat', 'multichoice', 'checkbox', 'toggle', 'rating', 'scale', 'matrix'].includes(property.id)) {
              await target.click()
              await focusWithKeyboard(page, target)
              await expect.poll(async () => (await readStyle(decoration)).shadow).not.toBe(idle.shadow)
              await target.blur()
            }

            if (property.id === 'text') {
              await page.getByRole('button', { name: presentation === 'classic' ? 'Submit' : 'Next', exact: true }).click()
              await expect.poll(async () => (await readStyle(target)).color).not.toBe(color)
              await focusWithKeyboard(page, target)
              const red = await target.evaluate(element => getComputedStyle(element).getPropertyValue('--color-red-500').trim())
              expect((await readStyle(target)).color).toBe(red)
              await target.fill('Focus visitor')
              await expect.poll(async () => (await readStyle(target)).color).toBe(color)

              await page.emulateMedia({ reducedMotion: 'reduce' })
              await expect.poll(async () => (await readStyle(target)).transition).toBe('none')
              await target.blur()
              await page.emulateMedia({ reducedMotion: 'no-preference' })
            }
            if (property.id === 'dropdown' || property.id === 'date') {
              await target.click()
              await expect(target).toHaveAttribute('aria-expanded', 'true')
              await expect.poll(async () => (await readStyle(decoration)).shadow).not.toBe(idle.shadow)
              await page.keyboard.press('Escape')
            }
            if (presentation === 'focused' && property.id !== 'files') {
              await page.getByRole('button', { name: 'Next', exact: true }).click()
            }
          })
        }
        await page.getByRole('button', { name: 'Submit', exact: true }).click()
        await expect(page.getByText('Focus submission saved.', { exact: true })).toBeVisible()
        expect(errors).toEqual([])
      })
    }
  }
}

test('editor compound controls retain animated keyboard focus', async ({ page, request }, testInfo) => {
  test.skip(testInfo.project.name === 'mobile-chromium', 'The form editor requires a desktop viewport')
  const { form, token } = await createFocusForm(request, { title: `Focus editor ${Date.now()}` })
  const baseURL = testInfo.project.use.baseURL
  if (!baseURL) throw new Error('Playwright baseURL is required')
  await page.context().addCookies([{ name: 'opnform_token', value: token, url: baseURL }])
  await page.goto(`/forms/${form.slug}/edit`)
  await page.waitForLoadState('networkidle')
  await page.getByRole('tab', { name: 'Design', exact: true }).click()
  await page.waitForLoadState('networkidle')

  const picker = page.locator('input[type="color"]')
  await focusWithKeyboard(page, picker)
  await expect.poll(async () => (await readStyle(picker)).shadow).toContain('3px')
  const colorMode = page.getByRole('listbox').filter({ hasText: 'System' })
  await focusWithKeyboard(page, colorMode)
  await expect.poll(async () => (await readStyle(colorMode)).shadow).toContain('3px')

  await page.getByRole('button', { name: 'Settings', exact: true }).click()
  await page.getByRole('button', { name: 'Custom Code', exact: true }).click()
  const code = page.locator('.cm-content[contenteditable="true"]')
  await code.click()
  const container = page.locator('.h-40').filter({ has: code })
  await expect.poll(async () => (await readStyle(container)).shadow).toContain('3px')
  await expect(page.locator('.cm-editor.cm-focused')).toHaveCSS('outline-style', 'none')
  await page.emulateMedia({ reducedMotion: 'reduce' })
  await expect(container).toHaveCSS('transition-property', 'none')
})
