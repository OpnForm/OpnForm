<template>
  <ComparisonPage
    competitor-name="Formbricks"
    competitor-icon="i-simple-icons-formbricks"
    competitor-icon-class="text-[#00CABA]"
    :hero-title="'OpnForm — The Open-Source Formbricks Alternative'"
    competitor-plan-label="Hobby"
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
    label: "Monthly responses",
    cells: ["Unlimited", "250/month on Cloud Hobby"],
  },
  {
    label: "Unlimited forms / surveys",
    cells: ["Y", "Y"],
  },
  {
    label: "File uploads",
    cells: ["Included", "Included"],
  },
  {
    label: "Custom CSS / styling",
    cells: ["Business+", "Included self-host"],
  },
  {
    label: "Native integrations",
    cells: ["Included", "Pro cloud / self-host"],
  },
  {
    label: "API access",
    cells: ["Included", "Self-host / paid cloud"],
  },
  {
    label: "Open source",
    cells: ["Y", "Y"],
  },
  {
    label: "Self-hosting option",
    cells: ["Y", "Y"],
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
    description: "API, webhook support, and automation integration.",
  },
  {
    icon: "i-heroicons-globe-alt",
    title: "Custom domains & embeds",
    description: "Use custom domains and branding controls on paid plans.",
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
    cells: ["Included API + webhooks", "Cloud tiers / self-host"],
  },
  {
    label: "Custom domains for forms",
    cells: ["Pro+", "Custom URL self-host"],
  },
  {
    label: "Flexible form uses (lead capture, workflows, apps)",
    cells: ["Y", "Survey-oriented"],
  },
  {
    label: "Native ecosystem integrations",
    cells: ["Basic included; advanced paid", "Pro cloud / self-host"],
  },
  {
    label: "Advanced analytics & dashboards",
    cells: ["Pro+", "Survey metrics only"],
  },
  {
    label: "Branding removal & customization",
    cells: ["Branding Pro+; CSS Business+", "Pro cloud / Enterprise self-host"],
  },
]

function getPrice(submissions) {
  if (submissions <= 250) return 0
  if (submissions <= 2000) return 89
  if (submissions <= 5000) return 390
  return 390
}

</script>
