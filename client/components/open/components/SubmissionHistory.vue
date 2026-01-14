<template>
  <UTooltip 
    text="Submission History" 
    :content="{ side: 'left' }" 
    arrow
  >
    <UButton
      :disabled="isLoading || !versions.length"
      :loading="isLoading"
      size="sm"
      color="neutral"
      variant="outline"
      class="disabled:text-neutral-500 shadow-none"
      icon="i-material-symbols-history"
      @click="isHistoryModalOpen=true"
    />
  </UTooltip>

  <UModal
    v-model:open="isHistoryModalOpen"
    :ui="{ content: 'sm:max-w-xl' }"
  >
    <template #header>
      <div class="flex items-center justify-between w-full">
        <div class="grow w-full">
          <h3 class="text-base font-semibold leading-6 text-neutral-900 dark:text-white">
            Submission History
          </h3>
          <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
            View and restore previous versions of this submission
          </p>
        </div>
        <UButton color="neutral" variant="ghost" icon="i-heroicons-x-mark-20-solid" class="-my-1" @click="isHistoryModalOpen = false" />
      </div>
    </template>

    <template #body>
      <div class="flow-root">
        <ul role="list" class="-mb-8">
          <li v-for="(version, index) in versions" :key="version.id">
            <div class="relative pb-8">
              <span v-if="index !== versions.length - 1" class="absolute left-4 top-4 -ml-px h-full w-0.5 bg-neutral-200 dark:bg-neutral-700" aria-hidden="true" />
              <div class="relative flex space-x-3">
                <div>
                  <img
                    v-if="version.user?.photo_url"
                    :src="version.user.photo_url"
                    :alt="version.user?.name || 'User'"
                    class="h-8 w-8 rounded-full bg-neutral-50 dark:bg-neutral-800 ring-2 ring-white dark:ring-neutral-900"
                  />
                  <div
                    v-else
                    class="flex h-8 w-8 items-center justify-center rounded-full bg-neutral-100 dark:bg-neutral-800 ring-2 ring-white dark:ring-neutral-900"
                  >
                    <span class="text-xs font-medium leading-none text-neutral-500 dark:text-neutral-400">
                      {{ (version.user?.name || 'U').charAt(0).toUpperCase() }}
                    </span>
                  </div>
                </div>
                
                <div class="flex min-w-0 flex-1 justify-between space-x-4 pt-1.5">
                  <div class="w-full">
                    <div class="flex justify-between items-start mb-2">
                      <div class="text-sm text-neutral-500 dark:text-neutral-400">
                        <span class="font-medium text-neutral-900 dark:text-white mr-1">
                          {{ version.user?.name || 'Unknown user' }}
                        </span>
                        
                        <UTooltip :text="formatDate(version.created_at)">
                          <span class="whitespace-nowrap">{{ timeAgo(version.created_at) }}</span>
                        </UTooltip>
                      </div>

                      <UButton
                        size="xs"
                        variant="soft"
                        color="neutral"
                        label="Restore"
                        icon="i-heroicons-arrow-path"
                        @click="onRestore(version)"
                      />
                    </div>

                    <div v-if="getTags(version).length > 0" class="flex flex-wrap gap-1.5 mt-1">
                      <span 
                        v-for="tag in getTags(version)" 
                        :key="tag.key"
                        class="inline-flex items-center rounded-md bg-neutral-50 dark:bg-neutral-800 px-2 py-1 text-xs font-medium text-neutral-600 dark:text-neutral-300 ring-1 ring-inset ring-neutral-500/10"
                      >
                        {{ tag.label }}
                      </span>
                    </div>
                    <p v-else class="text-xs text-neutral-500 italic mt-1">
                      No tracked changes
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </li>
        </ul>
      </div>
    </template>
  </UModal>
</template>

<script setup>
import { versionsApi } from '~/api/versions'
import { format, formatDistanceToNow } from 'date-fns'

const props = defineProps({
  form: { type: Object, required: true },
  submissionId: {
    type: Number,
    required: true,
  }
})

const { openSubscriptionModal } = useAppModals()
const isHistoryModalOpen = ref(false)
const versions = ref([])
const isLoading = ref(false)
const { submissionDetailById, invalidateSubmission } = useFormSubmissions()
const submissionDetailQuery = submissionDetailById(props.form.id, props.submissionId, { enabled: false })

onMounted(() => {
  if (props.submissionId) {
    fetchVersions()
  }
})

watch(() => props.submissionId, () => {
  fetchVersions()
})

const fetchVersions = async () => {
  isLoading.value = true
  try {
    const response = await versionsApi.list('submission', props.submissionId)
    versions.value = response || []
  } catch (error) {
    console.error('Failed to fetch submission versions:', error)
    versions.value = []
  } finally {
    isLoading.value = false
  }
}

const formatDate = (val) => {
  try {
    return format(new Date(val), 'MMM dd, yyyy h:mm a')
  } catch {
    return ''
  }
}

const timeAgo = (date) => {
  try {
    return formatDistanceToNow(new Date(date), { addSuffix: true })
  } catch {
    return ''
  }
}

const getTags = (version) => {
  const tags = []
  for (const [key] of Object.entries(version?.diff?.data || {})) {
    const label = getFieldName(key)
    tags.push({ key, label: `${label} changed` })
  }
  return tags
}

const getFieldName = (key) => {
  const allProperties = props.form.properties.concat(props.form.removed_properties)
  return allProperties.find(property => property.id === key)?.name || key
}

const onRestore = async (version) => {
  if (!props.form.is_pro) {
    openSubscriptionModal({ modal_title: 'Upgrade to restore submission history' })
    return
  }

  useAlert().confirm('Are you sure you want to restore this version?', () => restoreVersion(version))
}

const restoreVersion = async (version) => {
  try {
    await versionsApi.restore(version.id)
    submissionDetailQuery.refetch()
    invalidateSubmission(props.submissionId)
    useAlert().success('Submission restored successfully')
    await fetchVersions()
    isHistoryModalOpen.value = false
  } catch {
    useAlert().error('Failed to restore version')
  }
}
</script>
