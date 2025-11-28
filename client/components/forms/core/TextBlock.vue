<template>
  <div
    class="text-block"
    :class="classes"
  >
    <div v-if="media && media.url" class="mb-3" :class="ui.media({ class: props.ui?.slots?.media })">
      <BlockMediaLayout
        :image="media"
        :fallback-height="''"
        :class="ui.mediaComponent({ class: props.ui?.slots?.mediaComponent })"
        :img-class="ui.mediaImg({ class: props.ui?.slots?.mediaImg })"
      />
    </div>
    <div v-html="processedContent" />
  </div>
  
</template>

<script setup>
import { useParseMention } from '@/composables/components/useParseMention'
import { tv } from 'tailwind-variants'
import { textBlockTheme } from '~/lib/forms/themes/text-block.theme.js'
import BlockMediaLayout from '~/components/open/forms/components/BlockMediaLayout.vue'
import { inputWrapperTheme } from '~/lib/forms/themes/input-wrapper.theme.js'

const props = defineProps({
  content: { type: String, required: true },
  mentionsAllowed: { type: Boolean, default: false },
  form: { type: Object, default: null },
  formData: { type: Object, default: null },
  size: { type: String, default: null },
  media: { type: Object, default: null },
  borderRadius: { type: String, default: null },
  ui: { type: Object, default: () => ({}) }
})

const processedContent = computed(() => {
  return useParseMention(props.content, props.mentionsAllowed, props.form, props.formData)
})

const injectedSize = inject('formSize', null)
const injectedBorderRadius = inject('formBorderRadius', null)
const resolvedSize = computed(() => props.size || injectedSize?.value || 'md')
const variants = computed(() => tv(textBlockTheme))
const classes = computed(() => variants.value({ size: resolvedSize.value }).root())

// Media UI (reuse input wrapper theme for border radius handling)
const resolvedBorderRadius = computed(() => props.borderRadius || injectedBorderRadius?.value || 'small')
const ui = computed(() => {
  return tv(inputWrapperTheme, props.ui)({
    borderRadius: resolvedBorderRadius.value,
    mediaStyle: 'intrinsic'
  })
})
</script>

<style>
@reference '~/css/app.css';

.text-block h1 {
  @apply mb-2;
}
.text-block h2 {
  @apply mb-1;
}
</style>
