<template>
  <section class="bg-white px-5 py-16 sm:px-8 sm:py-20 lg:px-12 lg:py-24">
    <div
      class="relative mx-auto max-w-6xl overflow-hidden rounded-[2rem] bg-blue-700 px-6 py-12 text-center text-white shadow-xl shadow-blue-950/15 sm:px-12 sm:py-16"
    >
      <div
        aria-hidden="true"
        class="absolute inset-0 opacity-20 [background-image:radial-gradient(circle_at_center,white_1px,transparent_1.5px)] [background-size:22px_22px]"
      />
      <div class="relative mx-auto max-w-3xl">
        <p class="text-sm font-semibold uppercase tracking-[0.14em] text-blue-100">
          {{ eyebrow }}
        </p>
        <h2 class="mt-4 text-3xl font-semibold tracking-tight !text-white sm:text-4xl lg:text-5xl">
          {{ title }}
        </h2>
        <p class="mx-auto mt-5 max-w-2xl text-lg leading-8 text-blue-100">
          {{ description }}
        </p>
        <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
          <slot />
        </div>
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
  description: {
    type: String,
    required: true,
  },
})
</script>
