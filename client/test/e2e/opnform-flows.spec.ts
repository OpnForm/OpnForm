import { expect, test, type APIRequestContext, type Page } from "@playwright/test"

test.setTimeout(240_000)

const API_BASE_URL = process.env.PLAYWRIGHT_API_BASE_URL || "http://127.0.0.1:8089"
const seededUser = {
  email: "e2e@example.test",
  password: "Abcd@1234",
}
const browserLogsByTest = new Map<string, string[]>()

function uniqueEmail(prefix = "playwright") {
  return `${prefix}+${Date.now()}${Math.random().toString(36).slice(2, 8)}@gmail.com`
}

function uniqueTitle(prefix = "Playwright Form") {
  return `${prefix} ${Date.now()} ${Math.random().toString(36).slice(2, 6)}`
}

function buildFormPayload(workspaceId: number, title: string, presentationStyle: "classic" | "focused" = "classic") {
  return {
    title,
    visibility: "public",
    workspace_id: workspaceId,
    properties: [
      {
        type: "nf-text",
        content: `<h1>${title}</h1><p>Please fill out this form.</p>`,
        name: "Title",
        id: "default_title",
      },
      {
        name: "Name",
        type: "text",
        hidden: false,
        required: true,
        placeholder: "Enter your full name",
        id: "default_name",
      },
      {
        name: "Email",
        type: "email",
        hidden: false,
        placeholder: "Enter your email address",
        id: "default_email",
      },
      {
        name: "Message",
        type: "text",
        hidden: false,
        multi_lines: true,
        placeholder: "Type your message here",
        id: "default_message",
      },
    ],
    language: "en",
    font_family: null,
    theme: "default",
    presentation_style: presentationStyle,
    width: "centered",
    size: presentationStyle === "focused" ? "lg" : "md",
    layout_rtl: false,
    border_radius: "small",
    dark_mode: "light",
    color: "#3B82F6",
    uppercase_labels: false,
    no_branding: false,
    transparent_background: false,
    submit_button_text: null,
    re_fillable: false,
    re_fill_button_text: "Fill Again",
    pdf_download_enabled: false,
    pdf_download_button_text: null,
    submitted_text: "Amazing, we saved your answers. Thank you for your time and have a great day!",
    redirect_url: null,
    max_submissions_count: null,
    max_submissions_reached_text: "This form has reached the maximum number of submissions.",
    editable_submissions: false,
    editable_submissions_button_text: "Edit submission",
    confetti_on_submission: false,
    show_progress_bar: false,
    auto_save: true,
    auto_focus: true,
    enable_partial_submissions: false,
    enable_ip_tracking: false,
    can_be_indexed: true,
    password: null,
    use_captcha: false,
    captcha_provider: "recaptcha",
    seo_meta: {},
    settings: presentationStyle === "focused" ? { navigation_arrows: true } : {},
    analytics: [],
    tags: [],
    computed_variables: [],
  }
}

function buildOptionList(optionNames: string[]) {
  return optionNames.map((name) => ({ id: name, name }))
}

function buildTextField(id: string, name: string, overrides: Record<string, unknown> = {}) {
  return {
    id,
    name,
    type: "text",
    hidden: false,
    required: false,
    ...overrides,
  }
}

function buildEmailField(id: string, name: string, overrides: Record<string, unknown> = {}) {
  return {
    id,
    name,
    type: "email",
    hidden: false,
    required: false,
    ...overrides,
  }
}

function buildCheckboxField(id: string, name: string, overrides: Record<string, unknown> = {}) {
  return {
    id,
    name,
    type: "checkbox",
    hidden: false,
    required: false,
    ...overrides,
  }
}

function buildSelectField(id: string, name: string, options: string[], overrides: Record<string, unknown> = {}) {
  return {
    id,
    name,
    type: "select",
    hidden: false,
    required: false,
    allow_creation: false,
    without_dropdown: true,
    use_focused_selector: false,
    select: {
      options: buildOptionList(options),
    },
    ...overrides,
  }
}

function buildMultiSelectField(id: string, name: string, options: string[], overrides: Record<string, unknown> = {}) {
  return {
    id,
    name,
    type: "multi_select",
    hidden: false,
    required: false,
    allow_creation: false,
    without_dropdown: true,
    use_focused_selector: false,
    multi_select: {
      options: buildOptionList(options),
    },
    ...overrides,
  }
}

