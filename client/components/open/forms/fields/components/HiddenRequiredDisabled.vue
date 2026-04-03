<template>
  <OptionSelectorInput
    :options="availableOptions"
    v-model="selectedOption"
    :multiple="false"
    :disabled="false"
    :clearable="true"
    :columns="3"
    name="field_state"
  />
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  field: {
    type: Object,
    required: true
  },
  canBeDisabled: {
    type: Boolean,
    default: true
  },
  canBeRequired: {
    type: Boolean,
    default: true
  },
  canBeHidden: {
    type: Boolean,
    default: true
  }
})
const emit = defineEmits(['update:field'])

const options = [
  {
    name: 'required',
    label: 'Required',
    icon: 'ph:asterisk-bold',
    selectedIcon: 'ph:asterisk-bold',
    iconClass: (isActive) => isActive ? 'text-red-500' : '',
  },
  {
    name: 'hidden',
    label: 'Hidden',
    icon: 'heroicons:eye',
    selectedIcon: 'heroicons:eye-slash-solid',
  },
  {
    name: 'disabled',
    label: 'Disabled',
    icon: 'heroicons:lock-open',
    selectedIcon: 'heroicons:lock-closed-solid',
  }
]

const availableOptions = computed(() => {
  return options.filter(option => {
    if (option.name === 'disabled') return props.canBeDisabled
    if (option.name === 'required') return props.canBeRequired
    if (option.name === 'hidden') return props.canBeHidden
    return true
  })
})

const selectedOption = computed({
  get() {
    // Only one can be true at a time, priority: required > hidden > disabled
    if (props.field.required) return 'required'
    if (props.field.hidden) return 'hidden'
    if (props.field.disabled) return 'disabled'
    return null
  },
  set(optionName) {
    // Reset all
    props.field.required = false
    props.field.hidden = false
    props.field.disabled = false
    // Apply business logic
    if (optionName === 'required') {
      props.field.required = true
      props.field.hidden = false
    } else if (optionName === 'hidden') {
      props.field.hidden = true
      props.field.required = false
      props.field.disabled = false
      props.field.generates_uuid = false
      props.field.generates_auto_increment_id = false
    } else if (optionName === 'disabled') {
      props.field.disabled = true
      props.field.hidden = false
    }
    emit('update:field', { ...props.field })
  }
})
</script>