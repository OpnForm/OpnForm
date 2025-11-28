<template>
  <div class="flex flex-col h-full bg-white">
    <div class="sticky top-0 z-50 bg-white border-b border-neutral-200 p-2 sm:px-4">
      <div class="max-w-4xl mx-auto flex items-center justify-between flex-wrap flex-shrink-0 gap-2 px-2 sm:px-0">
          <h1 class="text-lg font-semibold text-neutral-900">My Form Templates</h1>
          <div class="flex items-center gap-2 w-full justify-end sm:w-auto">
            <UButton
              to="/templates"
              variant="outline"
              icon="i-heroicons-eye"
              label="View All Templates"
            />
            <UButton
              @click="openTemplateGuide"
              variant="outline"
              color="neutral"
              icon="i-heroicons-question-mark-circle"
              label="How to Create"
            />
          </div>
      </div>
    </div>

    <div class="flex-1 overflow-y-auto p-4">
      <div class="max-w-4xl mx-auto">
        <VTransition name="fade">
          
            <templates-list
              v-if="loading || templates?.length > 0"
              grid-classes="grid-cols-1 mt-8 sm:grid-cols-2 lg:grid-cols-3"
              :templates="templates"
              :loading="loading"
              :show-types="false"
              :show-industries="false"
            />

          <div v-else class="text-center py-16 px-4">
            <UIcon name="i-heroicons-document-duplicate" class="h-12 w-12 text-neutral-400 mx-auto" />
            <h3 class="mt-4 text-lg font-semibold text-neutral-900">
              No templates yet
            </h3>
            <p class="mt-1 text-sm text-neutral-500">
              You haven't created any templates yet. Create forms and share them as templates!
            </p>
            <UButton
              class="mt-4"
              @click="openTemplateGuide"
              variant="outline"
              color="neutral"
              icon="i-heroicons-question-mark-circle"
              label="How to Create"
            />
          </div>
        </VTransition>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useCrisp } from '~/composables/useCrisp'

definePageMeta({
  middleware: "auth",
  layout: "dashboard",
})

useOpnSeoMeta({
  title: "My Templates",
  description:
    "Our collection of beautiful templates to create your own forms!",
})

const { list } = useTemplates()
const { openHelpdeskArticle } = useCrisp()

const { data: templates, isLoading: loading } = list({
  params: { onlymy: true }
})

const openTemplateGuide = () => {
  openHelpdeskArticle('how-to-create-an-opnform-template-1fn84i4')
}
</script>
