<template>
  <component
    :is="element"
    ref="parentRef"
    tabindex="0"
    :class="{
              'hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-sm px-2 cursor-pointer':
        !editing,
    }"
    class="relative"
    :style="{ height: editing ? divHeight + 'px' : 'auto' }"
    @click="startEditing"
  >
    <slot
      v-if="!editing"
      :content="content"
    >
      {{ content }}
    </slot>
    <div
      v-if="editing"
      class="absolute inset-0 border rounded-md border-inset transition-colors"
      :class="{ 'border-transparent': !editing, 'border-blue-500': editing }"
    >
      <input
        ref="editInputRef"
        v-model="content"
        class="absolute inset-0 focus:outline-hidden bg-white rounded-sm transition-colors px-2"
        :class="[{ 'bg-blue-50': editing }, contentClass]"
        :maxlength="maxLength"
        @blur="editing = false"
        @keyup.enter="editing = false"
        @input="handleInput"
      >
    </div>
  </component>
</template>

<script setup>
import { ref, onMounted, watch, nextTick, defineProps, defineEmits } from "vue"

const props = defineProps({
  modelValue: { type: String, required: true },
  textAlign: { type: String, default: "left" },
  contentClass: { type: String, default: "" },
  element: { type: String, default: 'div' }, // New prop for element type
  maxLength: { type: Number, default: null },
})

const emit = defineEmits(['update:modelValue'])
const content = ref(props.modelValue)
const editing = ref(false)
const divHeight = ref(0)

const parentRef = ref(null) // Ref for parent element
const editInputRef = ref(null) // Ref for edit input element

const startEditing = (event) => {
  if (editing.value || (event.type === 'keydown' && event.target !== parentRef.value)) {
    return
  }

  if (parentRef.value) {
    divHeight.value = parentRef.value.offsetHeight
    editing.value = true
    nextTick(() => {
      if (editInputRef.value) {
        editInputRef.value.focus()
      }
    })
  }
}

const handleInput = () => {
  emit("update:modelValue", content.value)
}

// Watch for changes in props.modelValue and update the local content
watch(
  () => props.modelValue,
  (newValue) => {
    content.value = newValue
  },
)

// Wait until the component is mounted to set the initial divHeight
onMounted(() => {
  if (parentRef.value) {
    divHeight.value = parentRef.value.offsetHeight
  }
})
</script>
