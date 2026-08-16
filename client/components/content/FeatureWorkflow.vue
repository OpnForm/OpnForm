<template>
  <section class="my-10 rounded-[28px] border border-neutral-200 bg-white p-6 sm:p-8">
    <div class="max-w-2xl">
      <p class="text-sm font-semibold uppercase tracking-[0.16em] text-blue-600">
        Workflow
      </p>
      <h2 class="mt-3 text-2xl font-semibold tracking-[-0.8%] text-neutral-950">
        {{ title }}
      </h2>
      <div
        v-if="$slots.default"
        class="mt-3 text-base leading-7 text-neutral-600"
      >
        <slot />
      </div>
    </div>

    <ol class="mt-7 space-y-4">
      <li
        v-for="(step, index) in steps"
        :key="step.title"
        class="grid gap-4 rounded-2xl bg-neutral-50 p-5 sm:grid-cols-[auto_minmax(0,1fr)]"
      >
        <div class="flex h-9 w-9 items-center justify-center rounded-full bg-neutral-950 text-sm font-semibold text-white">
          {{ index + 1 }}
        </div>
        <div>
          <h3 class="text-base font-semibold text-neutral-950">
            {{ step.title }}
          </h3>
          <p class="mt-1 text-sm leading-6 text-neutral-600">
            {{ step.description }}
          </p>
        </div>
      </li>
    </ol>
  </section>
</template>

<script setup>
defineProps({
  title: {
    type: String,
    required: true,
  },
  steps: {
    type: Array,
    default: () => [],
  },
})
</script>
