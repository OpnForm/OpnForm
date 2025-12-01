<template>
  <div>
    <div class="divide-y divide-neutral-100">
      <div
        v-for="(value, index) in displayedValues"
        :key="index"
        class="py-2.5 flex items-center justify-between group"
      >
        <!-- URL field -->
        <a
          v-if="fieldType === 'url'"
          :href="value"
          target="_blank"
          rel="noopener noreferrer"
          class="text-neutral-700 truncate flex-1 pr-4"
        >
          {{ value }}
        </a>

        <!-- Email field -->
        <a
          v-else-if="fieldType === 'email'"
          :href="'mailto:' + value"
          class="text-neutral-700 truncate flex-1 pr-4"
        >
          {{ value }}
        </a>

        <!-- Rich text field -->
        <div
          v-else-if="fieldType === 'rich_text'"
          class="text-neutral-700 truncate flex-1 pr-4"
          v-html="value"
        />

        <!-- Default text -->
        <span v-else class="text-neutral-700 truncate flex-1 pr-4">{{ value }}</span>

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

    <!-- Empty state -->
    <div
      v-if="displayedValues.length === 0"
      class="text-center py-4 text-neutral-400 text-sm"
    >
      No responses
    </div>

    <!-- Load More Button -->
    <div v-if="hasMore" class="pt-3 border-t border-neutral-100 mt-2">
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

// Local state
const displayedValues = ref([...(props.field.data?.values || [])])
const nextOffset = ref(props.field.data?.next_offset || 10)
const hasMore = ref(props.field.data?.has_more || false)
const totalCount = ref(props.field.data?.total_count || 0)
const isLoadingMore = ref(false)

const remainingCount = computed(() => totalCount.value - displayedValues.value.length)

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

