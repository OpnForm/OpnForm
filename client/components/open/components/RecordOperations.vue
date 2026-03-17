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
        icon="heroicons:arrows-pointing-out"
        @click="showViewSubmissionModal = true"
      />
    </TrackClick>
  </div>
  
  <ViewSubmissionModal
    :show="showViewSubmissionModal"
    :form="form"
    :data="data"
    :submission-id="submissionId"
    @close="showViewSubmissionModal = false"
  />
</template>

<script setup>
import ViewSubmissionModal from "./ViewSubmissionModal.vue"
import TrackClick from "~/components/global/TrackClick.vue"

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
const showViewSubmissionModal = ref(false)

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
