<template>
  <div class="bg-slate-100 rounded-xl flex p-1 relative">
    <button class="font-medium block flex-grow cursor-pointer">
      <div
        class="py-1.5 px-3 rounded-lg transition-colors transition-colors text-slate-500"
        :class="{ 'bg-white shadow-sm text-slate-900': !modelValue }"
        @click="set(false)"
      >
        Monthly
      </div>
    </button>
    <button
      class="font-medium block flex-grow cursor-pointer"
      @click="set(true)"
    >
      <div
        class="py-1.5 px-4 rounded-lg transition-colors text-slate-500"
        :class="{ 'bg-white shadow-sm text-slate-900': modelValue }"
      >
        Yearly
      </div>
    </button>
    <div
      class="absolute hidden sm:block -right-4 -top-3 translate-x-full translate-y-full"
    >
      <div
        class="justify-center px-2 py-1 text-xs font-semibold tracking-wide text-center text-emerald-600 uppercase bg-emerald-50 rounded-md"
      >
        Save 20%
      </div>
    </div>
  </div>
</template>

<script setup>
import { defineEmits, defineProps } from "vue"

defineProps({
  modelValue: { type: Boolean, required: true },
})
const emit = defineEmits(['update:modelValue'])

const set = (value) => {
  emit("update:modelValue", value)
}
</script>
