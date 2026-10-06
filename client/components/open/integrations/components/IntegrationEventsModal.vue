<template>
  <UModal
    v-model:open="isOpen"
    :ui="{ content: 'sm:max-w-4xl' }"
    title="Integration events"
    description="Review delivery attempts and retry eligible failures."
  >
    <template #body>
      <UAlert
        v-if="loadError"
        color="error"
        variant="subtle"
        title="Could not load integration events"
        :description="loadError"
        class="mb-4"
      />
      <p class="mb-3 text-xs text-neutral-500 sm:hidden">
        Swipe horizontally to see event details and actions.
      </p>
      <UTable
        :loading="integrationEventsLoading"
        :columns="columns"
        :data="integrationEvents"
        :ui="{ base: 'min-w-[44rem]' }"
        aria-label="Integration event history"
        tabindex="0"
        empty="No integration events yet."
      >
        <template #date-cell="{ row }">
          <span class="whitespace-nowrap">{{ row.original.date }}</span>
        </template>
        <template #submission-cell="{ row }">
          <span>{{ row.original.data?.submission_id ? `#${row.original.data.submission_id}` : '—' }}</span>
        </template>
        <template #status-cell="{ row }">
          <UBadge variant="subtle" :color="eventColor(row.original)" :label="eventLabel(row.original)" />
        </template>
        <template #data-cell="{ row }">
          <IntegrationEmailEvent v-if="row.original.email_tracking" :event="row.original" />
          <vue-json-pretty
            v-else-if="row.original.data && Object.keys(row.original.data).length > 0"
            :data="row.original.data"
            :collapsed-node-length="0"
            :show-length="true"
            :show-icon="true"
          />
          <span v-else>-</span>
        </template>
        <template #actions-cell="{ row }">
          <UButton
            v-if="row.original.can_retry"
            color="primary"
            variant="soft"
            size="sm"
            icon="i-heroicons-arrow-path"
            label="Retry"
            :aria-label="`Retry event ${row.original.id} for submission ${row.original.data?.submission_id}`"
            :loading="retryingEventId === row.original.id"
            :disabled="retryingEventId !== null || integrationEventsLoading"
            @click="eventToRetry = row.original"
          />
          <span
            v-else-if="row.original.status === 'Error' && row.original.retry_unavailable_reason"
            class="block max-w-56 whitespace-normal text-xs text-neutral-500"
          >
            {{ row.original.retry_unavailable_reason }}
          </span>
        </template>
      </UTable>
    </template>

    <template #footer>
      <UButton color="neutral" variant="outline" label="Refresh" :loading="integrationEventsLoading" :disabled="retryingEventId !== null" @click="fetchEvents" />
      <UButton color="neutral" variant="outline" label="Close" @click="close" />
    </template>
  </UModal>

  <UModal
    :open="eventToRetry !== null"
    title="Retry this integration?"
    :description="`Resend submission #${eventToRetry?.data?.submission_id ?? ''} using its latest saved answers and the current integration settings.`"
    @update:open="value => { if (!value) eventToRetry = null }"
  >
    <template #body>
      <p class="text-sm text-neutral-600">
        Only this integration will run. If the destination already processed the previous request, retrying may create a duplicate.
      </p>
    </template>
    <template #footer>
      <UButton color="neutral" variant="outline" label="Cancel" @click="eventToRetry = null" />
      <UButton icon="i-heroicons-arrow-path" label="Retry integration" :disabled="retryingEventId !== null" @click="retryEvent" />
    </template>
  </UModal>
</template>

<script setup>
import IntegrationEmailEvent from "./IntegrationEmailEvent.vue"
import { eventColor, eventLabel } from "~/lib/integration-email-status"
import VueJsonPretty from "vue-json-pretty"
import "vue-json-pretty/lib/styles.css"
import { formsApi } from "~/api/forms"

const props = defineProps({
  show: { type: Boolean, required: true },
  form: { type: Object, required: true },
  formIntegrationId: { type: Number, required: true },
})

const emit = defineEmits(["close"])
const alert = useAlert()
const isOpen = computed({
  get: () => props.show,
  set: (value) => { if (!value) close() },
})
const columns = [
  { accessorKey: "date", header: "Date" },
  { id: "submission", header: "Submission" },
  { accessorKey: "status", header: "Status" },
  { accessorKey: "data", header: "Info" },
  { id: "actions", header: "Action" },
]
const integrationEvents = ref([])
const integrationEventsLoading = ref(false)
const loadError = ref(null)
const retryingEventId = ref(null)
const eventToRetry = ref(null)
let fetchRequestId = 0

function fetchEvents() {
  if (!props.show) return Promise.resolve()

  const requestId = ++fetchRequestId
  integrationEventsLoading.value = true
  loadError.value = null
  return formsApi.integrations.events(props.form.id, props.formIntegrationId).then((data) => {
    if (requestId === fetchRequestId) integrationEvents.value = data
  }).catch(() => {
    if (requestId === fetchRequestId) loadError.value = "Please refresh to try again."
  }).finally(() => {
    if (requestId === fetchRequestId) integrationEventsLoading.value = false
  })
}

function retryEvent() {
  if (!eventToRetry.value || retryingEventId.value !== null) return

  const event = eventToRetry.value
  const formId = props.form.id
  const integrationId = props.formIntegrationId
  const isCurrentIntegration = () => props.show && props.form.id === formId && props.formIntegrationId === integrationId
  eventToRetry.value = null
  retryingEventId.value = event.id

  return formsApi.integrations.retryEvent(formId, integrationId, event.id).then((data) => {
    if (data.type === "error") {
      alert.error(data.message || "The retry failed. Review the latest event for details.")
    } else if (data.type === "success") {
      alert.success(data.message)
    } else {
      alert.warning(data.message || "Review the latest event to check the retry outcome.")
    }
    if (isCurrentIntegration()) {
      integrationEvents.value = integrationEvents.value.map((item) => item.data?.submission_id === event.data?.submission_id
        ? { ...item, can_retry: false, retry_unavailable_reason: "A newer attempt exists. Refresh the history to review it." }
        : item)
      return fetchEvents()
    }
  }).catch((error) => {
    alert.error(error.data?.message || "Could not confirm the retry outcome. Refresh the history before trying again.")
    if (isCurrentIntegration()) return fetchEvents()
  }).finally(() => {
    retryingEventId.value = null
  })
}

watch(
  () => [props.show, props.form.id, props.formIntegrationId],
  () => {
    ++fetchRequestId
    integrationEvents.value = []
    integrationEventsLoading.value = false
    loadError.value = null
    eventToRetry.value = null
    fetchEvents()
  },
  { immediate: true },
)

function close() {
  emit("close")
}
</script>
