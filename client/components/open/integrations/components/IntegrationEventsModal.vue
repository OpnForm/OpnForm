<template>
  <UModal
    v-model:open="isOpen"
    :ui="{ content: 'sm:max-w-4xl' }"
    title="Past Events"
  >
    <template #body>
      <UTable
        :loading="integrationEventsLoading"
        :columns="columns"
        :data="integrationEvents"
      >
        <template #status-cell="{ row }">
          <UBadge
            variant="subtle"
            :color="row.original.status === 'Success' ? 'success' : 'error'"
            :label="row.original.status"
          />
        </template>
        <template #data-cell="{ row }">
          <vue-json-pretty
            v-if="row.original.data && Object.keys(row.original.data).length > 0"
            :data="row.original.data"
            :collapsed-node-length="0"
            :show-length="true"
            :show-icon="true"
          />
          <span v-else>-</span>
        </template>
        <template #actions-cell="{ row }">
          <div
            v-if="row.original.can_retry"
            class="flex justify-end"
          >
            <UButton
              color="primary"
              variant="soft"
              size="sm"
              icon="i-heroicons-arrow-path"
              label="Retry"
              :loading="retryingEventId === row.original.id"
              :disabled="retryingEventId !== null && retryingEventId !== row.original.id"
              @click="retryEvent(row.original)"
            />
          </div>
        </template>
      </UTable>
    </template>

    <template #footer>
      <UButton
        color="neutral"
        variant="outline"
        @click="close"
        label="Close"
      />
    </template>
  </UModal>
</template>

<script setup>
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

// Modal state
const isOpen = computed({
  get() {
    return props.show
  },
  set(value) {
    if (!value) {
      close()
    }
  }
})
const columns = [
  { accessorKey: "date", header: "Date" },
  { accessorKey: "status", header: "Status" },
  { accessorKey: "data", header: "Info" },
  { id: "actions", header: "" },
]
const integrationEvents = ref([])
const integrationEventsLoading = ref(false)
const retryingEventId = ref(null)

watch(
  () => props.show,
  () => {
    fetchEvents()
  },
)

const fetchEvents = () => {
  if (props.show) {
    nextTick(() => {
      integrationEventsLoading.value = true
      integrationEvents.value = []
      formsApi.integrations.events(props.form.id, props.formIntegrationId).then((data) => {
        integrationEvents.value = data
        integrationEventsLoading.value = false
      })
    })
  }
}

const retryEvent = (event) => {
  alert.confirm("Retry this failed integration event?", () => {
    retryingEventId.value = event.id
    formsApi.integrations.retryEvent(props.form.id, props.formIntegrationId, event.id).then((data) => {
      if (data.event?.status === "Error") {
        alert.error(data.message || "Failed to retry event.")
      } else {
        alert.success(data.message)
      }
      fetchEvents()
    }).catch((error) => {
      alert.error(error.data?.message || "Failed to retry event.")
    }).finally(() => {
      retryingEventId.value = null
    })
  })
}

const close = () => {
  emit("close")
}
</script>
