<template>
  <div class="relative flex justify-center">
    <div class="relative grid grid-cols-2 rounded-xl bg-slate-200/90 p-1 ring-1 ring-inset ring-white/60">
      <div
        class="pointer-events-none absolute inset-y-1 left-1 z-0 w-[calc(50%-0.25rem)] rounded-lg bg-white shadow-[0_8px_18px_-12px_rgba(15,23,42,0.18),0_1px_0_rgba(255,255,255,0.9)_inset] transform-gpu transition-transform duration-300 ease-out"
        :class="modelValue ? 'translate-x-full' : 'translate-x-0'"
      />

      <button
        type="button"
        class="relative z-10 block cursor-pointer rounded-lg px-3 py-1.5 text-sm font-medium transition-colors duration-200"
        :class="!modelValue ? 'text-slate-900' : 'text-slate-500 hover:text-slate-700'"
        :aria-pressed="!modelValue"
        @click="set(false)"
      >
        Monthly
      </button>
      <button
        type="button"
        class="relative z-10 block cursor-pointer rounded-lg px-4 py-1.5 text-sm font-medium transition-colors duration-200"
        :class="modelValue ? 'text-slate-900' : 'text-slate-500 hover:text-slate-700'"
        :aria-pressed="modelValue"
        @click="set(true)"
      >
        Yearly
      </button>
    </div>

    <div class="absolute top-1/2 left-full ml-1.5 hidden -translate-y-1/2 sm:block">
      <div
        class="inline-flex items-center whitespace-nowrap rounded-[7px] bg-purple-50 px-2 py-1 pr-2.5 text-xs font-medium text-purple-600"
      >
        Save 15% with yearly billing
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  modelValue: { type: Boolean, required: true },
})
const emit = defineEmits(['update:modelValue'])

const set = (value) => {
  emit("update:modelValue", value)
}
</script>
