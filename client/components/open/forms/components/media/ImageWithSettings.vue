<template>
  <image-input
    :form="form"
    :name="name"
    :label="label"
    :required="false"
  >
    <template #left-action>
      <UPopover
        v-if="hasImage"
        arrow
        :content="{ side: 'bottom', align: 'start' }"
      >
        <button
          class="text-neutral-500 hover:text-neutral-700 flex items-center mr-2"
          type="button"
          :aria-label="settingsAriaLabel"
          @click.stop
        >
          <Icon name="heroicons:cog-6-tooth" class="h-5 w-5" />
        </button>

        <template #content>
          <div class="p-3 w-80">
            <FocalPointPicker :name="focalName" :label="focalLabel" v-model="focalPointModel" :src="imageUrl" />

            <div class="mt-4">
              <SliderInput
                v-model="brightnessModel"
                :name="brightnessName"
                :form="form"
                :min-slider="-100"
                :max-slider="100"
                :step-slider="1"
                :label="brightnessLabel"
              />
            </div>

            <div v-if="showAlt" class="mt-4">
              <text-input
                :form="form"
                :name="altName"
                label="Alt text"
                placeholder="Describe the image for accessibility (max 125 characters)"
                :max-char-limit="125"
                :show-char-limit="true"
              />
            </div>
          </div>
        </template>
      </UPopover>
    </template>
  </image-input>
</template>

<script setup>
import FocalPointPicker from './FocalPointPicker.vue'
import SliderInput from '~/components/forms/core/SliderInput.vue'

const props = defineProps({
  // The vForm object to bind ImageInput and text-input/slider to
  form: { type: Object, required: true },
  // The field path for the ImageInput (e.g. 'cover_picture' or 'image.url')
  name: { type: String, required: true },
  // UI label for the ImageInput
  label: { type: String, required: true },
  // Kind controls default paths for settings handling
  kind: { type: String, default: 'block' } // 'block' | 'cover'
})

const settingsAriaLabel = computed(() => props.kind === 'cover' ? 'Cover image settings' : 'Image settings')
const focalLabel = computed(() => 'Focal point')
const brightnessLabel = computed(() => 'Brightness')

// Derived paths based on kind
const showAlt = computed(() => props.kind === 'block')
const altName = computed(() => props.kind === 'block' ? 'image.alt' : null)
const brightnessName = computed(() => props.kind === 'block' ? 'image.brightness' : 'cover_picture_brightness')
const focalName = computed(() => props.kind === 'cover' ? 'cover_focal_point' : 'image.focal_point')

// Resolve current image URL from form + name path (simple cases only)
const imageUrl = computed(() => {
  try {
    if (!props.form || !props.name) return null
    const parts = props.name.split('.')
    let ref = props.form
    for (const p of parts) {
      ref = ref && ref[p]
    }
    return ref || null
  } catch {
    return null
  }
})

const hasImage = computed(() => !!imageUrl.value)

// Focal point mapping
const focalPointModel = computed({
  get() {
    if (props.kind === 'cover') {
      const s = props.form?.cover_settings || {}
      return s.focal_point ?? { x: 50, y: 50 }
    } else {
      const img = props.form?.image || {}
      return img.focal_point ?? { x: 50, y: 50 }
    }
  },
  set(val) {
    if (props.kind === 'cover') {
      const base = props.form?.cover_settings ? { ...props.form.cover_settings } : {}
      base.focal_point = val
      props.form.cover_settings = base
    } else {
      if (!props.form.image) props.form.image = {}
      props.form.image.focal_point = val
    }
  }
})

// Brightness mapping
const brightnessModel = computed({
  get() {
    if (props.kind === 'cover') {
      const s = props.form?.cover_settings
      return (s && typeof s.brightness === 'number') ? s.brightness : 0
    } else {
      const img = props.form?.image
      return (img && typeof img.brightness === 'number') ? img.brightness : 0
    }
  },
  set(val) {
    const num = typeof val === 'number' ? val : 0
    if (props.kind === 'cover') {
      const base = props.form?.cover_settings ? { ...props.form.cover_settings } : {}
      base.brightness = num
      props.form.cover_settings = base
    } else {
      if (!props.form.image) props.form.image = {}
      props.form.image.brightness = num
    }
  }
})

watch(imageUrl, (val) => {
  // Reset settings when image cleared
  if (!val) {
    if (props.kind === 'cover') {
      props.form.cover_settings = {}
    } else if (props.form?.image) {
      props.form.image.alt = null
      props.form.image.layout = null
      props.form.image.focal_point = null
      props.form.image.brightness = 0
    }
  } else {
    // Ensure defaults exist when an image is set
    if (props.kind !== 'cover') {
      if (!props.form.image) props.form.image = {}
      props.form.image.focal_point = props.form.image.focal_point ?? { x: 50, y: 50 }
      props.form.image.brightness = typeof props.form.image.brightness === 'number' ? props.form.image.brightness : 0
    }
  }
})
</script>


