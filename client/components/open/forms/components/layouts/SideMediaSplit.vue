<template>
  <div class="w-full flex z-10 flex-col @3xl:flex-row overflow-hidden">
    <!-- Mobile: media as background on top, content dictates scroll -->
    <div class="relative block @3xl:hidden w-full">
      <!-- Spacer for 50vh visual band -->
      <div class="pt-[50vh]"></div>
      <!-- Absolute background image so it doesn't affect layout height beyond spacer -->
      <div class="absolute inset-0 pointer-events-none">
        <BlockMediaLayout :image="image" :fallback-height="null" />
      </div>
    </div>

    <!-- Desktop: media on the left -->
    <div v-if="isLeft" class="hidden @3xl:block w-1/2 relative overflow-hidden h-[100cqh]">
      <BlockMediaLayout :image="image" :fallback-height="null" />
    </div>

    <!-- Content -->
    <div class="w-full @3xl:w-1/2 flex items-center px-6">
      <div class="w-full max-w-2xl mx-auto mt-4 @3xl:mt-0 py-4 @2xl:px-8 @3xl:px-4 @4xl:px-8">
        <slot />
      </div>
    </div>

    <!-- Desktop: media on the right -->
    <div v-if="!isLeft" class="hidden @3xl:block w-1/2 relative overflow-hidden h-[100cqh]">
      <BlockMediaLayout :image="image" :fallback-height="null" />
    </div>
  </div>
  
</template>

<script setup>
import BlockMediaLayout from '../BlockMediaLayout.vue'

const props = defineProps({
  image: { type: Object, required: true },
  side: { type: String, default: 'left' } // 'left' | 'right'
})

const isLeft = computed(() => (props.side || 'left') === 'left')
</script>


