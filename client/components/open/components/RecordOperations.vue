<template>
  <div class="flex gap-1">
    <TrackClick
      name="view_record_click"
      :properties="{}"
    >
      <UButton
        size="xs"
        color="neutral"
        variant="outline"
        icon="heroicons:eye"
        @click="showViewSubmissionModal = true"
      />
    </TrackClick>
    <TrackClick
      name="edit_record_click"
      :properties="{}"
    >
      <UButton
        size="xs"
        color="neutral"
        variant="outline"
        icon="heroicons:pencil-square"
        @click="showEditSubmissionModal = true"
      />
    </TrackClick>
    <TrackClick
      name="delete_record_click"
      :properties="{}"
    >
      <UButton
        size="xs"
        color="error"
        variant="outline"
        icon="heroicons:trash"
        @click="onDeleteClick"
      />
    </TrackClick>
  </div>
  
  <EditSubmissionModal
    :show="showEditSubmissionModal"
    :form="form"
    :submission="submission"
    @close="showEditSubmissionModal = false"
  />

  <ViewSubmissionModal
    :show="showViewSubmissionModal"
    :form="form"
    :data="data"
    :submission-id="submissionId"
    @close="showViewSubmissionModal = false"
  />
</template>

<script setup>
import EditSubmissionModal from "./EditSubmissionModal.vue"
import ViewSubmissionModal from "./ViewSubmissionModal.vue"
import TrackClick from "~/components/global/TrackClick.vue"
import { useFormSubmissions } from "~/composables/query/forms/useFormSubmissions"

const props = defineProps({
  form: {
    type: Object,
    required: true,
  },
  submissionId: {
    type: Number,
    required: true,
  },
  data: {
    type: Array,
    default: () => [],
  },
})

const alert = useAlert()
const route = useRoute()
const showEditSubmissionModal = ref(false)
const showViewSubmissionModal = ref(false)

// Use form submissions composable for cache management
const { deleteSubmission } = useFormSubmissions()
const deleteSubmissionMutation = deleteSubmission()

const submission = computed(() => props.data.find(s => s.id === props.submissionId))

// Auto-open view modal if URL view param matches THIS component's submission ID (only on mount)
onMounted(() => {
  const urlViewId = route.query.view
  if (urlViewId && parseInt(urlViewId) === props.submissionId) {
    nextTick(() => {
      showViewSubmissionModal.value = true
    })
  }
})

const onDeleteClick = () => {
  alert.confirm("Do you really want to delete this record?", deleteRecord)
}

const deleteRecord = () => {
  deleteSubmissionMutation.mutateAsync({ 
    formId: props.form.id, 
    submissionId: submission.value.id 
  }).then((data) => {
    if (data.type === "success") {
      alert.success(data.message)
    } else {
      alert.error("Something went wrong!")
    }
  }).catch((error) => {
    alert.error(error.data?.message || "Something went wrong!")
  })
}
</script>