function buildPageBreak(id: string, nextText: string, previousText: string) {
  return {
    id,
    name: "Page Break",
    type: "nf-page-break",
    next_btn_text: nextText,
    previous_btn_text: previousText,
  }
}

async function gotoPageWithRetry(page: Page, path: string, readyCheck: () => Promise<void>, attempts = 8) {
  let lastError: unknown

  for (let attempt = 1; attempt <= attempts; attempt += 1) {
    try {
      const response = await page.goto(path, { waitUntil: "commit", timeout: 10_000 })
      expect(response?.status(), `unexpected status for ${path} on attempt ${attempt}`).toBeLessThan(500)
      await readyCheck()
      return
    } catch (error) {
      lastError = error
      if (attempt === attempts) {
        break
      }

      await page.waitForTimeout(1500 + attempt * 500)
    }
  }

  throw lastError
}

async function openLogin(page: Page) {
  await gotoPageWithRetry(page, "/login", async () => {
    await expect(page.getByRole("heading", { name: /login to opnform/i })).toBeVisible()
    await expect(page.locator('input[name="email"]')).toBeEnabled()
    await expect(page.locator('input[name="password"]')).toBeEnabled()
  })
}

async function openRegister(page: Page) {
  await gotoPageWithRetry(page, "/register", async () => {
    await expect(page.getByRole("heading", { name: "Create an account" })).toBeVisible()
    await expect(page.locator('input[name="name"]')).toBeEnabled()
    await expect(page.locator('input[name="email"]')).toBeEnabled()
    await expect(page.locator('input[name="password"]')).toBeEnabled()
  })
}

async function selectHearAboutUs(page: Page) {
  await page.getByRole("button", { name: "Select option", exact: true }).click()
  await page.getByText("Friend or Colleague", { exact: true }).click()
}

async function registerUi(page: Page, email: string) {
  await openRegister(page)
  await page.locator('input[name="name"]').fill("Playwright User")
  await page.locator('input[name="email"]').fill(email)
  await selectHearAboutUs(page)
  await page.locator('input[name="password"]').fill("Abcd@1234")
  await page.locator('input[name="password_confirmation"]').fill("Abcd@1234")
  await page.locator('input[name="agree_terms"]').check({ force: true })

  await page.getByRole("button", { name: "Create account", exact: true }).click()
  await expect(page).toHaveURL(/\/forms\/create$/, { timeout: 30_000 })
}

async function dismissBlockingModals(page: Page) {
  const noThanksButton = page.getByRole("button", { name: "No thanks", exact: true })
  if (await noThanksButton.isVisible().catch(() => false)) {
    await noThanksButton.click()
  }
}

async function loginUi(page: Page, email = seededUser.email, password = seededUser.password) {
  await openLogin(page)
  await page.locator('input[name="email"]').fill(email)
  await page.locator('input[name="password"]').fill(password)

  await page.getByRole("button", { name: /log in to continue/i }).click()
  await expect(page).toHaveURL(/\/home$/, { timeout: 20_000 })
  await dismissBlockingModals(page)
  await expect(page.getByTestId("home-page")).toBeVisible()
}

async function logoutUi(page: Page) {
  await page.getByRole("button", { name: "User menu" }).click()
  await page.getByText("Logout", { exact: true }).click()
  await expect(page).toHaveURL(/\/login$/, { timeout: 20_000 })
}

async function renameEditorTitle(page: Page, title: string) {
  const titleWrapper = page.locator("#form-editor-title")
  await titleWrapper.click()
  const input = titleWrapper.locator("input")
  await expect(input).toBeVisible()
  await input.fill(title)
  await input.blur()
}

