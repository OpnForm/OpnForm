<template>
  <UModal
    v-model:open="isOpen"
    :ui="{ content: 'sm:max-w-md' }"
  >
    <template #header>
      <div class="flex items-center justify-center">
        <Icon
          :name="service.icon"
          class="h-8 w-8 text-neutral-500"
        />
        <span class="ml-2 text-neutral-700 font-medium">{{ service.title }}</span>
      </div>
    </template>

    <template #body>
      <div class="flex items-center justify-center">
        <!-- Dynamic component loading based on service -->
        <Suspense v-if="service?.widget_file">
          <component
            :is="widgetComponent"
            v-if="widgetComponent"
            :service="service"
            @auth-data="handleAuthData"
          />
          <template #fallback>
            <div class="flex items-center justify-center p-8">
              <USkeleton class="h-24 w-full" />
            </div>
          </template>
        </Suspense>
      </div>
    </template>

    <template #footer>
      <UButton
        color="neutral"
        variant="outline"
        @click="closeModal"
      >
        Close
      </UButton>
    </template>
  </UModal>
</template>

<script setup>
import { useComponentRegistry } from "~/composables/components/useComponentRegistry"

const props = defineProps({
  show: Boolean,
  service: Object
})

const oAuth = useOAuth()
const router = useRouter()
const alert = useAlert()
const { getProviderWidget } = useComponentRegistry()
const emit = defineEmits(['close'])

// Modal state
const isOpen = computed({
  get: () => props.show,
  set: (value) => {
    if (!value) {
      emit('close')
    }
  }
})

const closeModal = () => {
  isOpen.value = false
}

// Dynamically compute which widget component to load
const widgetComponent = computed(() => {
  if (!props.service?.widget_file) return null
  return getProviderWidget(props.service.widget_file)
})

// Widget callback mutation
const widgetCallbackMutation = oAuth.widgetCallback()

const handleAuthData = (data) => {
  if (!data) {
    alert.error('Authentication failed')
    return
  }

  // Ensure intent is provided for widget callbacks
  const payload = { ...data, intent: data?.intent || 'integration' }

  widgetCallbackMutation.mutateAsync({ service: props.service.name, data: payload }).then((response) => {
    if (response.intention) {
      router.push(response.intention)
    } else {
      alert.success('Successfully connected')
      emit('close')
      oAuth.fetchOAuthProviders()
    }
  }).catch((error) => {
    alert.error(error?.data?.message || 'Failed to authenticate')
    oAuth.fetchOAuthProviders()
  })
}
</script>