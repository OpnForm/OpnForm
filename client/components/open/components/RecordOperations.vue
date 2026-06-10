<template>
  <div class="flex gap-1">
    <TrackClick
      name="edit_record_click"
      :properties="{ form_id: form.id, submission_id: submissionId }"
    >
      <UButton
        size="xs"
        color="neutral"
        variant="outline"
        icon="heroicons:pencil-square"
        @click="emit('edit', submissionId)"
      />
    </TrackClick>
    <UButton
      v-if="hasPdfTemplates"
      size="xs"
      color="neutral"
      variant="outline"
      icon="i-heroicons-arrow-down-tray-20-solid"
      aria-label="Download PDF"
      @click="emit('download-pdf', submissionId)"
    />
    <TrackClick
      name="view_record_click"
      :properties="{ form_id: form.id, submission_id: submissionId }"
    >
      <UButton
        size="xs"
        color="neutral"
        variant="outline"
        icon="heroicons:arrows-pointing-out"
        @click="emit('view', submissionId)"
      />
    </TrackClick>
  </div>
</template>

<script setup>
import TrackClick from "~/components/global/TrackClick.vue"

defineProps({
  form: {
    type: Object,
    required: true,
  },
  submissionId: {
    type: Number,
    required: true,
  },
  hasPdfTemplates: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['edit', 'download-pdf', 'view'])
</script>
