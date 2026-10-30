<template>
  <div
    v-if="content"
    class="flex items-center gap-2"
  >
    <!-- Operator Selection -->
    <USelectMenu
      v-model="content.operator"
      class="min-w-[100px] shrink-0"
      :items="operators"
      value-key="value"
      placeholder="Operator"
      size="sm"
      variant="outline"
      :search-input="false"
      @update:model-value="operatorChanged()"
      :ui="{
        content: 'min-w-fit'
      }"
    />

    <!-- Value Input (if needed) -->
    <template v-if="needsInput">
      <USelectMenu
        v-if="supportsDateReference"
        :model-value="dateValueMode"
        :items="[{ label: 'Fixed date', value: 'fixed' }, { label: 'Field or variable', value: 'reference' }]"
        value-key="value"
        aria-label="Date comparison value type"
        size="sm"
        class="w-[135px] shrink-0"
        :search-input="false"
        @update:model-value="changeDateValueMode"
      />
      <USelectMenu
        v-if="supportsDateReference && dateValueMode === 'reference'"
        :model-value="dateReferenceId"
        :items="dateReferenceOptions"
        value-key="value"
        aria-label="Date comparison field or variable"
        placeholder="Choose a field or variable"
        size="sm"
        class="flex-1 min-w-[140px]"
        :search-input="{ placeholder: 'Search fields and variables...' }"
        @update:model-value="selectDateReference"
      />
      <component
        v-else
        v-bind="inputComponentData"
        :is="inputComponentData.component"
        v-model="content.value"
        class="flex-1 min-w-[120px]"
        :name="'value_' + property.id"
        placeholder="Value"
        wrapper-class="my-0"
        margin-bottom=""
        @update:model-value="emitInput()"
      />
    </template>
  </div>
</template>

<script>
import { defineAsyncComponent } from 'vue'
import { escapeHtml } from '~/lib/utils'
import OpenFilters from "../../../../../data/open_filters.json"
import {
  getConditionInputComponent,
  getMentionComputedVariables,
  getMentionFields,
} from '~/lib/forms/conditionInputConfig.js'

