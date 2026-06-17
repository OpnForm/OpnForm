<template>
  <section class="my-10 overflow-hidden rounded-[28px] border border-neutral-200 bg-neutral-50 p-6 sm:p-8">
    <div class="flex flex-col gap-5 sm:flex-row sm:items-start">
      <div
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
        <h2 class="text-2xl font-semibold tracking-[-0.8%] text-neutral-950">
          {{ title }}
        </h2>
        <div class="mt-3 text-base leading-7 text-neutral-600">
          <slot />
        </div>

        <ul
          v-if="items.length"
          class="mt-6 grid gap-3 sm:grid-cols-2"
        >
          <li
            v-for="item in items"
            :key="item"
            class="flex items-start gap-3 rounded-2xl bg-white p-4 text-sm leading-6 text-neutral-700"
          >
            <UIcon
              name="i-heroicons-check-20-solid"
              class="mt-0.5 h-5 w-5 shrink-0 text-blue-600"
            />
            <span>{{ item }}</span>
          </li>
        </ul>
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
  icon: {
    type: String,
    default: 'i-heroicons-sparkles',
  },
  tone: {
    type: String,
    default: 'blue',
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
</script>
