<template>
  <div class="min-h-screen bg-neutral-50 p-4 sm:p-8">
    <div class="mx-auto max-w-5xl">
      <div class="mb-4 flex items-center justify-between">
        <div>
          <p class="text-sm font-medium text-blue-600">Private draft preview</p>
          <h1 class="text-xl font-semibold text-neutral-900">{{ form?.title || 'OpnForm draft' }}</h1>
        </div>
        <UBadge color="neutral" variant="subtle">Expires in 15 minutes</UBadge>
      </div>

      <div v-if="loading" class="rounded-xl border bg-white p-12 text-center">
        <Loader class="mx-auto h-6 w-6 text-blue-500" />
      </div>
      <UAlert
        v-else-if="error"
        color="error"
        title="This preview is unavailable"
        :description="error"
      />
      <div v-else class="min-h-[650px] overflow-hidden rounded-xl border bg-white shadow-sm">
        <OpenCompleteForm
          :form="form"
          :mode="FormMode.PREVIEW"
          class="min-h-[650px] w-full"
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
const loading = ref(true)
const error = ref(null)
const form = ref(null)
provide('disableCustomCodeExecution', true)

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
        plan_tier: 'pro',
        is_trialing: false,
        max_file_size: 10,
        workspace: { plan_tier: 'pro', features: [], limits: {} },
      }
    }).catch(() => {
      error.value = 'The signed preview link has expired or is no longer valid.'
    }).finally(() => {
      loading.value = false
    })
  } catch (exception) {
    error.value = exception.message || 'The preview link is invalid.'
    loading.value = false
  }
})
</script>
