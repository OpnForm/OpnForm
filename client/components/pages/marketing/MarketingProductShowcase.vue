<template>
  <section :id="id || undefined" class="scroll-mt-20 border-b border-neutral-200 bg-white py-16 sm:py-20 lg:py-24">
    <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-12">
      <div class="mx-auto max-w-3xl text-center">
        <p class="text-sm font-semibold uppercase tracking-[0.14em] text-blue-600">
          {{ eyebrow }}
        </p>
        <h2 class="mt-4 text-3xl font-semibold tracking-tight text-neutral-950 sm:text-4xl lg:text-5xl">
          {{ title }}
        </h2>
        <p class="mt-5 text-lg leading-8 text-neutral-600">
          {{ description }}
        </p>
      </div>

      <div class="mt-12 grid gap-5 lg:grid-cols-12">
        <article
          v-for="(feature, index) in features"
          :key="feature.title"
          class="relative isolate grid min-h-[26rem] w-full overflow-hidden rounded-[2rem] border p-6 shadow-sm sm:p-8"
          :class="[
            feature.tone === 'blue'
              ? 'border-blue-300/80 bg-[#d2e4ff]'
              : feature.tone === 'mint'
                ? 'border-emerald-300/70 bg-[#ccefdc]'
                : 'border-violet-300/70 bg-[#dfd7ff]',
            index === 0
              ? 'lg:col-span-12 lg:min-h-[29rem] lg:grid-cols-[0.82fr_1.18fr] lg:items-center lg:gap-10 lg:px-12'
              : 'lg:col-span-6 lg:min-h-[34rem] lg:content-between lg:p-10',
          ]"
        >
          <div
            aria-hidden="true"
            class="absolute -right-20 -top-20 h-56 w-56 rounded-full bg-white/70 blur-3xl"
          />

          <div class="relative max-w-xl">
            <h3
              class="text-2xl font-semibold tracking-tight text-neutral-950 sm:text-3xl"
              :class="index === 0 ? 'lg:text-4xl' : 'lg:text-3xl'"
            >
              {{ feature.title }}
            </h3>
            <p class="mt-4 max-w-xl text-base leading-7 text-neutral-600" :class="index === 0 ? 'sm:text-lg sm:leading-8' : ''">
              {{ feature.description }}
            </p>

            <ul v-if="feature.highlights?.length" class="mt-5 flex flex-wrap gap-2">
              <li
                v-for="highlight in feature.highlights"
                :key="highlight"
                class="inline-flex items-center gap-1.5 rounded-full bg-white/85 px-3 py-1.5 text-xs font-semibold text-neutral-700 shadow-xs ring-1 ring-neutral-200/80"
              >
                <UIcon name="i-heroicons-check-20-solid" class="h-3.5 w-3.5 text-emerald-600" />
                {{ highlight }}
              </li>
            </ul>
          </div>

          <div
            class="relative mt-8 flex min-h-56 items-center justify-center"
            :class="index === 0 ? 'lg:mt-0 lg:min-h-0' : 'lg:mt-6 lg:min-h-60'"
          >
            <img
              :src="feature.image"
              :srcset="feature.imageSrcset"
              sizes="(min-width: 1280px) 560px, (min-width: 1024px) 44vw, 92vw"
              :alt="feature.imageAlt"
              width="1536"
              height="1024"
              loading="lazy"
              decoding="async"
              class="w-full max-w-2xl object-contain drop-shadow-[0_24px_42px_rgba(30,41,59,0.16)]"
              :class="index === 0 ? 'max-h-[24rem]' : 'max-h-[17rem]'"
            />
          </div>
        </article>
      </div>

      <div class="mt-6 flex flex-col items-center justify-between gap-5 rounded-2xl border border-neutral-200 bg-neutral-950 px-6 py-5 text-white sm:flex-row sm:px-7">
        <div class="flex flex-wrap justify-center gap-x-5 gap-y-2 sm:justify-start">
          <span v-for="proof in proofs" :key="proof" class="inline-flex items-center gap-2 text-sm font-medium text-neutral-200">
            <UIcon name="i-heroicons-check-circle-20-solid" class="h-4.5 w-4.5 text-emerald-400" />
            {{ proof }}
          </span>
        </div>
        <UButton
          :to="cta.to"
          color="neutral"
          variant="ghost"
          :label="cta.label"
          trailing-icon="i-heroicons-arrow-right-20-solid"
          class="shrink-0 justify-center rounded-xl !border !border-white/15 !bg-white/10 !text-white hover:!bg-white/15"
        />
      </div>
    </div>
  </section>
</template>

<script setup>
defineProps({
  id: { type: String, default: "" },
  eyebrow: { type: String, required: true },
  title: { type: String, required: true },
  description: { type: String, required: true },
  features: { type: Array, required: true },
  proofs: { type: Array, default: () => [] },
  cta: { type: Object, required: true },
})
</script>
