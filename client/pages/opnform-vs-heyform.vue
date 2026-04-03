<template>
  <ComparisonPage
    competitor-name="HeyForm"
    competitor-icon="i-simple-icons-heyform"
    :hero-title="'OpnForm — The Open-Source HeyForm Alternative'"
    :free-plan-comparison="freePlanComparison"
    :switch-reasons="switchReasons"
    :feature-comparison="featureComparison"
    :get-competitor-price="getPrice"
  >
    <template #hero-subtitle>
      Create beautiful, logic‑driven forms with <b>unlimited responses, full control, and zero limits</b> — all for free.
      <br/><br/>
      HeyForm is a popular form builder that lets users create conversational forms with logic and integrations. Its free plan, however, limits submissions and doesn’t focus on advanced customization. OpnForm delivers a <b>full form-building experience</b> with unlimited submissions, rich developer support, and self-hosting options.
    </template>
  </ComparisonPage>
</template>

<script setup>
import ComparisonPage from "~/components/pages/comparisons/ComparisonPage.vue"

definePageMeta({
  layout: "default",
})

useOpnSeoMeta({
  title: "OpnForm vs HeyForm",
  description:
    "A powerful HeyForm alternative with unlimited responses and full control — for free.",
})

const freePlanComparison = [
  {
    label: "Unlimited submissions",
    cells: ["Unlimited", "Limited to 250/month"],
  },
  {
    label: "Unlimited forms",
    cells: ["Unlimited", "Unlimited"],
  },
  {
    label: "Unlimited fields & questions",
    cells: ["Unlimited", "Unlimited"],
  },
  {
    label: "Conditional logic",
    cells: ["Yes", "Yes"],
  },
  {
    label: "File uploads",
    cells: ["Yes", "Yes"],
  },
  {
    label: "Multi-page / conversational forms",
    cells: ["Yes", "Yes"],
  },
  {
    label: "Drag-and-drop builder",
    cells: ["Yes", "Yes"],
  },
  {
    label: "Embedded forms",
    cells: ["Yes", "Yes"],
  },
  {
    label: "No code required",
    cells: ["Yes", "Yes"],
  },
]

const switchReasons = [
  {
    icon: "i-ph-infinity-bold",
    title: "Unlimited submissions",
    description: "No monthly caps.",
  },
  {
    icon: "i-heroicons-sparkles",
    title: "AI-powered form creation",
    description: "Build complex forms instantly.",
  },
  {
    icon: "i-heroicons-adjustments-horizontal",
    title: "Advanced customization & controls",
    description: "Deep control over logic and exports.",
  },
  {
    icon: "i-heroicons-code-bracket-square",
    title: "Developer-friendly integrations",
    description: "API, webhooks, automation ready.",
  },
  {
    icon: "i-heroicons-server-stack",
    title: "Flexible hosting options",
    description: "Self-host or cloud, full data ownership.",
  },
  {
    icon: "i-heroicons-globe-alt",
    title: "Custom domains & branding",
    description: "Manage form identity easily.",
  },
]

const featureComparison = [
  {
    label: "True unlimited responses",
    cells: ["Y", "N"],
  },
  {
    label: "Open source",
    cells: ["Y", "Y"],
  },
  {
    label: "Self-hosting available",
    cells: ["Y", "Yes (complex)"],
  },
  {
    label: "Developer workflows (API + webhooks)",
    cells: ["Y", "Y"],
  },
  {
    label: "AI-powered form generation",
    cells: ["Y", "N"],
  },
  {
    label: "Unique submission identifiers",
    cells: ["Y", "N"],
  },
  {
    label: "Hidden fields & advanced logic",
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
