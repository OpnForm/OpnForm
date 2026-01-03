<template>
  <input-wrapper v-bind="inputWrapperProps">
    <template #label>
      <slot name="label" />
    </template>

    <v-select
      v-model="compVal"
      :dusk="name"
      :data="finalOptions"
      :label="label"
      :option-key="optionKey"
      :emit-key="emitKey"
      :required="required"
      :multiple="multiple"
      :clearable="clearable"
      :searchable="searchable"
      :loading="loading"
      :color="color"
      :placeholder="placeholder"
      :uppercase-labels="uppercaseLabels"
      :has-error="hasError"
      :allow-creation="allowCreation"
      :disabled="disabled"
      :remote="remote"
      :dropdown-class="dropdownClass"
      :min-selection="minSelection"
      :max-selection="maxSelection"
      :theme="resolvedTheme"
      :size="resolvedSize"
      :border-radius="resolvedBorderRadius"
      :ui="ui"
      @update-options="updateOptions"
      @update:model-value="updateModelValue"
    >
      <template #selected="{ option }">
        <template v-if="multiple">
          <div class="flex items-center truncate ltr-only:mr-6 rtl-only:ml-6">
            <!-- Show images for multiple select if enabled -->
            <template v-if="hasImages && getSelectedOptionsWithImages().length > 0">
              <div class="flex items-center gap-1">
                <img
                  v-for="(opt, idx) in getSelectedOptionsWithImages().slice(0, 3)"
                  :key="idx"
                  :src="opt.image"
                  :alt="opt.name"
                  :title="opt.name"
                  :class="imageSizeClass"
                  class="rounded object-cover"
                >
                <span v-if="selectedValues.length > 3" class="text-sm text-neutral-500">
                  +{{ selectedValues.length - 3 }}
                </span>
              </div>
            </template>
            <span
              v-else
              class="truncate"
              :class="ui.selected({ class: props.ui?.slots?.selected })"
            >
              {{ getOptionNames(selectedValues).join(', ') }}
            </span>
          </div>
        </template>
        <template v-else>
          <slot
            name="selected"
            :option="option"
            :option-name="getOptionName(option)"
          >
            <div class="flex items-center truncate ltr-only:mr-6 rtl-only:ml-6 gap-2">
              <img
                v-if="hasImages && getOptionImage(option)"
                :src="getOptionImage(option)"
                :alt="getOptionName(option)"
                :title="optionDisplayMode === 'image_only' ? getOptionName(option) : ''"
                :class="imageSizeClass"
                class="rounded object-cover shrink-0"
              >
              <div
                v-if="optionDisplayMode !== 'image_only'"
                :class="ui.selected({ class: props.ui?.slots?.selected })"
              >
                {{ getOptionName(option) }}
              </div>
            </div>
          </slot>
        </template>
      </template>
      <template #option="{ option, selected }">
        <slot
          name="option"
          :option="option"
          :selected="selected"
        >
          <span class="flex items-center">
            <!-- Image (if enabled) -->
            <img
              v-if="hasImages && option.image"
              :src="option.image"
              :alt="option.name"
              :title="optionDisplayMode === 'image_only' ? option.name : ''"
              :class="[imageSizeClass, 'mr-3']"
              class="rounded object-cover shrink-0"
            >
            <!-- Placeholder if no image but images enabled -->
            <div
              v-else-if="hasImages && !option.image"
              :class="[imageSizeClass, 'mr-3 bg-neutral-200 dark:bg-neutral-700 rounded flex items-center justify-center shrink-0']"
            >
              <Icon name="heroicons:photo" class="w-4 h-4 text-neutral-400" />
            </div>
            <!-- Text (unless image_only mode) -->
            <p
              v-if="optionDisplayMode !== 'image_only'"
              class="flex-grow"
              :class="ui.option({ class: props.ui?.slots?.option })"
            >
              {{ getOptionName(option) }}
            </p>
            <span
              v-if="selected"
              class="absolute inset-y-0 right-0 flex items-center pr-4 dark:text-white"
            >
              <Icon
                name="heroicons:check-16-solid"
                class="w-5 h-5"
              />
            </span>
          </span>
        </slot>
      </template>
    </v-select>

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
import { computed } from 'vue'
import { inputProps, useFormInput } from '../useFormInput.js'
import { selectInputTheme } from '~/lib/forms/themes/select-input.theme.js'

