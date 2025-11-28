<template>
  <input-wrapper v-bind="inputWrapperProps">
    <template #label>
      <slot name="label" />
    </template>
    <div class="flex space-x-2">
      <div class="flex-1 relative">
        <div
          class="font-medium text-sm absolute -top-[6px]"
          :style="labelStyle"
        >
          <div class="">
            {{ compVal }}
          </div>
        </div>
        <input
          ref="range"
          v-model.number="compVal"
          type="range"
          :class="[ui.slider({ class: props.ui?.slots?.slider }), 'slider']"
          :style="{ '--thumb-color': color }"
          :disabled="disabled"
          :min="minSlider"
          :max="maxSlider"
          :step="stepSlider"
        >
          <div class="grid grid-cols-3 gap-2 -mt-1">
            <div
              v-for="(i, idx) in sliderLabelsList"
              :key="i.label ?? idx"
              :class="[ui.stepLabel({ class: props.ui?.slots?.stepLabel }), i.style]"
            >
              {{ i.label }}
            </div>
          </div>
      </div>
    </div>

    <template #help>
      <slot name="help" />
    </template>
    <template #error>
      <slot name="error" />
    </template>
  </input-wrapper>
</template>

<script>
import { inputProps, useFormInput } from "../useFormInput.js"
import { sliderInputTheme } from "~/lib/forms/themes/slider-input.theme.js"

export default {
  name: "SliderInput",
  components: { },

  props: {
    ...inputProps,
    minSlider: { type: Number, default: 0 },
    maxSlider: { type: Number, default: 50 },
    stepSlider: { type: Number, default: 5 },
    thumbSize: { type: Number, default: 16 },
  },

  setup(props, context) {
    const formInput = useFormInput(props, context, {
      variants: sliderInputTheme,
      additionalVariants: {
        disabled: props.disabled
      }
    })

    return {
      ...formInput,
      props
    }
  },
  data() {
    return {
      inputWidth: 0,
    }
  },
  computed: {
    labelStyle() {
      const ratio =
        (Number(this.compVal) - this.minSlider) / (this.maxSlider - this.minSlider)
      const width = this.inputWidth || (this.$refs.range ? this.$refs.range.offsetWidth : 0)
      let x = (ratio * (width - this.thumbSize)) + (this.thumbSize / 2)
      if (x < 0) x = 0
      return {
        left: `${x}px`,
        transform: 'translateX(-50%)',
      }
    },
    sliderLabelsList() {
      const midPoint = (this.maxSlider - this.minSlider) / 2 + this.minSlider
      const labels = [
        {
          label: `${this.minSlider}`,
          style: "flex items-center justify-start",
        },
        {
          label: Math.floor(midPoint),
          style: "flex items-center justify-center",
        },
        {
          label: `${this.maxSlider}`,
          style: "flex items-center justify-end",
        },
      ]
      return labels
    },
  },
  mounted() {
    // Initialize only if no value provided; don't override an existing model value
    if (this.compVal === undefined || this.compVal === null || isNaN(Number(this.compVal))) {
      const initial = (this.modelValue !== undefined && this.modelValue !== null && !isNaN(Number(this.modelValue)))
        ? Number(this.modelValue)
        : 0
      this.compVal = initial
    }
    this.updateInputWidth()
    window.addEventListener('resize', this.updateInputWidth)
  },
  beforeUnmount() {
    window.removeEventListener('resize', this.updateInputWidth)
  },
  methods: {
    updateInputWidth() {
      this.$nextTick(() => {
        this.inputWidth = this.$refs.range ? this.$refs.range.offsetWidth : 0
      })
    }
  }
}
</script>

<style>
  .slider {
    accent-color: var(--thumb-color);
  }
</style>
