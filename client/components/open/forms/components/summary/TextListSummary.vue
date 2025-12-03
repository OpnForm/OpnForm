<template>
  <div>
    <div class="divide-y">
      <div
        v-for="(item, index) in displayedValues"
        :key="index"
        class="px-4 py-2.5 flex items-center justify-between group"
      >
        <!-- File field -->
        <a
          v-if="isFileType"
          :href="item.value"
          target="_blank"
          rel="noopener noreferrer"
          class="inline-flex items-center gap-2 px-3 py-2 border border-neutral-200 rounded-lg hover:bg-neutral-50 transition-colors text-sm text-neutral-600"
        >
          <UIcon name="i-heroicons-arrow-top-right-on-square" class="w-4 h-4 text-neutral-400" />
          <span class="truncate max-w-xs">{{ getDisplayFileName(item.value) }}</span>
        </a>

        <!-- URL field -->
        <a
          v-else-if="fieldType === 'url'"
          :href="item.value"
          target="_blank"
          rel="noopener noreferrer"
          class="text-neutral-700 truncate flex-1 pr-4"
        >
          {{ item.value }}
        </a>

        <!-- Email field -->
        <a
          v-else-if="fieldType === 'email'"
          :href="'mailto:' + item.value"
          class="text-neutral-700 truncate flex-1 pr-4"
        >
          {{ item.value }}
        </a>

        <!-- Rich text field -->
        <div
          v-else-if="fieldType === 'rich_text'"
          class="text-neutral-700 truncate flex-1 pr-4"
          v-html="item.value"
        />

        <!-- Default text -->
        <span v-else class="text-neutral-700 truncate flex-1 pr-4">{{ item.value }}</span>

        <UDropdownMenu :items="menuItems(item)" :popper="{ placement: 'bottom-end' }">
          <UButton
            color="neutral"
            variant="ghost"
            icon="i-heroicons-ellipsis-vertical"
            size="xs"
          />
        </UDropdownMenu>
      </div>
    </div>

    <!-- Empty state -->
    <div
      v-if="displayedValues.length === 0"
      class="text-center p-4 text-neutral-400 text-sm"
    >
      {{ isFileType ? 'No files uploaded' : 'No responses' }}
    </div>

    <!-- Load More Button -->
    <div v-if="hasMore" class="p-4 border-t border-neutral-100">
      <UButton
        color="neutral"
        variant="ghost"
        size="sm"
        :loading="isLoadingMore"
        class="w-full"
        @click="loadMore"
      >
        <template v-if="!isLoadingMore">
          Show more ({{ remainingCount }} remaining)
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

