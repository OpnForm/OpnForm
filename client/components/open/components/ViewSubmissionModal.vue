<template>
  <UModal
    v-model:open="isModalOpen"
    :ui="{ content: 'sm:max-w-4xl', body: 'p-0!' }"
  >
    <template #header>
      <div class="flex items-center justify-between w-full">
        <h2 class="font-semibold">
          View Submission
        </h2>
        <UPagination
          v-model:page="currentPage"
          :items-per-page="1"
          :total="totalSubmissions"
          size="sm"
          :sibling-count="0"
          :ui="{
            wrapper: 'w-auto',
            list: 'gap-0',
            ellipsis: 'hidden',
            first: 'hidden',
            last: 'hidden'
          }"
        >
          <template #item="{ page, pageCount }">
            <span class="text-sm font-medium px-2">{{ page }} of {{ pageCount }}</span>
          </template>
        </UPagination>
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
</template>

<script setup>
import OpenForm from "../forms/OpenForm.vue"
import { FormMode } from "~/lib/forms/FormModeStrategy.js"
import { useFormManager } from '~/lib/forms/composables/useFormManager'

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
  form: { type: Object, required: true }
})

const emit = defineEmits(["close"])
const route = useRoute()
const router = useRouter()

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

const currentPage = ref(props.data.findIndex(s => s.id === props.submissionId) + 1)
const totalSubmissions = ref(props.data.length)
const submission = computed(() => props.data[currentPage.value - 1])

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
  formManager.initialize({
    skipPendingSubmission: true,
    skipUrlParams: true,
    defaultData: submission.value
  })
  updateUrlWithSubmission(submission.value.id)
}

watch(() => props.show, (newShow) => {
  if (newShow) {
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
</script>