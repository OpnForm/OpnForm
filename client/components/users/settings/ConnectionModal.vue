<template>
  <UModal
    v-model:open="isOpen"
    @close="closeModal"
  >
    <template #header>
      <div class="flex items-center w-full gap-4 px-2">
        <h2 class="font-semibold">
          Connect account
        </h2>
      </div>
    </template>

    <template #body>
      <div v-if="loading" class="text-center py-8">
        <Loader class="h-8 w-8 mx-auto mb-4" />
        <h3 class="text-lg font-medium text-neutral-900 mb-2">
          Connecting your account...
        </h3>
        <p class="text-sm text-neutral-500">
          Complete the authentication in the new tab that opened.
        </p>
      <UButton
        color="neutral"
        variant="soft"
        label="Cancel"
        class="mt-4"
        @click="loading = false"
      />
      </div>
      <div
        v-else
        class="flex"
      >
        <div
          v-for="service in services"
          :key="service.name"
          role="button"
          class="mr-2 bg-neutral-50 border border-neutral-200 rounded-md transition-colors p-4 pb-2 items-center justify-center w-[170px] h-[110px] flex flex-col relative"
          :class="{
            'hover:bg-blue-50 group cursor-pointer': service.enabled,
            'cursor-not-allowed': !service.enabled,
          }"
          @click="connect(service)"
        >
          <div class="flex justify-center">
            <div class="h-10 w-10 text-neutral-500 group-hover:text-blue-500 transition-colors flex items-center">
              <Icon
                :name="service.icon"
                class=""
                size="40px"
              />
            </div>
          </div>

          <div class="flex-grow flex items-center">
            <div class="text-neutral-400 font-medium text-sm text-center">
              {{ service.title }}
            </div>
          </div>
        </div>
      </div>
    </template>
  </UModal>

  <!-- Add widget modal -->
  <UsersSettingsProviderWidgetModal
    v-if="showWidgetModal"
    :show="showWidgetModal"
    :service="selectedService"
    @close="showWidgetModal = false"
  />
</template>

<script setup>
import { WindowMessageTypes, useWindowMessage } from '~/composables/useWindowMessage'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['close'])

const oAuth = useOAuth()
const services = oAuth.services
const loading = ref(false)
const showWidgetModal = ref(false)
const selectedService = ref(null)
const alert = useAlert()
const windowMessage = useWindowMessage(WindowMessageTypes.OAUTH_PROVIDER_CONNECTED)


// Listen for OAuth completion to close modal and refresh
onMounted(() => {  
  windowMessage.listen((_event) => {
    // OAuth connection completed, close modal and refresh
    loading.value = false
    closeModal()
    alert.success('Account connected successfully!')
  }, {
    useMessageChannel: false,
    acknowledge: false
  })
})

// Modal state
const isOpen = computed({
  get: () => props.modelValue,
  set: (value) => emit('close', value)
})

// Methods
const closeModal = () => {
  isOpen.value = false
}

function connect(service) {
  if (!service.enabled) {
    useAlert().error('This service is not enabled. Please contact support.')
    return
  }

  if (service.auth_type === 'widget') {
    emit('close')
    selectedService.value = service
    showWidgetModal.value = true
  } else {
    // Open OAuth flow in new tab to avoid losing current page
    loading.value = true
    oAuth.connect(service.name, false, true, true)
  }
}
</script>