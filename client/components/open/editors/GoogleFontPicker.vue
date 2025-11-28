<template>
  <UModal
    v-model:open="isOpen"
    :ui="{ content: 'sm:max-w-2xl' }"
    title="Google fonts"
  >
    <template #body>
      <text-input
        v-model="search"
        name="search"
        placeholder="Search fonts"
      />

      <div
        ref="scrollContainer"
        class="grid grid-cols-3 gap-2 p-5 mb-5 overflow-y-scroll max-h-[24rem] border rounded-md bg-neutral-50 mt-3"
      >
        <template v-if="loading">
          <div
            v-for="i in 9"
            :key="`skeleton-${i}`"
            class="flex flex-col p-3 rounded-md shadow border-neutral-200 border-[0.5px] bg-white"
          >
            <div class="flex flex-wrap gap-2 mb-3">
              <USkeleton class="h-5 w-full" />
              <USkeleton class="h-5 w-3/4" />
            </div>
            <USkeleton class="h-3 w-1/2" />
          </div>
        </template>
        <template v-else>
          <FontCard
            v-for="(fontName, index) in enrichedFonts"
            :key="fontName"
            :ref="el => setFontRef(el, index)"
            :font-name="fontName"
            :is-visible="visible[index]"
            :is-selected="selectedFont === fontName"
            @select-font="selectedFont = fontName"
          />
        </template>
      </div>
    </template>

    <template #footer>
      <UButton
        size="md"
        color="neutral"
        variant="outline"
        @click="$emit('apply', null)"
      >
        Reset
      </UButton>
      <UButton
        size="md"
        :disabled="!selectedFont"
        @click="$emit('apply', selectedFont)"
      >
        Apply
      </UButton>
    </template>
  </UModal>
</template>

<script setup>
import { defineEmits } from "vue"
import { refDebounced, useElementVisibility } from "@vueuse/core"
import { useFuse } from '@vueuse/integrations/useFuse'
import FontCard from './FontCard.vue'
import { useContent } from '~/composables/query/useContent'

const props = defineProps({
  show: {
    type: Boolean,
    default: false,
  },
  font: {
    type: String,
    default: null,
  },
})

// Modal state
const isOpen = computed({
  get: () => props.show,
  set: (value) => {
    if (!value) {
      emit('close')
    }
  }
})

const emit = defineEmits(['close', 'apply'])

const { fonts: fontsApi } = useContent()
const selectedFont = ref(props.font || null)
const search = ref("")
const debouncedSearch = refDebounced(search, 500)

// Use TanStack Query for fonts with caching
const fontsQuery = fontsApi.list({
  enabled: computed(() => props.show)
})

const fonts = computed(() => {
  const data = fontsQuery.data.value
  if (!data) return []
  // Convert object to array if needed (handles numeric string keys)
  return Array.isArray(data) ? data : Object.values(data)
})

const loading = computed(() => fontsQuery.isLoading.value)

const { results: fuseResults } = useFuse(
  debouncedSearch,
  fonts,
  {
    fuseOptions: {
      threshold: 0.3,
      ignoreLocation: true,
      includeScore: false,
    },
    matchAllWhenSearchEmpty: true,
  }
)
const scrollContainer = ref(null)
const fontRefs = new Map()
const visible = ref([])

const setFontRef = (el, index) => {
  if (el) fontRefs.set(index, el)
}

const initializeVisibilityTracking = async () => {
  await nextTick() // Ensure DOM has been fully updated
  fontRefs.forEach((el, index) => {
    const visibility = useElementVisibility(el, {
      root: scrollContainer.value,
      threshold: 0.1
    })
    watch(
      () => visibility.value,
      (isVisible) => {
        if (isVisible) {
          visible.value[index] = true
        }
      },
      { immediate: true }
    )
  })
}

watch(() => props.show, (show) => {
  if (show) {
    selectedFont.value = props.font || null
  }
})

watch(fonts, (newFonts) => {
  if (newFonts && newFonts.length > 0) {
    initializeVisibilityTracking()
  }
})

const enrichedFonts = computed(() => {
  return fuseResults.value && fuseResults.value.length > 0
    ? fuseResults.value.map((res) => res.item)
    : fonts.value
})
</script>
