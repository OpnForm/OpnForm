const DEFAULT_COLOR = "#2563EB"
const LIVE_DEMO_MEDIA = {
  intro: "/img/live-demo/variants/intro-big-soft-blobs-v2.webp",
  fields: "/img/live-demo/variants/intro-big-soft-blobs-v2.webp",
  logic: "/img/live-demo/variants/logic-big-soft-blobs-v2.webp",
  routing: "/img/live-demo/variants/logic-big-soft-blobs-v2.webp",
  scale: "/img/live-demo/variants/select-big-soft-blobs-v2.webp",
  select: "/img/live-demo/variants/select-big-soft-blobs-v2.webp",
  summary: "/img/live-demo/variants/summary-big-soft-blobs-v2.webp",
  switch: "/img/live-demo/variants/logic-big-soft-blobs-v2.webp",
}

function option(name, extra = {}) {
  return {
    id: name,
    name,
    ...extra,
  }
}

function competitorSlug(competitorName) {
  return (competitorName || "current-tool")
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-|-$/g, "")
}

function withSlideMedia(field, media, layout = "right-split", extra = {}) {
  return {
    ...field,
    image: {
      url: LIVE_DEMO_MEDIA[media] || LIVE_DEMO_MEDIA.intro,
      media_key: media,
      alt: "Abstract OpnForm live demo visual",
      layout,
      focal_point: { x: 50, y: 56 },
      brightness: 0,
      fade: false,
      loading: "eager",
      decoding: "sync",
      fetchpriority: "high",
      width: 1254,
      height: 1254,
      ...extra,
    },
  }
}

export function getLiveDemoMediaPreloads() {
  return [...new Set(Object.values(LIVE_DEMO_MEDIA))]
}

function mention(id, name, fallback = "") {
  return `<span mention="true" mention-field-id="${id}" mention-field-name="${name}" mention-fallback="${fallback}" contenteditable="false">${name}</span>`
}

function logicCondition(fieldId, type, operator, value) {
  const condition = {
    operator,
    property_meta: {
      id: fieldId,
      type,
    },
  }

  if (value !== undefined) {
    condition.value = value
  }

  return {
    identifier: fieldId,
    value: condition,
  }
}

function showWhen(fieldId, type, operator, value) {
  return {
    conditions: {
      operatorIdentifier: "and",
      children: [logicCondition(fieldId, type, operator, value)],
    },
    actions: ["show-block"],
  }
}

function baseForm(key, overrides = {}) {
  return {
    id: `live-demo-${key}`,
    slug: `live-demo-${key}`,
    title: "OpnForm Live Demo",
    visibility: "public",
    workspace_id: null,
    properties: [],
    computed_variables: [],
    presentation_style: "focused",
    language: "en",
    font_family: null,
    theme: "default",
    width: "centered",
    layout_rtl: false,
    dark_mode: "light",
    color: DEFAULT_COLOR,
    no_branding: true,
    uppercase_labels: false,
    transparent_background: false,
    auto_save: false,
    auto_focus: true,
    border_radius: "small",
    size: "lg",
    submit_button_text: "Submit demo response",
    submitted_text: null,
    use_captcha: false,
    show_progress_bar: false,
    re_fillable: false,
    confetti_on_submission: false,
    can_be_indexed: false,
    settings: {
      auto_next: true,
      navigation_arrows: false,
    },
    seo_meta: {},
    ...overrides,
  }
}

function textBlock(id, content, extra = {}) {
  return {
    id,
    type: "nf-text",
    name: "Text",
    content,
    ...extra,
  }
}

function introSlide(id, title, body, layout = "right-split", media = "intro") {
  return withSlideMedia(
    textBlock(
      id,
      `<p><strong>Live demo</strong></p><h2>${title}</h2><p>${body}</p>`,
    ),
    media,
    layout,
  )
}

function selectField(id, name, options, extra = {}) {
  return {
    id,
    type: "select",
    name,
    required: true,
    hidden: false,
    placeholder: "Select one",
    select: {
      options: options.map((item) => (typeof item === "string" ? option(item) : option(item.name, item))),
    },
    ...extra,
  }
}

function multiSelectField(id, name, options, extra = {}) {
  return {
    id,
    type: "multi_select",
    name,
    required: true,
    hidden: false,
    placeholder: "Choose all that apply",
    multi_select: {
      options: options.map((item) => (typeof item === "string" ? option(item) : option(item.name, item))),
    },
    ...extra,
  }
}

function checkboxField(id, name, extra = {}) {
  return {
    id,
    type: "checkbox",
    name,
    required: false,
    hidden: false,
    ...extra,
  }
}

function ratingField(id, name, extra = {}) {
  return {
    id,
    type: "rating",
    name,
    required: true,
    hidden: false,
    rating_max_value: 5,
    ...extra,
  }
}

function scaleField(id, name, extra = {}) {
  return {
    id,
    type: "scale",
    name,
    required: true,
    hidden: false,
    scale_min_value: 1,
    scale_max_value: 5,
    scale_step_value: 1,
    ...extra,
  }
}

function sliderField(id, name, extra = {}) {
  return {
    id,
    type: "slider",
    name,
    required: true,
    hidden: false,
    slider_min_value: 0,
    slider_max_value: 100,
    slider_step_value: 5,
    ...extra,
  }
}

function textField(id, name, extra = {}) {
  return {
    id,
    type: "text",
    name,
    required: true,
    hidden: false,
    placeholder: "Type your answer...",
    ...extra,
  }
}

