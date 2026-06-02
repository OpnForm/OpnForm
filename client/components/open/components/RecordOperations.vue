<template>
  <div class="flex gap-1">
    <TrackClick
      name="edit_record_click"
      :properties="{ form_id: form.id, submission_id: submissionId }"
    >
      <UButton
        size="xs"
        color="neutral"
        variant="outline"
        icon="heroicons:pencil-square"
        @click="showEditSubmissionModal = true"
      />
    </TrackClick>
    <UButton
      v-if="hasPdfTemplates"
      size="xs"
      color="neutral"
      variant="outline"
      icon="i-heroicons-arrow-down-tray-20-solid"
      aria-label="Download PDF"
      @click="downloadPdf"
    />
    <TrackClick
      name="view_record_click"
      :properties="{ form_id: form.id, submission_id: submissionId }"
    >
      <UButton
        size="xs"
        color="neutral"
        variant="outline"
        icon="heroicons:arrows-pointing-out"
        @click="showViewSubmissionModal = true"
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

  <DownloadPdf
    ref="downloadPdfRef"
    :form="form"
    :submission-id="submission?.submission_id"
  />
</template>

<script setup>
import DownloadPdf from "./DownloadPdf.vue"
import EditSubmissionModal from "./EditSubmissionModal.vue"
import ViewSubmissionModal from "./ViewSubmissionModal.vue"
import TrackClick from "~/components/global/TrackClick.vue"
import { usePdfTemplates } from '~/composables/query/forms/usePdfTemplates'

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

const route = useRoute()
const alert = useAlert()
const showEditSubmissionModal = ref(false)
const showViewSubmissionModal = ref(false)
const downloadPdfRef = ref(null)

const submission = computed(() => props.data.find(s => s.id === props.submissionId))

const { list: listPdfTemplates } = usePdfTemplates()
const { data: pdfTemplatesData } = listPdfTemplates(() => props.form?.id)
const hasPdfTemplates = computed(() => (pdfTemplatesData.value?.data?.length ?? 0) > 0)

const downloadPdf = () => {
  if (downloadPdfRef.value) {
    downloadPdfRef.value.handleDownload()
  } else {
    alert.error("Something went wrong!")
  }
}

// Auto-open view modal if URL view param matches THIS component's submission ID (only on mount)
onMounted(() => {
  const urlViewId = route.query.view
  if (urlViewId && parseInt(urlViewId) === props.submissionId) {
    nextTick(() => {
      showViewSubmissionModal.value = true
    })
  }
})

</script>
