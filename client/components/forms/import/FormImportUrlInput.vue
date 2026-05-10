<template>
  <div class="rounded-lg border border-neutral-200 bg-white p-4 shadow-sm">
    <TextInput
      :form="form"
      name="url"
      :label="label"
      :placeholder="urlPlaceholder"
      :disabled="loading"
    />

    <p class="mt-2 flex items-start gap-1.5 text-xs leading-5 text-neutral-500">
      <Icon
        name="i-heroicons-information-circle"
        class="w-3.5 h-3.5 mt-0.5 flex-shrink-0"
      />
      <span>{{ helpText }}</span>
    </p>

    <UButton
      class="mt-4"
      block
      icon="i-heroicons-arrow-down-tray"
      :loading="loading"
      :disabled="!form.url"
      :color="form.url ? 'primary' : 'neutral'"
      :variant="form.url ? 'solid' : 'soft'"
      :label="buttonLabel"
      @click="$emit('submit')"
    />
  </div>
</template>

<script setup>
defineProps({
  form: { type: Object, required: true },
  urlPlaceholder: { type: String, default: 'https://...' },
  label: { type: String, default: 'Form URL' },
  loading: { type: Boolean, default: false },
  helpText: { type: String, default: 'Make sure your form URL is public and accessible without a login.' },
  buttonLabel: { type: String, default: 'Import Form' },
})

defineEmits(['submit'])
</script>
