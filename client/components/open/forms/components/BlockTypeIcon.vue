<template>
  <div
    class="p-1 rounded-md flex items-center justify-center"
    :class="[bgClass, textClass]"
  >
    <Icon
      :name="icon"
      class="h-4 w-4"
    />
  </div>
</template>

<script setup>
import blocksTypes from '~/data/blocks_types.json'

const extraBlocksTypes = {
  status: {
    icon: 'i-heroicons-clipboard-document-check-20-solid',
    bg_class: 'bg-gray-100',
    text_class: 'text-gray-900',
  },
  ip_address: {
    icon: 'i-heroicons-globe-alt',
    bg_class: 'bg-gray-100',
    text_class: 'text-gray-900',
  },
}

const props = defineProps({
  type: {
    type: String,
    required: true
  },
  bgClass: {
    type: String,
    default: ''
  },
  textClass: {
    type: String,
    default: ''
  }
})

const blockType = computed(() => blocksTypes[props.type] || extraBlocksTypes[props.type])
const bgClass = computed(() => props.bgClass || blockType.value?.bg_class || '')
const textClass = computed(() => props.textClass || blockType.value?.text_class || '')
const icon = computed(() => blockType.value?.icon || '')
</script>