function pageBreak(id, extra = {}) {
  return {
    id,
    type: "nf-page-break",
    name: "Page Break",
    required: false,
    hidden: false,
    next_btn_text: "Continue",
    previous_btn_text: "Back",
    ...extra,
  }
}

function focusedSlides(properties, mediaSequence = ["intro", "fields", "select", "logic", "routing", "scale", "summary"]) {
  return properties
    .filter((field) => field.type !== "nf-page-break")
    .map((field, index) => {
      const focusedField = { ...field }
      delete focusedField.width

      if (focusedField.image) {
        return focusedField
      }

      const media = mediaSequence[index % mediaSequence.length]
      const layout = index % 2 === 0 ? "right-split" : "left-split"
      return withSlideMedia(focusedField, media, layout)
    })
}

function computedVariable(id, name, formula, resultType = "number") {
  return {
    id,
    name,
    formula,
    result_type: resultType,
  }
}

function emailField(id, name, extra = {}) {
  return {
    id,
    type: "email",
    name,
    required: true,
    hidden: false,
    placeholder: "you@example.com",
    ...extra,
  }
}

function urlField(id, name, extra = {}) {
  return {
    id,
    type: "url",
    name,
    required: false,
    hidden: false,
    placeholder: "https://example.com/form",
    ...extra,
  }
}

function baseScenario(key, overrides) {
  return {
    key,
    eyebrow: "Live demo",
    title: "Try OpnForm live",
    description: "Answer a short sample form and see how OpnForm feels for respondents.",
    urlLabel: "opnform.com/live-demo",
    highlights: ["Live form", "Conditional logic", "Personalized ending"],
    primaryCtaLabel: "Create a free form",
    secondaryCtaLabel: null,
    successTitle: "Demo complete.",
    successBody: "You just tried a real OpnForm flow: visuals, fields, conditional logic, and a custom ending in one simple form.",
    form: baseForm(key),
    ...overrides,
  }
}

function buildHomeScenario() {
  const form = baseForm("home", {
    title: "Product feedback demo",
    submit_button_text: "Submit demo response",
    properties: [
      introSlide(
        "home_intro",
        '<span class="text-blue-500">Try OpnForm live.</span>',
        "A short demo form with prefilled answers, a rating, text fields, and conditional logic.",
        "right-split",
        "intro",
      ),
      withSlideMedia(
        textField("home_name", "What should we call you?", {
          prefill: "Alex Morgan",
          placeholder: "Alex Morgan",
          help: "A prefilled text field you can edit.",
        }),
        "fields",
        "left-split",
      ),
      withSlideMedia(
        ratingField("home_rating", "How would you rate this demo so far?", {
          help: "Click a star to answer.",
        }),
        "scale",
        "right-split",
      ),
      withSlideMedia(
        selectField("home_use_case", "What would you use a form like this for?", [
          "Customer feedback",
          "Lead capture",
          "Event registration",
          "Client intake",
          "Internal request",
        ], {
          help: "A quick choice field for structured answers.",
        }),
        "select",
        "left-split",
      ),
      withSlideMedia(
        textField("home_comment", "Leave a short demo note", {
          multi_lines: true,
          prefill: "I would use this to collect product feedback after a customer onboarding call.",
          placeholder: "Write a short note...",
          help: "A longer text field, already filled in.",
        }),
        "summary",
        "right-split",
      ),
      withSlideMedia(
        selectField("home_follow_up", "Want to see conditional logic?", [
          "Yes, show me the follow-up field",
          "No, keep the demo short",
        ], {
          help: "Choose yes to reveal one extra field.",
        }),
        "routing",
        "right-split",
      ),
      withSlideMedia(
        emailField("home_email", "Where should a follow-up go?", {
          hidden: true,
          prefill: "alex@example.com",
          logic: showWhen("home_follow_up", "select", "equals", "Yes, show me the follow-up field"),
          help: "Shown only because of your previous answer.",
        }),
        "routing",
        "left-split",
      ),
      withSlideMedia(
        textBlock(
          "home_summary",
          `<p><strong>Demo complete</strong></p><h2><span class="text-blue-500">Thanks, ${mention("home_name", "Name", "Alex")}.</span></h2><p>You rated the demo ${mention("home_rating", "Rating", "5")}/5 and picked ${mention("home_use_case", "Use case", "a form workflow")}.</p><p>Your note: ${mention("home_comment", "Comment", "a short answer")}</p>`,
        ),
        "summary",
        "right-split",
      ),
    ],
  })

  return baseScenario("home", {
    title: "Try a real OpnForm live.",
    description: "Fill out a short demo form with prefilled text, stars, choices, and conditional logic.",
    urlLabel: "opnform.com/live-demo",
    highlights: ["Prefilled text", "Star rating", "Custom ending"],
    form,
  })
}

function buildMigrationFields(prefix, competitorName, importSource, media = "switch") {
  const hasImport = !!importSource
  const importOption = `Yes, import my ${competitorName} form`
  const importFieldId = `${prefix}_import`
  const fields = [
    withSlideMedia(
      selectField(importFieldId, "Would you bring an existing form with you?", hasImport
        ? [importOption, "No, I would rebuild it", "I am just comparing"]
        : ["No, I would rebuild it", "I am just comparing", "Maybe later"], {
          help: hasImport
            ? "Choose import to reveal a conditional URL field."
            : "This keeps the demo focused on the switching workflow.",
        }),
      media,
      "left-split",
    ),
  ]

  if (hasImport) {
    fields.push(
      withSlideMedia(
        urlField(`${prefix}_import_url`, `Paste a ${competitorName} form URL`, {
          hidden: true,
          placeholder: `https://${competitorSlug(competitorName).replaceAll("-", "")}.com/...`,
          logic: showWhen(importFieldId, "select", "equals", importOption),
          help: "This field appears only because you chose to import an existing form.",
        }),
        media,
        "right-split",
      ),
    )
  }

  return { fields, hasImport, importOption, importFieldId }
}

