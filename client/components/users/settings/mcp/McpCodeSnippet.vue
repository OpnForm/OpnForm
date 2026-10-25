<template>
  <div class="overflow-hidden rounded-xl border border-neutral-200 bg-neutral-950 text-white">
    <div class="flex items-center justify-between gap-3 border-b border-white/10 p-2 pl-2.5">
      <slot name="selector" />
      <UButton
        label="Copy"
        icon="i-heroicons-clipboard-document"
        size="xs"
        color="neutral"
        variant="soft"
        @click="$emit('copy')"
      />
    </div>
    <div v-if="$slots.instructions" class="border-b border-white/10 px-4 py-3">
      <slot name="instructions" />
    </div>
    <pre
      id="mcp-client-config-panel"
      role="region"
      :aria-label="`${label} configuration`"
      class="max-h-72 overflow-auto p-4 text-xs leading-6 text-blue-100"
    ><code>{{ content }}</code></pre>
  </div>
</template>

<script setup>
defineProps({
  label: {
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
