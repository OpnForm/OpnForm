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
      <!-- Choose source -->
      <FormImportSourcePicker
        v-if="step === 'source'"
        @select="selectSource"
      />

      <!-- URL based sources -->
      <FormImportUrlInput
        v-else-if="step === 'url'"
        :form="importForm"
        :url-placeholder="urlPlaceholder"
        :loading="loading"
        @submit="submitImport"
        @back="step = 'source'"
      />

      <!-- OAuth based sources -->
      <FormImportOAuth
        v-else-if="step === 'oauth'"
        :form="importForm"
        v-bind="oauthConfig"
        :url-placeholder="urlPlaceholder"
        :loading="loading"
        @submit="submitImport"
      />

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
import FormImportOAuth from './FormImportOAuth.vue'
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
  oauth_provider_id: null,
})

const oAuth = useOAuth()

const sourceConfigs = {
  typeform: { label: 'Typeform', placeholder: 'https://example.typeform.com/to/abc123' },
  tally: { label: 'Tally', placeholder: 'https://tally.so/r/mBGjOq' },
  fillout: { label: 'Fillout', placeholder: 'https://example.fillout.com/t/abc123' },
  google_forms: {
    label: 'Google Forms',
    placeholder: 'https://docs.google.com/forms/d/.../edit',
    oauth: {
      provider: 'google',
      providerLabel: 'Google',
      sourceLabel: 'Google Forms',
      requiredScope: oAuth.googleFormsPermissionScope,
      connectIcon: 'i-simple-icons-google',
      connectHelpText: 'We need read-only access to import your Google Forms.',
      helpText: 'Use the edit URL of your form. Published URLs (/d/e/...) are not supported.',
    },
  }
}

const sourceLabel = computed(() => sourceConfigs[selectedSource.value]?.label ?? '')
const urlPlaceholder = computed(() => sourceConfigs[selectedSource.value]?.placeholder ?? '')
const oauthConfig = computed(() => sourceConfigs[selectedSource.value]?.oauth ?? {})

const appStore = useAppStore()
const { isAuthenticated: authenticated } = useIsAuthenticated()

watch(() => props.show, (open) => {
  if (open) {
    if (!authenticated.value) {
      appStore.quickRegisterModal = true
      emit('close')
      return
    }
    selectSource(props.defaultSource ?? null)
  }
})

const selectSource = (source) => {
  selectedSource.value = source
  importForm.url = ''
  importForm.oauth_provider_id = null
  importForm.errors.clear()

  if (source === null) {
    step.value = 'source'
  } else if (sourceConfigs[source]?.oauth) {
    step.value = 'oauth'
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
    import_data: importForm.data(),
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
