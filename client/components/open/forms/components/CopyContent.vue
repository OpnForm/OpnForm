<template>
  <div class="flex gap-2">
    <div
      class="flex-1 truncate sm:w-auto border border-neutral-300 rounded-md px-2 py-1 flex-grow select-all bg-neutral-100 relative"
    >
      <div class="absolute right-0 top-0 bottom-0 w-12 bg-gradient-to-r from-transparent to-neutral-100"></div>
      <p class="select-all text-neutral-900 text-sm">
        {{ content }}
      </p>
    </div>
    <div class="shrink-0">
      <TrackClick
        v-if="trackingEvent"
        :name="trackingEvent"
        :properties="trackingProperties"
      >
        <UButton
          :color="copySuccess ? 'success' : 'primary'"
          :icon="copySuccess ? 'i-heroicons-check' : 'i-heroicons-clipboard-document'"
          class="w-full"
          @click.prevent="copyToClipboard"
        >
          <span class="hidden md:inline">{{ copySuccess ? 'Copied!' : label }}</span>
        </UButton>
      </TrackClick>
      <UButton
        v-else
        :color="copySuccess ? 'success' : 'primary'"
        :icon="copySuccess ? 'i-heroicons-check' : 'i-heroicons-clipboard-document'"
        class="w-full"
        @click.prevent="copyToClipboard"
      >
        <span class="hidden md:inline">{{ copySuccess ? 'Copied!' : label }}</span>
      </UButton>
    </div>
  </div>
</template>

<script setup>
import { defineProps, ref } from "vue"
import TrackClick from '~/components/global/TrackClick.vue'

const { copy } = useClipboard()

const props = defineProps({
  content: {
    type: String,
    required: true,
  },
  isDraft: {
    type: Boolean,
    default: false,
  },
  label: {
    type: String,
    default: "Copy Link",
  },
  trackingEvent: {
    type: String,
    default: null,
  },
  trackingProperties: {
    type: Object,
    default: () => ({}),
  },
})

const copySuccess = ref(false)

const copyToClipboard = () => {
  if (import.meta.server) return
  
  copy(props.content)
  
  // Show success state
  copySuccess.value = true
  
  // Reset after 2 seconds
  setTimeout(() => {
    copySuccess.value = false
  }, 2000)
}
</script>
