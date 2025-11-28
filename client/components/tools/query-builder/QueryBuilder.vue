<template>
  <QueryBuilderGroup
    :config="config"
    :query="ruleSet"
    :depth="0"
    :where-text="whereText"
    class="flex flex-col"
    @query-update="updateQuery"
  >
    <template v-for="(_, slotName) in $slots" #[slotName]="props">
      <slot :name="slotName" v-bind="props" />
    </template>
  </QueryBuilderGroup>
</template>

<script setup>
import QueryBuilderGroup from './QueryBuilderGroup.vue'

const props = defineProps({
  modelValue: {
    type: Object,
    default: null
  },
  config: {
    type: Object,
    required: true
  },
  whereText: {
    type: String,
    default: 'Where'
  }
})

const emit = defineEmits(['update:modelValue'])

const ruleSet = computed(() => {
  if (props.modelValue) {
    return props.modelValue
  }

  if (props.config.operators.length === 0) {
    return {
      operatorIdentifier: '',
      children: []
    }
  }

  return {
    operatorIdentifier: props.config.operators[0].identifier,
    children: []
  }
})

function updateQuery(newQuery) {
  emit('update:modelValue', { ...newQuery })
}
</script>