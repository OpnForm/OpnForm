<template>
  <UModal
    v-model:open="isOpen"
    :ui="{ content: 'sm:max-w-lg' }"
    :dismissible="!loading"
  >
    <template #header>
      <div class="flex items-center justify-between w-full">
        <div class="grow w-full">
          <h3 class="text-base font-semibold leading-6 text-neutral-900 dark:text-white">
            {{selectedSource ? 'Import from ' + sourceLabel : 'Import form'}}
          </h3>
          <p v-if="!selectedSource" class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
            Select the platform you want to import from
          </p>
        </div>
        <UButton color="neutral" variant="ghost" icon="i-heroicons-x-mark-20-solid" class="-my-1" @click="isOpen = false" />
      </div>
    </template>
    <template #body>
      <!-- Step 1: Choose source -->
      <FormImportSourcePicker
        v-if="step === 'source'"
        @select="selectSource"
      />

      <!-- Step 2: URL input (Typeform, Tally, Fillout) -->
      <FormImportUrlInput
        v-else-if="step === 'url'"
        :form="importForm"
        :source-label="sourceLabel"
        :url-placeholder="urlPlaceholder"
        :loading="loading"
        @submit="submitImport"
        @back="step = 'source'"
      />

      <!-- Step: Google Forms (coming soon) -->
      <div v-else-if="step === 'google'">
        <div class="text-center py-8">
          <Icon
            name="heroicons:clock"
            class="w-12 h-12 text-gray-300 mx-auto mb-3"
          />
          <p class="text-sm text-gray-500">
            Coming soon...
          </p>
        </div>
      </div>

      <div 
        v-if="step !== 'source'"
        class="flex gap-2 mt-4"
      >
        <UButton
          variant="ghost"
          color="neutral"
          icon="i-heroicons-arrow-left"
          @click="handleBack"
          label="Back"
        />
      </div>
    </template>
  </UModal>
</template>

<script setup>
import FormImportSourcePicker from './FormImportSourcePicker.vue'
import FormImportUrlInput from './FormImportUrlInput.vue'
import { formsApi } from '~/api/forms'

const props = defineProps({
  show: { type: Boolean, required: true },
  workspaceId: { type: [Number, String], default: null },
  defaultSource: { type: String, default: null },
})

const emit = defineEmits(['close', 'imported'])

const isOpen = computed({
  get: () => props.show,
  set: (val) => {
    if (!val) emit('close')
  },
})

const step = ref('source')
const selectedSource = ref(null)
const loading = ref(false)

const importForm = useForm({
  url: '',
})

const sourceConfigs = {
  typeform: { label: 'Typeform', placeholder: 'https://example.typeform.com/to/abc123' },
  tally: { label: 'Tally', placeholder: 'https://tally.so/r/mBGjOq' },
  fillout: { label: 'Fillout', placeholder: 'https://example.fillout.com/t/abc123' },
  google_forms: { label: 'Google Forms', placeholder: '' },
}

const sourceLabel = computed(() => sourceConfigs[selectedSource.value]?.label ?? '')
const urlPlaceholder = computed(() => sourceConfigs[selectedSource.value]?.placeholder ?? '')

watch(() => props.show, (open) => {
  if (open) {
    selectSource(props.defaultSource ?? null)
  }
})

const selectSource = (source) => {
  selectedSource.value = source
  importForm.url = ''
  importForm.errors.clear()

  if (source === null) {
    step.value = 'source'
  } else if (source === 'google_forms') {
    step.value = 'google'
  } else {
    step.value = 'url'
  }
}

const handleBack = () => {
  selectSource(null)
}

const submitImport = () => {
  if (loading.value || !importForm.url) return

  loading.value = true

  formsApi.import({
    source: selectedSource.value,
    import_data: { url: importForm.url },
    workspace_id: props.workspaceId,
  })
    .then((response) => {
      useAlert().success(response.message || 'Form imported successfully!')
      emit('imported', response.form)
      emit('close')
    })
    .catch((error) => {
      const message = error?.data?.message || error?.message || 'Failed to import form. Please check the URL and try again.'
      useAlert().error(message)
    })
    .finally(() => {
      loading.value = false
    })
}
</script>