function comparisonForm(key, competitorName, overrides = {}) {
  return baseForm(`comparison-${key}`, {
    title: `${competitorName} comparison demo`,
    submit_button_text: "Submit demo response",
    settings: {
      auto_next: false,
      navigation_arrows: false,
    },
    ...overrides,
  })
}

function comparisonScenario(key, competitorName, importSource, overrides) {
  return baseScenario(`comparison-${key}`, {
    urlLabel: `opnform.com/vs-${competitorSlug(competitorName)}`,
    secondaryCtaLabel: importSource ? `Import your ${competitorName} form` : null,
    successTitle: "Demo response submitted.",
    ...overrides,
  })
}

function buildTypeformScenario(importSource) {
  const competitorName = "Typeform"
  const migration = buildMigrationFields("typeform", competitorName, importSource || "typeform")
  const scoreFormula = `ROUND(({typeform_experience} + COUNT({typeform_controls}) + IF({${migration.importFieldId}} = "${migration.importOption}", 2, 0)) * 10)`
  const properties = [
    introSlide(
      "typeform_intro",
      '<span class="text-blue-500">Build a Typeform-style form in OpnForm.</span>',
      "Answer a few plain questions. You will see the same one-question-at-a-time feel, plus routing and unlimited responses.",
      "right-split",
      "intro",
    ),
    withSlideMedia(
      textField("typeform_name", "Who is filling this out?", {
        prefill: "Jamie from Acme",
        help: "A clean conversational opener keeps the Typeform feel.",
      }),
      "fields",
      "left-split",
    ),
    withSlideMedia(
      selectField("typeform_use_case", "What are you collecting?", [
        "Inbound leads",
        "Applications",
        "Product feedback",
        "Event registrations",
      ], {
        prefill: "Inbound leads",
        help: "Single-choice qualification works well in focused mode.",
      }),
      "select",
      "right-split",
    ),
    withSlideMedia(
      ratingField("typeform_experience", "How polished should the form feel?", {
        prefill: 5,
        help: "A simple rating field.",
      }),
      "scale",
      "left-split",
    ),
    withSlideMedia(
      multiSelectField("typeform_controls", "What should happen after someone submits?", [
        "Unlimited responses",
        "Ask follow-up questions",
        "Custom domain",
        "Send data to a webhook",
        "Export responses",
      ], {
        prefill: ["Unlimited responses", "Ask follow-up questions", "Send data to a webhook"],
        min_selection: 1,
        max_selection: 4,
        help: "Pick the actions you would actually use.",
      }),
      "select",
      "right-split",
    ),
    ...migration.fields,
    withSlideMedia(
      emailField("typeform_email", "Where should the qualified lead go?", {
        hidden: true,
        prefill: "sales@example.com",
        logic: showWhen("typeform_use_case", "select", "equals", "Inbound leads"),
        help: "Conditional routing appears because inbound leads were selected.",
      }),
      "routing",
      "left-split",
    ),
    withSlideMedia(
      textBlock(
        "typeform_summary",
        `<p><strong>Your demo form</strong></p><h2><span class="text-blue-500">A Typeform-style form, built in OpnForm.</span></h2><p>${mention("typeform_name", "Name", "Jamie")} wants to collect ${mention("typeform_use_case", "Use case", "inbound leads")} and then ${mention("typeform_controls", "Follow-up actions", "send responses to a webhook")}.</p>`,
      ),
      "summary",
      "right-split",
    ),
  ]

  const form = comparisonForm("typeform", competitorName, {
    properties,
    computed_variables: [
      computedVariable("cv_typeform_fit", "Fit score", scoreFormula),
    ],
  })

  return comparisonScenario("typeform", competitorName, importSource || "typeform", {
    title: "Try a Typeform-style flow in OpnForm.",
    description: "A simple one-question-at-a-time form with choices, routing, and a personalized ending.",
    highlights: ["Focused mode", "Plain questions", "Personalized ending"],
    successBody: "That was a Typeform-style experience, but with OpnForm's routing and unlimited responses behind it.",
    form,
  })
}

