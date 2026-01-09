<template>
  <div
    v-if="content"
    class="flex items-center gap-2"
  >
    <!-- Operator Selection -->
    <USelectMenu
      v-model="content.operator"
      class="w-[100px]"
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
      <component
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
import OpenFilters from "../../../../../data/open_filters.json"

export default {
  components: {
    DateInput: defineAsyncComponent(() => import('~/components/forms/heavy/DateInput.vue')),
    FileInput: defineAsyncComponent(() => import('~/components/forms/heavy/FileInput.vue')),
    MatrixInput: defineAsyncComponent(() => import('~/components/forms/heavy/MatrixInput.vue')),
    PhoneInput: defineAsyncComponent(() => import('~/components/forms/heavy/PhoneInput.vue'))
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
      inputComponent: {
        text: "TextInput",
        rich_text: "TextInput",
        number: "TextInput",
        rating: "TextInput",
        scale: "TextInput",
        slider: "TextInput",
        select: "SelectInput",
        multi_select: "SelectInput",
        date: "DateInput",
        files: "FileInput",
        checkbox: "CheckboxInput",
        url: "TextInput",
        email: "TextInput",
        phone_number: "TextInput",
        matrix: "MatrixInput",
      }
    }
  },

  computed: {
    // Return type of input, and props for that input
    inputComponentData() {
      const componentData = {
        component: this.inputComponent[this.property.type],
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
        // componentData.withTime = true
      } else if (this.property.type === "checkbox") {
        componentData.label = this.property.name
      }
      else if (this.property.type === "matrix"){
        componentData.rows = this.property.rows
        componentData.columns = this.property.columns
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
    castContent(content) {
      if (
        ["number", "rating", "scale", "slider"].includes(this.property.type) &&
        content.value
      ) {
        content.value = Number(content.value)
      }

      const operator = this.selectedOperator()
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

      this.content = {
        operator: this.operators[0]?.value,
        ...modelValue,
        property_meta: {
          id: this.property.id,
          type: this.property.type,
        },
      }
    },
  },
}
</script>
