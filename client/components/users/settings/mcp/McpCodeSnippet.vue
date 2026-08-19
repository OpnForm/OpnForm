<template>
  <div class="overflow-hidden rounded-xl border border-neutral-200 bg-neutral-950 text-white">
    <div class="flex items-start justify-between gap-4 border-b border-white/10 px-4 py-3">
      <div>
        <p class="text-sm font-semibold">{{ title }}</p>
        <p class="mt-0.5 text-xs leading-5 text-neutral-400">{{ description }}</p>
      </div>
      <UButton
        label="Copy"
        icon="i-heroicons-clipboard-document"
        size="xs"
        color="neutral"
        variant="soft"
        @click="$emit('copy')"
      />
    </div>
    <pre class="max-h-72 overflow-auto p-4 text-xs leading-6 text-blue-100"><code>{{ content }}</code></pre>
  </div>
</template>

<script setup>
defineProps({
  title: {
    type: String,
    required: true,
  },
  description: {
    type: String,
    required: true,
  },
  content: {
    type: String,
    required: true,
  },
})

defineEmits(['copy'])
</script>