async function createFormThroughUi(page: Page, options: { title: string, style: "classic" | "focused" }) {
  await page.goto("/forms/create")
  await expect(page.getByText("Choose a form style")).toBeVisible()

  await page.getByTestId(options.style === "classic" ? "form-style-classic" : "form-style-focused").click()
  await expect(page.getByText("Choose a base for your form")).toBeVisible()
  await page.getByTestId("form-base-simple-contact").click()

  await expect(page.locator("#form-editor")).toBeVisible()
  await renameEditorTitle(page, options.title)

  await page.getByTestId("save-form-button").click()
  await expect(page).toHaveURL(/\/forms\/.+\/show\/share/, { timeout: 30_000 })
  await dismissBlockingModals(page)
  await expect(page.getByRole("heading", { name: options.title, exact: true })).toBeVisible()
}

async function apiLogin(request: APIRequestContext) {
  const response = await request.post(`${API_BASE_URL}/login`, {
    data: {
      email: seededUser.email,
      password: seededUser.password,
      remember: false,
    },
    headers: {
      Accept: "application/json",
    },
  })

  expect(response.ok()).toBeTruthy()
  const data = await response.json()
  return data.token as string
}

async function apiWorkspaceId(request: APIRequestContext, token: string) {
  const response = await request.get(`${API_BASE_URL}/open/workspaces`, {
    headers: {
      Accept: "application/json",
      Authorization: `Bearer ${token}`,
    },
  })

  expect(response.ok()).toBeTruthy()
  const data = await response.json()
  return data[0].id as number
}

async function apiCreateForm(request: APIRequestContext, options: {
  title?: string,
  presentationStyle?: "classic" | "focused",
  payloadOverrides?: Record<string, unknown>,
} = {}) {
  const token = await apiLogin(request)
  const workspaceId = await apiWorkspaceId(request, token)
  const title = options.title || uniqueTitle()
  let lastFailure: { status: number, body: string } | null = null

  for (let attempt = 1; attempt <= 3; attempt += 1) {
    const response = await request.post(`${API_BASE_URL}/open/forms`, {
      headers: {
        Accept: "application/json",
        Authorization: `Bearer ${token}`,
      },
      data: {
        ...buildFormPayload(workspaceId, title, options.presentationStyle || "classic"),
        ...(options.payloadOverrides || {}),
      },
    })

    if (response.ok()) {
      const data = await response.json()
      return data.form as { id: number, slug: string, share_url: string, title: string }
    }

    lastFailure = {
      status: response.status(),
      body: await response.text(),
    }

    if (attempt < 3) {
      await new Promise((resolve) => setTimeout(resolve, attempt * 500))
    }
  }

  throw new Error(`Failed to create form via API (${lastFailure?.status}): ${lastFailure?.body}`)
}

async function submitPublicForm(page: Page, slug: string, visitorName = "Playwright Visitor") {
  const visitorEmail = uniqueEmail("visitor")

  await openPublicForm(page, slug)
  await fillFieldInput(page, "default_name", visitorName)
  await fillFieldInput(page, "default_email", visitorEmail)
  await fillFieldInput(page, "default_message", "Playwright public submission")

  await page.getByRole("button", { name: /submit/i }).click()
  await expect(page.getByText("Amazing, we saved your answers. Thank you for your time and have a great day!")).toBeVisible()
  return { visitorName, visitorEmail }
}

async function openPublicForm(page: Page, slug: string) {
  await gotoPageWithRetry(page, `/forms/${slug}`, async () => {
    await expect(page.getByTestId("public-form-page")).toBeVisible()
  })
}

async function expectSubmissionSuccess(page: Page) {
  await expect(page.getByText("Amazing, we saved your answers. Thank you for your time and have a great day!")).toBeVisible()
}

function getOpenFormField(page: Page, fieldId: string) {
  return page.getByTestId(`open-form-field-${fieldId}`)
}

async function expectFieldError(page: Page, fieldId: string, message: string | RegExp) {
  const exact = typeof message === "string"
  const fieldMessage = getOpenFormField(page, fieldId).getByText(message, { exact })
  if (await fieldMessage.count()) {
    await expect(fieldMessage.first()).toBeVisible()
    return
  }

  await expect(page.locator("li").filter({ hasText: message }).first()).toBeVisible()
}

async function fillFieldInput(page: Page, fieldId: string, value: string) {
  const input = getOpenFormField(page, fieldId).locator("input, textarea").first()
  await input.click()
  await input.fill("")
  if (value) {
    await input.pressSequentially(value)
  }
  await input.blur()
}

