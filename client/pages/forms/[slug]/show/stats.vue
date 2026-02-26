<template>
  <PageContainer spacing="lg">
    <PageSection
      title="Analytics"
      description="View form performance and submission statistics."
    >
      <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
        <DashboardPanel
          v-for="(stat, index) in statItems"
          :key="index"
          padding="sm"
        >
        <div class="mb-2 text-xs text-neutral-500">
          {{ stat.label }}
        </div>
        <VTransition name="fade">
          <USkeleton
            v-if="isLoading"
            class="h-7 w-16"
          />
          <span
            v-else-if="form.is_pro"
            class="font-medium text-xl"
          >
            {{ stat.value }}
          </span>
          <span
            v-else
            class="blur-[3px] pointer-events-none"
          >
            {{ stat.placeholder }}
          </span>
        </VTransition>
        </DashboardPanel>
      </div>

      <FormStats :form="form" />

      <FormTrafficBreakdown
        :form="form"
        :meta-data="statsData?.meta_stats ?? {}"
        :is-loading="isLoading"
      />
    </PageSection>
  </PageContainer>
</template>

<script setup>
import PageContainer from "~/components/dashboard/PageContainer.vue"
import PageSection from "~/components/dashboard/PageSection.vue"
import DashboardPanel from "~/components/dashboard/DashboardPanel.vue"
import FormStats from "~/components/open/forms/components/FormStats.vue"
import FormTrafficBreakdown from "~/components/open/forms/components/FormTrafficBreakdown.vue"

const props = defineProps({
  form: { type: Object, required: true },
})

const statItems = computed(() => [
  { label: 'Views', value: totalViews.value, placeholder: '123' },
  { label: 'Submissions', value: totalSubmissions.value, placeholder: '123' },
  { label: 'Completion', value: completionRate.value + '%', placeholder: '100%' },
  { label: 'Avg. Duration', value: averageDuration.value, placeholder: '10 seconds' },
])

definePageMeta({
  middleware: "auth",
})
useOpnSeoMeta({
  title: props.form ? "Form Analytics - " + props.form.title : "Form Analytics",
})

// Use query composables instead of manual API calls
const { statsDetails } = useFormStats()

// Get stats data using query composable
const { data: statsData, isFetching: isQueryLoading } = statsDetails(
  props.form.workspace_id, 
  props.form.id,
  {
    enabled: computed(() => import.meta.client && !!props.form && props.form.is_pro)
  }
)

const isLoading = computed(() => {
  if (import.meta.server) {
    return !!props.form && props.form.is_pro
  }
  return isQueryLoading.value
})

// Computed values derived from query data
const totalViews = computed(() => statsData.value?.views ?? 0)
const totalSubmissions = computed(() => statsData.value?.submissions ?? 0)
const completionRate = computed(() => Math.min(100, statsData.value?.completion_rate ?? 0))
const averageDuration = computed(() => statsData.value?.average_duration ?? '-')
</script>
