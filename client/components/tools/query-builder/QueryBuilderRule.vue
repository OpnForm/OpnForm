<template>
  <div class="flex items-center gap-2 flex-1">
    <!-- AND/OR Logic with consistent width -->
    <!-- First rule: "Where" text -->
    <!-- Second rule: operator selector -->
    <!-- Third+ rules: text-only operator -->
    
    <div v-if="index === 0" class="flex-shrink-0 text-sm font-medium text-neutral-600 w-17 text-right">
      {{ whereText }}
    </div>
    
    <div v-else-if="index === 1" class="flex-shrink-0 w-17 text-right">
      <USelectMenu
        v-model="currentOperator"
        :items="operatorOptions"
        value-key="value"
        class="w-full"
        size="sm"
        variant="outline"
        :search-input="false"
      />
    </div>
    
    <div v-else class="flex-shrink-0 text-sm font-medium text-neutral-600 w-17 text-right">
      {{ operatorDisplayName }}
    </div>

    <!-- Field Selector -->
    <USelectMenu
      v-model="selectedField"
      :items="availableFields"
      value-key="value"
      placeholder="Select field..."
      class="min-w-[140px] max-w-[200px]"
      size="sm"
      :ui="{
        content: 'max-w-[400px]'
      }"
      searchable
    />

    <!-- Rule Component (operator + value) - show when field is selected -->
    <div v-if="selectedField" class="flex-1 flex items-center">
      <slot
        name="rule"
        :rule-component="ruleComponent"
        :rule-data="ruleData"
        :rule-identifier="selectedField"
        :update-rule-data="updateRuleData"
      >
        <component
          v-if="ruleComponent"
          :is="ruleComponent"
          :model-value="ruleData"
          @update:model-value="updateRuleData"
        />
      </slot>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  config: {
    type: Object,
    required: true
  },
  query: {
    type: Object,
    required: true
  },
  index: {
    type: Number,
    required: true
  },
  selectedOperator: {
    type: String,
    default: ''
  },
  operatorOptions: {
    type: Array,
    default: () => []
  },
  whereText: {
    type: String,
    default: 'Where'
  }
})

const emit = defineEmits(['query-update', 'operator-update'])

const ruleDefinition = computed(() => {
  return props.config.rules.find(rule => rule.identifier === props.query.identifier)
})

const ruleComponent = computed(() => {
  return ruleDefinition.value?.component
})

const availableFields = computed(() => {
  return props.config.rules.map(rule => ({
    label: rule.name,
    value: rule.identifier
  }))
})

const selectedField = computed({
  get: () => props.query.identifier || '',
  set: (identifier) => {
    const ruleDefinition = props.config.rules.find(r => r.identifier === identifier)
    emit('query-update', {
      ...props.query,
      identifier,
      value: ruleDefinition?.initialValue || null
    })
  }
})

// Auto-set first field if no field is selected
watchEffect(() => {
  if (!props.query.identifier && props.config.rules.length > 0) {
    const firstRule = props.config.rules[0]
    emit('query-update', {
      ...props.query,
      identifier: firstRule.identifier,
      value: firstRule.initialValue || null
    })
  }
})

const ruleData = computed({
  get: () => props.query.value,
  set: (value) => {
    emit('query-update', {
      ...props.query,
      value
    })
  }
})

function updateRuleData(newData) {
  ruleData.value = newData
}

const currentOperator = computed({
  get: () => props.selectedOperator,
  set: (value) => emit('operator-update', value)
})

const operatorDisplayName = computed(() => {
  const operator = props.operatorOptions.find(op => op.value === props.selectedOperator)
  return operator?.label || 'And'
})
</script>