async function fillLabeledInput(page: Page, label: string, value: string) {
  const input = page.getByLabel(label).first()
  await input.click()
  await input.fill("")
  if (value) {
    await input.pressSequentially(value)
  }
  await input.blur()
}

async function checkFieldCheckbox(page: Page, fieldId: string) {
  await getOpenFormField(page, fieldId).locator('input[type="checkbox"]').check({ force: true })
}

async function selectFlatOption(page: Page, fieldId: string, optionName: string, role: "radio" | "checkbox") {
  await getOpenFormField(page, fieldId).getByRole(role, { name: optionName, exact: true }).click()
}

test.beforeEach(async ({ page }, testInfo) => {
  const browserLogs: string[] = []
  browserLogsByTest.set(testInfo.testId, browserLogs)

  page.on("console", (message) => {
    browserLogs.push(`[console:${message.type()}] ${message.text()}`)
  })

  page.on("pageerror", (error) => {
    browserLogs.push(`[pageerror] ${error.stack || error.message}`)
  })

  page.on("requestfailed", (request) => {
    browserLogs.push(
      `[requestfailed] ${request.method()} ${request.url()} :: ${request.failure()?.errorText || "unknown"}`,
    )
  })
})

test.afterEach(async ({}, testInfo) => {
  const browserLogs = browserLogsByTest.get(testInfo.testId) || []

  if (testInfo.status !== testInfo.expectedStatus && browserLogs.length > 0) {
    await testInfo.attach("browser-logs", {
      body: browserLogs.join("\n"),
      contentType: "text/plain",
    })
  }

  browserLogsByTest.delete(testInfo.testId)
})

test("register succeeds and lands in the form creation flow", async ({ page }) => {
  await registerUi(page, uniqueEmail("register"))

  await expect(page).toHaveURL(/\/forms\/create$/, { timeout: 20_000 })
  await expect(page.getByText("Choose a form style")).toBeVisible()
})

test("login succeeds for the seeded user", async ({ page }) => {
  await loginUi(page)
  await expect(page.getByTestId("home-page").getByRole("link", { name: "Create Form" }).first()).toBeVisible()
})

test("invalid login stays on the login page", async ({ page }) => {
  await openLogin(page)
  await page.locator('input[name="email"]').fill(seededUser.email)
  await page.locator('input[name="password"]').fill("wrong-password")

  await page.getByRole("button", { name: /log in to continue/i }).click()
  await expect(page).toHaveURL(/\/login$/)
  await expect(page.getByText(/credentials/i)).toBeVisible()
})

test("logout clears auth and protected routes redirect to login", async ({ page }) => {
  await loginUi(page)
  await logoutUi(page)

  await page.goto("/home")
  await expect(page).toHaveURL(/\/login$/, { timeout: 20_000 })
})

test("classic form creation works end to end", async ({ page }) => {
  await loginUi(page)

  const title = uniqueTitle("Classic Form")
  await createFormThroughUi(page, { title, style: "classic" })

  const shareValue = page.getByTestId("copy-content-value").first()
  await expect(shareValue).toContainText("/forms/")
})

test("focused form creation works end to end", async ({ page }) => {
  await loginUi(page)

  const title = uniqueTitle("Focused Form")
  await createFormThroughUi(page, { title, style: "focused" })

  const shareValue = page.getByTestId("copy-content-value").first()
  await expect(shareValue).toContainText("/forms/")
})

test("public form pages render the expected fields", async ({ page, request }) => {
  const form = await apiCreateForm(request, { title: uniqueTitle("Public Fields") })

  await page.goto(`/forms/${form.slug}`)
  await expect(page.getByTestId("public-form-page")).toBeVisible()
  await expect(page.getByLabel("Name")).toBeVisible()
  await expect(page.getByLabel("Email")).toBeVisible()
  await expect(page.getByLabel("Message")).toBeVisible()
})

test("share page displays the public form URL", async ({ page, request }) => {
  const form = await apiCreateForm(request, { title: uniqueTitle("Share URL") })

  await loginUi(page)
  await page.goto(`/forms/${form.slug}/show/share`)

  const shareValue = page.getByTestId("copy-content-value").first()
  await expect(shareValue).toContainText(form.slug)
  await expect(page.getByTestId("copy-content-button").first()).toBeVisible()
})

