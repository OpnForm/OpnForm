<template>
  <IntegrationWrapper
    v-model="props.integrationData"
    :integration="props.integration"
    :form="form"
  >
    <div class="mb-4">
      <p class="text-neutral-500 mb-4">
        Receive Telegram messages on each form submission.
      </p>
      <template v-if="providers.length">
        <FlatSelectInput
          v-model="integrationData.oauth_id"
          name="provider"
          :options="providers"
          display-key="name"
          option-key="id"
          emit-key="id"
          :required="true"
          label="Select Telegram Account"
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

        <h4 class="font-bold mt-4">
          Telegram message actions
        </h4>
        <notifications-message-actions
          v-model="integrationData.data"
          :form="form"
        />
      </template>

      <UButton
        v-else
        color="neutral"
        variant="outline"
        :loading="isLoading"
        @click.prevent="openConnectionsModal"
        label="Connect Telegram account"
      />
    </div>
  </IntegrationWrapper>
</template>

<script setup>
import IntegrationWrapper from './components/IntegrationWrapper.vue'
import NotificationsMessageActions from './components/NotificationsMessageActions.vue'

const props = defineProps({
  integration: { type: Object, required: true },
  form: { type: Object, required: true },
  integrationData: { type: Object, required: true },
  formIntegrationId: { type: Number, required: false, default: null }
})

const oAuth = useOAuth()
const { data: providersData, isLoading } = oAuth.providers()
const providers = computed(() => (providersData.value || []).filter(provider => provider.provider == 'telegram'))

const { openUserSettings } = useAppModals()

function openConnectionsModal () {
  openUserSettings('connections')
}
</script> 