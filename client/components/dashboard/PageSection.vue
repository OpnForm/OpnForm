<template>
  <section>
    <div v-if="title || $slots.header" class="mb-4 flex items-start justify-between gap-4">
      <slot name="header">
        <div>
          <h2 class="text-xl font-semibold">
            {{ title }}
          </h2>
          <p
            v-if="description"
            class="text-sm text-neutral-500 mt-1"
          >
            {{ description }}
          </p>
        </div>
      </slot>
      <div v-if="$slots.actions" class="shrink-0">
        <slot name="actions" />
      </div>
    </div>
    <slot />
  </section>
</template>

<script setup>
defineProps({
  title: { type: String, default: '' },
  description: { type: String, default: '' },
})
</script>
