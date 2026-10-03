<template>
  <div class="space-y-3 min-w-64 max-w-xl whitespace-normal">
    <p v-if="event.data?.email?.reason" class="text-sm">{{ event.data.email.reason }}</p>
    <p v-if="isStale(event)" class="text-sm text-amber-600">
      Processing has not completed. The outcome is unknown; do not retry blindly.
    </p>
    <div v-for="(recipient, id) in event.data?.email?.recipients" :key="id" class="text-sm">
      <p class="font-medium break-all">{{ recipient.address }}</p>
      <p>{{ recipientLabel(recipient.status) }}</p>
      <p v-if="recipient.reason && !['accepted', 'delivered'].includes(recipient.status)" class="text-neutral-500">{{ recipient.reason }}</p>
    </div>
    <details class="text-sm">
      <summary class="w-fit cursor-pointer rounded text-neutral-500 hover:text-neutral-700 focus-visible:outline-2 focus-visible:outline-offset-2 dark:hover:text-neutral-300">
        Delivery details
      </summary>
      <div class="mt-3 space-y-3">
        <div v-for="(recipient, id) in event.data?.email?.recipients" :key="id" class="border-b border-neutral-200 pb-2 dark:border-neutral-700">
          <p class="font-medium break-all">{{ recipient.address }}</p>
          <p v-if="recipient.reason" class="text-neutral-500">{{ recipient.reason }}</p>
          <p v-if="recipient.reason_code" class="text-xs text-neutral-500">{{ recipient.reason_code }}</p>
          <p v-if="recipient.feedback?.bounce" class="text-neutral-500">
            Bounce: {{ recipient.feedback.bounce.type }} / {{ recipient.feedback.bounce.subtype }}
          </p>
          <p v-for="(at, state) in recipient.timeline" :key="state" class="text-xs text-neutral-500">{{ recipientLabel(state) }}: {{ at }}</p>
          <p v-for="(feedback, type) in recipient.feedback" :key="type" class="text-xs text-neutral-500">{{ type }}: {{ feedback.at || feedback.received_at }}</p>
          <p v-if="recipient.updated_at" class="text-xs text-neutral-500">Updated: {{ recipient.updated_at }}</p>
          <p v-if="recipient.provider_message_id" class="text-xs text-neutral-500 break-all">Message ID: {{ recipient.provider_message_id }}</p>
        </div>
        <p v-if="event.tracking_id" class="text-xs text-neutral-500 break-all">Event ID: {{ event.tracking_id }}</p>
        <p class="text-xs text-neutral-500">Delivery means acceptance by the receiving mail server, not inbox placement. No delivery event means delivery is unconfirmed.</p>
      </div>
    </details>
  </div>
</template>

<script setup>
import { isStale, recipientLabel } from "~/lib/integration-email-status"

defineProps({ event: { type: Object, required: true } })
</script>
