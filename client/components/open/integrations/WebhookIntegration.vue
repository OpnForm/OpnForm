<template>
  <IntegrationWrapper
    v-model="props.integrationData"
    :integration="props.integration"
    :form="form"
  >
    <text-input
      :form="integrationData"
      name="data.webhook_url"
      class="mt-4"
      label="Webhook URL"
      help="We will post form submissions to this endpoint"
      required
    />

    <!-- Advanced Section -->
    <div class="border-t mt-6 pt-6">
      <collapse
        v-model="showAdvanced"
        class="w-full"
      >
        <template #title>
          <div class="flex gap-x-3 items-start pr-8">
            <div
              class="transition-colors"
              :class="{
                'text-blue-600': showAdvanced,
                'text-neutral-300': !showAdvanced,
              }"
            >
              <Icon
                name="material-symbols:webhook"
                size="30px"
              />
            </div>
            <div class="flex-grow">
              <h3 class="font-semibold">
                Advanced
              </h3>
              <p class="text-neutral-500 text-xs">
                Configure signing secret and custom headers
              </p>
            </div>
          </div>
        </template>

        <!-- Webhook Secret -->
        <text-input
          :form="integrationData"
          name="data.webhook_secret"
          class="mt-4"
          label="Webhook Secret"
          help="Minimum 12 characters. Used to sign webhook requests with HMAC-SHA256"
          placeholder="whsec_..."
        />

        <!-- Webhook Headers -->
        <div class="mt-4">
          <label class="block text-sm font-medium text-neutral-700 mb-2">
            Custom Headers
          </label>
          <p class="text-xs text-neutral-500 mb-3">
            Add custom HTTP headers to be sent with each webhook (max 10 headers)
          </p>

          <!-- Headers List -->
          <div
            v-if="Object.keys(webhookHeaders).length > 0"
            class="space-y-2 mb-4"
          >
            <div
              v-for="(value, key) in webhookHeaders"
              :key="key"
              class="flex items-center justify-between gap-3 bg-neutral-50 p-3 rounded border border-neutral-200"
            >
              <div class="flex-grow">
                <code class="text-sm font-mono text-neutral-700">
                  {{ key }}<span class="text-neutral-400">:</span> <span class="text-neutral-600">{{ value }}</span>
                </code>
              </div>
              <UTooltip text="Remove header" arrow>
                <UButton
                  icon="i-material-symbols:delete-outline"
                  color="error"
                  variant="ghost"
                  size="xs"
                  @click="removeHeader(key)"
                />
              </UTooltip>
            </div>
          </div>

          <!-- Add Header Form -->
          <div class="space-y-2 bg-neutral-50 p-3 rounded border border-neutral-200">
            <div class="grid grid-cols-2 gap-2">
              <TextInput
                v-model="newHeaderKey"
                name="webhook-header-key"
                placeholder="Header name"
                size="sm"
                :disabled="Object.keys(webhookHeaders).length >= 10"
              />
              <TextInput
                v-model="newHeaderValue"
                name="webhook-header-value"
                placeholder="Header value"
                size="sm"
                :disabled="Object.keys(webhookHeaders).length >= 10"
              />
            </div>
            <UButton
              type="button"
              block
              :disabled="!newHeaderKey || !newHeaderValue || Object.keys(webhookHeaders).length >= 10"
              @click="addHeader"
            >
              Add Header
            </UButton>
            <p
              v-if="Object.keys(webhookHeaders).length >= 10"
              class="text-xs text-red-600"
            >
              Maximum 10 headers reached
            </p>
          </div>
        </div>
      </collapse>
    </div>
  </IntegrationWrapper>
</template>

<script setup>
import IntegrationWrapper from "./components/IntegrationWrapper.vue"
import Collapse from "~/components/app/Collapse.vue"
import TextInput from "~/components/forms/core/TextInput.vue"

const props = defineProps({
  integration: { type: Object, required: true },
  form: { type: Object, required: true },
  integrationData: { type: Object, required: true },
  formIntegrationId: { type: Number, required: false, default: null },
})

const showAdvanced = ref(!!props.integrationData?.data?.webhook_secret)

// Webhook Headers Management
const newHeaderKey = ref('')
const newHeaderValue = ref('')

const webhookHeaders = computed({
  get: () => {
    if (typeof props.integrationData?.data?.webhook_headers === 'object') {
      return props.integrationData.data.webhook_headers
    }
    return {}
  },
  set: (value) => {
    if (props.integrationData?.data) {
      props.integrationData.data.webhook_headers = value
    }
  }
})

const addHeader = () => {
  if (newHeaderKey.value && newHeaderValue.value && Object.keys(webhookHeaders.value).length < 10) {
    webhookHeaders.value = {
      ...webhookHeaders.value,
      [newHeaderKey.value]: newHeaderValue.value
    }
    newHeaderKey.value = ''
    newHeaderValue.value = ''
  }
}

const removeHeader = (key) => {
  const updated = { ...webhookHeaders.value }
  delete updated[key]
  webhookHeaders.value = updated
}
</script>
