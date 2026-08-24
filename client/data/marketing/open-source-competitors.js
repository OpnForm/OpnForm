export const openSourceCompetitorColumns = [
  {
    label: "OpnForm",
    detail: "General-purpose forms",
    logo: "/img/logo.svg",
    highlight: true,
  },
  { label: "Formbricks", detail: "Surveys and experience" },
  { label: "HeyForm", detail: "Forms and surveys" },
  { label: "LimeSurvey", detail: "Research surveys" },
  { label: "Form.io", detail: "Form and data platform" },
]

export const openSourceCompetitorRows = [
  {
    label: "Core license",
    values: ["AGPLv3", "AGPLv3", "AGPLv3", "GPLv2+", "OSL 3.0"],
  },
  {
    label: "Primary focus",
    values: [
      "No-code forms and workflows",
      "Link, website, and in-app surveys",
      "General forms and surveys",
      "Advanced survey research",
      "Embedded forms and data APIs",
    ],
  },
  {
    label: "Builder experience",
    values: [
      "Visual no-code builder for non-technical teams",
      "Survey builder for link, web, and in-app feedback",
      "No-code form and survey builder",
      "Survey authoring for research teams",
      "Low-code builder for developers",
    ],
  },
  {
    label: "Managed option",
    values: ["OpnForm Cloud", "Formbricks Cloud", "Hosted HeyForm", "LimeSurvey Cloud", "Hosted Form.io"],
  },
  {
    label: "APIs and automation",
    values: [
      "REST API, webhooks, embeds, SDK",
      "Client and management APIs, webhooks",
      "Webhooks and integrations",
      "RemoteControl API and plugins",
      "Form APIs and framework SDKs",
    ],
  },
  {
    label: "Trade-off to consider",
    values: [
      "Enterprise governance uses a separate license",
      "Designed first for surveys and experience management",
      "Focused on forms and conversational surveys",
      "Survey-specialist workflow rather than general automation",
      "Form and data platform with more engineering ownership",
    ],
  },
  {
    label: "Choose it when",
    values: [
      "You need modern no-code forms with a Cloud-to-self-host path",
      "Product, customer, or employee experience surveys are the priority",
      "A small team needs straightforward forms and surveys",
      "You run complex multilingual research programs",
      "Developers are building form-driven applications and data APIs",
    ],
  },
]

export const openSourceCompetitorSources = [
  { label: "OpnForm", href: "https://github.com/OpnForm/OpnForm" },
  { label: "Formbricks", href: "https://formbricks.com/docs/self-hosting/overview" },
  { label: "HeyForm", href: "https://github.com/heyform/heyform" },
  { label: "LimeSurvey", href: "https://www.limesurvey.org/manual/Quick_start_guide" },
  { label: "Form.io", href: "https://github.com/formio/formio" },
]

export const openSourceDetailedComparisons = [
  {
    label: "OpnForm vs Formbricks",
    description: "General-purpose forms versus a survey and experience platform.",
    to: { name: "opnform-vs-formbricks" },
  },
  {
    label: "OpnForm vs HeyForm",
    description: "Compare two AGPL form builders on workflows, hosting, and team growth.",
    to: { name: "opnform-vs-heyform" },
  },
  {
    label: "OpnForm vs Form.io",
    description: "A no-code form product versus a developer-focused form and data platform.",
    to: { name: "opnform-vs-formio" },
  },
]

export const closedSaasComparisons = [
  { label: "Typeform", to: { name: "opnform-vs-typeform" } },
  { label: "Jotform", to: { name: "opnform-vs-jotform" } },
  { label: "Tally", to: { name: "opnform-vs-tally" } },
  { label: "Fillout", to: { name: "opnform-vs-fillout" } },
  { label: "Google Forms", to: { name: "opnform-vs-googleforms" } },
]
