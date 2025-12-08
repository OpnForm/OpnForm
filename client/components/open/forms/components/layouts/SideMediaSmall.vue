<template>
  <div class="w-full flex items-center px-6 @2xl:px-12 grow min-h-0 z-10">
    <div class="w-full max-w-6xl mx-auto flex items-stretch @3xl:items-center justify-between gap-6 @3xl:gap-8 flex-col @3xl:flex-row">
      <!-- Content -->
      <div
        class="w-full max-w-2xl order-1 py-4 @2xl:px-8 @3xl:px-4 @4xl:px-8 "
        :class="isLeft ? '@3xl:order-2' : '@3xl:order-1'"
      >
        <slot />
      </div>

      <!-- Media: below content on mobile; left/right on desktop -->
      <div
        class="w-full  @3xl:max-w-1/2 order-2"
        :class="isLeft ? '@3xl:order-1' : '@3xl:order-2'"
      >
        <div class="@3xl:p-4 @7xl:p-6 mb-4" :class="ui.mediaContainer({ class: props.ui?.slots?.mediaContainer })">
          <BlockMediaLayout
            :image="image"
            :fallback-height="null"
            :class="ui.mediaComponent({ class: props.ui?.slots?.mediaComponent })"
            :img-class="ui.mediaImg({ class: props.ui?.slots?.mediaImg })"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import BlockMediaLayout from '../BlockMediaLayout.vue'
import { tv } from 'tailwind-variants'
import { sideMediaSmallTheme } from '~/lib/forms/themes/side-media-small.theme.js'

const props = defineProps({
  image: { type: Object, required: true },
  side: { type: String, default: 'left' }, // 'left' | 'right'
  borderRadius: { type: String, default: 'small' }, // 'none' | 'small' | 'full'
  ui: { type: Object, default: () => ({}) }
})

const isLeft = computed(() => (props.side || 'left') === 'left')

const ui = computed(() => {
  return tv(sideMediaSmallTheme, props.ui)({
    borderRadius: props.borderRadius || 'small'
  })
})
</script>


