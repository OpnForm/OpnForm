<template>
  <div :class="ui.container({ class: props.ui?.slots?.container })">
    <button
      type="button"
      :class="ui.button({ class: props.ui?.slots?.button })"
      :aria-label="isPlaying ? 'Pause' : 'Play'"
      @click="togglePlay"
    >
      <Icon
        :name="isPlaying ? 'i-heroicons-pause' : 'i-heroicons-play'"
        :class="ui.icon({ class: props.ui?.slots?.icon })"
      />
    </button>

    <span :class="ui.time({ class: props.ui?.slots?.time })">
      {{ formatTime(currentTime) }}
    </span>

    <input
      type="range"
      :class="ui.progress({ class: props.ui?.slots?.progress })"
      min="0"
      max="100"
      step="0.1"
      :value="progressPercent"
      :disabled="!duration"
      @input="seek"
    >

    <span :class="[ui.time({ class: props.ui?.slots?.time }), 'text-end']">
      {{ formatTime(duration) }}
    </span>

    <button
      type="button"
      :class="ui.button({ class: props.ui?.slots?.button })"
      :aria-label="isMuted ? 'Unmute' : 'Mute'"
      @click="toggleMute"
    >
      <Icon
        :name="isMuted ? 'i-heroicons-speaker-x-mark' : 'i-heroicons-speaker-wave'"
        :class="ui.icon({ class: props.ui?.slots?.icon })"
      />
    </button>

    <audio
      ref="audioRef"
      :src="src"
      class="hidden"
      @timeupdate="onTimeUpdate"
      @loadedmetadata="onLoadedMetadata"
      @ended="onEnded"
      @play="isPlaying = true"
      @pause="isPlaying = false"
    />
  </div>
</template>

<script setup>
import { tv } from 'tailwind-variants'
import { nativeAudioPlayerTheme } from '~/lib/forms/themes/native-audio-player.theme.js'

const props = defineProps({
  src: { type: String, required: true },
  theme: { type: String, default: null },
  size: { type: String, default: null },
  borderRadius: { type: String, default: null },
  ui: { type: Object, default: () => ({}) },
})

const injectedTheme = inject('formTheme', null)
const injectedSize = inject('formSize', null)
const injectedBorderRadius = inject('formBorderRadius', null)

const resolvedTheme = computed(() => props.theme || injectedTheme?.value || 'default')
const resolvedSize = computed(() => props.size || injectedSize?.value || 'md')
const resolvedBorderRadius = computed(() => props.borderRadius || injectedBorderRadius?.value || 'small')

const ui = computed(() => tv(nativeAudioPlayerTheme, { twMerge: true })({
  theme: resolvedTheme.value,
  size: resolvedSize.value,
  borderRadius: resolvedBorderRadius.value,
}))

const audioRef = ref(null)
const isPlaying = ref(false)
const isMuted = ref(false)
const currentTime = ref(0)
const duration = ref(0)

const progressPercent = computed(() => {
  if (!duration.value) return 0
  return (currentTime.value / duration.value) * 100
})

function formatTime(seconds) {
  if (!seconds || Number.isNaN(seconds)) return '0:00'
  const minutes = Math.floor(seconds / 60)
  const secs = Math.floor(seconds % 60)
  return `${minutes}:${String(secs).padStart(2, '0')}`
}

function togglePlay() {
  const audio = audioRef.value
  if (!audio) return

  if (audio.paused) {
    audio.play().catch(() => {})
  } else {
    audio.pause()
  }
}

function toggleMute() {
  const audio = audioRef.value
  if (!audio) return

  audio.muted = !audio.muted
  isMuted.value = audio.muted
}

function seek(event) {
  const audio = audioRef.value
  if (!audio || !duration.value) return

  const percent = Number(event.target.value) / 100
  audio.currentTime = percent * duration.value
}

function onTimeUpdate() {
  currentTime.value = audioRef.value?.currentTime || 0
}

function onLoadedMetadata() {
  duration.value = audioRef.value?.duration || 0
}

function onEnded() {
  isPlaying.value = false
  currentTime.value = 0
}

watch(() => props.src, () => {
  isPlaying.value = false
  isMuted.value = false
  currentTime.value = 0
  duration.value = 0
})
</script>

<style scoped>
.native-audio-player__progress {
  -webkit-appearance: none;
  appearance: none;
  background: transparent;
}

.native-audio-player__progress::-webkit-slider-runnable-track {
  height: 4px;
  border-radius: 9999px;
  background: rgb(163 163 163 / 0.45);
}

.native-audio-player__progress::-webkit-slider-thumb {
  -webkit-appearance: none;
  appearance: none;
  width: 12px;
  height: 12px;
  margin-top: -4px;
  border-radius: 9999px;
  background: currentColor;
}

.native-audio-player__progress::-moz-range-track {
  height: 4px;
  border-radius: 9999px;
  background: rgb(163 163 163 / 0.45);
}

.native-audio-player__progress::-moz-range-thumb {
  width: 12px;
  height: 12px;
  border: 0;
  border-radius: 9999px;
  background: currentColor;
}
</style>
