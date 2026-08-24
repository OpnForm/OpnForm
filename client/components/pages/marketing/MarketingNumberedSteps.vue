<template>
  <section
    :id="id || undefined"
    class="scroll-mt-20 py-16 sm:py-20 lg:py-24"
    :class="dark ? 'bg-neutral-950 text-white' : 'bg-[#f7f9fc] text-neutral-950'"
  >
    <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-12">
      <div class="max-w-3xl">
        <p
          class="text-sm font-semibold uppercase tracking-[0.14em]"
          :class="dark ? 'text-blue-300' : 'text-blue-600'"
        >
          {{ eyebrow }}
        </p>
        <h2
          class="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl lg:text-5xl"
          :class="dark ? '!text-white' : 'text-neutral-950'"
        >
          {{ title }}
        </h2>
        <p
          v-if="description"
          class="mt-5 max-w-2xl text-lg leading-8"
          :class="dark ? 'text-neutral-300' : 'text-neutral-600'"
        >
          {{ description }}
        </p>
      </div>

      <ol
        class="mt-12 grid gap-px overflow-hidden rounded-3xl"
        :class="[dark ? 'bg-white/15' : 'bg-neutral-200', gridClass]"
      >
        <li
          v-for="(step, index) in steps"
          :key="step.title"
          class="relative p-7 sm:p-8"
          :class="dark ? 'bg-neutral-950' : 'bg-white'"
        >
          <div class="flex items-center gap-3" aria-hidden="true">
            <span
              class="text-6xl font-semibold tracking-tighter"
              :class="dark ? 'text-white/25' : 'text-neutral-200'"
            >
              {{ String(index + 1).padStart(2, "0") }}
            </span>
            <UIcon
              :name="step.icon"
              class="h-7 w-7 shrink-0"
              :class="dark ? 'text-blue-300' : 'text-blue-600'"
            />
          </div>
          <h3 class="mt-5 text-xl font-semibold" :class="dark ? 'text-white' : 'text-neutral-950'">
            {{ step.title }}
          </h3>
          <p class="mt-3 leading-7" :class="dark ? 'text-neutral-300' : 'text-neutral-600'">
            {{ step.description }}
          </p>
        </li>
      </ol>
    </div>
  </section>
</template>

<script setup>
const props = defineProps({
  id: {
    type: String,
    default: "",
  },
  eyebrow: {
    type: String,
    required: true,
  },
  title: {
    type: String,
    required: true,
  },
  description: {
    type: String,
    default: "",
  },
  steps: {
    type: Array,
    required: true,
  },
  dark: {
    type: Boolean,
    default: true,
  },
})

const gridClass = computed(() => {
  if (props.steps.length === 4) return "md:grid-cols-2 xl:grid-cols-4"
  if (props.steps.length === 5) return "md:grid-cols-2 xl:grid-cols-5"

  return "lg:grid-cols-3"
})
</script>
