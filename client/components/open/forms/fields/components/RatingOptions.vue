<template>
  <div
    v-if="field.type === 'rating'"
    class="px-4"
  >
    <EditorSectionHeader
      icon="i-heroicons-star"
      title="Rating"
    />
    <text-input
      name="rating_max_value"
      native-type="number"
      :min="1"
      class="mt-3"
      :form="field"
      required
      label="Max rating value"
      @update:model-value="onMaxValueChange"
    />

    <UPopover
      arrow
      :content="{ side: 'left', align: 'center' }"
    >
      <UButton
        class="mt-4"
        block
        color="neutral"
        variant="outline"
        :trailing-icon="hasCustomIcon ? 'i-heroicons-check-circle' : ''"
        label="Customize Icon"
      />
      <template #content>
        <div class="p-4 w-80">
          <h4 class="font-medium text-sm mb-3">
            Rating Icon
          </h4>

          <OptionSelectorInput
            v-model="iconType"
            name="rating_icon_type"
            seamless
            label="Type"
            :options="iconTypeOptions"
            :columns="2"
            @update:model-value="onIconTypeChange"
          />

          <OptionSelectorInput
            v-model="iconMode"
            name="rating_icon_mode"
            class="mt-3"
            seamless
            label="Apply to"
            :options="iconModeOptions"
            :columns="2"
            @update:model-value="onIconModeChange"
          />

          <!-- Single icon -->
          <TextInput
            v-if="iconType === 'icon' && iconMode === 'single'"
            name="rating_icon"
            class="mt-3"
            :form="field"
            label="Icon"
            placeholder="★"
            help="Enter a single character or emoji. Leave empty for the default star."
            @update:model-value="(val) => onIconChange(val, null)"
          />

          <!-- Multiple icons -->
          <div
            v-else-if="iconType === 'icon' && iconMode === 'multiple'"
            class="mt-3"
          >
            <div class="flex flex-wrap gap-1.5">
              <div
                v-for="index in maxRating"
                :key="`icon-${index}`"
                class="flex flex-col items-center gap-1"
              >
                <span class="text-[10px] leading-none text-neutral-400">{{ index }}</span>
                <TextInput
                  :model-value="field.rating_icons?.[index - 1] || ''"
                  :name="`rating_icon_${index}`"
                  wrapper-class="mb-0 w-9"
                  size="xs"
                  placeholder="★"
                  :ui="{ slots: { input: 'text-center px-0' } }"
                  @update:model-value="(val) => onIconChange(val, index - 1)"
                />
              </div>
            </div>
            <p class="text-xs text-neutral-500 mt-2">
              Empty slots use the default star.
            </p>
          </div>

          <!-- Single image -->
          <ImageInput
            v-else-if="iconType === 'image' && iconMode === 'single'"
            name="rating_image"
            class="mt-3"
            :form="field"
            label="Image"
            help="Upload an image to use instead of an icon"
          />

          <!-- Multiple images -->
          <div
            v-else
            class="mt-3"
          >
            <div class="flex flex-wrap gap-1.5">
              <div
                v-for="index in maxRating"
                :key="`image-${index}`"
                class="flex flex-col items-center gap-1"
              >
                <span class="text-[10px] leading-none text-neutral-400">{{ index }}</span>
                <ImageInput
                  :model-value="field.rating_images?.[index - 1] || null"
                  :name="`rating_image_${index}`"
                  wrapper-class="mb-0"
                  size="xs"
                  compact
                  class="h-9 w-9"
                  @update:model-value="(val) => updateImage(index - 1, val)"
                />
              </div>
            </div>
            <p class="text-xs text-neutral-500 mt-2">
              Empty slots use the default star.
            </p>
          </div>
        </div>
      </template>
    </UPopover>
  </div>
</template>

<script setup>
import EditorSectionHeader from '~/components/open/forms/components/form-components/EditorSectionHeader.vue'
import ImageInput from '~/components/forms/heavy/ImageInput.vue'

const props = defineProps({
  field: {
    type: Object,
    required: true
  }
})

const iconTypeOptions = [
  { name: 'icon', label: 'Icon' },
  { name: 'image', label: 'Image' },
]

const iconModeOptions = [
  { name: 'single', label: 'Same for all' },
  { name: 'multiple', label: 'Per rating' },
]

const maxRating = computed(() => {
  const value = parseInt(props.field.rating_max_value)
  return value > 0 ? value : 5
})

