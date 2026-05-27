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

const scenarioGroups = {
  typeform: ["typeform"],
  googleForms: ["google forms", "googleforms"],
  workflow: ["fillout", "jotform", "123formbuilder", "123 formbuilder", "form.io", "formio"],
  simpleSwitch: ["tally", "youform", "heyform", "formbricks"],
}

function option(name, extra = {}) {
  return {
    id: name,
    name,
    ...extra,
  }
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

function buildComparisonScenario(competitorName, importSource) {
  const hasImport = !!importSource
  const importOption = "Yes, I would import it"
  const importOptions = hasImport
    ? [importOption, "No, I would start fresh", "I am just comparing"]
    : ["No, I would start fresh", "I am just comparing", "Maybe later"]

  const properties = [
    introSlide(
      "cmp_intro",
      `Try the ${competitorName} demo.`,
      `A short live form for people comparing ${competitorName} with OpnForm.`,
      "right-split",
      "switch",
    ),
    withSlideMedia(
      textField("cmp_name", "What should we call you?", {
        prefill: "Jamie Lee",
        placeholder: "Jamie Lee",
        help: "A prefilled text field keeps the demo moving while showing editable answers.",
      }),
      "fields",
      "left-split",
    ),
    withSlideMedia(
      ratingField("cmp_satisfaction", `How satisfied are you with ${competitorName} today?`, {
        help: "A rating makes the comparison concrete without adding friction.",
      }),
      "scale",
      "right-split",
    ),
    withSlideMedia(
      selectField("cmp_reason", "What is the main reason you are comparing tools?", [
        "Response limits",
        "Price as traffic grows",
        "Branding restrictions",
        "Need more control over data",
        "Need better automations",
      ], {
        help: "This mirrors the main objection a visitor brings to a comparison page.",
      }),
      "select",
      "left-split",
    ),
    withSlideMedia(
      textField("cmp_note", `What should still feel good after leaving ${competitorName}?`, {
        multi_lines: true,
        prefill: "I want a clean form experience, but with fewer limits as responses grow.",
        placeholder: "Write a short note...",
        help: "This shows how comparison pages can collect qualitative switching context.",
      }),
      "summary",
      "right-split",
    ),
    withSlideMedia(
      selectField("cmp_import", "Would you bring an existing form with you?", importOptions, {
        help: hasImport
          ? "Choose import to see a conditional URL field."
          : "This keeps the demo focused on the switch decision.",
      }),
      "switch",
      "left-split",
    ),
  ]

  if (hasImport) {
    properties.push(
      withSlideMedia(
        urlField("cmp_import_url", `Paste a ${competitorName} form URL`, {
          hidden: true,
          placeholder: `https://${competitorName.toLowerCase().replace(/[^a-z0-9]+/g, "")}.com/...`,
          logic: showWhen("cmp_import", "select", "equals", importOption),
          help: "This field appears only because you chose to import an existing form.",
        }),
        "switch",
        "right-split",
      ),
    )
  }

  properties.push(
    withSlideMedia(
      textBlock(
        "cmp_summary",
        `<p><strong>Demo complete</strong></p><h2><span class="text-blue-500">Thanks, ${mention("cmp_name", "Name", "Jamie")}.</span></h2><p>You rated ${competitorName} ${mention("cmp_satisfaction", "Satisfaction", "3")}/5 and said your main reason to compare is ${mention("cmp_reason", "Reason", "response limits")}.</p><p>Your note: ${mention("cmp_note", "Switch note", "you want a cleaner form experience with fewer limits")}</p>`,
      ),
      "summary",
      "left-split",
    ),
  )

  const form = baseForm("comparison", {
    title: `${competitorName} comparison survey`,
    submit_button_text: "Submit demo response",
    properties,
  })

  return baseScenario("comparison", {
    title: `Try the ${competitorName} demo.`,
    description: `Answer a short live form for people comparing ${competitorName} with OpnForm.`,
    urlLabel: `opnform.com/vs-${competitorName.toLowerCase().replace(/[^a-z0-9]+/g, "-")}`,
    highlights: ["Prefilled text", "Star rating", "Conditional import"],
    secondaryCtaLabel: hasImport ? `Import your ${competitorName} form` : null,
    successTitle: "Demo response submitted.",
    successBody: `That was a real OpnForm-style survey tailored to someone comparing ${competitorName}.`,
    form,
  })
}

function buildTypeformScenario(importSource) {
  return buildComparisonScenario("Typeform", importSource || "typeform")
}

function buildGoogleFormsScenario(importSource) {
  return buildComparisonScenario("Google Forms", importSource || "google_forms")
}

function buildWorkflowScenario(competitorName, importSource) {
  return buildComparisonScenario(competitorName, importSource)
}

function buildSimpleSwitchScenario(competitorName, importSource) {
  return buildComparisonScenario(competitorName, importSource)
}

function normalizeCompetitorName(competitorName) {
  return (competitorName || "").toLowerCase().trim()
}

function getComparisonGroup(competitorName) {
  const normalized = normalizeCompetitorName(competitorName)

  for (const [group, names] of Object.entries(scenarioGroups)) {
    if (names.includes(normalized)) {
      return group
    }
  }

  return "comparison"
}

export function getLiveDemoScenario({
  variant = "home",
  competitorName = "your current tool",
  importSource = null,
} = {}) {
  if (variant !== "comparison") {
    return buildHomeScenario()
  }

  const group = getComparisonGroup(competitorName)

  if (group === "typeform") {
    return buildTypeformScenario(importSource)
  }

  if (group === "googleForms") {
    return buildGoogleFormsScenario(importSource)
  }

  if (group === "workflow") {
    return buildWorkflowScenario(competitorName, importSource)
  }

  if (group === "simpleSwitch") {
    return buildSimpleSwitchScenario(competitorName, importSource)
  }

  return buildComparisonScenario(competitorName, importSource)
}
