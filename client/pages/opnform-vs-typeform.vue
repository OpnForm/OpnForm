<template>
  <ComparisonPage
    competitor-name="Typeform"
    competitor-icon="opnform:typeform"
    :hero-title="'OpnForm — the powerful Typeform alternative'"
    :free-plan-comparison="freePlanComparison"
    :switch-reasons="switchReasons"
    :feature-comparison="featureComparison"
    :get-competitor-price="getPrice"
  >
    <template #hero-subtitle>
      Create beautiful, logic‑driven forms with <b>unlimited responses, full control, and zero limits</b> — all for free.
      <br/><br/>
      Collect data the way you want. Whether you’re a big or small team, a startup, or a builder, OpnForm gives you Typeform’s clean experience without usage caps, paywalls, or feature restrictions.
    </template>
  </ComparisonPage>
</template>

<script setup>
import ComparisonPage from "~/components/pages/comparisons/ComparisonPage.vue"

definePageMeta({
  layout: "default",
})

useOpnSeoMeta({
  title: "OpnForm vs Typeform",
  description:
    "A powerful Typeform alternative with unlimited responses and full control — for free.",
})

const freePlanComparison = [
  {
    label: "Monthly submissions",
    cells: ["Unlimited", "10 / month cap"],
  },
  {
    label: "Forms allowed",
    cells: ["Unlimited", "Limited"],
  },
  {
    label: "Questions per form",
    cells: ["Unlimited", "Limited per plan"],
  },
  {
    label: "File uploads",
    cells: ["Paid / Pro", "Paid"],
  },
  {
    label: "Conditional logic",
    cells: ["Yes", "Paid"],
  },
  {
    label: "Custom branding",
    cells: ["No", "Typeform branding"],
  },
  {
    label: "Custom CSS / styling",
    cells: ["Paid / Pro", "Not supported"],
  },
  {
    label: "Native integrations",
    cells: ["Basic", "Limited"],
  },
  {
    label: "API access",
    cells: ["Basic", "Paid"],
  },
  {
    label: "Webhooks",
    cells: ["No", "Paid"],
  },
]

const switchReasons = [
  {
    icon: "i-ph-infinity-bold",
    title: "Unlimited responses & forms",
    description: "No artificial caps like Typeform's free limits.",
  },
  {
    icon: "i-heroicons-server-stack",
    title: "Self-hosting & open source",
    description: "Full control of data and deployment.",
  },
  {
    icon: "i-heroicons-code-bracket-square",
    title: "Developer-friendly automation",
    description: "API, webhooks, and integrations ready.",
  },
  {
    icon: "i-heroicons-globe-alt",
    title: "Custom domains & branding",
    description: "Fully brandable forms.",
  },
  {
    icon: "i-heroicons-rectangle-stack",
    title: "Reusable blocks & advanced logic",
    description: "Build complex flows easily.",
  },
  {
    icon: "i-heroicons-arrow-trending-up",
    title: "Better pricing scalability",
    description: "No forced upgrades as you grow.",
  },
]

const featureComparison = [
  {
    label: "Unlimited responses (global)",
    cells: ["Y", "Restricted"],
  },
  {
    label: "Unlimited forms",
    cells: ["Y", "Restricted"],
  },
  {
    label: "Open source",
    cells: ["Y", "N"],
  },
  {
    label: "Self-hosting option",
    cells: ["Y", "N"],
  },
  {
    label: "Custom domains for forms",
    cells: ["Y", "N"],
  },
  {
    label: "Developer extensibility (API + webhooks)",
    cells: ["Full support", "Limited / paid"],
  },
  {
    label: "Native integrations ecosystem",
    cells: ["100+", "Some"],
  },
  {
    label: "Advanced logic & branching",
    cells: ["Y", "Paid"],
  },
  {
    label: "Reusable form blocks / modular UI",
    cells: ["Y", "N"],
  },
  {
    label: "Analytics dashboard",
    cells: ["Only Pro", "Some reporting"],
  },
  {
    label: "Payments & workflows",
    cells: ["Integrations", "Paid (Stripe/others)"],
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