function deriveIconType(field) {
  if (field.rating_image || (Array.isArray(field.rating_images) && field.rating_images.some(Boolean))) {
    return 'image'
  }
  return 'icon'
}

function deriveIconMode(field) {
  if (Array.isArray(field.rating_icons) || Array.isArray(field.rating_images)) {
    return 'multiple'
  }
  return 'single'
}

// UI-only toggles — not persisted
const iconType = ref(deriveIconType(props.field))
const iconMode = ref(deriveIconMode(props.field))

const hasCustomIcon = computed(() => {
  if (Array.isArray(props.field.rating_images) && props.field.rating_images.some(Boolean)) return true
  if (Array.isArray(props.field.rating_icons) && props.field.rating_icons.some(Boolean)) return true
  return !!(props.field.rating_image || props.field.rating_icon)
})

watch(() => props.field?.id, () => {
  iconType.value = deriveIconType(props.field)
  iconMode.value = deriveIconMode(props.field)
})

function clearAllCustomIcons() {
  props.field.rating_icon = null
  props.field.rating_image = null
  props.field.rating_icons = null
  props.field.rating_images = null
}

function createIconsArray(fillValue = '') {
  return Array.from({ length: maxRating.value }, () => fillValue || '')
}

function createImagesArray(fillValue = null) {
  return Array.from({ length: maxRating.value }, () => fillValue || null)
}

function resizeArray(current, length, emptyValue) {
  const source = Array.isArray(current) ? current : []
  return Array.from({ length }, (_, i) => source[i] ?? emptyValue)
}

function onIconTypeChange(val) {
  if (val === 'image') {
    if (iconMode.value === 'multiple') {
      clearAllCustomIcons()
      props.field.rating_images = createImagesArray()
    } else {
      props.field.rating_icon = null
      props.field.rating_icons = null
      props.field.rating_images = null
    }
  } else if (iconMode.value === 'multiple') {
    clearAllCustomIcons()
    props.field.rating_icons = createIconsArray()
  } else {
    props.field.rating_image = null
    props.field.rating_icons = null
    props.field.rating_images = null
  }
}

function onIconModeChange(val) {
  if (val === 'multiple') {
    if (iconType.value === 'image') {
      const single = props.field.rating_image || null
      clearAllCustomIcons()
      props.field.rating_images = createImagesArray(single)
    } else {
      const single = props.field.rating_icon || ''
      clearAllCustomIcons()
      props.field.rating_icons = createIconsArray(single)
    }
  } else if (iconType.value === 'image') {
    const first = Array.isArray(props.field.rating_images)
      ? (props.field.rating_images.find(Boolean) || null)
      : null
    clearAllCustomIcons()
    props.field.rating_image = first
  } else {
    const first = Array.isArray(props.field.rating_icons)
      ? (props.field.rating_icons.find(Boolean) || null)
      : null
    clearAllCustomIcons()
    props.field.rating_icon = first
  }
}

function onMaxValueChange() {
  if (iconMode.value !== 'multiple') return

  if (iconType.value === 'image' && Array.isArray(props.field.rating_images)) {
    props.field.rating_images = resizeArray(props.field.rating_images, maxRating.value, null)
  } else if (iconType.value === 'icon' && Array.isArray(props.field.rating_icons)) {
    props.field.rating_icons = resizeArray(props.field.rating_icons, maxRating.value, '')
  }
}

function updateImage(index, val) {
  if (!Array.isArray(props.field.rating_images)) {
    props.field.rating_images = createImagesArray()
  }
  props.field.rating_images[index] = val || null
  props.field.rating_icon = null
  props.field.rating_image = null
  props.field.rating_icons = null
}

function getFirstGrapheme(value) {
  if (!value || typeof value !== 'string') return value

  if (typeof Intl !== 'undefined' && Intl.Segmenter) {
    const segmenter = new Intl.Segmenter(undefined, { granularity: 'grapheme' })
    const first = segmenter.segment(value)[Symbol.iterator]().next().value
    return first?.segment ?? value
  }

  return [...value][0] ?? value
}

const onIconChange = (val, index) => {
  if (typeof val !== 'string') return

  const first = val ? getFirstGrapheme(val) : ''

  if (index === null) {
    props.field.rating_icon = first || null
    return
  }

  if (!Array.isArray(props.field.rating_icons)) {
    props.field.rating_icons = createIconsArray()
  }
  props.field.rating_icons[index] = first
  props.field.rating_icon = null
  props.field.rating_image = null
  props.field.rating_images = null
}
</script>
