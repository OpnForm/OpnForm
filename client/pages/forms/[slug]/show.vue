<template>
  <div class="flex flex-col sm:flex-row h-screen bg-white">
    <!-- Form Sidebar - Always shown -->
    <FormSidebar :form="form" :loading="isLoading" />
    
    <!-- Main content area -->
    <main class="flex-1 sm:pl-58 overflow-hidden">
      <div class="flex flex-col h-full">
        <!-- Loading State -->
        <div v-if="isLoading || !isFormFinished" class="flex-1 bg-white">
          <!-- Top Bar Skeleton -->
          <div class="bg-white p-4">
            <div class="max-w-4xl mx-auto">
              <!-- Form Title and Actions Skeleton -->
              <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex-1 min-w-0">
                  <USkeleton class="h-7 w-64 mb-2" />
                  <div class="flex flex-wrap items-center gap-2">
                    <USkeleton class="h-5 w-16" />
                    <USkeleton class="h-5 w-16" />
                    <USkeleton class="h-5 w-32" />
                  </div>
                </div>
                
                <!-- Action Buttons Skeleton -->
                <div class="hidden md:flex gap-2">
                  <USkeleton class="h-9 w-20" />
                  <USkeleton class="h-9 w-20" />
                  <USkeleton class="h-9 w-8" />
                </div>
              </div>
              
              <!-- Status Badges Skeleton -->
              <div class="flex flex-wrap gap-2 mt-2">
                <USkeleton class="h-4 w-16" />
                <USkeleton class="h-4 w-20" />
              </div>
            </div>
          </div>

          <!-- Page Content Skeleton -->
          <div :class="['flex-1 bg-white p-4', { 'overflow-y-auto': !isSubmissionsPage }]">
            <div class="max-w-4xl mx-auto space-y-4">
              <USkeleton class="h-8 w-48" />
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <USkeleton class="h-32 w-full" />
                <USkeleton class="h-32 w-full" />
                <USkeleton class="h-32 w-full" />
              </div>
              <USkeleton class="h-64 w-full" />
            </div>
          </div>
        </div>

        <!-- Loaded Content -->
        <template v-else-if="form">
          <!-- Top Bar (Non-Sticky) -->
          <div class="bg-white p-4 pb-0">
            <div class="max-w-4xl mx-auto">
              <!-- Form Title and Actions -->
              <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex-1 min-w-0 hidden sm:block">
                  <h1 class="text-xl font-semibold text-neutral-900 truncate ">
                    {{ form.title }}
                  </h1>
                </div>
                
                <!-- Action Buttons -->
                <div class="flex gap-2">
                  <UButton
                    v-if="form.visibility === 'draft'"
                    color="neutral"
                    variant="outline"
                    class="hover:no-underline"
                    icon="i-heroicons-eye"
                    @click="showDraftFormWarningNotification"
                  >
                    <span class="hidden sm:inline">Open <span class="hidden md:inline">form</span></span>
                  </UButton>
                  <TrackClick
                    v-else
                    name="view_form_click"
                    :properties="{form_id:form.id, form_slug:form.slug}"
                  >
                    <UButton
                      target="_blank"
                      :to="form.share_url"
                      color="neutral"
                      variant="outline"
                      class="hover:no-underline"
                      icon="i-heroicons-arrow-top-right-on-square"
                    >
                      <span class="hidden sm:inline">Open <span class="hidden md:inline">form</span></span>
                    </UButton>
                  </TrackClick>
                  <TrackClick
                    v-if="!workspace?.is_readonly"
                    name="edit_form_click"
                    :properties="{form_id: form.id, form_slug: form.slug}"
                  >
                    <UButton
                      color="primary"
                      icon="i-heroicons-pencil"
                      class="hover:no-underline"
                      :to="{ name: 'forms-slug-edit', params: { slug: form.slug } }"
                    >
                      Edit <span class="hidden md:inline">form</span>
                    </UButton>
                  </TrackClick>
                  <extra-menu
                    v-if="!workspace?.is_readonly"
                    :form="form"
                    portal="#form-show-portals"
                  />
                </div>
              </div>

              <div class="flex flex-wrap items-center gap-2 text-neutral-500 text-xs mt-2 sm:mt-0">
                <UTooltip :text="`${formatNumberWithCommas(form.views_count)} views`">
                  <div class="flex items-center gap-1">
                    <UIcon name="i-heroicons-eye" />
                    <span>{{ formatNumber(form.views_count) }}</span>
                  </div>
                </UTooltip>

                <UTooltip :text="`${formatNumberWithCommas(form.submissions_count)} submissions`">
                  <div class="flex items-center gap-1">
                    <UIcon name="i-heroicons-document-text" />
                    <span>{{ formatNumber(form.submissions_count) }}</span>
                  </div>
                </UTooltip>

                <span class="whitespace-nowrap">Edited {{ form.last_edited_human }}</span>
              </div>
              
              <!-- Status Badges and Form Cleanings -->
              <div class="flex flex-wrap gap-2 flex-col mt-2">
                <FormStatusBadges size="sm" :form="form" />
                <FormCleanings :form="form" />
              </div>
            </div>
          </div>

          <!-- Page Content -->
          <div :class="['flex-1 bg-white', { 'overflow-y-auto': !isSubmissionsPage }]">
            <NuxtPage :form="form" />
          </div>
        </template>

        <!-- Not Found State -->
        <div
          v-else
          class="flex items-center justify-center h-screen bg-white"
        >
          <div class="flex flex-col gap-4 items-center justify-center">
            <h2 class="text-lg font-semibold text-neutral-900">Form not found</h2>
            <p class="text-neutral-500">The form you're looking for doesn't exist or has been deleted.</p>
            <div class="">
            <UButton
              variant="soft"
              class="hover:no-underline"
              icon="i-heroicons-arrow-left"
              :to="{ name: 'home' }"
            >
                Go to Dashboard
              </UButton>
            </div>
          </div>
        </div>

        <div id="form-show-portals" class="z-20" />
      </div>
    </main>
  </div>
</template>

<script setup>
import { formatNumber, formatNumberWithCommas } from "~/lib/utils.js"
import FormSidebar from "../../../components/layouts/FormSidebar.vue"
import ExtraMenu from "../../../components/pages/forms/show/ExtraMenu.vue"
import FormCleanings from "../../../components/pages/forms/show/FormCleanings.vue"
import FormStatusBadges from "../../../components/open/forms/components/FormStatusBadges.vue"
import TrackClick from "../../../components/global/TrackClick.vue"

definePageMeta({
  layout: "empty",
})

useOpnSeoMeta({
  title: "Home",
})

// Composables
const route = useRoute()
const workingFormStore = useWorkingFormStore()
const { detail: formDetail } = useForms()

const slug = route.params.slug

// Get current workspace
const { current: workspaceRef } = useCurrentWorkspace()
const workspace = workspaceRef.value

// Get form by slug using private authenticated endpoint
const { data: form, isLoading: isFormLoading, isFetched: isFormFinished } = formDetail(slug, { usePrivate: true })

// Combined loading state
const isLoading = computed(() => isFormLoading.value)

// Disable sticky top-bar behaviour on the submissions page only
const isSubmissionsPage = computed(() => route.name?.includes('submissions'))

// Update working form store when form changes
watch(
  () => form.value,
  (newForm) => {
    workingFormStore.reset()
    if (newForm) {
      workingFormStore.set(newForm)
    }
  },
  { immediate: true }
)

const showDraftFormWarningNotification = () => {
  useAlert().warning(
    "This form is currently in Draft mode and is not publicly accessible, You can change the form status on the edit form page.",
  )
}
</script>