function buildGoogleFormsScenario(importSource) {
  const competitorName = "Google Forms"
  const migration = buildMigrationFields("google_forms", competitorName, importSource || "google_forms")
  const scoreFormula = `ROUND(({google_forms_depth} + COUNT({google_forms_outputs}) + IF({google_forms_access} = "Branded public form", 2, 0)) * 10)`
  const properties = [
    textBlock(
      "google_forms_intro",
      "<p><strong>Live demo</strong></p><h2>Make a Google Forms-style request form feel polished.</h2><p>Answer a few simple questions. OpnForm turns them into a clean form with follow-up actions.</p>",
    ),
    textField("google_forms_name", "Request owner", {
      width: "1/2",
      prefill: "Maya Chen",
    }),
    emailField("google_forms_email", "Team email", {
      width: "1/2",
      prefill: "maya@example.com",
    }),
    selectField("google_forms_access", "Who needs to fill it out?", [
      "Internal team only",
      "Branded public form",
      "Customers and partners",
    ], {
      prefill: "Branded public form",
    }),
    pageBreak("google_forms_break_1", { next_btn_text: "Design workflow" }),
    multiSelectField("google_forms_outputs", "What should happen after someone submits?", [
      "Notify the right team",
      "Send data to a webhook",
      "Embed on a website",
      "Export responses",
      "Use a custom domain",
    ], {
      prefill: ["Notify the right team", "Embed on a website", "Export responses"],
      min_selection: 1,
    }),
    scaleField("google_forms_depth", "How important is this form for your team?", {
      prefill: 4,
      help: "A simple 1-5 scale field.",
    }),
    textField("google_forms_brand_note", "What should feel more polished than a Google Form?", {
      multi_lines: true,
      prefill: "The form should match our site and route high-priority requests automatically.",
      hidden: true,
      logic: showWhen("google_forms_access", "select", "equals", "Branded public form"),
    }),
    ...migration.fields,
    pageBreak("google_forms_break_2", { next_btn_text: "Review form" }),
    textBlock(
      "google_forms_summary",
      `<p><strong>Your demo form</strong></p><h2><span class="text-blue-500">A cleaner request form than a basic Google Form.</span></h2><p>${mention("google_forms_name", "Request owner", "Maya")} needs a ${mention("google_forms_access", "Audience", "branded public form")} that can ${mention("google_forms_outputs", "Follow-up actions", "notify the team and export responses")}.</p>`,
    ),
  ]

  const form = comparisonForm("google-forms", competitorName, {
    show_progress_bar: true,
    properties: focusedSlides(properties),
    computed_variables: [
      computedVariable("cv_google_forms_workflow", "Workflow score", scoreFormula),
    ],
  })

  return comparisonScenario("google-forms", competitorName, importSource || "google_forms", {
    title: "Try a branded workflow beyond a basic Google Form.",
    description: "A simple request form with clear choices, follow-up actions, and a personalized ending.",
    highlights: ["Focused mode", "Clear choices", "Personalized ending"],
    successBody: "That was a Google Forms-style request upgraded into a branded OpnForm form.",
    form,
  })
}

function buildTallyScenario(importSource) {
  const competitorName = "Tally"
  const migration = buildMigrationFields("tally", competitorName, importSource || "tally")
  const scoreFormula = `ROUND(({tally_control} + COUNT({tally_needs}) + IF({tally_self_host} = TRUE, 2, 0)) * 10)`
  const properties = [
    introSlide(
      "tally_intro",
      '<span class="text-blue-500">Build a simple Tally-style form in OpnForm.</span>',
      "Keep the form short. Add unlimited responses, embeds, and exports when the project grows.",
      "right-split",
      "intro",
    ),
    withSlideMedia(
      textField("tally_project", "What are you building?", {
        prefill: "A waitlist for our new product",
      }),
      "fields",
      "left-split",
    ),
    withSlideMedia(
      multiSelectField("tally_needs", "What needs to stay simple?", [
        "Fast form creation",
        "Clean embeds",
        "Unlimited responses",
        "Custom domain",
        "Exportable data",
      ], {
        prefill: ["Fast form creation", "Clean embeds", "Unlimited responses"],
        min_selection: 1,
      }),
      "select",
      "right-split",
    ),
    withSlideMedia(
      scaleField("tally_control", "How important is it to keep this form flexible?", {
        prefill: 5,
      }),
      "scale",
      "left-split",
    ),
    withSlideMedia(
      checkboxField("tally_self_host", "I want the option to self-host later", {
        prefill: true,
        help: "A yes/no field can reveal different follow-up steps.",
      }),
      "routing",
      "right-split",
    ),
    ...migration.fields,
    withSlideMedia(
      textBlock(
        "tally_summary",
        `<p><strong>Your demo form</strong></p><h2><span class="text-blue-500">A simple form with room to grow.</span></h2><p>${mention("tally_project", "Project", "A waitlist")} needs ${mention("tally_needs", "Needs", "fast creation, embeds, and unlimited responses")}.</p>`,
      ),
      "summary",
      "right-split",
    ),
  ]

  const form = comparisonForm("tally", competitorName, {
    properties,
    computed_variables: [
      computedVariable("cv_tally_control", "Control score", scoreFormula),
    ],
  })

  return comparisonScenario("tally", competitorName, importSource || "tally", {
    title: "Try a simple form with infrastructure control built in.",
    description: "A short focused form for people who want speed now and more options later.",
    highlights: ["Focused mode", "Simple choices", "Personalized ending"],
    successBody: "That was a simple Tally-style flow with OpnForm controls ready when the project grows.",
    form,
  })
}

