<template>
  <div>
    <!-- Loading providers -->
    <div v-if="loadingProviders" class="text-center py-8">
      <Loader class="h-6 w-6 mx-auto mb-3" />
      <p class="text-sm text-gray-500">
        Checking connection...
      </p>
    </div>

    <!-- Has accounts: show account selector -->
    <div v-else-if="filteredProviders.length">
      <FlatSelectInput
        v-model="selectedProviderId"
        :form="form"
        name="oauth_provider_id"
        :options="filteredProviders"
        :disable-options="disabledProviderIds"
        disable-options-tooltip="Re-connect account to fix permissions"
        display-key="email"
        option-key="id"
        emit-key="id"
        :required="true"
        :label="'Select ' + providerLabel + ' Account'"
      >
        <template #help>
          <InputHelp>
            <span>
              <a
                class="text-blue-500 cursor-pointer"
                @click="connectProvider"
              >
                Click here
              </a>
              to connect another account.
            </span>
          </InputHelp>
        </template>
      </FlatSelectInput>

      <FormImportUrlInput
        v-if="selectedProviderId"
        :form="form"
        :url-placeholder="urlPlaceholder"
        :loading="loading"
        class="mt-3"
        @submit="$emit('submit')"
        :help-text="helpText"
      />
    </div>

    <!-- No accounts: show connect prompt -->
    <div v-else class="text-center py-6">
      <UButton
        :loading="connecting"
        :label="'Connect ' + providerLabel"
        :icon="connectIcon"
        @click="connectProvider"
      />
      <p class="text-sm text-gray-400 mt-2">
        {{ connectHelpText }}
      </p>
    </div>
  </div>
</template>

<script setup>
import FormImportUrlInput from './FormImportUrlInput.vue'
import { WindowMessageTypes, useWindowMessage } from '~/composables/useWindowMessage'

const props = defineProps({
  form: { type: Object, required: true },
  provider: { type: String, required: true },
  providerLabel: { type: String, required: true },
  sourceLabel: { type: String, required: true },
  requiredScope: { type: String, default: null },
  connectIcon: { type: String, default: null },
  connectHelpText: { type: String, default: 'We need access to import your forms.' },
  helpText: { type: String, default: null },
  urlPlaceholder: { type: String, default: 'https://...' },
  loading: { type: Boolean, default: false },
})

defineEmits(['submit'])

const oAuth = useOAuth()
const { data: providersData, isLoading: loadingProviders } = oAuth.providers()
const connecting = ref(false)
const selectedProviderId = ref(null)

const filteredProviders = computed(() =>
  (providersData.value || []).filter(p => p.provider === props.provider)
)

const disabledProviderIds = computed(() => {
  if (!props.requiredScope) return []
  return filteredProviders.value
    .filter(p => !p.scopes?.includes(props.requiredScope))
    .map(p => p.id)
})

watch(filteredProviders, (providers) => {
  if (!providers.length || !providers.some(p => p.id === selectedProviderId.value)) {
    selectedProviderId.value = null
  }
})

const connectProvider = () => {
  connecting.value = true
  oAuth.connect(props.provider, false, true, true, { intent: 'forms_import' })
    .catch(() => {
      connecting.value = false
    })
}

const windowMessage = useWindowMessage(WindowMessageTypes.OAUTH_PROVIDER_CONNECTED)
onMounted(() => {
  windowMessage.listen(() => {
    connecting.value = false
    oAuth.invalidateProviders()
  }, {
    useMessageChannel: false,
    acknowledge: false,
  })
})
</script>
