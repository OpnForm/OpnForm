<template>
  <section class="my-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <div
      v-for="(metric, index) in metrics"
      :key="metric.label"
      class="group relative overflow-hidden rounded-[24px] border border-neutral-200 bg-neutral-50 p-6 transition-colors duration-200 hover:border-blue-200 hover:bg-blue-50/40 sm:p-7"
    >
      <div
        class="flex h-10 w-10 items-center justify-center rounded-2xl ring-1 ring-inset"
        :class="[accentForIndex(index).wrap, accentForIndex(index).ring]"
      >
        <UIcon
          :name="accentForIndex(index).icon"
          class="h-5 w-5"
          :class="accentForIndex(index).text"
        />
      </div>

      <p
        class="mt-5 text-2xl font-semibold leading-tight tracking-[-0.8%]"
        :class="accentForIndex(index).text"
      >
        {{ metric.value }}
      </p>
      <p class="mt-2 text-sm leading-6 text-neutral-600">
        {{ metric.label }}
      </p>
    </div>
  </section>
</template>

<script setup>
defineProps({
  metrics: {
    type: Array,
    default: () => [],
  },
})

const metricAccents = [
  {
    wrap: 'bg-blue-50',
    ring: 'ring-blue-100',
    text: 'text-blue-600',
    icon: 'i-heroicons-sparkles',
  },
  {
    wrap: 'bg-emerald-50',
    ring: 'ring-emerald-100',
    text: 'text-emerald-600',
    icon: 'i-heroicons-check-badge',
  },
  {
    wrap: 'bg-violet-50',
    ring: 'ring-violet-100',
    text: 'text-violet-600',
    icon: 'i-heroicons-bolt',
  },
]

function accentForIndex (index) {
  return metricAccents[index % metricAccents.length]
}
</script>
