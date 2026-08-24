<template>
  <section class="border-b border-neutral-200 bg-white py-16 sm:py-20 lg:py-24">
    <div
      class="mx-auto grid max-w-6xl gap-10 px-5 sm:px-8 lg:grid-cols-[0.75fr_1.25fr] lg:px-12"
    >
      <div>
        <p class="text-sm font-semibold uppercase tracking-[0.14em] text-blue-600">
          {{ eyebrow }}
        </p>
        <h2 class="mt-4 text-3xl font-semibold tracking-tight text-neutral-950 sm:text-4xl">
          {{ title }}
        </h2>
      </div>
      <div class="space-y-5 text-lg leading-8 text-neutral-600">
        <slot />
      </div>
    </div>
  </section>
</template>

<script setup>
defineProps({
  eyebrow: {
    type: String,
    required: true,
  },
  title: {
    type: String,
    required: true,
  },
})
</script>