function buildJotformScenario(importSource) {
  const competitorName = "Jotform"
  const scoreFormula = "ROUND(({jotform_volume} * 0.5) + ({jotform_importance} * 8) + (COUNT({jotform_workflows}) * 4))"
  const properties = [
    textBlock(
      "jotform_intro",
      "<p><strong>Live demo</strong></p><h2>Build a scalable intake form without turning it into a maze.</h2><p>This focused flow shows the kind of client or operations intake teams often compare with Jotform.</p>",
    ),
    textField("jotform_company", "Company or client name", {
      width: "1/2",
      prefill: "Northstar Studio",
    }),
    emailField("jotform_contact", "Contact email", {
      width: "1/2",
      prefill: "ops@example.com",
    }),
    multiSelectField("jotform_workflows", "Which intake steps do you need?", [
      "File upload",
      "Approval routing",
      "Conditional questions",
      "Payment later",
      "CRM handoff",
    ], {
      prefill: ["Approval routing", "Conditional questions", "CRM handoff"],
      min_selection: 1,
    }),
    pageBreak("jotform_break_1", { next_btn_text: "Estimate volume" }),
    sliderField("jotform_volume", "Expected monthly submissions", {
      prefill: 70,
      slider_step_value: 10,
      help: "This slider contributes to the scale score.",
    }),
    scaleField("jotform_importance", "How painful are form limits as volume grows?", {
      prefill: 5,
    }),
    textField("jotform_high_volume_note", "What breaks first when volume grows?", {
      multi_lines: true,
      prefill: "Manual routing and plan limits become the bottleneck.",
      hidden: true,
      logic: showWhen("jotform_volume", "slider", "greater_than_or_equal_to", 60),
    }),
    pageBreak("jotform_break_2", { next_btn_text: "Review score" }),
    textBlock(
      "jotform_summary",
      `<p><strong>Scale score</strong></p><h2>${mention("cv_jotform_scale", "Scale score", "94")}/100</h2><p>${mention("jotform_company", "Company", "Northstar Studio")} expects ${mention("jotform_volume", "Monthly submissions", "70")} submissions and needs ${mention("jotform_workflows", "Workflow steps", "routing and CRM handoff")}.</p>`,
    ),
  ]

  const form = comparisonForm("jotform", competitorName, {
    show_progress_bar: true,
    properties: focusedSlides(properties),
    computed_variables: [
      computedVariable("cv_jotform_scale", "Scale score", scoreFormula),
    ],
  })

  return comparisonScenario("jotform", competitorName, importSource, {
    title: "Try a scalable intake form.",
    description: "A focused client intake with multi-select workflow needs, sliders, conditional follow-up, and a scale score.",
    highlights: ["Focused mode", "Slider input", "Scale score"],
    successBody: "That was a Jotform-style intake workflow rebuilt around OpnForm's scaling story.",
    form,
  })
}

function buildFilloutScenario(importSource) {
  const competitorName = "Fillout"
  const scoreFormula = `ROUND(({fillout_data_control} * 12) + (COUNT({fillout_destinations}) * 5) + IF({fillout_control_priority} = "Keep response data easy to export", 20, 0))`
  const properties = [
    textBlock(
      "fillout_intro",
      "<p><strong>Live demo</strong></p><h2>Build a lead form that sends people to the right next step.</h2><p>This demo shows a simple workflow with routing, exports, webhooks, and open-source flexibility.</p>",
    ),
    selectField("fillout_control_priority", "What would you want more control over?", [
      "Keep response data easy to export",
      "Move submissions to my tools",
      "Launch fast embeds first",
    ], {
      prefill: "Keep response data easy to export",
    }),
    multiSelectField("fillout_destinations", "Where should submissions go?", [
      "Email notification",
      "Webhook",
      "CRM",
      "Spreadsheet export",
      "Internal dashboard",
    ], {
      prefill: ["Email notification", "Webhook", "Spreadsheet export"],
      min_selection: 1,
    }),
    pageBreak("fillout_break_1", { next_btn_text: "Add control needs" }),
    scaleField("fillout_data_control", "How important are easy export and routing?", {
      prefill: 5,
      help: "This shapes the recommendation shown at the end.",
    }),
    checkboxField("fillout_open_source", "I want an open-source fallback if this workflow grows", {
      prefill: true,
    }),
    textField("fillout_control_note", "What should your team be able to change without rebuilding the form?", {
      multi_lines: true,
      prefill: "We want to adjust routing, exports, and copy without touching the rest of the workflow.",
      hidden: true,
      logic: showWhen("fillout_open_source", "checkbox", "is_checked"),
    }),
    pageBreak("fillout_break_2", { next_btn_text: "Review setup" }),
    textBlock(
      "fillout_summary",
      `<p><strong>Recommended setup</strong></p><h2><span class="text-blue-500">Use routing, webhooks, and exports.</span></h2><p>You chose ${mention("fillout_control_priority", "Control priority", "easy export")} and ${mention("fillout_destinations", "Destinations", "webhook and exports")}. OpnForm can collect the lead, send it to the right place, and keep the data easy to export.</p>`,
    ),
  ]

  const form = comparisonForm("fillout", competitorName, {
    show_progress_bar: true,
    properties: focusedSlides(properties),
    computed_variables: [
      computedVariable("cv_fillout_workflow_match", "Workflow match", scoreFormula),
    ],
  })

  return comparisonScenario("fillout", competitorName, importSource, {
    title: "Try a simple lead routing workflow.",
    description: "A focused workflow form showing data destinations, export needs, conditional notes, and a concrete recommended setup.",
    highlights: ["Focused mode", "Webhook routing", "Workflow match"],
    successBody: "That was a simple lead workflow using OpnForm's routing, exports, and open-source flexibility.",
    form,
  })
}

