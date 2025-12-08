<template>
  <input-wrapper v-bind="inputWrapperProps">
    <template #label>
      <slot name="label" />
    </template>

    <Loader
      v-if="loading"
      key="loader"
      class="h-6 w-6 text-blue-500 mx-auto"
    />
    <div
      v-else
      :class="ui.container({ class: props.ui?.slots?.container })"
      :role="multiple ? 'group' : 'radiogroup'"
      :aria-label="label || `Select ${multiple ? 'options' : 'option'}`"
    >
      <template
        v-if="options && options.length"
      >
        <div
          v-for="(option, index) in options"
          :key="option[optionKey]"
          :role="multiple?'checkbox':'radio'"
          :aria-checked="isSelected(option[optionKey])"
          :class="ui.option({ optionDisabled: disabled || disableOptions.includes(option[optionKey]) || isOptionDisabled(option[optionKey]), class: props.ui?.slots?.option })"
          :tabindex="getOptionTabIndex(index)"
          @click="onSelect(option[optionKey])"
          @keydown="handleKeydown($event, index)"
        >
          <template v-if="multiple">
            <CheckboxIcon
              :is-checked="isSelected(option[optionKey])"
              :color="color"
              :theme="resolvedTheme"
            />
          </template>
          <template v-else>
            <RadioButtonIcon
              :is-checked="isSelected(option[optionKey])"
              :color="color"
              :theme="resolvedTheme"
            />
          </template>
          <UTooltip
            :text="disableOptionsTooltip"
            :disabled="!disableOptions.includes(option[optionKey])"
            class="w-full"
          >
            <slot
              name="option"
              :option="option"
              :selected="isSelected(option[optionKey])"
            >
              <p class="flex-grow">
                {{ option[displayKey] }}
              </p>
            </slot>
          </UTooltip>
        </div>
      </template>
      <div
        v-else
        :class="[ui.option({ class: props.ui?.slots?.option }), '!text-neutral-500 !cursor-not-allowed']"
      >
        {{ $t('forms.select.noOptionAvailable') }}
      </div>
    </div>

    <template #help>
      <slot name="help" />
    </template>

    <template
      v-if="multiple && (minSelection || maxSelection) && selectedCount > 0"
      #bottom_after_help
    >
      <small :class="ui.help({ class: props.ui?.slots?.help })">
        <span v-if="minSelection && maxSelection">
          {{ selectedCount }} of {{ minSelection }}-{{ maxSelection }}
        </span>
        <span v-else-if="minSelection">
          {{ selectedCount }} selected (min {{ minSelection }})
        </span>
        <span v-else-if="maxSelection">
          {{ selectedCount }}/{{ maxSelection }} selected
        </span>
      </small>
    </template>

    <template #error>
      <slot name="error" />
    </template>
  </input-wrapper>
</template>

<script>
import {inputProps, useFormInput} from "../useFormInput.js"
import RadioButtonIcon from "./components/RadioButtonIcon.vue"
import CheckboxIcon from "./components/CheckboxIcon.vue"
import { flatSelectInputTheme } from "~/lib/forms/themes/flat-select-input.theme.js"

/**
 * Options: {name,value} objects
 */
