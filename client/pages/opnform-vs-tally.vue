<template>
  <ComparisonPage
    competitor-name="Tally"
    competitor-icon="opnform:tally"
    hero-import-source="tally"
    :hero-title="'OpnForm — The Open-Source Tally Alternative'"
    :free-plan-comparison="freePlanComparison"
    :switch-reasons="switchReasons"
    :feature-comparison="featureComparison"
  >
    <template #hero-subtitle>
      Create beautiful, logic‑driven forms with <b>unlimited responses, full customization, and complete control over your data.</b>
      <br/><br/>
      Whether you're a creator, startup, agency, or enterprise team, OpnForm delivers the simplicity of modern form builders like Tally — but with <b>more flexibility, developer control, and open infrastructure.</b>
    </template>
  </ComparisonPage>
</template>

<script setup>
import ComparisonPage from "~/components/pages/comparisons/ComparisonPage.vue"

definePageMeta({
  layout: "default",
})

useOpnSeoMeta({
  title: "OpnForm vs Tally",
  description:
    "A powerful Tally alternative with unlimited responses and full control — for free.",
})

const freePlanComparison = [
  {
    label: "Monthly responses",
    cells: ["Unlimited", "Unlimited"],
  },
  {
    label: "Forms allowed",
    cells: ["Unlimited", "Unlimited"],
  },
  {
    label: "Questions per form",
    cells: ["Unlimited", "Unlimited"],
  },
  {
    label: "Branding removal",
    cells: ["No", "No"],
  },
  {
    label: "Conditional logic",
    cells: ["Yes", "Yes"],
  },
  {
    label: "File uploads",
    cells: ["Free basic quota", "Yes"],
  },
  {
    label: "Custom CSS",
    cells: ["No", "No"],
  },
  {
    label: "Native integrations",
    cells: ["Basic", "Basic"],
  },
  {
    label: "Webhooks",
    cells: ["Free", "Free"],
  },
]

const switchReasons = [
  {
    icon: "i-heroicons-circle-stack",
    title: "Open source platform",
    description: "Extend and adapt OpnForm's codebase.",
  },
  {
    icon: "i-heroicons-server-stack",
    title: "Self-hosting option",
    description: "Full control of your data and deployment environment.",
  },
  {
    icon: "i-heroicons-code-bracket-square",
    title: "Developer-friendly workflows",
    description: "Full API, webhooks, and automation support.",
  },
  {
    icon: "i-heroicons-puzzle-piece",
    title: "Advanced customization",
    description: "Deep integrations and extensible capabilities.",
  },
  {
    icon: "i-heroicons-rectangle-stack",
    title: "Flexible form infrastructure",
    description: "Built for more than basic forms.",
  },
  {
    icon: "i-heroicons-globe-alt",
    title: "Custom domains & branding",
    description: "Manage form identity easily.",
  },
]

const featureComparison = [
  {
    label: "Open source and community extensibility",
    cells: ["Y", "N"],
  },
  {
    label: "Self-hosting option",
    cells: ["Y", "N"],
  },
  {
    label: "Full API access",
    cells: ["Free API + webhooks", "Available"],
  },
  {
    label: "Custom domains for forms",
    cells: ["Pro+", "N"],
  },
  {
    label: "Advanced analytics / dashboard",
    cells: ["Pro+", "Basic"],
  },
  {
    label: "Native integrations ecosystem",
    cells: ["Basic free; advanced paid", "Zapier, Make, webhooks"],
  },
  {
    label: "Developer-first workflows",
    cells: ["Free API + webhooks", "API + webhooks"],
  },
  {
    label: "Flexible form use cases",
    cells: ["Lead, apps, workflows", "Forms and surveys"],
  },
  {
    label: "Universal embedding (sites/apps)",
    cells: ["Y", "Y"],
  },
  {
    label: "GDPR compliance",
    cells: ["Y", "Y"],
  },
]

</script>
