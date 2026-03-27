<template>
  <ComparisonPage
    competitor-name="Formbricks"
    competitor-icon="i-simple-icons-formbricks"
    competitor-icon-class="text-[#00CABA]"
    :hero-title="'OpnForm — The Open-Source Formbricks Alternative'"
    :free-plan-comparison="freePlanComparison"
    :switch-reasons="switchReasons"
    :feature-comparison="featureComparison"
    :get-competitor-price="getPrice"
  >
    <template #hero-subtitle>
      Create beautiful, logic‑driven forms with <b>unlimited responses, full control, and zero limits</b> — all for free.
      <br/><br/>
      Collect data the way you want. Whether you’re a big or small team, a startup, or a builder, OpnForm delivers everything Formbricks does — without paywalls, usage caps, or per‑month submission limits.
    </template>
  </ComparisonPage>
</template>

<script setup>
import ComparisonPage from "~/components/pages/comparisons/ComparisonPage.vue"

definePageMeta({
  layout: "default",
})

useOpnSeoMeta({
  title: "OpnForm vs Formbricks",
  description:
    "A powerful Formbricks alternative with unlimited responses and full control — for free.",
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
