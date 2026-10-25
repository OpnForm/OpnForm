<template>
  <section class="relative overflow-hidden border-b border-neutral-200 bg-[#f7f9fc]">
    <div
      aria-hidden="true"
      class="absolute inset-0 opacity-70 [background-image:linear-gradient(to_right,#dbe4f0_1px,transparent_1px),linear-gradient(to_bottom,#dbe4f0_1px,transparent_1px)] [background-size:32px_32px] [mask-image:linear-gradient(to_bottom,black,transparent_90%)]"
    />
    <div
      aria-hidden="true"
      class="absolute -left-24 top-28 h-72 w-72 rounded-full bg-blue-200/40 blur-3xl"
    />
    <div
      aria-hidden="true"
      class="absolute -right-20 bottom-8 h-64 w-64 rounded-full bg-amber-100/70 blur-3xl"
    />

    <div
      class="relative mx-auto grid max-w-7xl items-center gap-12 px-5 py-12 sm:px-8 sm:py-14 lg:grid-cols-[0.92fr_1.08fr] lg:px-12 lg:py-16"
    >
      <div class="max-w-2xl">
        <slot name="eyebrow">
          <div
            class="inline-flex items-center gap-2 rounded-full border border-blue-200 bg-white/80 px-3.5 py-1.5 text-sm font-medium text-blue-700 shadow-xs backdrop-blur"
          >
            <span class="relative flex h-2 w-2" aria-hidden="true">
              <span
                class="absolute inline-flex h-full w-full animate-ping rounded-full bg-blue-400 opacity-60 motion-reduce:animate-none"
              />
              <span class="relative inline-flex h-2 w-2 rounded-full bg-blue-600" />
            </span>
            {{ props.eyebrow }}
          </div>
        </slot>

        <h1
          class="mt-6 text-4xl font-semibold tracking-[-0.04em] text-neutral-950 sm:text-5xl lg:text-6xl lg:leading-[1.05]"
        >
          <slot name="title">
            {{ props.title }}
          </slot>
        </h1>
        <p class="mt-6 max-w-xl text-lg leading-8 text-neutral-600 sm:text-xl sm:leading-9">
          {{ props.description }}
        </p>

        <div
          v-if="$slots.actions"
          class="mt-8 flex flex-col gap-3 sm:flex-row"
          @click="handleActionClick"
        >
          <slot name="actions" />
        </div>

        <ul
          v-if="props.proofs.length"
          class="mt-8 flex flex-wrap gap-x-6 gap-y-3 text-sm font-medium text-neutral-700"
        >
          <li v-for="proof in props.proofs" :key="proof" class="flex items-center gap-2">
            <UIcon
              name="i-heroicons-check-circle-20-solid"
              class="h-5 w-5 shrink-0 text-emerald-600"
            />
            {{ proof }}
          </li>
        </ul>
      </div>

      <div class="relative mx-auto w-full max-w-2xl lg:mx-0">
        <div class="relative flex items-center justify-center">
          <slot name="visual" />
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
const props = defineProps({
  eyebrow: {
    type: String,
    required: true,
  },
  title: {
    type: String,
    default: "",
  },
  description: {
    type: String,
    required: true,
  },
  proofs: {
    type: Array,
    default: () => [],
  },
})

const handleActionClick = (event) => {
  if (!(event.target instanceof Element)) return

  const link = event.target.closest('a[href^="#"]')
  const hash = link?.getAttribute("href")

  if (!hash || hash === "#") return

  const target = document.getElementById(decodeURIComponent(hash.slice(1)))
  if (!target) return

  event.preventDefault()

  target.scrollIntoView({
    behavior: window.matchMedia("(prefers-reduced-motion: reduce)").matches
      ? "auto"
      : "smooth",
    block: "start",
  })

  window.history.pushState(null, "", hash)
}
</script>
