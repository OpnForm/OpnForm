<template>
  <div :class="containerClass">
    <OpenCompleteForm
      v-if="form"
      :form="previewForm"
      :mode="FormMode.TEMPLATE"
      class="w-full mt-4"
    />
    <div v-else class="p-4 text-sm text-neutral-500">Loading preview...</div>
  </div>
</template>

<script setup>
import OpenCompleteForm from '~/components/open/forms/OpenCompleteForm.vue'
import { FormMode } from '~/lib/forms/FormModeStrategy.js'

definePageMeta({
  layout: 'empty',
  middleware: ['chatgpt-preview'],
})

const route = useRoute()
const gptChatId = Array.isArray(route.params.gpt_chat_id)
  ? route.params.gpt_chat_id[0]
  : route.params.gpt_chat_id

const { detail } = useChatGptDrafts()
const { data, error, suspense, isFetched } = detail(gptChatId, {
  queryKey: ['chatgpt', 'drafts', gptChatId, route.query.v ?? 'latest'],
  retry: false,
  refetchOnWindowFocus: false,
})

await suspense()

if (error.value || (isFetched.value && !data.value?.draft?.form_state)) {
  throw createError({ statusCode: 404, statusMessage: 'Page Not Found' })
}

const form = computed(() => data.value?.draft?.form_state ?? null)
const previewForm = computed(() => {
  const base = form.value
  if (!base) return null
  return {
    ...base,
    no_branding: true,
    re_fillable: true,
    re_fill_button_text: base.re_fill_button_text || 'Restart',
  }
})
const isChatGptEmbed = computed(() => route.query.embed === 'chatgpt')
const containerClass = computed(() => (
  isChatGptEmbed.value
    ? 'h-auto min-h-0 bg-transparent overflow-auto'
    : 'min-h-screen bg-white'
))

useOpnSeoMeta({
  title: 'ChatGPT Draft Preview',
})

useHead(() => {
  if (!isChatGptEmbed.value) {
    return {}
  }

  return {
    htmlAttrs: {
      'data-chatgpt-embed': '1',
    },
    style: [
      {
        key: 'chatgpt-preview-embed-overrides',
        children: `
html[data-chatgpt-embed="1"],
html[data-chatgpt-embed="1"] body,
html[data-chatgpt-embed="1"] #__nuxt,
html[data-chatgpt-embed="1"] #app {
  min-height: 0 !important;
  height: auto !important;
}
html[data-chatgpt-embed="1"] body {
  overflow-x: hidden !important;
}
html[data-chatgpt-embed="1"] .open-complete-form {
  min-height: 0 !important;
}
        `,
      },
    ],
  }
})
</script>
