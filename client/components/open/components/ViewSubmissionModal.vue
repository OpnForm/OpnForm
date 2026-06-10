<template>
  <UModal
    v-model:open="isModalOpen"
    :ui="{ content: 'sm:max-w-4xl', body: 'p-0!' }"
  >
    <template #header>
      <div class="flex flex-col gap-3 w-full min-w-0 sm:flex-row sm:items-center sm:justify-between sm:gap-2">
        <h2 class="font-semibold shrink-0">
          View Submission
        </h2>

        <div class="flex flex-wrap items-center gap-2 justify-between sm:justify-end min-w-0">
          <div class="flex items-center gap-1 relative z-20 shrink-0">
            <TrackClick
              name="edit_record_click"
              :properties="{ form_id: form.id, submission_id: submissionId }"
            >
              <UButton
                color="neutral"
                variant="outline"
                size="sm"
                icon="heroicons:pencil-square"
                aria-label="Edit"
                @click="showEditSubmissionModal = true"
              >
                <span class="hidden sm:inline">Edit</span>
              </UButton>
            </TrackClick>
            <UButton
              v-if="hasPdfTemplates"
              color="neutral"
              variant="outline"
              size="sm"
              icon="i-heroicons-arrow-down-tray-20-solid"
              aria-label="Download PDF"
              @click="downloadPdf"
            >
              <span class="hidden sm:inline">Download PDF</span>
            </UButton>
            <UDropdownMenu
              :items="getMenuItems"
              :content="{ side: 'bottom', align: 'end' }"
              arrow
            >
              <UButton
                color="neutral"
                variant="outline"
                icon="i-heroicons-ellipsis-horizontal"
                size="sm"
                aria-label="More actions"
              />
            </UDropdownMenu>
          </div>

          <UPagination
            v-model:page="currentPage"
            :items-per-page="1"
            :total="totalSubmissions"
            size="sm"
            :sibling-count="0"
            class="shrink-0"
            :ui="{
              wrapper: 'w-auto',
              list: 'gap-0',
              ellipsis: 'hidden',
              first: 'hidden',
              last: 'hidden'
            }"
          >
            <template #item="{ page, pageCount }">
              <span class="text-sm font-medium px-2 whitespace-nowrap">{{ page }} of {{ pageCount }}</span>
            </template>
          </UPagination>
        </div>
      </div>
    </template>

    <template #body>
      <OpenForm
        v-if="form"
        :form-manager="formManager"
        @submit="isModalOpen = false"
      >
        <template #submit-btn="{ loading }">
          <UButton
            class="mt-2"
            color="neutral"
            variant="outline"
            @click.prevent="isModalOpen = false"
            label="Close"
          />
        </template>
      </OpenForm>
    </template>
  </UModal>
  
  <DownloadPdf
    ref="downloadPdfRef"
    :form="form"
    :submission-id="submission.submission_id"
  />

  <SubmissionHistory 
    :show="showSubmissionHistoryModal"
    :form="form" 
    :submission-id="submission.id"
    @restored="onSubmissionRestored"
    @close="showSubmissionHistoryModal = false"
  />

  <EditSubmissionModal
    :show="showEditSubmissionModal"
    :form="form"
    :submission="submission"
    @close="onEditSubmissionModalClose"
  />
</template>

<script setup>
import DownloadPdf from "./DownloadPdf.vue"
import SubmissionHistory from "./SubmissionHistory.vue"
import EditSubmissionModal from "./EditSubmissionModal.vue"
import OpenForm from "../forms/OpenForm.vue"
import { FormMode } from "~/lib/forms/FormModeStrategy.js"
import { useFormManager } from '~/lib/forms/composables/useFormManager'

// Provide form size context for OpenForm (same pattern as OpenCompleteForm)
provide('formSize', ref('sm'))

