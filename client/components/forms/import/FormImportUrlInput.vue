<template>
  <div>
    <TextInput
      :form="form"
      name="url"
      :label="label"
      :placeholder="urlPlaceholder"
      :disabled="loading"
    />

    <p class="text-xs text-gray-500 mt-1.5 flex items-start gap-1">
      <Icon
        name="heroicons:information-circle"
        class="w-3.5 h-3.5 mt-0.5 flex-shrink-0"
      />
      <span>{{ helpText }}</span>
    </p>

    <UButton
      class="mt-4"
      block
      :loading="loading"
      :disabled="!form.url"
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
