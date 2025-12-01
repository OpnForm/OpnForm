<template>
  <div class="w-full max-w-4xl mx-auto">
    <div class="flex flex-wrap items-end gap-3">
      <h2 class="text-lg font-semibold flex-grow">
        Submission Summary
      </h2>

      <VForm size="sm" class="flex flex-wrap items-end gap-1">
        <SelectInput
          v-if="form.enable_partial_submissions"
          :form="filterForm"
          name="status"
          :options="statusOptions"
          class="w-32 !mb-0"
        />
        <DateInput
          :form="filterForm"
          name="date_range"
          :date-range="true"
          :disable-future-dates="true"
          class="!mb-0"
        />
      </VForm>
      <UButton
        color="neutral"
        variant="outline"
        icon="i-heroicons-arrow-path" 
        :loading="isFetching"
        @click="refetch"
      />
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="space-y-4 pt-8">
      <USkeleton v-for="i in 3" :key="i" class="h-40 w-full rounded-lg" />
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
    <div
      v-else-if="!summaryData?.fields?.length"
      class="text-center py-12 text-neutral-500"
    >
      <UIcon name="i-heroicons-document-text" class="w-12 h-12 mx-auto mb-4 opacity-50" />
      <p>No submissions yet</p>
    </div>

    <!-- Summary Content -->
    <div v-else class="space-y-4">
      <!-- Total submissions badge -->
      <div class="text-sm text-neutral-500 mb-4">
        {{ summaryData.total_submissions }} {{ summaryData.total_submissions === 1 ? 'submission' : 'submissions' }}
      </div>

      <!-- Field Cards -->
      <SummaryFieldCard
        v-for="field in summaryData.fields"
        :key="field.id"
        :field="field"
        :form="form"
        :filters="currentFilters"
      />
    </div>
  </div>
</template>

<script setup>
import SummaryFieldCard from "~/components/open/forms/components/summary/SummaryFieldCard.vue"
import { useFormSummary } from "~/composables/query/forms/useFormSummary"

const props = defineProps({
  form: { type: Object, required: true },
})

// Default to last 30 days
const toDate = new Date()
const fromDate = new Date(toDate)
fromDate.setDate(toDate.getDate() - 29)

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
</script>

