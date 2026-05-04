<template>
  <ComparisonPage
    competitor-name="Youform"
    competitor-icon="opnform:youform"
    :hero-title="'OpnForm — The Open-Source Youform Alternative'"
    :free-plan-comparison="freePlanComparison"
    :switch-reasons="switchReasons"
    :feature-comparison="featureComparison"
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
    cells: ["Free basic quota", "Free up to 10MB"],
  },
  {
    label: "Custom branding",
    cells: ["No", "No"],
  },
  {
    label: "Custom CSS / styling",
    cells: ["Business+", "Paid"],
  },
  {
    label: "Native integrations",
    cells: ["Basic", "Basic"],
  },
  {
    label: "API access",
    cells: ["Basic", "Not listed"],
  },
  {
    label: "Webhooks",
    cells: ["Free", "Free"],
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
    title: "Custom domains available",
    description: "Use custom domains on paid plans.",
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
    cells: ["Pro+", "Pro"],
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
    cells: ["Free API + webhooks", "Webhooks; API not listed"],
  },
  {
    label: "Native integration ecosystem",
    cells: ["Basic free; advanced paid", "Selected integrations"],
  },
  {
    label: "Advanced logic & branching",
    cells: ["Y", "Moderate"],
  },
  {
    label: "Advanced analytics dashboard",
    cells: ["Pro+", "Basic"],
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

</script>
