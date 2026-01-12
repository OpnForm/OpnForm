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
    title="Submission History"
    description="View the history of changes to your submission"
    :ui="{ content: 'sm:max-w-3xl' }"
    @close="isHistoryModalOpen = false"
  >
    <template #body>
      <div class="space-y-2">
        <div
          v-for="(version, index) in versions"
          :key="version.id"
          class="flex items-start gap-3 py-1 border-b"
          :class="{ '!border-b-0': index === versions.length - 1 }"
        >
          <img
            v-if="version.user?.photo_url"
            :src="version.user.photo_url"
            :alt="version.user?.name || 'User'"
            class="w-8 h-8 rounded-full"
          />
          <div
            v-else
            class="w-8 h-8 rounded-full bg-neutral-200 flex items-center justify-center text-neutral-500 text-xs font-medium"
          >
            {{ (version.user?.name || 'U').charAt(0).toUpperCase() }}
          </div>
          <div class="min-w-0 flex-1">
            <div class="flex items-center justify-between gap-2">
              <div class="truncate">
                <div class="text-sm font-medium text-neutral-900">
                  {{ version.user?.name || 'Unknown user' }}
                </div>
                <div class="text-xs text-neutral-500">
                  {{ formatDate(version.created_at) }}
                </div>
              </div>
              <UButton
                size="sm"
                variant="outline"
                label="Restore"
                icon="i-heroicons-arrow-path"
                @click="onRestore(version)"
              />
            </div>

            <div class="mt-2 text-sm text-neutral-800">
              <template v-if="getTags(version).length > 0">
                {{ getTags(version).length }} changes made
              </template>
              <template v-else>
                No changes made
              </template>
            </div>

            <div class="mt-2 flex flex-wrap gap-2">
              <UBadge
                v-for="tag in getTags(version)"
                :key="tag.key"
                size="sm"
                variant="subtle"
                color="neutral"
              >
                {{ tag.label }}
              </UBadge>
            </div>
          </div>
        </div>
      </div>
    </template>
  </UModal>
</template>

<script setup>
import { versionsApi } from '~/api/versions'
import { format } from 'date-fns'

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
    return format(new Date(val), 'MMM dd h:mm a')
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