const props = defineProps({
  submissionId: {
    type: Number,
    required: true,
  },
  data: {
    type: Array,
    default: () => [],
  },
  show: { type: Boolean, required: true },
  form: { type: Object, required: true },
  hasPdfTemplates: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(["close", "restored"])
const route = useRoute()
const router = useRouter()
const alert = useAlert()
const { copy } = useClipboard()
const downloadPdfRef = ref(null)

// Use form submissions composable for delete
const { deleteSubmission } = useFormSubmissions()
const deleteSubmissionMutation = deleteSubmission()

// Get menu items for submission dropdown
const getMenuItems = computed(() => {
  return [
    [
      {
        label: 'Copy link',
        icon: 'i-heroicons-clipboard-document-check-20-solid',
        onClick: copyLink
      },
      {
        label: 'Submission History',
        icon: 'i-heroicons-clock',
        onClick: () => {
          showSubmissionHistoryModal.value = true
        }
      }
    ],
    [
      {
        label: 'Delete submission',
        icon: 'i-heroicons-trash',
        onClick: () => onDeleteClick(),
        class: 'text-red-800 hover:bg-red-50 hover:text-red-600 group',
        iconClass: 'text-red-900 group-hover:text-red-800'
      }
    ]
  ]
})

// Modal state
const isModalOpen = computed({
  get() {
    return props.show
  },
  set(value) {
    if (!value) {
      emit("close")
      updateUrlWithSubmission(null)
    }
  }
})

const showEditSubmissionModal = ref(false)
const showSubmissionHistoryModal = ref(false)
const currentPage = ref(1)
const totalSubmissions = ref(props.data.length)
const submission = computed(() => props.data[currentPage.value - 1])

const syncCurrentPage = () => {
  const index = props.data.findIndex(s => Number(s.id) === Number(props.submissionId))
  currentPage.value = index >= 0 ? index + 1 : 1
  totalSubmissions.value = props.data.length
}

// Set up form manager with proper mode
let formManager = null
const setupFormManager = () => {
  if (!props.form) return null
  
  formManager = useFormManager(props.form, FormMode.READ_ONLY)
  
  return formManager
}

// Initialize form manager
formManager = setupFormManager()

const formManagerInit = () => {
  if (!submission.value) return

  formManager.initialize({
    skipPendingSubmission: true,
    skipUrlParams: true,
    defaultData: submission.value
  })
  updateUrlWithSubmission(submission.value.id)
}

watch(() => props.show, (newShow) => {
  if (newShow) {
    syncCurrentPage()
    nextTick(() => {
      formManagerInit()
    })
  }
}, { immediate: true })

watch(() => props.submissionId, () => {
  if (props.show) {
    syncCurrentPage()
    nextTick(() => {
      formManagerInit()
    })
  }
})

watch(() => props.data, () => {
  if (props.show) {
    syncCurrentPage()
    nextTick(() => {
      formManagerInit()
    })
  }
})

watch(currentPage, () => {
  formManagerInit()
})

const updateUrlWithSubmission = (submissionId) => {
  const query = { ...route.query }
  if(submissionId) {
    query.view = submissionId
  } else {
    delete query.view
  }
  router.replace({ query })
}

const onSubmissionRestored = (restoredData) => {
  // Re-initialize form manager with restored data
  formManager.initialize({
    skipPendingSubmission: true,
    skipUrlParams: true,
    defaultData: restoredData
  })
  // Emit to parent so it can update its data array
  emit('restored', restoredData)
}

const copyLink = () => {
  copy(window.location.href)
  alert.success("Copied!")
}

const downloadPdf = () => {
  if (downloadPdfRef.value) {
    downloadPdfRef.value.handleDownload()
  } else {
    alert.error("Something went wrong!")
  }
}

const onDeleteClick = () => {
  alert.confirm("Do you really want to delete this submission?", deleteRecord)
}

const deleteRecord = () => {
  const currentSubmissionId = submission.value?.id
  if (!currentSubmissionId) {
    alert.error("Something went wrong!")
    return
  }

  deleteSubmissionMutation.mutateAsync({ 
    formId: props.form.id, 
    submissionId: currentSubmissionId
  }).then((data) => {
    if (data.type === "success") {
      alert.success(data.message)
      isModalOpen.value = false
    } else {
      alert.error("Something went wrong!")
    }
  }).catch((error) => {
    alert.error(error.data?.message || "Something went wrong!")
  })
}

const onEditSubmissionModalClose = () => {
  showEditSubmissionModal.value = false
  formManagerInit()
}
</script>