export default {
  name: "FlatSelectInput",
  components: {RadioButtonIcon, CheckboxIcon},

  props: {
    ...inputProps,
    options: {type: Array, required: true},
    optionKey: {type: String, default: "value"},
    emitKey: {type: String, default: "value"},
    displayKey: {type: String, default: "name"},
    loading: {type: Boolean, default: false},
    multiple: { type: Boolean, default: false },
    disableOptions: { type: Array, default: () => [] },
    disableOptionsTooltip: { type: String, default: "Not allowed" },
    clearable: { type: Boolean, default: false },
    minSelection: { type: Number, default: null },
    maxSelection: { type: Number, default: null }
  },
  setup(props, context) {
    const formInput = useFormInput(props, context, {
      variants: flatSelectInputTheme,
      additionalVariants: {
        loading: props.loading,
        multiple: props.multiple
      }
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
    selectedOptions() {
      if (!this.compVal) return []
      
      if (this.multiple) {
        return this.options.filter(option => this.compVal.includes(option[this.optionKey]))
      }
      
      return this.options.find(option => option[this.optionKey] === this.compVal) || null
    },
    selectedCount() {
      if (!this.multiple || !Array.isArray(this.compVal)) return 0
      return this.compVal.length
    },
    maxSelectionReached() {
      if (!this.multiple || !this.maxSelection) return false
      return this.selectedCount >= this.maxSelection
    },
  },
  methods: {
    onSelect(value) {
      if (this.disabled || this.disableOptions.includes(value) || this.isOptionDisabled(value)) {
        return
      }

      if (this.multiple) {
        const emitValue = Array.isArray(this.compVal) ? [...this.compVal] : []

        // Already in value, remove it only if clearable or not the last item
        if (this.isSelected(value)) {
          const nextLen = emitValue.length - 1
          if (this.minSelection && nextLen < this.minSelection) return
          if (this.clearable || nextLen >= 1) {
            this.compVal = emitValue.filter((item) => item !== value)
          }
          return
        }

        // Otherwise add value
        emitValue.push(value)
        this.compVal = emitValue
      } else {
        // For single select, only change value if it's different or clearable
        if (this.compVal !== value || this.clearable) {
          const nextVal = this.compVal === value && this.clearable ? null : value
          this.compVal = nextVal
          if (nextVal !== null && nextVal !== undefined) {
            this.$emit('input-filled')
          }
        }
      }
    },
    isSelected(value) {
      if (!this.compVal) return false

      if (this.multiple) {
        return this.compVal.includes(value)
      }
      return this.compVal === value
    },
    isOptionDisabled(value) {
      if (!this.multiple || !this.maxSelection) return false
      // Allow deselection of already selected options
      const isSelected = this.isSelected(value)
      return !isSelected && this.maxSelectionReached
    },
    getOptionName(option) {
      return option ? option[this.displayKey] : ''
    },
    getSelectedOptionsNames() {
      if (!this.compVal) return []
      
      if (this.multiple) {
        return this.selectedOptions.map(option => option[this.displayKey])
      }
      
      return [this.getOptionName(this.selectedOptions)]
    },
    handleKeydown(event, currentIndex) {
      if (this.disabled || !this.options || this.options.length === 0) return

      const maxIndex = this.options.length - 1
      let nextIndex = currentIndex

      switch (event.key) {
        case 'ArrowDown':
          event.preventDefault()
          nextIndex = Math.min(currentIndex + 1, maxIndex)
          break
        case 'ArrowUp':
          event.preventDefault()
          nextIndex = Math.max(currentIndex - 1, 0)
          break
        case 'Enter':
        case ' ':
          event.preventDefault()
          if (this.options[currentIndex]) {
            this.onSelect(this.options[currentIndex][this.optionKey])
          }
          return
        case 'Home':
          event.preventDefault()
          nextIndex = 0
          break
        case 'End':
          event.preventDefault()
          nextIndex = maxIndex
          break
        default:
          // For single select, allow typing to jump to options
          if (!this.multiple && event.key.length === 1) {
            const char = event.key.toLowerCase()
            const startIndex = (currentIndex + 1) % this.options.length
            
            for (let i = 0; i < this.options.length; i++) {
              const searchIndex = (startIndex + i) % this.options.length
              const option = this.options[searchIndex]
              const optionText = option[this.displayKey].toLowerCase()
              
              if (optionText.startsWith(char)) {
                event.preventDefault()
                nextIndex = searchIndex
                break
              }
            }
          } else {
            return
          }
          break
      }

      // Move focus to the next option
      if (nextIndex !== currentIndex) {
        this.focusOnOption(nextIndex)
      }
    },
    focusOnOption(index) {
      // Find the option element and focus it
      this.$nextTick(() => {
        const optionElements = this.$el.querySelectorAll('[role="checkbox"], [role="radio"]')
        const optionElement = optionElements[index]
        if (optionElement) {
          optionElement.focus()
        }
      })
    },
    getOptionTabIndex(index) {
      // Make the first selected option focusable, or first option if none selected
      if (this.compVal) {
        if (this.multiple && Array.isArray(this.compVal) && this.compVal.length > 0) {
          const firstSelectedIndex = this.options.findIndex(option => 
            this.compVal.includes(option[this.optionKey])
          )
          return index === (firstSelectedIndex >= 0 ? firstSelectedIndex : 0) ? '0' : '-1'
        } else if (!this.multiple) {
          const selectedIndex = this.options.findIndex(option => 
            option[this.optionKey] === this.compVal
          )
          return index === (selectedIndex >= 0 ? selectedIndex : 0) ? '0' : '-1'
        }
      }
      return index === 0 ? '0' : '-1'
    },
  },
}
</script>
