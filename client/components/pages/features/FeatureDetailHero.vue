<template>
  <section class="relative overflow-hidden border-b border-neutral-200">
    <div
      class="pointer-events-none absolute inset-0 bg-linear-to-b from-white from-35% via-blue-50 via-60% to-white to-85%"
    />

    <div
      class="pointer-events-none absolute -left-16 top-16 h-56 w-56 rounded-full blur-3xl animate-feature-glow"
      :class="colorClasses.glow"
    />
    <div
      class="pointer-events-none absolute -right-10 bottom-8 h-48 w-48 rounded-full blur-3xl animate-feature-glow animation-delay-300"
      :class="colorClasses.glow"
    />

    <div class="relative z-2 px-5 py-10 sm:px-8 sm:py-16 lg:px-12">
      <div :class="hasHeroImage ? 'mx-auto max-w-7xl' : 'mx-auto max-w-4xl'">
        <UButton
          :to="{ name: 'features' }"
          variant="ghost"
          color="neutral"
          icon="i-heroicons-arrow-left"
          class="-ml-3 mb-10 animate-fade-in-up"
        >
          All features
        </UButton>

        <div
          class="grid items-center gap-10 lg:gap-14"
          :class="hasHeroImage ? 'lg:grid-cols-[minmax(0,1fr)_minmax(320px,0.95fr)]' : ''"
        >
          <div :class="hasHeroImage ? 'text-left' : 'text-center'">
            <div
              v-if="!hasHeroImage"
              class="animate-fade-in-up animation-delay-100"
            >
              <div class="relative mx-auto mb-8 inline-flex animate-feature-float">
                <div
                  class="absolute inset-0 rounded-[32px] blur-2xl animate-feature-glow"
                  :class="colorClasses.glow"
                />
                <div
                  class="relative flex h-20 w-20 items-center justify-center rounded-[28px] shadow-lg ring-4 sm:h-24 sm:w-24"
                  :class="[colorClasses.iconBg, colorClasses.iconText, colorClasses.ring]"
                >
                  <UIcon
                    :name="feature.icon"
                    class="h-10 w-10 sm:h-11 sm:w-11"
                  />
                </div>
              </div>
            </div>

            <div
              class="animate-fade-in-up animation-delay-200 flex flex-wrap items-center gap-2"
              :class="hasHeroImage ? '' : 'justify-center'"
            >
              <UBadge
                color="primary"
                variant="subtle"
                class="rounded-full"
              >
                {{ feature.category }}
              </UBadge>
              <FeaturePlanBadge :plan="feature.plan" />
            </div>

            <h1
              class="animate-fade-in-up animation-delay-300 mt-7 text-4xl font-semibold leading-tight tracking-[-1.2%] text-neutral-950 sm:text-5xl lg:text-[56px] lg:leading-[1.1]"
              :class="hasHeroImage ? 'max-w-2xl' : ''"
            >
              {{ feature.title }}
            </h1>

            <p
              class="animate-fade-in-up animation-delay-400 mt-6 text-lg leading-8 text-neutral-600 sm:text-xl"
              :class="hasHeroImage ? 'max-w-xl' : 'mx-auto max-w-2xl'"
            >
              {{ feature.summary }}
            </p>

            <div
              class="animate-fade-in-up animation-delay-500 mt-8 flex flex-col gap-4 sm:flex-row"
              :class="hasHeroImage ? '' : 'items-center justify-center'"
            >
              <UButton
                :to="{ name: authenticated ? 'forms-create' : 'forms-create-guest' }"
                size="lg"
                trailing-icon="i-heroicons-arrow-up-right-20-solid"
                label="Create a form"
                class="w-fit rounded-[12px] py-2.5 pl-4 pr-3.5 text-base font-medium leading-7 tracking-[-1.1%]"
              />
              <UButton
                :to="{ name: 'pricing' }"
                size="lg"
                variant="outline"
                color="neutral"
                label="View pricing"
                class="w-fit rounded-[12px] px-4 py-2.5 text-base font-medium leading-7 tracking-[-1.1%]"
              />
            </div>
          </div>

          <div
            v-if="hasHeroImage"
            class="animate-fade-in-up animation-delay-300"
          >
            <div class="overflow-hidden rounded-[28px] border border-neutral-200 bg-white p-2 shadow-sm">
              <img
                :src="feature.heroImage"
                :alt="feature.title"
                class="aspect-[4/3] w-full rounded-[22px] object-cover"
              />
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { getFeatureColorClasses } from '~/lib/features.js'

const props = defineProps({
  feature: {
    type: Object,
    required: true,
  },
})

const { isAuthenticated: authenticated } = useIsAuthenticated()

const colorClasses = computed(() => getFeatureColorClasses(props.feature.color))
const hasHeroImage = computed(() => Boolean(props.feature.heroImage))
</script>
