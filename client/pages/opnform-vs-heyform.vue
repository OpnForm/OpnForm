<template>
  <ComparisonPage
    competitor-name="HeyForm"
    competitor-icon="opnform:heyform"
    :hero-title="'OpnForm — The Open-Source HeyForm Alternative'"
    :free-plan-comparison="freePlanComparison"
    :switch-reasons="switchReasons"
    :feature-comparison="featureComparison"
    :get-competitor-price="getPrice"
  >
    <template #hero-subtitle>
      Create beautiful, logic‑driven forms with <b>unlimited responses, full control, and zero limits</b> — all for free.
      <br/><br/>
      HeyForm is a popular form builder that lets users create conversational forms with logic and integrations. Its public pricing starts with capped paid plans, while OpnForm delivers a <b>full form-building experience</b> with unlimited submissions, rich developer support, and self-hosting options.
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
    cells: ["Unlimited", "5k/month on Basic"],
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
    cells: ["Free basic quota", "Yes, capped by storage"],
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
    description: "Generate forms faster on paid plans.",
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
    cells: ["Y", "Business cap / Enterprise custom"],
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
    cells: ["Free API + webhooks", "Integrations listed"],
  },
  {
    label: "AI-powered form generation",
    cells: ["Pro+", "N"],
  },
  {
    label: "Unique submission identifiers",
    cells: ["Pro+", "N"],
  },
  {
    label: "Hidden fields & advanced logic",
    cells: ["Logic free; secret fields Pro+", "Y"],
  },
]

function getPrice(submissions) {
  if (submissions <= 5000) return 15
  if (submissions <= 20000) return 24
  if (submissions <= 150000) return 40
  return 40
}

</script>
