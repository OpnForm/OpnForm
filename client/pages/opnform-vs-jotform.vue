<template>
  <ComparisonPage
    competitor-name="JotForm"
    competitor-icon="opnform:jotform"
    :hero-title="'OpnForm — The Open-Source JotForm Alternative'"
    :free-plan-comparison="freePlanComparison"
    :switch-reasons="switchReasons"
    :feature-comparison="featureComparison"
    :get-competitor-price="getPrice"
  >
    <template #hero-subtitle>
      Create beautiful, logic‑driven forms with <b>unlimited responses, full control, and zero limits</b> — all for free.
      <br/><br/>
      Collect data the way you want. Whether you’re a big or small team, a startup, or a builder, OpnForm delivers everything Jotform does — without paywalls, usage caps, or per‑month submission limits.
    </template>
  </ComparisonPage>
</template>

<script setup>
import ComparisonPage from "~/components/pages/comparisons/ComparisonPage.vue"

definePageMeta({
  layout: "default",
})

useOpnSeoMeta({
  title: "OpnForm vs JotForm",
  description:
    "A powerful JotForm alternative with unlimited responses and full control — for free.",
})

const freePlanComparison = [
  {
    label: "Monthly submissions",
    cells: ["Unlimited submissions", "Limited (100/month)"],
  },
  {
    label: "Active forms",
    cells: ["Unlimited forms", "Limited (5 forms)"],
  },
  {
    label: "Form views",
    cells: ["Unlimited", "Limited (1000/month)"],
  },
  {
    label: "File uploads",
    cells: ["Included", "Included"],
  },
  {
    label: "Conditional Logic",
    cells: ["Included", "Included"],
  },
  {
    label: "Custom CSS",
    cells: ["Included", "Not available"],
  },
  {
    label: "API access",
    cells: ["Included", "Basic"],
  },
  {
    label: "Webhooks",
    cells: ["Not available", "Not available"],
  },
  {
    label: "Storage space",
    cells: ["Depends on plan", "Limited"],
  }
]

const switchReasons = [
  {
    icon: "i-ph-infinity-bold",
    title: "Unlimited responses & forms",
    description: "No per‑month caps like Jotform’s free limits.",
  },
  {
    icon: "i-heroicons-paint-brush",
    title: "Self‑hosting & open source",
    description: "Full control of your deployment and data.",
  },
  {
    icon: "i-heroicons-command-line",
    title: "No branding paywalls",
    description: "Free plan without limits on usage.",
  },
  {
    icon: "i-heroicons-circle-stack",
    title: "Developer workflows",
    description: "Full API + webhook support for automation.",
  },
  {
    icon: "i-heroicons-code-bracket-square",
    title: "Developer-friendly",
    description: "REST API, webhooks, and automation-ready.",
  },
  {
    icon: "i-heroicons-user-group",
    title: "Scales with you",
    description: "Build forms, workflows, and apps on one platform.",
  },
]

const featureComparison = [
  {
    label: "Open source",
    cells: ["Y", "N"],
  },
  {
    label: "Self‑hosting option",
    cells: ["Y", "N"],
  },
  {
    label: "Unlimited responses",
    cells: ["Y", "Only with paid plan"],
  },
  {
    label: "Advanced workflow use cases",
    cells: ["Lead forms, apps, workflows", "Form‑centric"],
  },
  {
    label: "Developer extensibility",
    cells: ["High", "Moderate"],
  },
  {
    label: "Custom domains for forms",
    cells: ["Y", "Only Enterprise"],
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
