import { expect, test } from '@playwright/test'
import { waitForAnonymousApiBudget, waitForAuthenticatedApiBudget } from './api-budget'

const api = process.env.PLAYWRIGHT_API_BASE_URL || 'http://127.0.0.1:8089'

for (const mode of ['closed', 'quota']) {
  test(`closed and quota alerts: ${mode} blocks new answers without a page error`, async ({ page, request }, testInfo) => {
    await waitForAnonymousApiBudget(request, testInfo)
    const login = await request.post(`${api}/login`, { data: {
      email: 'e2e@example.test', password: 'Abcd@1234', remember: false,
    } })
    expect(login.ok(), await login.text()).toBeTruthy()
    const headers = { Authorization: `Bearer ${(await login.json()).token}`, Accept: 'application/json' }
    await waitForAuthenticatedApiBudget(request, testInfo, headers)
    const workspaces = await request.get(`${api}/open/workspaces`, { headers })
    expect(workspaces.ok()).toBeTruthy()
    const workspaceId = (await workspaces.json())[0].id
    const notice = `Local ${mode} notice ${Date.now()}`
    const response = await request.post(`${api}/open/forms`, { headers, data: {
      title: `Local ${mode} form ${Date.now()}`, workspace_id: workspaceId, visibility: mode === 'closed' ? 'closed' : 'public',
      properties: [{ id: 'answer', name: 'Answer', type: 'text', required: true }],
      language: 'en', theme: 'default', presentation_style: 'classic', width: 'centered',
      size: 'md', border_radius: 'small', no_branding: false, dark_mode: 'light', color: '#3B82F6',
      uppercase_labels: false, transparent_background: false, auto_save: false, auto_focus: false,
      use_captcha: false, settings: { auto_next: false },
      closed_text: notice, max_submissions_reached_text: notice,
      ...(mode === 'quota' ? { max_submissions_count: 1 } : {}),
    } })
    expect(response.ok(), await response.text()).toBeTruthy()
    const form = (await response.json()).form
    const pageErrors: string[] = []
    page.on('pageerror', error => pageErrors.push(error.message))

    try {
      if (mode === 'quota') {
        const first = await request.post(`${api}/forms/${form.slug}/answer`, { data: { answer: 'First response' } })
        expect(first.ok(), await first.text()).toBeTruthy()
      }
      // The statistics endpoint refuses a closed form; its optional failure is handled.
      const statistics = page.waitForResponse(result => result.url().endsWith(`/forms/${form.slug}/view`))
      await page.goto(`/forms/${form.slug}`, { waitUntil: 'domcontentloaded' })
      await expect(page.getByText(notice, { exact: true })).toBeVisible()
      const tracked = await statistics
      expect(tracked.status()).toBe(mode === 'closed' ? 404 : 200)
      await expect(page.getByRole('button', { name: 'Submit', exact: true })).not.toBeVisible()
      const rejected = await request.post(`${api}/forms/${form.slug}/answer`, { data: { answer: 'Must be refused' } })
      expect(rejected.status()).toBe(403)
      const submissions = await request.get(`${api}/open/forms/${form.slug}/submissions`, { headers })
      expect(submissions.ok()).toBeTruthy()
      expect((await submissions.json()).data).toHaveLength(mode === 'quota' ? 1 : 0)
      expect(pageErrors).toEqual([])
    } finally {
      const removed = await request.delete(`${api}/open/forms/${form.slug}`, { headers })
      expect(removed.ok()).toBeTruthy()
    }
  })
}
