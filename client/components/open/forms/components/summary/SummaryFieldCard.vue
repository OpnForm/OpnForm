<template>
  <div class="border border-neutral-300 rounded-lg shadow-xs overflow-hidden">
    <!-- Header -->
    <div class="flex items-start gap-3 p-4 bg-neutral-50">
      <div
        :class="[
          'w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0',
          fieldConfig?.bg_class || 'bg-gray-100'
        ]"
      >
        <UIcon
          :name="fieldConfig?.icon || 'i-heroicons-question-mark-circle'"
          :class="[fieldConfig?.text_class || 'text-gray-600', 'w-4 h-4']"
        />
      </div>

      <div class="flex-1 min-w-0">
        <h3 class="font-medium text-neutral-900 truncate">
          {{ field.name }}
        </h3>
        <p class="text-sm text-neutral-500">
          {{ field.answered_count }} of {{ field.total_submissions }} answered
        </p>
      </div>

      <!-- Chart Toggle -->
      <USwitch 
        v-if="['distribution', 'boolean'].includes(field.summary_type)" 
        v-model="showPieChart" 
        label="Pie chart"
      />
    </div>

    <!-- Content -->
    <div class="max-h-80 overflow-y-auto overflow-x-auto">
      <component
        :is="summaryComponent"
        :field="field"
        :form="form"
        :filters="filters"
        :show-pie-chart="showPieChart"
      />
    </div>
  </div>
</template>

<script setup>
import blockTypes from "~/data/blocks_types.json"
import TextListSummary from "./TextListSummary.vue"
import DistributionSummary from "./DistributionSummary.vue"
import NumericStatsSummary from "./NumericStatsSummary.vue"
import RatingSummary from "./RatingSummary.vue"
import BooleanSummary from "./BooleanSummary.vue"
import DateSummary from "./DateSummary.vue"
import MatrixSummary from "./MatrixSummary.vue"
import PaymentSummary from "./PaymentSummary.vue"

const props = defineProps({
  field: { type: Object, required: true },
  form: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
})

const showPieChart = ref(false)

const fieldConfig = computed(() => blockTypes[props.field.type])

const summaryComponent = computed(() => {
  const componentMap = {
    text_list: TextListSummary,
    distribution: DistributionSummary,
    numeric_stats: NumericStatsSummary,
    rating: RatingSummary,
    boolean: BooleanSummary,
    date_summary: DateSummary,
    matrix: MatrixSummary,
    payment: PaymentSummary,
  }

  return componentMap[props.field.summary_type] || TextListSummary
})
</script>