test("editing an existing form title persists after save", async ({ page, request }) => {
  const form = await apiCreateForm(request, { title: uniqueTitle("Editable Form") })
  const updatedTitle = uniqueTitle("Updated Form")

  await loginUi(page)
  await page.goto(`/forms/${form.slug}/edit`)
  await expect(page.locator("#form-editor")).toBeVisible()

  await renameEditorTitle(page, updatedTitle)

  await page.getByTestId("save-form-button").click()
  await expect(page).toHaveURL(new RegExp(`/forms/${form.slug}/show/share$`), { timeout: 30_000 })
  await expect(page.getByRole("heading", { name: updatedTitle, exact: true })).toBeVisible()
})

test("public submissions are accepted and visible in the submissions dashboard", async ({ page, request }) => {
  const form = await apiCreateForm(request, { title: uniqueTitle("Submission Form") })
  const { visitorName, visitorEmail } = await submitPublicForm(page, form.slug)

  await loginUi(page)
  await page.goto(`/forms/${form.slug}/show/submissions`)

  await expect(page.getByTestId("form-submissions-page")).toBeVisible()
  await expect(page.getByText(visitorName)).toBeVisible()
  await expect(page.getByText(visitorEmail)).toBeVisible()
})

test("classic public form submits successfully with mixed field components", async ({ page, request }) => {
  const form = await apiCreateForm(request, {
    title: uniqueTitle("Mixed Components"),
    payloadOverrides: {
      properties: [
        {
          type: "nf-text",
          content: "<h1>Mixed components</h1><p>Fill every field.</p>",
          name: "Title",
          id: crypto.randomUUID(),
        },
        buildTextField("full_name", "Full name", { required: true }),
        buildEmailField("contact_email", "Contact email", { required: true }),
        buildCheckboxField("accept_terms", "Accept terms", { required: true }),
        buildSelectField("plan", "Plan", ["Starter", "Growth", "Enterprise"], { required: true }),
        buildMultiSelectField("channels", "Preferred channels", ["Email", "Phone", "Slack"], { required: true }),
      ],
    },
  })

  await openPublicForm(page, form.slug)
  await fillFieldInput(page, "full_name", "Ada Lovelace")
  await fillFieldInput(page, "contact_email", uniqueEmail("mixed"))
  await checkFieldCheckbox(page, "accept_terms")
  await selectFlatOption(page, "plan", "Growth", "radio")
  await selectFlatOption(page, "channels", "Email", "checkbox")
  await selectFlatOption(page, "channels", "Slack", "checkbox")
  await page.getByRole("button", { name: /submit/i }).click()

  await expectSubmissionSuccess(page)
})

test("classic multi-page public form navigates across pages and submits", async ({ page, request }) => {
  const form = await apiCreateForm(request, {
    title: uniqueTitle("Multi Page"),
    payloadOverrides: {
      properties: [
        buildTextField("first_name", "First name", { required: true }),
        buildPageBreak("page_break_1", "Continue", "Back"),
        buildTextField("company_name", "Company name", { required: true }),
        buildPageBreak("page_break_2", "Next step", "Previous step"),
        buildEmailField("work_email", "Work email", { required: true }),
      ],
    },
  })

  await openPublicForm(page, form.slug)
  await expect(page.getByLabel("First name")).toBeVisible()
  await fillFieldInput(page, "first_name", "Grace")
  await page.getByRole("button", { name: "Continue", exact: true }).click()

  await expect(page.getByLabel("Company name")).toBeVisible()
  await fillFieldInput(page, "company_name", "Analytical Engines Inc")
  await page.getByRole("button", { name: "Back", exact: true }).click()
  await expect(page.getByLabel("First name")).toBeVisible()
  await page.getByRole("button", { name: "Continue", exact: true }).click()
  await page.getByRole("button", { name: "Next step", exact: true }).click()

  await expect(page.getByLabel("Work email")).toBeVisible()
  await fillFieldInput(page, "work_email", uniqueEmail("multipage"))
  await page.getByRole("button", { name: /submit/i }).click()

  await expectSubmissionSuccess(page)
})

