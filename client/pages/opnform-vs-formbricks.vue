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
      While Formbricks focuses primarily on <b>surveys and in‑app feedback, OpnForm is built as a general‑purpose form platform</b> — ideal for lead capture, registrations, applications, workflows, embedded forms, and more.
      <br/><br/>
      Whether you're a creator, startup, agency, or enterprise team, OpnForm delivers a modern form‑building experience with <b>greater flexibility, extensibility, and scalability.</b>
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
    cells: ["Unlimited", "Unlimited surveys & feedback"],
  },
  {
    label: "Unlimited forms / surveys",
    cells: ["Yes", "Yes"],
  },
  {
    label: "File uploads",
    cells: ["Included", "Limited"],
  },
  {
    label: "Custom CSS / styling",
    cells: ["Paid", "Limited"],
  },
  {
    label: "Native integrations",
    cells: ["Basic", "API Integrations"],
  },
  {
    label: "Self-hosting",
    cells: ["Yes", "Yes"],
  },
  {
    label: "API access",
    cells: ["Yes", "Yes"],
  },
]

const switchReasons = [
  {
    icon: "i-heroicons-rectangle-stack",
    title: "Flexible form capabilities",
    description: "Build beyond surveys: lead capture, signup flows, workflows, applications.",
  },
  {
    icon: "i-ph-infinity-bold",
    title: "Unlimited responses and forms",
    description: "No usage ceilings.",
  },
  {
    icon: "i-heroicons-code-bracket-square",
    title: "Developer-friendly workflows",
    description: "Full API, webhook support, and automation integration.",
  },
  {
    icon: "i-heroicons-globe-alt",
    title: "Custom domains & embeds",
    description: "Fully brand your forms anywhere.",
  },
  {
    icon: "i-heroicons-puzzle-piece",
    title: "Rich integration ecosystem",
    description: "Connect to modern workflows with ease.",
  },
  {
    icon: "i-heroicons-arrow-trending-up",
    title: "Scales with your business",
    description: "From solo creator to enterprise teams.",
  },
]

const featureComparison = [
  {
    label: "Open source platform",
    cells: ["Y", "Y"],
  },
  {
    label: "Self-hosting with cloud option",
    cells: ["Y", "Y"],
  },
  {
    label: "Developer extensibility (API + webhooks)",
    cells: ["Full support", "API only"],
  },
  {
    label: "Custom domains for forms",
    cells: ["Y", "N"],
  },
  {
    label: "Flexible form uses (lead capture, workflows, apps)",
    cells: ["Y", "Survey-oriented"],
  },
  {
    label: "Native ecosystem integrations",
    cells: ["100+ available", "Limited via API"],
  },
  {
    label: "Advanced analytics & dashboards",
    cells: ["Enhanced options", "Survey metrics only"],
  },
  {
    label: "Branding removal & customization",
    cells: ["White-label (Pro)", "Enterprise only"],
  },
]

function getPrice(submissions) {
  if (submissions <= 250) return 0
  if (submissions <= 2000) return 89
  if (submissions <= 5000) return 390
  return 390
}

</script>
