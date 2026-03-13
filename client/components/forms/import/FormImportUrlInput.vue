<template>
  <div>
    <TextInput
      :form="form"
      name="url"
      label="Form URL"
      :placeholder="urlPlaceholder"
      :disabled="loading"
    />

    <p class="text-xs text-gray-500 mt-1.5 flex items-start gap-1">
      <Icon
        name="heroicons:information-circle"
        class="w-3.5 h-3.5 mt-0.5 flex-shrink-0"
      />
      <span>Make sure your form URL is public and accessible without a login.</span>
    </p>

    <UButton
      class="mt-4"
      block
      :loading="loading"
      :disabled="!form.url"
      label="Import Form"
      @click="$emit('submit')"
    />
  </div>
</template>

<script setup>
defineProps({
  form: { type: Object, required: true },
  sourceLabel: { type: String, required: true },
  urlPlaceholder: { type: String, default: 'https://...' },
  loading: { type: Boolean, default: false },
})

defineEmits(['submit'])
</script>
