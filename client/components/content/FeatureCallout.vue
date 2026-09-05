<template>
  <section :class="hasImage ? 'my-16' : 'my-10 overflow-hidden rounded-[28px] border border-neutral-200 bg-neutral-50 p-6 sm:p-8'">
    <div
      :class="hasImage
        ? 'grid items-center gap-10 lg:grid-cols-2 lg:gap-14'
        : 'flex flex-col gap-5 sm:flex-row sm:items-start'"
    >
      <div
        v-if="!hasImage"
        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl"
        :class="toneClasses.iconWrap"
      >
        <UIcon
          :name="icon"
          class="h-6 w-6"
          :class="toneClasses.icon"
        />
      </div>

      <div class="min-w-0 flex-1">
        <p
          v-if="eyebrow"
          class="text-sm font-semibold uppercase tracking-[0.16em] text-blue-600"
        >
          {{ eyebrow }}
        </p>
        <h2
          class="font-semibold text-neutral-950"
          :class="[
            eyebrow ? 'mt-3' : '',
            hasImage ? 'text-3xl tracking-[-1%] sm:text-4xl' : 'text-2xl tracking-[-0.8%]',
          ]"
        >
          {{ title }}
        </h2>
        <div
          class="text-base leading-7 text-neutral-600"
          :class="hasImage ? 'mt-4' : 'mt-3'"
        >
          <slot />
        </div>

        <ul
          v-if="items.length"
          class="mt-6"
          :class="hasImage ? 'space-y-3' : 'grid gap-3 sm:grid-cols-2'"
        >
          <li
            v-for="item in items"
            :key="item"
            class="flex items-start gap-3 text-sm leading-6 text-neutral-700"
            :class="hasImage ? '' : 'rounded-2xl bg-white p-4'"
          >
            <UIcon
              name="i-heroicons-check-20-solid"
              class="mt-0.5 h-5 w-5 shrink-0 text-blue-600"
            />
            <span>{{ item }}</span>
          </li>
        </ul>
      </div>

      <div
        v-if="hasImage"
        class="min-w-0"
      >
        <div class="overflow-hidden rounded-[28px] border border-neutral-200 bg-white p-2 shadow-sm">
          <img
            :src="image"
            :alt="featureTitle"
            class="aspect-[4/3] w-full rounded-[22px] object-cover"
          />
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
const props = defineProps({
  title: {
    type: String,
    required: true,
  },
  eyebrow: {
    type: String,
    default: null,
  },
  icon: {
    type: String,
    default: 'i-heroicons-sparkles',
  },
  tone: {
    type: String,
    default: 'blue',
  },
  image: {
    type: String,
    default: null,
  },
  items: {
    type: Array,
    default: () => [],
  },
})

const toneMap = {
  blue: {
    iconWrap: 'bg-blue-50',
    icon: 'text-blue-600',
  },
  emerald: {
    iconWrap: 'bg-emerald-50',
    icon: 'text-emerald-600',
  },
  violet: {
    iconWrap: 'bg-violet-50',
    icon: 'text-violet-600',
  },
  amber: {
    iconWrap: 'bg-amber-50',
    icon: 'text-amber-600',
  },
}

const toneClasses = computed(() => toneMap[props.tone] ?? toneMap.blue)
const hasImage = computed(() => Boolean(props.image))
const featureTitle = inject('featureTitle', '')
</script>
