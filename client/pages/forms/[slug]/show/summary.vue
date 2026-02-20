<template>
  <div class="p-4 sm:p-6 lg:p-8">
    <FormSummary v-if="canAccessSummary" :form="form" />

    <div v-else class="border border-neutral-300 rounded-lg shadow-xs p-4 relative overflow-hidden max-w-5xl mx-auto space-y-6">
      <div class="absolute inset-0 z-10">
        <div class="p-5 max-w-md mx-auto flex flex-col items-center justify-center h-full">
          <p class="text-center">
            You need a <PlanTag
              upgrade-modal-title="Upgrade today to access form summaries"
              class="mx-1"
            /> subscription to access form summaries. 
          </p>
          <UButton
            class="mt-5 flex justify-center"
            @click.prevent="openUpgradeModal()"
            label="Subscribe"
          />
        </div>
      </div>
      <img
        src="/img/pages/forms/blurred_summary.png"
        alt="Sample Graph"
        class="mx-auto w-full filter blur-md z-0 pointer-events-none"
      >
    </div>
  </div>
</template>

<script setup>
import PlanTag from "~/components/app/PlanTag.vue"
import FormSummary from "~/components/open/forms/components/FormSummary.vue"

const props = defineProps({
  form: { type: Object, required: true },
})

definePageMeta({
  middleware: "auth",
})

useOpnSeoMeta({
  title: props.form ? "Form Summary - " + props.form.title : "Form Summary",
})

const { current: workspace } = useCurrentWorkspace()
const { openSubscriptionModal } = useAppModals()

const canAccessSummary = computed(() => workspace.value?.is_pro)

const openUpgradeModal = () => {
  openSubscriptionModal({
    plan: 'pro',
    modal_title: 'Upgrade to Pro for Form Summaries',
    modal_description: 'Get visual breakdowns, statistics, and insights for all your form submissions with the Pro plan.',
  })
}
</script>
