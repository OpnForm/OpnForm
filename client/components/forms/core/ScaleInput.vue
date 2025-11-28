<template>
  <input-wrapper v-bind="inputWrapperProps">
    <template #label>
      <slot name="label" />
    </template>

    <div 
      class="rectangle-outer grid grid-cols-5 gap-2"
      role="radiogroup"
      :aria-label="`Scale from ${minScale} to ${maxScale}`"
    >
      <div
        v-for="(i, index) in scaleList"
        :key="i"
        :class="[
          { 'font-semibold': compVal === i },
          ui.button({ class: props.ui?.slots?.button }),
          compVal !== i ? ui.buttonUnselected({ class: props.ui?.slots?.buttonUnselected }) : '',
          compVal !== i ? ui.buttonHover({ class: props.ui?.slots?.buttonHover }) : ''
        ]"
        class="focus-visible:ring-2 focus-visible:ring-form/100 focus-visible:outline-none"
        :style="btnStyle(i === compVal)"
        role="radio"
        :tabindex="getScaleTabIndex(i)"
        :aria-checked="compVal === i"
        :aria-label="`Scale value ${formatNumber(i)}`"
        @click="setScale(i)"
        @keydown="handleKeydown($event, index)"
      >
        {{ formatNumber(i) }}
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
import { scaleInputTheme } from "~/lib/forms/themes/scale-input.theme.js"

export default {
  name: "ScaleInput",
  components: { },

  props: {
    ...inputProps,
    minScale: { type: Number, default: 1 },
    maxScale: { type: Number, default: 5 },
    stepScale: { type: Number, default: 1 },
  },

  setup(props, context) {
    const formInput = useFormInput(props, context, {
      variants: scaleInputTheme
    })
    return {
      ...formInput,
      props
    }
  },

  data() {
    return {}
  },

  computed: {
    scaleList() {
      const list = []
      if (this.stepScale == 0) {
        list.push(this.minScale)
        return list
      }
      for (let i = this.minScale; i <= this.maxScale; i += this.stepScale) {
        list.push(i)
      }
      return list
    },
    // No longer used; kept for compatibility if referenced elsewhere
    textColor() {
      const color =
        this.color.charAt(0) === "#" ? this.color.substring(1, 7) : this.color
      const r = parseInt(color.substring(0, 2), 16) // hexToR
      const g = parseInt(color.substring(2, 4), 16) // hexToG
      const b = parseInt(color.substring(4, 6), 16) // hexToB
      const uicolors = [r / 255, g / 255, b / 255]
      const c = uicolors.map((col) => {
        if (col <= 0.03928) {
          return col / 12.92
        }
        return Math.pow((col + 0.055) / 1.055, 2.4)
      })
      const L = 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2]
      return L > 0.55 ? "#000000" : "#FFFFFF"
    },
  },

  mounted() {
    if (this.compVal && typeof this.compVal === "string") {
      this.compVal = parseInt(this.compVal)
    }
  },

  methods: {
    formatNumber(num) {
      if (Math.floor(num) === num) {
        // return as Integer
        return num
      } else {
        // Fformat to 2 decimal places
        return parseFloat(num.toFixed(2))
      }
    },
    btnStyle(isSelected) {
      if (!isSelected) return {}
      return {
        color: this.textColor,
        backgroundColor: this.color,
      }
    },
    setScale(val) {
      if (this.disabled) {
        return
      }
      if (this.compVal === val) {
        this.compVal = null
      } else {
        this.compVal = val
        if (val !== null && val !== undefined) {
          this.$emit('input-filled')
        }
      }
    },
    handleKeydown(event, currentIndex) {
      if (this.disabled) return

      const maxIndex = this.scaleList.length - 1
      let nextIndex = currentIndex

      switch (event.key) {
        case 'ArrowRight':
        case 'ArrowUp':
          event.preventDefault()
          nextIndex = Math.min(currentIndex + 1, maxIndex)
          break
        case 'ArrowLeft':
        case 'ArrowDown':
          event.preventDefault()
          nextIndex = Math.max(currentIndex - 1, 0)
          break
        case 'Enter':
        case ' ':
          event.preventDefault()
          this.setScale(this.scaleList[currentIndex])
          return
        case 'Home':
          event.preventDefault()
          nextIndex = 0
          break
        case 'End':
          event.preventDefault()
          nextIndex = maxIndex
          break
        default: {
          // Handle direct number input
          const inputNum = parseFloat(event.key)
          const scaleIndex = this.scaleList.findIndex(scale => scale === inputNum)
          if (scaleIndex >= 0) {
            event.preventDefault()
            this.setScale(inputNum)
            nextIndex = scaleIndex
          } else {
            return
          }
          break
        }
      }

      // Move focus to the next scale button
      if (nextIndex !== currentIndex) {
        this.focusOnScale(nextIndex)
      }
    },
    focusOnScale(index) {
      // Find the scale element and focus it
      this.$nextTick(() => {
        const scaleElements = this.$el.querySelectorAll('[role="radio"]')
        const scaleElement = scaleElements[index]
        if (scaleElement) {
          scaleElement.focus()
        }
      })
    },
    getScaleTabIndex(scaleValue) {
      // Make the current value focusable, or first scale if no value
      const currentIndex = this.compVal !== null && this.compVal !== undefined 
        ? this.scaleList.findIndex(scale => scale === this.compVal)
        : 0
      const targetIndex = currentIndex >= 0 ? currentIndex : 0
      const thisIndex = this.scaleList.findIndex(scale => scale === scaleValue)
      return thisIndex === targetIndex ? '0' : '-1'
    },
  },
}
</script>
