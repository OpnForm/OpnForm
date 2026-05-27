<template>
  <ComparisonPage
    competitor-name="Form.io"
    competitor-icon="opnform:formio"
    :hero-title="'OpnForm — The Open-Source Form.io Alternative'"
    :free-plan-comparison="freePlanComparison"
    :switch-reasons="switchReasons"
    :feature-comparison="featureComparison"
  >
    <template #hero-subtitle>
      Create beautiful, logic‑driven forms with <b>unlimited responses, full control, and zero limits</b> — all for free.
      <br/><br/>
      While Form.io is a developer‑centric form + API platform requiring backend setup and engineering effort, OpnForm delivers both <b>a user‑friendly form builder and developer workflows</b> out of the box. You get all the power of modern forms without needing custom backend infrastructure.
    </template>
  </ComparisonPage>
</template>

<script setup>
import ComparisonPage from "~/components/pages/comparisons/ComparisonPage.vue"

definePageMeta({
  layout: "default",
})

useOpnSeoMeta({
  title: "OpnForm vs Form.io",
  description:
    "A powerful Form.io alternative with unlimited responses and full control — for free.",
})

const freePlanComparison = [
  {
    label: "Unlimited submissions",
    cells: ["Unlimited", "Yes (requires self-host deployment)"],
  },
  {
    label: "Unlimited forms",
    cells: ["Unlimited", "Yes (self-host)"],
  },
  {
    label: "Unlimited users / seats",
    cells: ["Unlimited", "Depends on deployment"],
  },
  {
    label: "Conditional logic",
    cells: ["Included", "Included in builder"],
  },
  {
    label: "Multi-page forms",
    cells: ["Included", "Included"],
  },
  {
    label: "File uploads",
    cells: ["Free basic quota", "Included with file storage setup"],
  },
  {
    label: "Drag-and-drop builder",
    cells: ["Included", "Developer-oriented"],
  },
  {
    label: "No code required",
    cells: ["Yes", "Low-code / developer-oriented"],
  },
  {
    label: "Hosted experience without infra",
    cells: ["Yes", "Must self-host"],
  },
]

const switchReasons = [
  {
    icon: "i-heroicons-cloud-arrow-up",
    title: "Hosted forms with no backend required",
    description: "Start collecting data instantly.",
  },
  {
    icon: "i-ph-infinity-bold",
    title: "Unlimited responses and forms out of the box",
    description: "No usage caps on submissions or forms.",
  },
  {
    icon: "i-heroicons-computer-desktop",
    title: "Non-technical UI",
    description: "Easy for marketers, creators, and teams.",
  },
  {
    icon: "i-heroicons-code-bracket-square",
    title: "Developer-friendly when needed",
    description: "API, webhooks, and automation support.",
  },
  {
    icon: "i-heroicons-puzzle-piece",
    title: "Rich integrations without custom code",
    description: "Zapier, Make, Sheets, Slack, and more.",
  },
  {
    icon: "i-heroicons-globe-alt",
    title: "Custom domains & branding",
    description: "Manage form identity on paid plans.",
  },
]

const featureComparison = [
  {
    label: "True unlimited responses (no ceilings)",
    cells: ["Y", "Only via custom deployment"],
  },
  {
    label: "Turnkey hosted form builder",
    cells: ["Y", "N"],
  },
  {
    label: "Easy setup for non-devs",
    cells: ["Y", "N"],
  },
  {
    label: "Developer workflows (API + webhooks)",
    cells: ["Free API + webhooks", "Y"],
  },
  {
    label: "Native integrations ecosystem",
    cells: ["Basic free; advanced paid", "Must build integrations"],
  },
  {
    label: "Full control of styling without code",
    cells: ["Business+", "Code-level control"],
  },
  {
    label: "Custom domains out of the box",
    cells: ["Pro+", "Custom deployment only"],
  },
  {
    label: "Intuitive UI for builders",
    cells: ["Y", "Developer-oriented builder"],
  },
]

</script>