function buildHeyformScenario(importSource) {
  const competitorName = "HeyForm"
  const scoreFormula = `ROUND(({heyform_conversation} + COUNT({heyform_needs}) + IF({heyform_followup} = "Trigger a workflow", 2, 0)) * 10)`
  const properties = [
    introSlide(
      "heyform_intro",
      '<span class="text-blue-500">Conversational forms with workflow depth.</span>',
      "Try a friendly one-question-at-a-time form that adds routing, multi-select needs, and a readiness score.",
      "right-split",
      "intro",
    ),
    withSlideMedia(
      selectField("heyform_goal", "What should this conversational form do?", [
        "Collect leads",
        "Run a survey",
        "Qualify applicants",
        "Handle support requests",
      ], {
        prefill: "Qualify applicants",
      }),
      "select",
      "left-split",
    ),
    withSlideMedia(
      ratingField("heyform_conversation", "How much does conversational UX matter?", {
        prefill: 4,
      }),
      "scale",
      "right-split",
    ),
    withSlideMedia(
      multiSelectField("heyform_needs", "What should happen behind the form?", [
        "Conditional questions",
        "Email routing",
        "Webhook",
        "Custom branding",
        "Unlimited submissions",
      ], {
        prefill: ["Conditional questions", "Email routing", "Unlimited submissions"],
        min_selection: 1,
      }),
      "select",
      "left-split",
    ),
    withSlideMedia(
      selectField("heyform_followup", "What should happen to qualified responses?", [
        "Trigger a workflow",
        "Just store the response",
        "Review manually",
      ], {
        prefill: "Trigger a workflow",
      }),
      "routing",
      "right-split",
    ),
    withSlideMedia(
      emailField("heyform_route_to", "Workflow owner email", {
        hidden: true,
        prefill: "hiring@example.com",
        logic: showWhen("heyform_followup", "select", "equals", "Trigger a workflow"),
      }),
      "routing",
      "left-split",
    ),
    withSlideMedia(
      textBlock(
        "heyform_summary",
        `<p><strong>Readiness score</strong></p><h2><span class="text-blue-500">${mention("cv_heyform_readiness", "Readiness score", "90")}/100</span></h2><p>This ${mention("heyform_goal", "Goal", "applicant qualification")} form needs ${mention("heyform_needs", "Needs", "logic and routing")}.</p>`,
      ),
      "summary",
      "right-split",
    ),
  ]

  const form = comparisonForm("heyform", competitorName, {
    properties,
    computed_variables: [
      computedVariable("cv_heyform_readiness", "Readiness score", scoreFormula),
    ],
  })

  return comparisonScenario("heyform", competitorName, importSource, {
    title: "Try a conversational form with workflow depth.",
    description: "A focused HeyForm-style flow with routing logic, multi-select workflow needs, and a readiness score.",
    highlights: ["Focused mode", "Email routing", "Readiness score"],
    successBody: "That was a HeyForm-style conversational flow with OpnForm workflow depth built in.",
    form,
  })
}

function buildYouformScenario(importSource) {
  const competitorName = "Youform"
  const scoreFormula = `ROUND(({youform_growth} + COUNT({youform_needs}) + IF({youform_limit} = "I need more control", 2, 0)) * 10)`
  const properties = [
    introSlide(
      "youform_intro",
      '<span class="text-blue-500">Simple form first, platform later.</span>',
      "Try a lightweight form that starts simple and checks when a team needs more control, routing, and ownership.",
      "right-split",
      "intro",
    ),
    withSlideMedia(
      textField("youform_project", "What is the form for?", {
        prefill: "Newsletter sponsorship requests",
      }),
      "fields",
      "left-split",
    ),
    withSlideMedia(
      multiSelectField("youform_needs", "What do you need beyond a simple form?", [
        "Unlimited responses",
        "Conditional logic",
        "Custom branding",
        "Embeds",
        "Exports",
      ], {
        prefill: ["Unlimited responses", "Conditional logic", "Embeds"],
        min_selection: 1,
      }),
      "select",
      "right-split",
    ),
    withSlideMedia(
      scaleField("youform_growth", "How likely is this form to become a repeat workflow?", {
        prefill: 4,
      }),
      "scale",
      "left-split",
    ),
    withSlideMedia(
      selectField("youform_limit", "What would make you switch from a simple-only tool?", [
        "I need more control",
        "I need more styling",
        "I need more integrations",
      ], {
        prefill: "I need more control",
      }),
      "switch",
      "right-split",
    ),
    withSlideMedia(
      textBlock(
        "youform_summary",
        `<p><strong>Platform score</strong></p><h2><span class="text-blue-500">${mention("cv_youform_platform", "Platform score", "90")}/100</span></h2><p>${mention("youform_project", "Project", "Sponsorship requests")} needs ${mention("youform_needs", "Needs", "logic, embeds, and unlimited responses")}.</p>`,
      ),
      "summary",
      "right-split",
    ),
  ]

  const form = comparisonForm("youform", competitorName, {
    properties,
    computed_variables: [
      computedVariable("cv_youform_platform", "Platform score", scoreFormula),
    ],
  })

  return comparisonScenario("youform", competitorName, importSource, {
    title: "Try a simple form that can become a platform.",
    description: "A focused lightweight form that shows when OpnForm's logic, embeds, and ownership become useful.",
    highlights: ["Focused mode", "Multi-select", "Platform score"],
    successBody: "That was a Youform-style simple flow with OpnForm's path to richer workflows.",
    form,
  })
}

