<template>
  <div>
    <div class="w-full relative flex items-center">
      <div
        class="cursor-pointer"
        @click="trigger"
      >
        <slot name="title" />
      </div>
      <div
        class="text-neutral-400 hover:text-neutral-600 absolute right-4 cursor-pointer p-2"
        @click="trigger"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          class="h-5 w-5 transition transform duration-500"
          :class="{ 'rotate-180': showContent }"
          viewBox="0 0 20 20"
          fill="currentColor"
        >
          <path
            fill-rule="evenodd"
            d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v3.586L7.707 9.293a1 1 0 00-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 10.586V7z"
            clip-rule="evenodd"
          />
        </svg>
      </div>
    </div>
    <VTransition>
      <div
        v-if="showContent"
        class="w-full"
      >
        <slot />
      </div>
    </VTransition>
  </div>
</template>

<script setup>
import VTransition from "../global/transitions/VTransition.vue"
import { ref, defineProps, defineEmits } from "vue"

const props = defineProps({
  modelValue: { type: Boolean, default: null },
})

const showContent = ref(props.modelValue)
const emit = defineEmits(['update:modelValue'])

const trigger = () => {
  showContent.value = !showContent.value
  emit("update:modelValue", showContent.value)
}
</script>