test("public form surfaces required and invalid email validation errors", async ({ page, request }) => {
  const form = await apiCreateForm(request, {
    title: uniqueTitle("Validation"),
    payloadOverrides: {
      properties: [
        buildTextField("required_name", "Required name", { required: true }),
        buildEmailField("required_email", "Required email", { required: true }),
      ],
    },
  })

  await openPublicForm(page, form.slug)
  await page.getByRole("button", { name: /submit/i }).click()
  await expectFieldError(page, "required_name", "The Required name field is required.")
  await expectFieldError(page, "required_email", "The Required email field is required.")

  await fillFieldInput(page, "required_name", "Validation User")
  await fillFieldInput(page, "required_email", "not-an-email")
  await page.getByRole("button", { name: /submit/i }).click()
  await expectFieldError(page, "required_email", /valid email address/i)
})

test("public form logic reveals and requires follow-up details conditionally", async ({ page, request }) => {
  const form = await apiCreateForm(request, {
    title: uniqueTitle("Conditional Logic"),
    payloadOverrides: {
      properties: [
        buildMultiSelectField("support_need", "Support need", ["General question", "Need follow-up"], { required: true }),
        buildSelectField("follow_up_details", "Follow-up details", ["Call me", "Email me"], {
          hidden: true,
          required: false,
          logic: {
            conditions: {
              operatorIdentifier: "and",
              children: [
                {
                  identifier: "support_need",
                  value: {
                    operator: "contains",
                    property_meta: {
                      id: "support_need",
                      type: "multi_select",
                    },
                    value: "Need follow-up",
                  },
                },
              ],
            },
            actions: ["require-answer", "show-block"],
          },
        }),
      ],
    },
  })

  await openPublicForm(page, form.slug)
  await expect(page.getByLabel("Follow-up details")).toHaveCount(0)
  await selectFlatOption(page, "support_need", "Need follow-up", "checkbox")
  await expect(page.getByLabel("Follow-up details")).toBeVisible()

  await page.getByRole("button", { name: /submit/i }).click()
  await expectFieldError(page, "follow_up_details", "The Follow-up details field is required.")

  await selectFlatOption(page, "follow_up_details", "Call me", "radio")
  await page.getByRole("button", { name: /submit/i }).click()
  await expectSubmissionSuccess(page)
})

test("public multi-select form enforces minimum selection before allowing submission", async ({ page, request }) => {
  const form = await apiCreateForm(request, {
    title: uniqueTitle("Multi Select Validation"),
    payloadOverrides: {
      properties: [
        buildMultiSelectField("team_tools", "Team tools", ["Notion", "Slack", "Linear", "Figma"], {
          required: true,
          min_selection: 2,
        }),
      ],
    },
  })

  await openPublicForm(page, form.slug)
  await selectFlatOption(page, "team_tools", "Notion", "checkbox")
  await page.getByRole("button", { name: /submit/i }).click()
  await expectFieldError(page, "team_tools", "Please select at least 2 options")

  await selectFlatOption(page, "team_tools", "Slack", "checkbox")
  await page.getByRole("button", { name: /submit/i }).click()
  await expectSubmissionSuccess(page)
})

test("focused public form accepts a complete stepped response flow", async ({ page, request }) => {
  const form = await apiCreateForm(request, {
    title: uniqueTitle("Focused Response"),
    presentationStyle: "focused",
    payloadOverrides: {
      properties: [
        buildTextField("focused_name", "Your name", { required: true }),
        buildEmailField("focused_email", "Your email", { required: true }),
        {
          id: "focused_goal",
          name: "Primary goal",
          type: "select",
          hidden: false,
          required: true,
          allow_creation: false,
          select: {
            options: buildOptionList(["Lead generation", "Feedback"]),
          },
        },
      ],
    },
  })

  await openPublicForm(page, form.slug)
  await fillLabeledInput(page, "Your name", "Focused User")
  await page.getByRole("button", { name: /next/i }).click()
  await fillLabeledInput(page, "Your email", uniqueEmail("focused"))
  await page.getByRole("button", { name: /next/i }).click()
  await page.getByRole("option", { name: /Lead generation/i }).click()

  await expectSubmissionSuccess(page)
})
