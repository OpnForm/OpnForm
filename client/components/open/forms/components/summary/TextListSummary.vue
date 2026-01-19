<template>
  <div class="flex flex-col">
    <div class="divide-y divide-neutral-100">
      <div
        v-for="(item, index) in displayedValues"
        :key="index"
        class="px-4 py-3 flex items-start gap-3 hover:bg-neutral-50 transition-colors group"
      >
        <!-- Icon based on type -->
        <div class="mt-0.5 flex-shrink-0">
          <UIcon 
            v-if="isFileType" 
            name="i-heroicons-paper-clip" 
            class="w-4 h-4 text-neutral-400" 
          />
          <UIcon 
            v-else-if="fieldType === 'email'" 
            name="i-heroicons-envelope" 
            class="w-4 h-4 text-neutral-400" 
          />
          <UIcon 
            v-else-if="fieldType === 'url'" 
            name="i-heroicons-link" 
            class="w-4 h-4 text-neutral-400" 
          />
          <UIcon 
            v-else 
            name="i-heroicons-chat-bubble-left-ellipsis" 
            class="w-4 h-4 text-neutral-400" 
          />
        </div>

        <div class="flex-1 min-w-0">
          <!-- File field -->
          <a
            v-if="isFileType"
            :href="item.value"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex items-center gap-2 text-sm font-medium text-neutral-700 hover:text-neutral-900 underline decoration-neutral-300 hover:decoration-neutral-900 underline-offset-2 transition-all"
          >
            {{ getDisplayFileName(item.value) }}
            <UIcon name="i-heroicons-arrow-top-right-on-square" class="w-3 h-3" />
          </a>

          <!-- URL field -->
          <a
            v-else-if="fieldType === 'url'"
            :href="item.value"
            target="_blank"
            rel="noopener noreferrer"
            class="text-sm text-blue-600 hover:text-blue-800 hover:underline truncate block"
          >
            {{ item.value }}
          </a>

          <!-- Email field -->
          <a
            v-else-if="fieldType === 'email'"
            :href="'mailto:' + item.value"
            class="text-sm text-neutral-700 hover:text-neutral-900 truncate block"
          >
            {{ item.value }}
          </a>

          <!-- Rich text field -->
          <div
            v-else-if="fieldType === 'rich_text'"
            class="text-sm text-neutral-700 prose prose-sm max-w-none line-clamp-3"
            v-html="item.value"
          />

          <!-- Default text -->
          <span v-else class="text-sm text-neutral-700 break-words block">{{ item.value }}</span>
          
          <!-- Timestamp (if available in future) -->
          <!-- <div class="text-xs text-neutral-400 mt-1">2 hours ago</div> -->
        </div>

        <!-- Actions -->
        <UDropdownMenu :items="menuItems(item)" :popper="{ placement: 'bottom-end' }">
          <UButton
            color="neutral"
            variant="ghost"
            icon="i-heroicons-ellipsis-horizontal"
            size="xs"
            class="opacity-0 group-hover:opacity-100 transition-opacity"
          />
        </UDropdownMenu>
      </div>
    </div>

    <!-- Empty state -->
    <div
      v-if="displayedValues.length === 0"
      class="flex flex-col items-center justify-center py-8 text-neutral-400"
    >
      <UIcon 
        :name="isFileType ? 'i-heroicons-document' : 'i-heroicons-chat-bubble-bottom-center-text'" 
        class="w-8 h-8 mb-2 opacity-50" 
      />
      <span class="text-sm">{{ isFileType ? 'No files uploaded' : 'No responses' }}</span>
    </div>

    <!-- Load More Button -->
    <div v-if="hasMore" class="p-3 border-t border-neutral-100 bg-neutral-50/50">
      <UButton
        color="neutral"
        variant="soft"
        block
        size="sm"
        :loading="isLoadingMore"
        @click="loadMore"
      >
        <template v-if="!isLoadingMore">
          Load more responses ({{ remainingCount }} remaining)
        </template>
      </UButton>
    </div>
  </div>
</template>

<script setup>
import { useFormSummary } from "~/composables/query/forms/useFormSummary"

const props = defineProps({
  field: { type: Object, required: true },
  form: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
})

const { fieldValues } = useFormSummary()

const fieldType = computed(() => props.field.type)
const isFileType = computed(() => ['files', 'signature'].includes(fieldType.value))

const displayedValues = ref([...(props.field.data?.values || [])])
const nextOffset = ref(props.field.data?.next_offset || 10)
const hasMore = ref(props.field.data?.has_more || false)
const totalCount = ref(props.field.data?.total_count || 0)
const isLoadingMore = ref(false)

const remainingCount = computed(() => totalCount.value - displayedValues.value.length)

const getDisplayFileName = (url) => {
  if (!url) return 'Unknown file'
  try {
    const parts = url.split('/')
    let fileName = parts[parts.length - 1]
    // Remove query params
    fileName = fileName.split('?')[0] || fileName
    // Remove UUID suffix (format: name_uuid.ext)
    const uuidSuffixPattern = /_[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}(\.[^.]+)?$/i
    return fileName.replace(uuidSuffixPattern, '$1')
  } catch {
    return 'File'
  }
}

// Load more function
const loadMore = async () => {
  if (!hasMore.value || isLoadingMore.value) return

  isLoadingMore.value = true

  try {
    const response = await fieldValues(
      props.form.workspace_id,
      props.form.id,
      props.field.id,
      nextOffset.value,
      props.filters
    )

    displayedValues.value.push(...response.values)
    nextOffset.value = response.next_offset
    hasMore.value = response.has_more
    totalCount.value = response.total_count
  } catch (error) {
    console.error(error)
    useAlert().error('Failed to load more values')
  } finally {
    isLoadingMore.value = false
  }
}

// Reset when field data changes (e.g., filter change)
watch(() => props.field.data, (newData) => {
  displayedValues.value = [...(newData?.values || [])]
  nextOffset.value = newData?.next_offset || 10
  hasMore.value = newData?.has_more || false
  totalCount.value = newData?.total_count || 0
}, { deep: true })

const menuItems = (item) => [
  {
    label: 'View Submission',
    onClick: () => {
      navigateTo({
        name: 'forms-slug-show-submissions',
        params: { slug: props.form.slug },
        query: { view: item.submission_id }
      }, { open: '_blank' })
    }
  }
]
</script>

