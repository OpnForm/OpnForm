import { computed, ref } from 'vue'
import {
  extractAttribution,
  mergeAttribution,
  sanitizeAttribution,
} from '~/lib/forms/submissionAttribution'

export function useSubmissionAttribution() {
  const iframeAttribution = ref({})
  const parentAttribution = ref({})
  let iframeAttributionCaptured = false

  const attribution = computed(() => mergeAttribution(
    iframeAttribution.value,
    parentAttribution.value,
  ))

  const captureIframeAttribution = (searchParams) => {
    if (iframeAttributionCaptured) return

    iframeAttribution.value = extractAttribution(searchParams)
    iframeAttributionCaptured = true
  }

  const mergeParentAttribution = (parameters) => {
    parentAttribution.value = {
      ...parentAttribution.value,
      ...sanitizeAttribution(parameters),
    }
  }

  return {
    attribution,
    captureIframeAttribution,
    mergeParentAttribution,
  }
}