function buildFormbricksScenario(importSource) {
  const competitorName = "Formbricks"
  const scoreFormula = `ROUND(({formbricks_need} + COUNT({formbricks_use_cases}) + IF({formbricks_scope} = "I need full forms too", 2, 0)) * 10)`
  const properties = [
    introSlide(
      "formbricks_intro",
      '<span class="text-blue-500">From feedback survey to full form workflow.</span>',
      "Formbricks is strong for feedback. This OpnForm demo shows what happens when the job expands into applications, registrations, and lead capture.",
      "right-split",
      "intro",
    ),
    withSlideMedia(
      selectField("formbricks_scope", "Are you collecting feedback only, or full form submissions?", [
        "Feedback only",
        "I need full forms too",
        "Not sure yet",
      ], {
        prefill: "I need full forms too",
      }),
      "select",
      "left-split",
    ),
    withSlideMedia(
      multiSelectField("formbricks_use_cases", "Which form use cases are on the roadmap?", [
        "Lead capture",
        "Applications",
        "Registrations",
        "Customer intake",
        "Embedded website forms",
      ], {
        prefill: ["Lead capture", "Applications", "Embedded website forms"],
        min_selection: 1,
      }),
      "select",
      "right-split",
    ),
    withSlideMedia(
      scaleField("formbricks_need", "How important is a general-purpose form builder?", {
        prefill: 5,
      }),
      "scale",
      "left-split",
    ),
    withSlideMedia(
      emailField("formbricks_owner", "Who owns non-survey responses?", {
        hidden: true,
        prefill: "growth@example.com",
        logic: showWhen("formbricks_scope", "select", "equals", "I need full forms too"),
      }),
      "routing",
      "right-split",
    ),
    withSlideMedia(
      textBlock(
        "formbricks_summary",
        `<p><strong>Workflow score</strong></p><h2><span class="text-blue-500">${mention("cv_formbricks_workflow", "Workflow score", "100")}/100</span></h2><p>You selected ${mention("formbricks_use_cases", "Use cases", "lead capture and applications")}, so this is more than an in-app survey.</p>`,
      ),
      "summary",
      "right-split",
    ),
  ]

  const form = comparisonForm("formbricks", competitorName, {
    properties,
    computed_variables: [
      computedVariable("cv_formbricks_workflow", "Workflow score", scoreFormula),
    ],
  })

  return comparisonScenario("formbricks", competitorName, importSource, {
    title: "Try a general-purpose form, not just a survey.",
    description: "A focused flow that contrasts feedback collection with broader OpnForm use cases and routing.",
    highlights: ["Focused mode", "Use-case branching", "Workflow score"],
    successBody: "That was an OpnForm workflow tailored to someone comparing Formbricks for more than surveys.",
    form,
  })
}

function buildFormioScenario(importSource) {
  const competitorName = "Form.io"
  const scoreFormula = `ROUND(({formio_dev_load} * 10) + (COUNT({formio_needs}) * 6) + IF({formio_backend} = "No, I want hosted forms first", 20, 0))`
  const properties = [
    textBlock(
      "formio_intro",
      "<p><strong>Live demo</strong></p><h2>Production intake without provisioning a form backend.</h2><p>This focused flow shows a developer-friendly request workflow without making the respondent feel like they are filling an API schema.</p>",
    ),
    textField("formio_project", "Project name", {
      width: "1/2",
      prefill: "Partner onboarding portal",
    }),
    selectField("formio_backend", "Do you want to maintain form backend infrastructure?", [
      "No, I want hosted forms first",
      "Yes, we have a backend team",
      "Maybe for enterprise cases",
    ], {
      width: "1/2",
      prefill: "No, I want hosted forms first",
    }),
    multiSelectField("formio_needs", "Which developer workflows still matter?", [
      "Webhooks",
      "API access",
      "Self-hosting option",
      "Custom domains",
      "Exports",
    ], {
      prefill: ["Webhooks", "API access", "Self-hosting option"],
      min_selection: 1,
    }),
    pageBreak("formio_break_1", { next_btn_text: "Estimate effort" }),
    scaleField("formio_dev_load", "How much engineering effort should the form builder save?", {
      prefill: 5,
    }),
    checkboxField("formio_business_owner", "A non-technical teammate should edit this form", {
      prefill: true,
    }),
    textField("formio_handoff_note", "What should the non-technical owner be able to change?", {
      multi_lines: true,
      prefill: "They should edit fields, copy, and routing without asking engineering.",
      hidden: true,
      logic: showWhen("formio_business_owner", "checkbox", "is_checked"),
    }),
    pageBreak("formio_break_2", { next_btn_text: "Review developer workflow" }),
    textBlock(
      "formio_summary",
      `<p><strong>Developer workflow score</strong></p><h2>${mention("cv_formio_dev_workflow", "Developer workflow score", "88")}/100</h2><p>${mention("formio_project", "Project", "Partner onboarding")} needs ${mention("formio_needs", "Developer workflows", "webhooks and API access")} while avoiding unnecessary backend maintenance.</p>`,
    ),
  ]

  const form = comparisonForm("formio", competitorName, {
    show_progress_bar: true,
    properties: focusedSlides(properties),
    computed_variables: [
      computedVariable("cv_formio_dev_workflow", "Developer workflow score", scoreFormula),
    ],
  })

  return comparisonScenario("formio", competitorName, importSource, {
    title: "Try a focused production form without provisioning a form backend.",
    description: "A focused Form.io comparison flow for teams who want developer workflows without starting from infrastructure.",
    highlights: ["Focused mode", "Developer workflow", "Computed score"],
    successBody: "That was a Form.io-style production intake reframed around OpnForm's no-backend setup.",
    form,
  })
}

