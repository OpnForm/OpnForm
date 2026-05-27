<template>
  <div class="bg-white border border-neutral-200 rounded-xl shadow-sm overflow-hidden hover:shadow-md transition-shadow duration-200">
    <!-- Header -->
    <div class="flex items-center justify-between p-4 border-b border-neutral-100 bg-neutral-50/50">
      <div class="flex items-center gap-3 min-w-0">
        <div
          :class="[
            'w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 ring-1 ring-inset ring-neutral-900/5',
            fieldConfig?.bg_class || 'bg-neutral-100'
          ]"
        >
          <UIcon
            :name="fieldConfig?.icon || 'i-heroicons-question-mark-circle'"
            :class="[fieldConfig?.text_class || 'text-neutral-600', 'w-5 h-5']"
          />
        </div>

        <div class="min-w-0">
          <h3 class="font-semibold text-neutral-900 truncate text-sm sm:text-base">
            {{ field.name }}
          </h3>
          <div class="flex items-center gap-2 text-xs text-neutral-500 mt-0.5">
            <span class="font-medium text-neutral-700">{{ field.answered_count }}</span> answered
            <span class="text-neutral-300">•</span>
            <span class="font-medium text-neutral-700">{{ field.total_submissions - field.answered_count }}</span> skipped
          </div>
        </div>
      </div>

      <!-- Controls -->
      <div class="flex items-center gap-2 pl-4">
        <div 
          v-if="['distribution', 'boolean'].includes(field.summary_type)"
          class="flex bg-neutral-100 p-0.5 rounded-lg border border-neutral-200"
        >
          <button
            v-for="mode in ['bar', 'pie']"
            :key="mode"
            class="px-2 py-1 rounded-md text-xs font-medium transition-all"
            :class="[
              (mode === 'pie' ? showPieChart : !showPieChart) 
                ? 'bg-white text-neutral-900 shadow-sm' 
                : 'text-neutral-500 hover:text-neutral-700'
            ]"
            @click="showPieChart = (mode === 'pie')"
          >
            <UIcon :name="mode === 'pie' ? 'i-heroicons-chart-pie' : 'i-heroicons-chart-bar'" class="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>

    <!-- Content -->
    <div class="max-h-96 overflow-y-auto overflow-x-auto custom-scrollbar">
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

