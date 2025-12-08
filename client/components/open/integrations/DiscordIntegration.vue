<template>
  <IntegrationWrapper
    v-model="props.integrationData"
    :integration="props.integration"
    :form="form"
  >
    <text-input
      :form="integrationData"
      name="data.discord_webhook_url"
      label="Discord webhook url"
      help="help"
      required
    >
      <template #help>
        <InputHelp>
          <span>
            Receive a discord message on each form submission.
            <a
              href="https://support.discord.com/hc/en-us/articles/228383668-Intro-to-Webhooks"
              target="_blank"
            >
              Click here
            </a>
            to learn how to get a discord webhook url.
          </span>
        </InputHelp>
      </template>
    </text-input>
    <h4 class="font-bold mt-4">
      Discord message options
    </h4>
    <notifications-message-actions
      v-model="integrationData.data"
      :form="form"
    />
  </IntegrationWrapper>
</template>

<script setup>
import IntegrationWrapper from "./components/IntegrationWrapper.vue"
import NotificationsMessageActions from "./components/NotificationsMessageActions.vue"

const props = defineProps({
  integration: {type: Object, required: true},
  form: {type: Object, required: true},
  integrationData: {type: Object, required: true},
  formIntegrationId: {type: Number, required: false, default: null},
})
</script>
