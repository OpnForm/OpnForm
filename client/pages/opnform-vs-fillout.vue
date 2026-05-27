<template>
  <ComparisonPage
    competitor-name="Fillout"
    competitor-icon="opnform:fillout"
    hero-import-source="fillout"
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
    cells: ["Included", "Included"],
  },
  {
    label: "Multi‑page forms",
    cells: ["Included", "Included"],
  },
  {
    label: "File Uploads",
    cells: ["Free basic quota", "Included"],
  },
  {
    label: "Custom CSS",
    cells: ["No direct custom CSS", "Pro+"],
  },
  {
    label: "Self-hosting Option",
    cells: ["Available", "No self-hosting"],
  },
  {
    label: "API access",
    cells: ["Free", "Included"],
  },
]

const switchReasons = [
  {
    icon: "i-ph-infinity-bold",
    title: "No monthly submission caps",
    description: "Freedom to scale without paywalls",
  },
  {
    icon: "i-heroicons-paint-brush",
    title: "Flexible form types ",
    description: "Lead capture, registrations, applications, workflows, surveys",
  },
  {
    icon: "i-heroicons-command-line",
    title: "Advanced logic & branching",
    description: "Create dynamic, intelligent forms",
  },
  {
    icon: "i-heroicons-circle-stack",
    title: "Open source & self-hosting",
    description: "Full control over deployment and data",
  },
  {
    icon: "i-heroicons-code-bracket-square",
    title: "Developer-first workflows",
    description: "API, webhooks, and automation-ready",
  },
  {
    icon: "i-heroicons-user-group",
    title: "Branding and styling controls",
    description: "Remove branding on Pro and use advanced styling on Business.",
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
    cells: ["Y", "Y"],
  },
  {
    label: "Custom branding",
    cells: ["Pro+", "Paid"],
  },
  {
    label: "Custom CSS",
    cells: ["Business+", "Pro+"],
  },
  {
    label: "Integrations",
    cells: ["Basic free; advanced paid", "50+ included"],
  },
  {
    label: "Analytics dashboard",
    cells: ["Pro+", "Summary free; analytics paid"],
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
    cells: ["Free", "Y"],
  },
  {
    label: "GDPR compliance",
    cells: ["Y", "Y"],
  },
]

function getPrice(submissions) {
  if (submissions <= 1000) return 0
  if (submissions <= 2000) return 15
  if (submissions <= 5000) return 40
  return 100
}

</script>
