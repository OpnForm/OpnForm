export const selfHostedColumns = [
  {
    label: "Cloud",
    detail: "Managed by OpnForm",
    icon: "i-heroicons-cloud",
  },
  {
    label: "Community",
    detail: "Self-hosted",
    icon: "i-simple-icons-github",
  },
  {
    label: "Enterprise",
    detail: "Self-hosted",
    icon: "i-heroicons-shield-check",
    highlight: true,
  },
]

export const selfHostedRows = [
  {
    label: "Forms and submissions",
    values: ["Unlimited", "Unlimited", "Unlimited"],
  },
  {
    label: "Core form features",
    values: ["Logic, calculations, files, embeds, and exports", "Logic, calculations, files, embeds, and exports", "Logic, calculations, files, embeds, and exports"],
  },
  {
    label: "Integrations",
    values: ["API, webhooks, and standard integrations", "API, webhooks, and standard integrations", "API, webhooks, and standard integrations"],
  },
  {
    label: "Team size",
    values: ["Based on Cloud plan", "Up to 2 users per instance", "More than 2 users"],
  },
  {
    label: "Identity",
    values: ["Based on Cloud plan", "OIDC within the 2-user limit", "OIDC plus SAML and LDAP"],
  },
  {
    label: "Branding",
    values: ["Based on Cloud plan", "OpnForm branding remains", "Branding removal and white label"],
  },
  {
    label: "Advanced controls",
    values: ["Based on Cloud plan", "Core workspace controls", "Roles, audit logs, external storage, and custom code"],
  },
  {
    label: "Best fit",
    values: ["Teams that want OpnForm to manage hosting", "Individuals and small teams", "Growing teams with advanced requirements"],
  },
  {
    label: "Hosting",
    values: ["Managed by OpnForm", "Runs in your environment", "Runs in your environment with priority support"],
  },
]

export const selfHostedSources = [
  { label: "Cloud vs self-hosting", href: "https://docs.opnform.com/deployment/cloud-vs-self-hosting" },
  { label: "Self-hosted license", href: "https://docs.opnform.com/deployment/self-hosted-license" },
  { label: "Docker deployment", href: "https://docs.opnform.com/deployment/docker" },
]
