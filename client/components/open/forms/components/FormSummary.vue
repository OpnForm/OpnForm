<template>
  <div class="max-w-5xl mx-auto space-y-6">
    <PageSection
      title="Summary"
      description="Overview of your form submissions and statistics"
    >
      <template #actions>
        <div class="flex items-center gap-2">
          <UButton
            color="neutral"
            variant="outline"
            icon="i-heroicons-arrow-path"
            :loading="isFetching"
            @click="refetch"
          />
          <DateInput
            :form="filterForm"
            size="sm"
            name="date_range"
            wrapper-class="mb-0"
            :date-range="true"
            :disable-future-dates="true"
            class="!mb-0 w-full sm:w-auto"
          />
        </div>
      </template>

      <div class="space-y-6">
        <!-- Stats Overview -->
        <div v-if="summaryData" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <DashboardPanel padding="sm">
            <div class="text-sm font-medium text-neutral-500 mb-1">
              Total Submissions
            </div>
            <div class="text-3xl font-bold text-neutral-900">
              {{ summaryData.total_submissions }}
            </div>
          </DashboardPanel>
          <DashboardPanel padding="sm" class="opacity-50">
            <div class="text-sm font-medium text-neutral-500 mb-1">
              Completion Rate
            </div>
            <div class="text-3xl font-bold text-neutral-900">
              -
            </div>
          </DashboardPanel>
          <DashboardPanel padding="sm" class="opacity-50">
            <div class="text-sm font-medium text-neutral-500 mb-1">
              Avg. Completion Time
            </div>
            <div class="text-3xl font-bold text-neutral-900">
              -
            </div>
          </DashboardPanel>
        </div>

    <!-- Filters Bar -->
    <div class="flex items-center justify-between py-3 border-b border-neutral-200">
      <div class="flex items-center gap-3">
         <SelectInput
          v-if="form.enable_partial_submissions"
          :form="filterForm"
          name="status"
          :options="statusOptions"
          class="w-40 !mb-0"
        />
      </div>
      <div class="text-sm text-neutral-500">
        <template v-if="summaryData?.is_limited">
          Showing stats for <span class="font-medium text-neutral-900">{{ summaryData?.processed_submissions?.toLocaleString() }}</span> of {{ summaryData?.total_submissions?.toLocaleString() }} submissions
        </template>
        <template v-else>
          Showing stats for <span class="font-medium text-neutral-900">{{ summaryData?.total_submissions?.toLocaleString() || 0 }}</span> submissions
        </template>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="space-y-6">
      <DashboardPanel padding="sm">
        <USkeleton class="h-32 w-full" />
      </DashboardPanel>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <DashboardPanel
          v-for="i in 4"
          :key="i"
          padding="sm"
        >
          <USkeleton class="h-64 w-full" />
        </DashboardPanel>
      </div>
    </div>

    <!-- Error State -->
    <UAlert
      v-else-if="isError"
      color="error"
      variant="subtle"
      icon="i-heroicons-exclamation-triangle"
      title="Failed to load summary"
      :description="error?.message || 'Please try again later.'"
    />

    <!-- Empty State -->
    <DashboardEmptyState
      v-else-if="!summaryData?.fields?.length"
      icon="i-heroicons-document-chart-bar"
      title="No data available"
      description="There are no submissions to display for the selected period."
    />

    <!-- Summary Content -->
    <div v-else class="space-y-6">
      <!-- Limitation Notice -->
      <UAlert
        v-if="summaryData?.is_limited"
        color="info"
        variant="subtle"
        icon="i-heroicons-information-circle"
      >
        <template #title>
          Summary based on {{ summaryData.processed_submissions.toLocaleString() }} most recent submissions
        </template>
        <template #description>
          {{ limitationDescription }}
        </template>
      </UAlert>

      <!-- Field Cards -->
      <div class="grid grid-cols-1 gap-6">
        <SummaryFieldCard
          v-for="field in summaryData.fields"
          :key="field.id"
          :field="field"
          :form="form"
          :filters="currentFilters"
        />
      </div>
    </div>
      </div>
    </PageSection>
  </div>
</template>

<script setup>
import DashboardEmptyState from "~/components/dashboard/states/DashboardEmptyState.vue"
import DashboardPanel from "~/components/dashboard/DashboardPanel.vue"
import PageSection from "~/components/dashboard/PageSection.vue"
import SummaryFieldCard from "~/components/open/forms/components/summary/SummaryFieldCard.vue"
import { useFormSummary } from "~/composables/query/forms/useFormSummary"

const props = defineProps({
  form: { type: Object, required: true },
})

// Default to last 6 months
const toDate = new Date()
const fromDate = new Date(toDate)
fromDate.setMonth(toDate.getMonth() - 6)

const filterForm = useForm({
  status: 'completed',
  date_range: [fromDate.toISOString().split('T')[0], toDate.toISOString().split('T')[0]],
})

const statusOptions = [
  { value: 'all', name: 'All' },
  { value: 'completed', name: 'Completed' },
  { value: 'partial', name: 'Partial' },
]

const dateFrom = computed(() => filterForm.date_range?.[0] || null)
const dateTo = computed(() => filterForm.date_range?.[1] || null)
const status = computed(() => filterForm.status || 'completed')

const currentFilters = computed(() => ({
  status: status.value,
  date_from: dateFrom.value,
  date_to: dateTo.value,
}))

const { summary } = useFormSummary()

const { data: summaryData, isLoading, isFetching, isError, error, refetch } = summary(
  computed(() => props.form?.workspace_id),
  computed(() => props.form?.id),
  {
    dateFrom,
    dateTo,
    status,
    queryOptions: {
      enabled: computed(() => import.meta.client && !!props.form),
    }
  }
)

const limitationDescription = computed(() => {
  const total = summaryData.value?.total_submissions?.toLocaleString()
  const hasDateFilter = dateFrom.value || dateTo.value
  
  if (hasDateFilter) {
    return `${total} submissions match your selected period. For performance, the summary is calculated from the most recent entries.`
  }
  return `Your form has ${total} total submissions. For performance, the summary is calculated from the most recent entries.`
})
</script>