function build123FormBuilderScenario(importSource) {
  const competitorName = "123FormBuilder"
  const scoreFormula = "ROUND(({builder_volume} * 0.5) + ({builder_complexity} * 8) + (COUNT({builder_needs}) * 5))"
  const properties = [
    textBlock(
      "builder_intro",
      "<p><strong>Live demo</strong></p><h2>A client request form without starter-plan ceilings.</h2><p>This focused demo keeps the familiar request-form questions, then adds logic and a growth score.</p>",
    ),
    textField("builder_client", "Client or team name", {
      width: "1/2",
      prefill: "Atlas Events",
    }),
    emailField("builder_email", "Contact email", {
      width: "1/2",
      prefill: "events@example.com",
    }),
    multiSelectField("builder_needs", "What does the request form need?", [
      "More fields",
      "More submissions",
      "Conditional logic",
      "File uploads",
      "Embeds",
    ], {
      prefill: ["More fields", "More submissions", "Conditional logic"],
      min_selection: 1,
    }),
    pageBreak("builder_break_1", { next_btn_text: "Add volume" }),
    sliderField("builder_volume", "Expected monthly responses", {
      prefill: 80,
      slider_step_value: 10,
    }),
    scaleField("builder_complexity", "How complex will this form become?", {
      prefill: 4,
    }),
    textField("builder_limit_note", "Which limit would block this form first?", {
      multi_lines: true,
      prefill: "Field count and response volume would block us before the workflow is mature.",
      hidden: true,
      logic: showWhen("builder_needs", "multi_select", "contains", "More fields"),
    }),
    pageBreak("builder_break_2", { next_btn_text: "Review growth fit" }),
    textBlock(
      "builder_summary",
      `<p><strong>Growth score</strong></p><h2>${mention("cv_123_growth", "Growth score", "87")}/100</h2><p>${mention("builder_client", "Client", "Atlas Events")} expects ${mention("builder_volume", "Monthly responses", "80")} responses and needs ${mention("builder_needs", "Needs", "more fields and logic")}.</p>`,
    ),
  ]

  const form = comparisonForm("123formbuilder", competitorName, {
    show_progress_bar: true,
    properties: focusedSlides(properties),
    computed_variables: [
      computedVariable("cv_123_growth", "Growth score", scoreFormula),
    ],
  })

  return comparisonScenario("123formbuilder", competitorName, importSource, {
    title: "Try a focused client request form without starter-plan ceilings.",
    description: "A focused request form with multi-select needs, response-volume slider, conditional fields, and a growth score.",
    highlights: ["Focused mode", "Conditional field", "Growth score"],
    successBody: "That was a 123FormBuilder-style request form showing why OpnForm fits growing workflows.",
    form,
  })
}

function buildComparisonScenario(competitorName, importSource) {
  const safeName = competitorName || "your current tool"
  const migration = buildMigrationFields("cmp", safeName, importSource)
  const scoreFormula = `ROUND(({cmp_satisfaction} + COUNT({cmp_needs}) + IF({${migration.importFieldId}} = "${migration.importOption}", 2, 0)) * 10)`
  const properties = [
    introSlide(
      "cmp_intro",
      '<span class="text-blue-500">Try the OpnForm switching workflow.</span>',
      `A short live form for people comparing ${safeName} with OpnForm.`,
      "right-split",
      "switch",
    ),
    withSlideMedia(
      textField("cmp_name", "What should we call you?", {
        prefill: "Jamie Lee",
      }),
      "fields",
      "left-split",
    ),
    withSlideMedia(
      ratingField("cmp_satisfaction", `How satisfied are you with ${safeName} today?`, {
        prefill: 3,
      }),
      "scale",
      "right-split",
    ),
    withSlideMedia(
      multiSelectField("cmp_needs", "What are you comparing for?", [
        "More responses",
        "Better branding",
        "More control",
        "Automations",
        "Open-source flexibility",
      ], {
        prefill: ["More responses", "More control", "Automations"],
        min_selection: 1,
      }),
      "select",
      "left-split",
    ),
    ...migration.fields,
    withSlideMedia(
      textBlock(
        "cmp_summary",
        `<p><strong>Switch score</strong></p><h2><span class="text-blue-500">${mention("cv_cmp_switch", "Switch score", "80")}/100</span></h2><p>${mention("cmp_name", "Name", "Jamie")} is comparing for ${mention("cmp_needs", "Needs", "more control and automations")}.</p>`,
      ),
      "summary",
      "right-split",
    ),
  ]

  const form = comparisonForm(competitorSlug(safeName), safeName, {
    properties,
    computed_variables: [
      computedVariable("cv_cmp_switch", "Switch score", scoreFormula),
    ],
  })

  return comparisonScenario(competitorSlug(safeName), safeName, importSource, {
    title: "Try a switching workflow in OpnForm.",
    description: `Answer a short live form for people comparing ${safeName} with OpnForm.`,
    highlights: ["Focused mode", "Conditional import", "Switch score"],
    successBody: `That was an OpnForm switching workflow tailored to someone comparing ${safeName}.`,
    form,
  })
}

function normalizeCompetitorName(competitorName) {
  return (competitorName || "").toLowerCase().trim()
}

export function getLiveDemoScenario({
  variant = "home",
  competitorName = "your current tool",
  importSource = null,
} = {}) {
  if (variant !== "comparison") {
    return buildHomeScenario()
  }

  switch (normalizeCompetitorName(competitorName)) {
    case "typeform":
      return buildTypeformScenario(importSource)
    case "google forms":
    case "googleforms":
      return buildGoogleFormsScenario(importSource)
    case "tally":
      return buildTallyScenario(importSource)
    case "jotform":
    case "jot form":
      return buildJotformScenario(importSource)
    case "fillout":
      return buildFilloutScenario(importSource)
    case "heyform":
      return buildHeyformScenario(importSource)
    case "youform":
      return buildYouformScenario(importSource)
    case "formbricks":
      return buildFormbricksScenario(importSource)
    case "form.io":
    case "formio":
      return buildFormioScenario(importSource)
    case "123formbuilder":
    case "123 formbuilder":
      return build123FormBuilderScenario(importSource)
    default:
      return buildComparisonScenario(competitorName, importSource)
  }
}
