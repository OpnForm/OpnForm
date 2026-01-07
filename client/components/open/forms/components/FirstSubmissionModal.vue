<template>
  <UModal
    v-model:open="isModalOpen"
    :ui="{ content: 'sm:max-w-2xl' }"
    title="🎉 Your first submission!"
  >
    <template #body>
      <div class="text-sm text-neutral-500 mb-6">
        Congratulations! Your form is now live and ready for action. Share it with others or check your submissions below.
      </div>

      <!-- Quick Actions -->
      <div class="space-y-3 mb-6">
        <div class="flex gap-3 items-center">
          <p class="text-sm w-36 text-neutral-500 flex-shrink-0">
            Share form URL:
          </p>
          <ShareFormUrl
            class="flex-grow"
            :form="form"
          />
        </div>
        <div class="flex items-center">
          <p class="text-sm w-36 text-neutral-500 flex-shrink-0">
            Check submissions:
          </p>
          <UButton
            color="neutral"
            variant="outline"
            icon="i-heroicons-table-cells"
            @click="trackOpenDbClick"
            label="View Submissions"
          />
        </div>
      </div>

      <!-- Integrations Section -->
      <div class="border-t border-neutral-200 dark:border-neutral-700 pt-5">
        <div class="flex items-center justify-between mb-3">
          <p class="text-neutral-700 dark:text-neutral-200 font-semibold text-sm">
            🔗 Connect your form to other apps
          </p>
          <NuxtLink
            :to="integrationsPageUrl"
            target="_blank"
            class="text-xs text-blue-600 hover:text-blue-700 dark:text-blue-400 hover:underline flex items-center gap-1"
            @click="trackIntegrationsLinkClick"
          >
            View all integrations
            <Icon
              name="heroicons:arrow-top-right-on-square-16-solid"
              size="12px"
            />
          </NuxtLink>
        </div>
        <p class="text-xs text-neutral-500 mb-4">
          Get notified instantly when someone submits your form, or sync data to your favorite tools.
        </p>

        <!-- Featured Integration: Email -->
        <div
          role="button"
          class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-4 hover:bg-blue-100 dark:hover:bg-blue-900/30 transition-colors cursor-pointer group"
          @click="openEmailIntegration"
        >
          <div class="flex items-start gap-3">
            <div class="flex-shrink-0 w-10 h-10 bg-blue-100 dark:bg-blue-800 rounded-lg flex items-center justify-center">
              <Icon
                name="heroicons:envelope-20-solid"
                class="text-blue-600 dark:text-blue-300"
                size="20px"
              />
            </div>
            <div class="flex-grow">
              <div class="flex items-center gap-2">
                <p class="text-sm font-semibold text-neutral-800 dark:text-white">
                  Email Notification
                </p>
                <UBadge
                  variant="subtle"
                  color="success"
                  size="xs"
                >
                  Recommended
                </UBadge>
              </div>
              <p class="text-xs text-neutral-600 dark:text-neutral-400 mt-1">
                Get an email every time someone submits your form. Perfect for staying on top of responses.
              </p>
            </div>
            <Icon
              name="heroicons:chevron-right"
              class="text-neutral-400 group-hover:text-blue-600 transition-colors flex-shrink-0"
              size="20px"
            />
          </div>
        </div>

        <!-- Other Popular Integrations -->
        <p class="text-xs text-neutral-500 mb-2">
          Other popular integrations:
        </p>
        <div class="grid grid-cols-4 gap-2">
          <div
            v-for="(integration, i) in popularIntegrations"
            :key="i"
            role="button"
            class="bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg p-3 flex flex-col items-center justify-center hover:bg-neutral-50 dark:hover:bg-neutral-700 transition-colors cursor-pointer group relative"
            @click="openIntegrationPage(integration)"
          >
            <Icon
              :name="integration.icon"
              class="w-6 h-6 text-neutral-500 group-hover:text-neutral-700 dark:group-hover:text-white transition-colors"
            />
            <p class="text-xs text-neutral-600 dark:text-neutral-400 mt-1.5 text-center font-medium truncate w-full">
              {{ integration.name }}
            </p>
            <pro-tag
              v-if="integration.is_pro"
              class="absolute top-1 right-1"
              size="xs"
            />
          </div>
        </div>
      </div>
    </template>
  </UModal>
</template>

<script setup>
import ShareFormUrl from '~/components/open/forms/components/ShareFormUrl.vue'
import ProTag from '~/components/app/ProTag.vue'

const props = defineProps({
  show: { type: Boolean, required: true },
  form: { type: Object, required: true }
})

const emit = defineEmits(['close'])

// Modal state
const isModalOpen = computed({
  get() {
    return props.show
  },
  set(value) {
    if (!value) {
      emit("close")
    }
  }
})

const confetti = useConfetti()
const amplitude = useAmplitude()

watch(() => props.show, () => {
  if (props.show) {
    confetti.play()
    useAmplitude().logEvent('form_first_submission_modal_viewed')
  }
})

// Integrations page URL
const integrationsPageUrl = computed(() => {
  return `/forms/${props.form.slug}/show/integrations`
})

// Popular integrations to display (subset from integrations.json)
const popularIntegrations = computed(() => [
  {
    id: 'slack',
    name: 'Slack',
    icon: 'mdi:slack',
    is_pro: true
  },
  {
    id: 'google_sheets',
    name: 'Sheets',
    icon: 'mdi:google-spreadsheet',
    is_pro: false
  },
  {
    id: 'zapier',
    name: 'Zapier',
    icon: 'cib:zapier',
    is_pro: false
  },
  {
    id: 'webhook',
    name: 'Webhook',
    icon: 'material-symbols:webhook',
    is_pro: false
  }
])

const trackOpenDbClick = () => {
  const submissionsUrl = props.form.submissions_url
  window.open(submissionsUrl, '_blank')
  amplitude.logEvent('form_first_submission_modal_open_db_click')
}

const trackIntegrationsLinkClick = () => {
  amplitude.logEvent('form_first_submission_modal_integrations_link_click')
}

const openEmailIntegration = () => {
  amplitude.logEvent('form_first_submission_modal_email_integration_click')
  const url = `${integrationsPageUrl.value}?integration=email`
  window.open(url, '_blank')
}

const openIntegrationPage = (integration) => {
  amplitude.logEvent('form_first_submission_modal_integration_click', { integration_id: integration.id })
  const url = `${integrationsPageUrl.value}?integration=${integration.id}`
  window.open(url, '_blank')
}
</script>