/**
 * Options: {name,value} objects
 */
export default {
  name: 'SelectInput',
  components: {},

  props: {
    ...inputProps,
    options: { type: Array, required: true },
    optionKey: { type: String, default: 'value' },
    emitKey: { type: String, default: 'value' },
    displayKey: { type: String, default: 'name' },
    loading: { type: Boolean, default: false },
    multiple: { type: Boolean, default: false },
    searchable: { type: Boolean, default: false },
    clearable: { type: Boolean, default: false },
    allowCreation: { type: Boolean, default: false },
    dropdownClass: { type: String, default: 'w-full' },
    remote: { type: Function, default: null },
    minSelection: { type: Number, default: null },
    maxSelection: { type: Number, default: null },
    optionDisplayMode: { type: String, default: 'text_only' },
    optionImageSize: { type: String, default: 'md' }
  },
  setup(props, context) {
    const additionalVariants = computed(() => ({
      loading: props.loading,
      multiple: props.multiple,
      searchable: props.searchable,
      clearable: props.clearable
    }))

    const formInput = useFormInput(props, context, {
      variants: selectInputTheme,
      additionalVariants
    })

    return {
      ...formInput,
      props
    }
  },
  data() {
    return {
      additionalOptions: [],
      selectedValues: []
    }
  },
  computed: {
    finalOptions() {
      return this.options.concat(this.additionalOptions)
    },
    selectedCount() {
      if (!this.multiple || !Array.isArray(this.selectedValues)) return 0
      return this.selectedValues.length
    },
    hasImages() {
      return ['text_and_image', 'image_only'].includes(this.optionDisplayMode)
    },
    imageSizeClass() {
      const sizes = {
        sm: 'w-12 h-12',
        md: 'w-20 h-20',
        lg: 'w-28 h-28'
      }
      return sizes[this.optionImageSize] || sizes.md
    },
  },
  watch: {
    compVal: {
      handler(newVal, oldVal) {
        if (!oldVal) {
          this.handleCompValChanged()
        }
      },
      immediate: false
    }
  },
  mounted() {
    this.handleCompValChanged()
  },
  methods: {
    getOptionName(val) {
      if (val == null) return ''
      const option = this.finalOptions.find((optionCandidate) => {
        return optionCandidate && optionCandidate[this.optionKey] === val ||
          (typeof val === 'object' && val && optionCandidate && optionCandidate[this.optionKey] === val[this.optionKey])
      })
      if (option && option[this.displayKey] !== undefined) {
        return option[this.displayKey]
      }
      return val.toString() // Convert to string to ensure it's not null
    },
    getOptionNames(values) {
      if (!Array.isArray(values)) return []
      return values.map(val => this.getOptionName(val)).filter(Boolean)
    },
    getOptionImage(val) {
      if (val == null) return null
      const option = this.finalOptions.find((optionCandidate) => {
        return optionCandidate && optionCandidate[this.optionKey] === val ||
          (typeof val === 'object' && val && optionCandidate && optionCandidate[this.optionKey] === val[this.optionKey])
      })
      return option?.image || null
    },
    getSelectedOptionsWithImages() {
      if (!Array.isArray(this.selectedValues)) return []
      return this.selectedValues
        .map(val => {
          const option = this.finalOptions.find(opt => opt && opt[this.optionKey] === val)
          return option
        })
        .filter(opt => opt && opt.image)
    },
    updateModelValue(newValues) {
      if (newValues === null) newValues = []
      this.selectedValues = newValues
      if (!this.multiple) {
        const hasValue = newValues !== null && newValues !== undefined && newValues !== ''
        if (hasValue) this.$emit('input-filled')
      }
    },
    updateOptions(newItem) {
      if (newItem) {
        this.additionalOptions.push(newItem)
      }
    },
    handleCompValChanged() {
      if (this.compVal) {
        this.selectedValues = this.compVal
      }
    }
  }
}
</script>
