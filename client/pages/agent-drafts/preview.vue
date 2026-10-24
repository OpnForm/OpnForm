<template>
  <div
    class="relative bg-transparent"
    :class="isEmbedded && isFocused ? 'h-screen' : (!isEmbedded ? 'min-h-screen pt-2' : '')"
  >
    <div :class="isEmbedded && isFocused ? 'h-full w-full' : 'mx-auto max-w-5xl'">
      <header
        v-if="!isEmbedded || isSubmitted"
        class="flex min-h-8 items-center gap-3"
        :class="isEmbedded ? 'absolute right-4 top-4 z-30' : 'mb-2'"
      >
        <p v-if="!isEmbedded" class="flex items-center gap-2 text-sm font-medium text-neutral-700">
          <span class="size-2 rounded-full bg-blue-500" aria-hidden="true" />
          Private preview
        </p>
        <UButton
          v-if="isSubmitted"
          color="neutral"
          variant="outline"
          size="sm"
          icon="i-lucide-rotate-ccw"
          class="ml-auto"
          :loading="isResetting"
          :disabled="isResetting"
          aria-label="Reset form"
          @click="resetForm"
        >
          Reset form
        </UButton>
      </header>

      <div v-if="loading" class="rounded-xl border bg-white p-12 text-center">
        <Loader class="mx-auto h-6 w-6 text-blue-500" />
      </div>
      <UAlert
        v-else-if="error"
        color="error"
        :title="errorTitle"
        :description="error"
      />
      <div
        v-else
        class="overflow-hidden"
        :class="isEmbedded
          ? (isFocused ? 'h-full bg-transparent' : 'bg-transparent')
          : 'min-h-[650px] rounded-xl border border-neutral-200 bg-white shadow-sm'"
      >
        <OpenCompleteForm
          ref="formPreview"
          :form="form"
          :mode="FormMode.TEST"
          class="w-full"
          :class="{
            'h-full': isEmbedded && isFocused,
            'min-h-[650px]': !isEmbedded,
            'pt-3 sm:pt-4': !isFocused,
          }"
          @submitted="isSubmitted = true"
          @restarted="isSubmitted = false"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import OpenCompleteForm from '~/components/open/forms/OpenCompleteForm.vue'
import { FormMode } from '~/lib/forms/FormModeStrategy.js'

definePageMeta({ layout: 'empty' })
useOpnSeoMeta({ title: 'Private form draft preview' })

const route = useRoute()
const config = useRuntimeConfig()
const alert = useAlert()
const loading = ref(true)
const errorTitle = ref('This preview is unavailable')
const error = ref(null)
const form = ref(null)
const formPreview = ref(null)
const isSubmitted = ref(false)
const isResetting = ref(false)
const isEmbedded = computed(() => route.query.embedded === '1')
const isFocused = computed(() => form.value?.presentation_style === 'focused')
provide('disableCustomCodeExecution', true)

useHead(() => ({
  script: [{ src: '/widgets/iframeResizer.contentWindow.min.js' }],
  style: isEmbedded.value
    ? [{ children: 'html, body, #__nuxt { background: transparent !important; }' }]
    : [],
}))

const resetForm = () => {
  if (!formPreview.value?.restart || isResetting.value) return

  isResetting.value = true
  formPreview.value.restart()
    .catch(() => {
      alert.error('The form could not be reset. Please try again.')
    })
    .finally(() => {
      isResetting.value = false
    })
}

onMounted(() => {
  try {
    const source = new URL(String(route.query.source || ''))
    const configuredApi = config.public.apiBase ? new URL(config.public.apiBase, window.location.origin) : null
    const expectedOrigin = configuredApi?.origin || window.location.origin
    if (source.origin !== expectedOrigin || !source.pathname.includes('/agent-drafts/preview/')) {
      throw new Error('The preview link is invalid.')
    }

    $fetch(source.toString()).then((response) => {
      form.value = {
        ...response.draft.definition,
        no_branding: true,
        plan_tier: 'pro',
        is_trialing: false,
        max_file_size: 10,
        workspace: { plan_tier: 'pro', features: [], limits: {} },
      }
    }).catch(() => {
      errorTitle.value = 'This preview link has expired'
      error.value = 'Your draft is usually still available for seven days. Ask your AI assistant: “Generate a new preview of this form.”'
    }).finally(() => {
      loading.value = false
    })
  } catch (exception) {
    error.value = exception.message || 'The preview link is invalid.'
    loading.value = false
  }
})
</script>
