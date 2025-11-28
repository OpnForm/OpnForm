<template>
  <IntegrationWrapper
    v-model="props.integrationData"
    :integration="props.integration"
    :form="form"
  >
    <div class="mb-4">
      <p class="text-neutral-500 mb-4">
        Adds new entry to spreadsheets on each form submission.
      </p>
      <FlatSelectInput
        v-if="providers.length"
        v-model="integrationData.oauth_id"
        name="provider"
        :options="providers"
        :disable-options="disableProviders"
        disable-options-tooltip="Re-connect account to fix permissions"
        display-key="email"
        option-key="id"
        emit-key="id"
        :required="true"
        label="Select Google Account"
      >
        <template #help>
          <InputHelp>
            <span>
              <a
                class="text-blue-500 cursor-pointer"
                @click="openConnectionsModal"
              >
                Click here
              </a>
              to connect another account.
            </span>
          </InputHelp>
        </template>
      </FlatSelectInput>

      <UButton
        v-else
        color="neutral"
        variant="outline"
        :loading="isLoading"
        @click.prevent="connect"
        label="Connect Google account"
      />
    </div>
  </IntegrationWrapper>
</template>

<script setup>
import IntegrationWrapper from './components/IntegrationWrapper.vue'

const props = defineProps({
  integration: { type: Object, required: true },
  form: { type: Object, required: true },
  integrationData: { type: Object, required: true },
  formIntegrationId: { type: Number, required: false, default: null }
})

const oAuth = useOAuth()
const { data: providersData, isLoading } = oAuth.providers()
const providers = computed(() => (providersData.value || []).filter(provider => provider.provider == 'google'))
const disableProviders = computed(() => (providersData.value || []).filter(provider => !provider.scopes.includes(oAuth.googleDrivePermissionFileScope)).map((provider) => provider.id))
const { openUserSettings } = useAppModals()

function connect () {
  oAuth.connect('google', true)
}

function openConnectionsModal () {
  openUserSettings('connections')
}
</script>
