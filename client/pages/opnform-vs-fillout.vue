<template>
  <ComparisonPage
    competitor-name="Fillout"
    competitor-icon="opnform:fillout"
    :hero-title="'OpnForm — The Open-Source Fillout Alternative'"
    :free-plan-comparison="freePlanComparison"
    :switch-reasons="switchReasons"
    :feature-comparison="featureComparison"
    :get-competitor-price="getPrice"
  >
    <template #hero-subtitle>
      Create powerful, customizable forms with <b>unlimited responses, developer control, and complete data ownership</b> — without monthly caps or usage ceilings.
      <br/><br/>
      Fillout has a modern interface and a free plan (1,000 responses/month), but OpnForm gives you <b>true unlimited submissions, open-source flexibility, and self-hosting options</b> so you can scale without constraints.
    </template>
  </ComparisonPage>
</template>

<script setup>
import ComparisonPage from "~/components/pages/comparisons/ComparisonPage.vue"

definePageMeta({
  layout: "default",
})

useOpnSeoMeta({
  title: "OpnForm vs Fillout",
  description:
    "A powerful Fillout alternative with unlimited responses and full control — for free.",
})

const freePlanComparison = [
  {
    label: "Monthly Responses",
    cells: ["Unlimited submissions per month", "1,000 / month"],
  },
  {
    label: "Forms Allowed",
    cells: ["Unlimited forms", "Unlimited forms"],
  },
  {
    label: "Branding Removal",
    cells: ["Not available on free", "Not available"],
  },
  {
    label: "Conditional Logic",
    cells: ["Conditional logic included", "Basic conditional logic available"],
  },
  {
    label: "File Uploads",
    cells: ["File uploads available", "Not Included"],
  },
  {
    label: "Custom CSS",
    cells: ["No direct custom CSS", "No custom CSS"],
  },
  {
    label: "Integrations",
    cells: [
      "Webhooks and API access available",
      "Only limited native integrations",
    ],
  },
  {
    label: "Self-hosting Option",
    cells: ["Available", "No self-hosting"],
  },
  {
    label: "Price for 1,000 Responses",
    cells: ["Free", "$10/month"],
  },
]

const switchReasons = [
  {
    icon: "i-ph-infinity-bold",
    title: "No limits, no hidden costs",
    description: "Unlimited forms and responses on every plan.",
  },
  {
    icon: "i-heroicons-paint-brush",
    title: "Fully branded forms",
    description: "Add your logo, colors, and even custom CSS.",
  },
  {
    icon: "i-heroicons-command-line",
    title: "Advanced logic & calculations",
    description: "Create dynamic, intelligent forms easily.",
  },
  {
    icon: "i-heroicons-circle-stack",
    title: "Open source",
    description: "Own your data, host it yourself, or use our managed version.",
  },
  {
    icon: "i-heroicons-code-bracket-square",
    title: "Developer-friendly",
    description: "REST API, webhooks, and automation-ready.",
  },
  {
    icon: "i-heroicons-user-group",
    title: "Some title goes here...",
    description: "Some text goes here...",
  },
]

const featureComparison = [
  {
    label: "Unlimited responses",
    cells: ["Y", "N"],
  },
  {
    label: "Logic jumps & branching",
    cells: ["Y", "Y"],
  },
  {
    label: "File uploads",
    cells: ["Y", "Y"],
  },
  {
    label: "Calculations",
    cells: ["Y", "Paid"],
  },
  {
    label: "Custom branding",
    cells: ["Y", "N"],
  },
  {
    label: "Custom CSS",
    cells: ["Y", "N"],
  },
  {
    label: "Integrations",
    cells: ["100+", "Limited"],
  },
  {
    label: "Analytics dashboard",
    cells: ["Y", "Paid"],
  },
  {
    label: "Open source",
    cells: ["Y", "N"],
  },
  {
    label: "Self-hosting",
    cells: ["Y", "N"],
  },
  {
    label: "API access",
    cells: ["Y", "Paid"],
  },
  {
    label: "GDPR compliance",
    cells: ["Y", "Y"],
  },
]

function getPrice(submissions) {
  if (submissions <= 100) return 0
  if (submissions <= 1000) return 29
  if (submissions <= 2500) return 59
  if (submissions <= 5000) return 99
  if (submissions <= 10000) return 149
  if (submissions <= 15000) return 249
  if (submissions <= 20000) return 399
  return 499
}

</script>
