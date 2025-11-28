<template>
  <div
    v-if="field"
    class="py-2"
  >
    <div class="px-4">
      <text-input
        name="name"
        :form="field"
        wrapper-class="mb-2"
        :required="true"
        label="Block Name"
      />

      <HiddenRequiredDisabled
        :form="field"
        :field="field"
        :can-be-disabled="false"
        :can-be-hidden="true"
        :can-be-required="false"
      />

      <OptionSelectorInput
          v-model="field.width"
          name="width"
          class="grow mt-4"
          :form="field"
          label="Block Width"
          seamless
          v-if="!isFocused"
          :options="[
            { name: 'full', label: 'Full' },
            { name: '1/2', label: '1/2' },
            { name: '1/3', label: '1/3' },
            { name: '2/3', label: '2/3' },
            { name: '1/4', label: '1/4' },
            { name: '3/4', label: '3/4' },
          ]"
          :multiple="false"
          :columns="6"
        />
        <OptionSelectorInput
          v-if="['nf-text', 'nf-image', 'nf-video'].includes(field.type)"
          v-model="field.align"
          name="align"
          class="mt-4 w-2/3"
          :form="field"
          label="Text Alignment"
          seamless
          :options="[
            { name: 'left', icon: 'i-heroicons-bars-3-bottom-left', tooltip: 'Align left' },
            { name: 'center', icon: 'i-heroicons-bars-3', tooltip: 'Align center' },
            { name: 'right', icon: 'i-heroicons-bars-3-bottom-right', tooltip: 'Align right' },
            { name: 'justify', icon: 'i-heroicons-bars-4', tooltip: 'Justify text' },
          ]"
          :multiple="false"
          :columns="4"
        />
    </div>

    <!-- Focused Mode: Media settings (high priority under general) -->
    <div v-if="isFocused && field.type==='nf-text'" class="mt-2">
      <BlockMediaOptions :model="field" :form="form" />
    </div>

    <div
      v-if="field.type == 'nf-text'"
      class="border-t mt-4"
    >
      <rich-text-area-input
        class="mx-4"
        :allow-fullscreen="true"
        name="content"
        :form="field"
        label="Content"
        :required="false"
      />
    </div>

    <div
      v-else-if="field.type == 'nf-page-break'"
      class="border-b py-2 px-4"
    >
      <text-input
        name="next_btn_text"
        :form="field"
        label="Next button label"
        :required="true"
      />
      <text-input
        name="previous_btn_text"
        :form="field"
        label="Previous button label"
        help="Displayed on the next page"
        :required="true"
      />
    </div>

    <div
      v-else-if="field.type == 'nf-image'"
      class="border-t mt-4"
    >
      <image-input
        name="image_block"
        class="mx-4"
        :form="field"
        label="Upload Image"
        :required="false"
      />
    </div>
    
    <div
      v-else-if="field.type == 'nf-video'"
      class="border-t mt-4"
    >
      <TextInput
        name="video_block"
        class="mx-4"
        :form="field"
        label="Video URL"
        help="You can add a video URL here. It will be displayed as a video block."
      />
    </div>

    <div
      v-else-if="field.type == 'nf-code'"
      class="border-t mt-6"
    >
      <CodeInput
        :allow-fullscreen="true"
        name="content"
        class="mt-4 mx-4"
        :form="field"
        label="Content"
        help="You can add any html code, including iframes"
      />
    </div>
  </div>

  <!-- (moved above for focused mode) -->
</template>

<script setup>
import HiddenRequiredDisabled from './HiddenRequiredDisabled.vue'
import BlockMediaOptions from '~/components/open/forms/components/media/BlockMediaOptions.vue'

const props = defineProps({
  field: {
    type: Object,
    required: false
  },
  form: {
    type: Object,
    required: false
  }
})

const isFocused = computed(() => props.form?.presentation_style === 'focused')

watch(() => props.field?.width, (val) => {
  if (val === undefined || val === null) {
    props.field.width = 'full'
  }
}, { immediate: true })

watch(() => props.field?.align, (val) => {
  if (val === undefined || val === null) {
    props.field.align = 'left'
  }
}, { immediate: true })

onMounted(() => {
  if (props.field?.width === undefined || props.field?.width === null) {
    props.field.width = 'full'
  }
})
</script>
