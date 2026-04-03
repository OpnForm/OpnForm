<template>
  <ComparisonPage
    competitor-name="Youform"
    competitor-icon="i-simple-icons-youform"
    :hero-title="'OpnForm — The Open-Source Youform Alternative'"
    :free-plan-comparison="freePlanComparison"
    :switch-reasons="switchReasons"
    :feature-comparison="featureComparison"
    :get-competitor-price="getPrice"
  >
    <template #hero-subtitle>
      Create powerful, customizable forms with <b>unlimited responses, deep logic, and total control over your data</b> — all without usage caps or platform lock‑in.
      <br/><br/>
      Youform is a modern form builder with intuitive design and free forms, but OpnForm is built as a <b>general‑purpose form platform</b> that scales from simple lead capture to complex workflows, apps, and embedded experiences — with <b>more extensibility, self‑hosting, and customization.</b>
    </template>
  </ComparisonPage>
</template>

<script setup>
import ComparisonPage from "~/components/pages/comparisons/ComparisonPage.vue"

definePageMeta({
  layout: "default",
})

useOpnSeoMeta({
  title: "OpnForm vs Youform",
  description:
    "A powerful Youform alternative with unlimited responses and full control — for free.",
})

const freePlanComparison = [
  {
    label: "Monthly submissions",
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
    label: "Conditional logic",
    cells: ["Basic", "Yes"],
  },
  {
    label: "File uploads",
    cells: ["Paid / Pro", "Paid"],
  },
  {
    label: "Custom branding",
    cells: ["No", "No"],
  },
  {
    label: "Custom CSS / styling",
    cells: ["Paid / Pro", "Paid"],
  },
  {
    label: "Native integrations",
    cells: ["Basic", "Basic"],
  },
  {
    label: "API access",
    cells: ["Basic", "Not yet"],
  },
  {
    label: "Webhooks",
    cells: ["No", "No"],
  },
]

const switchReasons = [
  {
    icon: "i-heroicons-server-stack",
    title: "Self-hosting option",
    description: "Full control of your data and deployment.",
  },
  {
    icon: "i-heroicons-circle-stack",
    title: "Open source codebase",
    description: "Audit, extend, and adapt the platform.",
  },
  {
    icon: "i-heroicons-globe-alt",
    title: "Custom domains included",
    description: "Brand your forms at no extra cost.",
  },
  {
    icon: "i-heroicons-rectangle-stack",
    title: "Advanced workflows and logic",
    description: "Build complex flows easily.",
  },
  {
    icon: "i-heroicons-code-bracket-square",
    title: "Developer-friendly automation",
    description: "API, webhooks, and extensibility.",
  },
  {
    icon: "i-heroicons-puzzle-piece",
    title: "Rich integrations ecosystem",
    description: "Connect to tools you already use.",
  },
]

const featureComparison = [
  {
    label: "Unlimited usage (forms + responses)",
    cells: ["Y", "Y"],
  },
  {
    label: "Custom domains for forms",
    cells: ["Y", "N"],
  },
  {
    label: "Open source platform",
    cells: ["Y", "N"],
  },
  {
    label: "Self-hosting option",
    cells: ["Y", "N"],
  },
  {
    label: "Developer extensibility (API + webhooks)",
    cells: ["Full", "Limited"],
  },
  {
    label: "Native integration ecosystem",
    cells: ["100+", "Limited"],
  },
  {
    label: "Advanced logic & branching",
    cells: ["Y", "Moderate"],
  },
  {
    label: "Advanced analytics dashboard",
    cells: ["Built-in options", "Basic"],
  },
  {
    label: "Reusable form components",
    cells: ["Y", "N"],
  },
  {
    label: "GDPR / compliance controls",
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
