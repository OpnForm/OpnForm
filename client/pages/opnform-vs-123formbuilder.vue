<template>
  <ComparisonPage
    competitor-name="123FormBuilder"
    competitor-icon="opnform:123formbuilder"
    :hero-title="'OpnForm — The Open-Source 123FormBuilder Alternative'"
    :free-plan-comparison="freePlanComparison"
    :switch-reasons="switchReasons"
    :feature-comparison="featureComparison"
    :get-competitor-price="getPrice"
  >
    <template #hero-subtitle>
      Create beautiful, logic‑driven forms with <b>unlimited responses, full control, and zero limits</b> — all for free.
      <br/><br/>
      123FormBuilder is a well‑known form builder with paid plans behind many advanced features. Its free plan comes with meaningful limitations on submissions, forms, and fields. OpnForm removes those constraints and adds open‑source flexibility, self‑hosting options, and extensibility — making it ideal for builders, teams, and developers.
    </template>
  </ComparisonPage>
</template>

<script setup>
import ComparisonPage from "~/components/pages/comparisons/ComparisonPage.vue"

definePageMeta({
  layout: "default",
})

useOpnSeoMeta({
  title: "OpnForm vs 123FormBuilder",
  description:
    "A powerful 123FormBuilder alternative with unlimited responses and full control — for free.",
})

const freePlanComparison = [
  {
    label: "Monthly submissions",
    cells: ["Unlimited submissions", "Limited (100/month)"],
  },
  {
    label: "Forms Allowed",
    cells: ["Unlimited forms", "Limited (5 forms)"],
  },
  {
    label: "Fields per form",
    cells: ["Unlimited", "Limited (10 fields)"],
  },
  {
    label: "Multi‑page forms",
    cells: ["Included", "Included"],
  },
  {
    label: "Conditional logic",
    cells: ["Included", "Included"],
  },
  {
    label: "File Uploads",
    cells: ["Included", "Not Included"],
  },
  {
    label: "Custom CSS",
    cells: ["No direct custom CSS", "No custom CSS"],
  },
  {
    label: "API access",
    cells: ["Included", "Not available"],
  }
]

const switchReasons = [
  {
    icon: "i-ph-infinity-bold",
    title: "Unlimited responses & forms",
    description: "No submission or form count caps",
  },
  {
    icon: "i-heroicons-paint-brush",
    title: "Self‑hosting & open source",
    description: "Full control of deployment and data",
  },
  {
    icon: "i-heroicons-command-line",
    title: "Developer‑friendly workflows",
    description: "Full API, webhook, and automation support",
  },
  {
    icon: "i-heroicons-circle-stack",
    title: "Custom domains & white‑label",
    description: "Brand your forms everywhere",
  },
  {
    icon: "i-heroicons-code-bracket-square",
    title: "Rich integrations",
    description: "Hundreds of integrations for modern workflows",
  },
  {
    icon: "i-heroicons-user-group",
    title: "Extensibility",
    description: "Build complex experiences and embed anywhere",
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
    cells: ["Y", "N"],
  },
  {
    label: "Custom domains for forms",
    cells: ["Y", "Paid only"],
  },
  {
    label: "Advanced logic & branching",
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
    label: "GDPR compliance",
    cells: ["Y", "Y"],
  },
]

function getPrice(submissions) {
  if (submissions <= 100) return 0
  if (submissions <= 5000) return 18.5
  if (submissions <= 20000) return 24.5
  if (submissions <= 100000) return 49.5
  return 25.5
}

</script>