export default {
  components: {
    DateInput: defineAsyncComponent(() => import('~/components/forms/heavy/DateInput.vue')),
    FileInput: defineAsyncComponent(() => import('~/components/forms/heavy/FileInput.vue')),
    MatrixInput: defineAsyncComponent(() => import('~/components/forms/heavy/MatrixInput.vue')),
    PhoneInput: defineAsyncComponent(() => import('~/components/forms/heavy/PhoneInput.vue')),
    MentionInput: defineAsyncComponent(() => import('~/components/forms/heavy/MentionInput.vue')),
  },
  props: {
    modelValue: { type: Object, required: false, default: null },
    customValidation: { type: Boolean, default: false },
  },

  emits: ['update:modelValue'],
  data() {
    return {
      content: this.modelValue ? { ...this.modelValue } : {},
      available_filters: OpenFilters,
      hasInput: false,
      dateValueMode: typeof this.modelValue?.value === 'string' && this.modelValue.value.includes('mention-field-id') ? 'reference' : 'fixed',
    }
  },

  computed: {
    formProperties() {
      return []
    },
    formComputedVariables() {
      return []
    },
    mentionFields() {
      return getMentionFields(this.formProperties, this.property)
    },
    mentionComputedVariables() {
      return getMentionComputedVariables(this.formComputedVariables, this.property)
    },
    supportsDateReference() {
      return this.property.type === 'date' && !this.property.date_range &&
        this.selectedOperator()?.format?.type === 'date'
    },
    dateReferenceId() {
      return typeof this.content.value === 'string'
        ? this.content.value.match(/mention-field-id="([^"]+)"/)?.[1]
        : undefined
    },
    dateReferenceOptions() {
      return [
        ...this.mentionFields
          .filter(field => field.type === 'date' && !field.date_range && !!field.with_time === !!this.property.with_time)
          .map(field => ({ label: field.name, value: field.id, icon: 'i-heroicons-calendar-days' })),
        ...this.mentionComputedVariables
          .filter(variable => !['number', 'boolean'].includes(variable.result_type))
          .map(variable => ({ label: variable.name, value: variable.id, icon: 'i-heroicons-variable' })),
      ]
    },
    inputComponentData() {
      const componentData = {
        component: getConditionInputComponent(this.property),
        name: this.property.id,
        required: true,
        size: 'xs',
        borderRadius: 'small',
        wrapperClass: 'm-0',
      }

      if (
        this.property.type === "phone_number" &&
        !this.property.use_simple_text_input
      ) {
        componentData.component = "PhoneInput"
      }

      if (["select", "multi_select"].includes(this.property.type)) {
        componentData.multiple = false
        componentData.options = this.property[this.property.type].options.map(
          (option) => {
            return {
              name: option.name,
              value: option.name,
            }
          },
        )
      } else if (this.property.type === "date") {
        // For date range operators, use number input instead of date input
        if (["at_least_x_days_ago", "at_least_x_days_from_now", "within_past_x_days", "within_next_x_days"].includes(this.content.operator)) {
          componentData.component = "TextInput"
        }
        if (componentData.component === 'DateInput') {
          componentData.withTime = this.property.with_time === true
          componentData.ui = {
            slots: {
              input: 'h-7 rounded-md',
              inner: 'py-1 text-sm/4',
            },
          }
        }
      } else if (this.property.type === "checkbox") {
        componentData.label = this.property.name
      }
      else if (this.property.type === "matrix"){
        componentData.rows = this.property.rows
        componentData.columns = this.property.columns
      }

      // Regex patterns are plain text, regardless of the field being checked.
      // In particular, PhoneInput would format/reject the regex as a phone number.
      if (this.selectedOperator()?.format?.type === 'regex') {
        componentData.component = "TextInput"
      }

      // Pass mention props for MentionInput
      if (componentData.component === 'MentionInput') {
        componentData.mentions = this.mentionFields
        componentData.computedVariables = this.mentionComputedVariables
      }

      return componentData
    },
    operators() {
      return Object.entries(this.available_filters[this.property.type].comparators)
        .filter(([_, value]) => this.customValidation || (!this.customValidation && !value.custom_validation_only))
        .map(([filterKey]) => {
          return {
            value: filterKey,
            label: this.optionFilterNames(filterKey),
          }
        })
    },
    needsInput() {
      const operator = this.selectedOperator()
      if (!operator) {
        return false
      }
      
      // If operator has no format and no expected_type, it means it doesn't need input
      if (!operator.format && !operator.expected_type) {
        return false
      }

      if (
        operator.expected_type === "boolean" &&
        operator.format?.type === "enum" &&
        operator.format.values.length === 1
      ) {
        return false
      } else if (
        operator.expected_type === "object" &&
        operator.format?.type === "empty" &&
        operator.format.values === "{}"
      ) {
        return false
      }

      return true
    },
  },

  watch: {
    modelValue() {
      this.refreshContent()
    },
    "content.operator": function (val) {
      if (val) {
        this.operatorChanged()
      }
    },
  },

  mounted() {
    this.refreshContent()
  },

  methods: {
    changeDateValueMode(mode) {
      this.dateValueMode = mode
      this.content.value = null
      this.emitInput()
    },
    selectDateReference(id) {
      const reference = this.dateReferenceOptions.find(option => option.value === id)
      if (!reference) return
      this.content.value = `<span mention="true" mention-field-id="${escapeHtml(id)}" mention-field-name="${escapeHtml(reference.label)}" mention-fallback="">${escapeHtml(reference.label)}</span>`
      this.emitInput()
    },
    castContent(content) {
      if (typeof content.value === 'string' && content.value.includes('mention-field-id')) {
        return content
      }
      const operator = this.selectedOperator()
      if (
        (["number", "rating", "scale", "slider"].includes(this.property.type) ||
        (this.property.type === 'computed' &&
          ['number', 'numeric', 'integer', 'float'].includes(this.property.result_type)) ||
        operator?.expected_type === "number") &&
        content.value
      ) {
        content.value = Number(content.value)
      }

      if (operator.expected_type === "boolean") {
        content.value = Boolean(content.value)
      }

      return content
    },
    operatorChanged() {
      if (!this.content.operator) {
        return
      }

      const operator = this.selectedOperator()
      const operatorFormat = operator.format

      // Relative date operators expect a number of days, never a date reference.
      if (this.property.type === 'date' && !this.supportsDateReference && this.dateReferenceId) {
        this.content.value = null
        this.dateValueMode = 'fixed'
      }

      if (
        operator.expected_type === "boolean" &&
        operatorFormat.type === "enum" &&
        operatorFormat.values.length === 1
      ) {
        this.content.value = operator.format.values[0]
      } else if (
        operator.expected_type === "object" &&
        operatorFormat.type === "empty" &&
        operatorFormat.values === "{}"
      ) {
        this.content.value = {}
      } else if (
        this.property.type !== 'matrix' && 
        (typeof this.content.value === 'boolean' || 
        typeof this.content.value === 'object')
      ) {
        this.content.value = null
      }
      this.emitInput()
    },
    selectedOperator() {
      if (!this.content.operator) {
        return null
      }
      return this.available_filters[this.property.type].comparators[
        this.content.operator
      ]
    },
    optionFilterNames(key) {
      return key
        .split("_")
        .map(function (item) {
          return item.charAt(0).toUpperCase() + item.substring(1)
        })
        .join(" ")
    },
    emitInput() {
      this.$emit("update:modelValue", this.castContent(this.content))
    },
    refreshContent() {
      const modelValue = this.modelValue ? { ...this.modelValue } : {}
      
      // Migrate legacy checkbox operators
      if (this.property.type === 'checkbox') {
        if (modelValue?.operator === 'equals') {
          modelValue.operator = 'is_checked'
        } else if (modelValue?.operator === 'does_not_equal') {
          modelValue.operator = 'is_not_checked'
        }
      }

      // Keep an empty reference selector open while editing; infer mode for saved values.
      if (modelValue.value) {
        this.dateValueMode = typeof modelValue.value === 'string' && modelValue.value.includes('mention-field-id') ? 'reference' : 'fixed'
      }
      this.content = {
        operator: this.operators[0]?.value,
        ...modelValue,
        property_meta: {
          id: this.property.id,
          type: this.property.type,
          ...(this.property.type === 'date' ? { with_time: this.property.with_time === true } : {}),
        },
      }
    },
  },
}
</script>
