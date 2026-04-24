<template>
  <ComparisonPage
    competitor-name="Google Forms"
    competitor-icon="i-simple-icons-googleforms"
    competitor-icon-class="text-[#7248B9]"
    :hero-title="'OpnForm — The Open-Source Google Forms Alternative'"
    :free-plan-comparison="freePlanComparison"
    :switch-reasons="switchReasons"
    :feature-comparison="featureComparison"
  >
    <template #hero-subtitle>
      Build powerful forms with <b>unlimited responses, deep customization, and full control over your data</b> — without limitations.
      <br/><br/>
      Google Forms is a simple and reliable tool for basic data collection. It’s widely used for surveys and internal workflows, especially within the Google ecosystem. However, it has limitations when it comes to customization, advanced workflows, and developer flexibility. OpnForm provides a modern form-building experience with <b>greater flexibility, customization, and scalability.</b>
    </template>
  </ComparisonPage>
</template>

<script setup>
import ComparisonPage from "~/components/pages/comparisons/ComparisonPage.vue"

definePageMeta({
  layout: "default",
})

useOpnSeoMeta({
  title: "OpnForm vs Google Forms",
  description:
    "A powerful Google Forms alternative with unlimited responses and full control — for free.",
})

const freePlanComparison = [
  {
    label: "Unlimited submissions",
    cells: ["Yes", "Yes"],
  },
  {
    label: "Unlimited forms",
    cells: ["Yes", "Yes"],
  },
  {
    label: "Conditional logic",
    cells: ["Yes", "Yes (basic logic jumps)"],
  },
  {
    label: "Multi-page forms",
    cells: ["Yes", "Yes"],
  },
  {
    label: "File uploads",
    cells: ["Yes", "Yes (requires Google account)"],
  },
  {
    label: "Custom branding",
    cells: ["No", "No"],
  },
  {
    label: "Custom CSS",
    cells: ["No", "No"],
  },
  {
    label: "Analytics dashboard",
    cells: ["Basic / Pro", "Basic summaries"],
  },
  {
    label: "Integrations",
    cells: ["Via integrations", "Google ecosystem + Apps Script"],
  },
]

const switchReasons = [
  {
    icon: "i-heroicons-rectangle-stack",
    title: "More advanced workflows",
    description: "Go beyond simple surveys with logic-driven forms.",
  },
  {
    icon: "i-heroicons-adjustments-horizontal",
    title: "Greater customization",
    description: "Control form design and user experience.",
  },
  {
    icon: "i-heroicons-code-bracket-square",
    title: "Developer-friendly",
    description: "API, webhooks, and automation support.",
  },
  {
    icon: "i-heroicons-server-stack",
    title: "Open-source option",
    description: "Self-host and control your infrastructure.",
  },
  {
    icon: "i-heroicons-puzzle-piece",
    title: "Flexible integrations",
    description: "Connect beyond a single ecosystem.",
  },
  {
    icon: "i-heroicons-arrow-trending-up",
    title: "Scales with your business",
    description: "From solo creator to enterprise teams.",
  },
]

const featureComparison = [
  {
    label: "Open source",
    cells: ["Y", "N"],
  },
  {
    label: "Self-hosting option",
    cells: ["Y", "N"],
  },
  {
    label: "API + webhooks",
    cells: ["Y", "N"],
  },
  {
    label: "Custom domains",
    cells: ["Y", "N"],
  },
  {
    label: "Advanced logic & workflows",
    cells: ["Y", "Basic logic only"],
  },
  {
    label: "Full design customization",
    cells: ["Y", "N"],
  },
  {
    label: "Data ownership (self-hosting control)",
    cells: ["Y", "N"],
  },
  {
    label: "Ecosystem integrations",
    cells: ["Integrations", "Google ecosystem"],
  },
]
</script>
