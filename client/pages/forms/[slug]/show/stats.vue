<template>
  <div class="p-4">
    <div class="w-full max-w-4xl mx-auto grid grid-cols-2 md:grid-cols-4 gap-2">
      <div
        v-for="(stat, index) in [
          { label: 'Views', value: totalViews, placeholder: '123' },
          { label: 'Submissions', value: totalSubmissions, placeholder: '123' },
          { label: 'Completion', value: completionRate + '%', placeholder: '100%' },
          { label: 'Avg. Duration', value: averageDuration, placeholder: '10 seconds' }
        ]"
        :key="index"
        class="border border-neutral-300 rounded-lg shadow-xs p-4"
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
      </div>
    </div>

    <FormStats 
      class="w-full max-w-4xl mx-auto" 
      :form="form" 
    />
    
    <FormTrafficBreakdown
      class="w-full max-w-4xl mx-auto mt-8" 
      :form="form" 
      :meta-data="statsData?.meta_stats ?? {}" 
      :is-loading="isLoading"
    />
  </div>
</template>

<script setup>
import FormStats from "~/components/open/forms/components/FormStats.vue"
import FormTrafficBreakdown from "~/components/open/forms/components/FormTrafficBreakdown.vue"

const props = defineProps({
  form: { type: Object, required: true },
})

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
