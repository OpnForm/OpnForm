<template>
  <div class="unsplash-images-container">
    <div class="py-2">
      <UInput
        ref="searchInput"
        v-model="searchTerm"
        variant="outline"
        class="w-full mb-1"
        placeholder="Search image..."
        icon="i-heroicons-magnifying-glass-solid"
        :disabled="!isFeatureEnabled"
        :ui="{ trailing: 'pe-1' }"
        autofocus
      >
        <template v-if="searchTerm?.length" #trailing>
          <UButton
            color="neutral"
            variant="link"
            size="sm"
            icon="i-heroicons-x-mark-20-solid"
            aria-label="Clear"
            title="Clear"
            @click="searchTerm = ''"
          />
        </template>
      </UInput>
    </div>

    <div class="mb-5 border rounded-lg bg-gradient-to-b from-neutral-50 to-neutral-100">
      <VTransition name="fade">
        <div v-if="!isFeatureEnabled" key="disabled" class="flex flex-col items-center justify-center h-64 p-4">
          <UIcon name="i-heroicons-exclamation-triangle-20-solid" class="w-12 h-12 text-amber-500 mb-3" />
          <p class="text-neutral-600 text-sm font-medium mb-1">Unsplash integration is not available</p>
          <p class="text-neutral-500 text-xs text-center">Please configure Unsplash API keys to use this feature.</p>
        </div>
        <div v-else-if="loading" key="loading" class="grid grid-cols-3 gap-3 p-4">
          <div v-for="i in 9" :key="i" class="group cursor-pointer rounded-lg overflow-hidden shadow-sm transition-all duration-200">
            <div class="aspect-square overflow-hidden bg-neutral-200">
              <USkeleton class="w-full h-full" />
            </div>
          </div>
        </div>
        <div v-else-if="images.length === 0" key="empty" class="flex flex-col items-center justify-center h-64">
          <UIcon name="i-heroicons-photo-20-solid" class="w-16 h-16 text-neutral-300 mb-3" />
          <p class="text-neutral-500 text-sm">{{ searchTerm ? 'No image found' : 'No image available' }}</p>
        </div>
        <div v-else key="results" class="grid grid-cols-3 gap-3 p-4">
          <div v-for="image in images.slice(0, 9)" :key="image.id" class="group cursor-pointer rounded-lg overflow-hidden shadow-sm hover:shadow-md transition-all duration-200 relative">
            <div class="aspect-square overflow-hidden bg-neutral-200">
              <img 
                :src="image.url" 
                :alt="image.alt_text"
                class="w-full h-full object-cover"
                @click="selectImage(image)"
              />
            </div>
            <div v-if="image.photographer_name || image.photographer_url" class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity duration-200 flex items-end justify-start z-20 pointer-events-none">
              <div class="w-full p-2 text-white text-xs pointer-events-auto">
                <p v-if="image.photographer_name" class="font-medium truncate mb-0.5">{{ image.photographer_name }}</p>
                <a 
                  v-if="image.photographer_url"
                  :href="`${image.photographer_url}?utm_source=opnform&utm_medium=referral`"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="text-white/80 hover:text-white text-xs underline block"
                  @click.stop
                >
                  {{ image.photographer_name ? 'on Unsplash' : 'View on Unsplash' }}
                </a>
              </div>
            </div>
          </div>
        </div>
      </VTransition>
    </div>
  </div>
</template>

<script setup>
import { refDebounced } from '@vueuse/core'
import { useContent } from '~/composables/query/useContent'
import { contentApi } from '~/api/content'

const isFeatureEnabled = useFeatureFlag('services.unsplash', false)
const { unsplash } = useContent()
const searchTerm = ref("")
const debouncedTerm = refDebounced(searchTerm, 300)

const emit = defineEmits(['selectImage'])

// Use TanStack Query for images with caching
const { data: images = [], isLoading: loading } = unsplash.list(debouncedTerm, {
  enabled: computed(() => isFeatureEnabled)
})

const selectImage = async (image) => {
  // Trigger download tracking as per Unsplash API guidelines
  if (image.download_location) {
    try {
      // Fire-and-forget - pass the full download_location URL with ixid parameter
      contentApi.unsplash.download(image.download_location)
    } catch (error) {
      // Silently fail - don't block image selection if download tracking fails
      console.warn('Failed to track Unsplash download:', error)
    }
  }
  emit('selectImage', image.url)
}

</script>