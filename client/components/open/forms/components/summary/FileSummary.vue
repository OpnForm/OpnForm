<template>
  <div>
    <!-- Empty state -->
    <div
      v-if="displayedValues.length === 0"
      class="text-center py-4 text-neutral-400 text-sm"
    >
      No files uploaded
    </div>

    <div class="divide-y divide-neutral-100">
      <div
        v-for="(value, index) in displayedValues"
        :key="index"
        class="py-2.5 flex items-center justify-between group"
      >
        <a
          :href="value"
          target="_blank"
          rel="noopener noreferrer"
          class="inline-flex items-center gap-2 px-3 py-2 border border-neutral-200 rounded-lg hover:bg-neutral-50 transition-colors text-sm text-neutral-600"
        >
          <UIcon name="i-heroicons-arrow-top-right-on-square" class="w-4 h-4 text-neutral-400" />
          <span class="text-neutral-700 truncate flex-1 pr-4">{{ getFileName(value) }}</span>
        </a>
        <UDropdownMenu :items="menuItems(value)" :popper="{ placement: 'bottom-end' }">
          <UButton
            color="neutral"
            variant="ghost"
            icon="i-heroicons-ellipsis-vertical"
            size="xs"
          />
        </UDropdownMenu>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  field: { type: Object, required: true },
})

const displayedValues = computed(() => props.field.data?.files || [])

const getFileName = (url) => {
  if (!url) return 'Unknown file'
  try {
    // Extract filename from URL
    const parts = url.split('/')
    const fileName = parts[parts.length - 1]
    // Remove query params if any
    return fileName.split('?')[0] || fileName
  } catch {
    return 'File'
  }
}

const menuItems = (value) => [
  {
    label: 'View Submission',
    click: () => {
      navigateTo({
        name: 'forms-slug-show-submissions',
        params: { slug: props.form.slug },
        query: { search: value }
      }, { external: true })
    }
  }
]
</script>

