<template>
  <div class="w-full">
    <div v-if="descriptor">
      <div v-if="descriptor.kind === 'iframe'" :class="wrapperClasses">
        <iframe
          :src="descriptor.src"
          v-bind="iframeAttrs"
          class="w-full h-full"
          :class="roundedClass"
          sandbox="allow-scripts allow-same-origin allow-popups allow-forms"
        />
      </div>

      <div v-else-if="descriptor.kind === 'video'" :class="wrapperClasses">
        <video
          :src="descriptor.src"
          class="w-full h-full"
          :class="roundedClass"
          controls
        />
      </div>

      <div v-else-if="descriptor.kind === 'audio'">
        <audio :src="descriptor.src" class="w-full" controls />
      </div>

      <div v-else-if="descriptor.kind === 'image'" :class="['inline-block', 'max-w-full']">
        <img :src="descriptor.src" :alt="descriptor.title || ''" :class="[roundedClass, 'max-w-full']" />
      </div>

      <div v-else>
        <a :href="src" target="_blank" rel="noopener" class="text-blue-600 underline">{{ src }}</a>
      </div>
    </div>

    <div v-else>
      <div class="p-4 border border-dashed text-center text-neutral-500">
        Invalid or unsupported media URL.
      </div>
    </div>
  </div>
</template>

<script setup>
import { resolveEmbed } from 'embedkit'

const props = defineProps({
  src: { type: String, required: true },
  isDark: { type: Boolean, default: false },
  preferLang: { type: String, default: 'en' }
})

const result = computed(() => {
  if (!props.src) return null
  try {
    const options = {
      preferLang: props.preferLang,
      theme: props.isDark ? 'dark' : 'light'
    }
    if (process.client && typeof location !== 'undefined') {
      options.hostname = location.hostname
    }
    const res = resolveEmbed(props.src, options)
    return res.ok ? res : null
  } catch (error) {
    console.error('Failed to resolve embed', error)
    return null
  }
})

const descriptor = computed(() => result.value?.descriptor || null)

const iframeAttrs = computed(() => {
  if (!descriptor.value || descriptor.value.kind !== 'iframe') return {}
  return descriptor.value.attrs || {}
})

const aspectClass = computed(() => {
  const ratio = descriptor.value?.aspectRatio || 'auto'
  if (ratio === '16:9') return 'aspect-video'
  if (ratio === '9:16') return 'aspect-[9/16]'
  if (ratio === '4:3') return 'aspect-[4/3]'
  if (ratio === '1:1') return 'aspect-square'
  return 'aspect-auto'
})

const wrapperClasses = computed(() => ['w-full', aspectClass.value])

const injectedBorderRadius = inject('formBorderRadius', null)
const resolvedBorderRadius = computed(() => injectedBorderRadius?.value || 'small')
const roundedClass = computed(() => {
  const map = { none: 'rounded-none', small: 'rounded-lg', full: 'rounded-[20px]' }
  return map[resolvedBorderRadius.value] || 'rounded-lg'
})
</script>


