<template>
  <div>
    <div class="flex flex-wrap items-end mt-5 gap-2">
      <h3 class="flex-grow font-medium text-lg">
        Views & Submission History
      </h3>
      <VForm size="sm">
      <DateInput
        :form="filterForm"
        name="filter_date"
        class="flex-1 !mb-0"
        :date-range="true"
        :disable-future-dates="true"
        :disabled="!form.is_pro"
      />
      </VForm>
      <UButton 
        class="self-stretch mt-1"
        color="neutral"
        variant="outline"
        :disabled="!form.is_pro"
        @click.prevent="refresh" 
        icon="i-heroicons-arrow-path" 
        :loading="isLoading"
      />
    </div>
    <div
      class="border border-neutral-300 rounded-lg shadow-xs p-4 mb-5 w-full mx-auto mt-2 select-all"
    >
      <div
        v-if="!form.is_pro"
        class="relative"
      >
        <div class="absolute inset-0 z-10">
          <div class="p-5 max-w-md mx-auto flex flex-col items-center justify-center h-full">
            <p class="text-center">
              You need a <pro-tag
                upgrade-modal-title="Upgrade today to access form analytics"
                class="mx-1"
              /> subscription to access your form
              analytics.
            </p>
            <UButton
              class="mt-5 flex justify-center"
              @click.prevent="openSubscriptionModal({modal_title: 'Upgrade to unlock form Analytics'})"
              label="Subscribe"
            />
          </div>
        </div>
        <img
          src="/img/pages/forms/blurred_graph.png"
          alt="Sample Graph"
          class="mx-auto w-full filter blur-md z-0 pointer-events-none"
        >
      </div>
      <VTransition v-else name="fade">
        <div
          v-if="isLoading"
          class="space-y-3"
        >
          <USkeleton class="h-4 w-full" />
          <USkeleton class="h-4 w-3/4" />
          <USkeleton class="h-4 w-1/2" />
          <USkeleton class="h-32 w-full" />
        </div>
        <LineChart
          v-else
          :options="chartOptions"
          :data="chartData"
        />
      </VTransition>
    </div>
  </div>
</template>

<script setup>
import ProTag from "~/components/app/ProTag.vue"
import { Line as LineChart } from "vue-chartjs"
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  Legend,
  LineElement,
  LinearScale,
  CategoryScale,
  PointElement,
} from "chart.js"

ChartJS.register(
  Title,
  Tooltip,
  Legend,
  LineElement,
  LinearScale,
  CategoryScale,
  PointElement,
)

const props = defineProps({
  form: {
    type: Object,
    required: true,
  },
})

const { openSubscriptionModal } = useAppModals()

const toDate = new Date()
const fromDate = new Date(toDate)
fromDate.setDate(toDate.getDate() - 29)
const filterForm = useForm({
  filter_date: [fromDate.toISOString().split('T')[0], toDate.toISOString().split('T')[0]],
})

// Use query composables instead of manual API calls
const { stats, invalidateStats } = useFormStats()

// Set up default date range (last 30 days)
onMounted(() => {
  const toDate = new Date()
  const fromDate = new Date(toDate)
  fromDate.setDate(toDate.getDate() - 29)
  filterForm.filter_date = [fromDate.toISOString().split('T')[0], toDate.toISOString().split('T')[0]]
})

const fromDateComputed = computed(() => {
  return filterForm.filter_date?.[0] ? filterForm.filter_date[0].split('T')[0] : null
})
const toDateComputed = computed(() => {
  return filterForm.filter_date?.[1] ? filterForm.filter_date[1].split('T')[0] : null
})

// Get stats data using query composable
const { data: statsData, isFetching: isQueryLoading } = stats(
  props.form.workspace_id,
  props.form.id,
  fromDateComputed,
  toDateComputed,
  {enabled: computed(() => import.meta.client && props.form && props.form.is_pro)}
)

// Handle loading state for SSR - show skeleton during SSR if query would run on client
const isLoading = computed(() => {
  if (import.meta.server) {
    return !!props.form && props.form.is_pro
  }
  return isQueryLoading.value
})

// Refresh function to invalidate both stats and stats-details queries with current parameters
const refresh = () => {
  invalidateStats(props.form.id)
}

// Chart configuration
const chartOptions = {
  scales: {
    y: {
      beginAtZero: true,
      ticks: {
        precision: 0,
      },
    },
  },
  responsive: true,
  maintainAspectRatio: true,
}

// Chart data computed from query results
const chartData = computed(() => {
  const baseDatasets = [
    {
      label: "Form Views",
      backgroundColor: "rgba(59, 130, 246, 1)",
      borderColor: "rgba(59, 130, 246, 1)",
      data: statsData.value?.views || [],
    },
    {
      label: "Form Submissions",
      backgroundColor: "rgba(16, 185, 129, 1)",
      borderColor: "rgba(16, 185, 129, 1)",
      data: statsData.value?.submissions || [],
    },
  ]

  // Add partial submissions dataset if enabled
  if (props.form.enable_partial_submissions) {
    baseDatasets.push({
      label: "Partial Submissions",
      backgroundColor: "rgba(255, 193, 7, 1)",
      borderColor: "rgba(255, 193, 7, 1)",
      data: statsData.value?.partial_submissions || [],
    })
  }

  return {
    labels: Object.keys(statsData.value?.views || {}),
    datasets: baseDatasets,
  }
})
</script>
