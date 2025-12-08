<template>
  <div
    ref="select"
    :class="variantSlots.container({ class: ui?.slots?.container })"
  >
    <UPopover
      v-model:open="isOpen"
      :content="{
        align: 'start',
        side: 'bottom',
        sideOffset: 4
      }"
      :ui="{
        content: 'bg-white dark:!bg-notion-dark-light shadow-xl z-30 overflow-auto ' + borderRadiusClass
      }"
    >
      <template #anchor>
        <div
          :style="inputStyle"
          :class="[variantSlots.anchor({ class: ui?.slots?.anchor }), inputClass]"
        >
        <button
          type="button"
          aria-haspopup="listbox"
          :aria-expanded="isOpen"
          aria-labelledby="listbox-label"
          :class="variantSlots.button({ class: ui?.slots?.button })"
          @click.stop="toggleDropdown"
          @focus="onFocus"
          @blur="onBlur"
          @keydown="handleButtonKeydown"
        >
          <div
            :class="variantSlots.buttonInner({ class: ui?.slots?.buttonInner })"
          >
            <transition
              name="fade"
              mode="out-in"
            >
              <Loader
                v-if="loading"
                key="loader"
                class="h-6 w-6 text-form mx-auto"
              />
              <div
                v-else-if="modelValue"
                key="value"
                class="flex"
              >
                <slot
                  name="selected"
                  :option="modelValue"
                  :toggle="select"
                />
              </div>
              <div
                v-else
                key="placeholder"
              >
                <slot name="placeholder">
                  <div
                    :class="[
                      variantSlots.placeholder({ class: ui?.slots?.placeholder }),
                      { 'py-1': multiple && !loading }
                    ]"
                  >
                    {{ placeholder }}
                  </div>
                </slot>
              </div>
            </transition>
          </div>
          <div
            :class="variantSlots.chevronGradient({ class: ui?.slots?.chevronGradient })"
          />
          <span
            :class="variantSlots.chevronContainer({ class: ui?.slots?.chevronContainer })"
          >
            <Icon
              name="heroicons:chevron-up-down-16-solid" 
              :class="variantSlots.chevronIcon({ class: ui?.slots?.chevronIcon })"
            />
          </span>
        </button>
        <button
          v-if="clearable && showClearButton && !disabled && !isEmpty"
          :class="variantSlots.clearButton({ class: ui?.slots?.clearButton })"
          @click.stop.prevent="clear()"
        >
          <Icon
            name="heroicons:x-mark-20-solid"
            :class="variantSlots.clearIcon({ class: ui?.slots?.clearIcon })"
            width="2em"
            dynamic
          />
        </button>
        </div>
      </template>

      <template #content>
        <div
          tabindex="-1"
          role="listbox"
          ref="scrollRef"
          :class="variantSlots.dropdown({ class: ui?.slots?.dropdown })"
          class="w-(--reka-popper-anchor-width)"
          :style="popoverContentStyle"
          :aria-activedescendant="highlightedIndex >= 0 ? `option-${highlightedIndex}` : undefined"
          @keydown="handleDropdownKeydown"
        >
          <div
            v-if="isSearchable"
            :class="variantSlots.searchContainer({ class: ui?.slots?.searchContainer })"
          >
            <input
              ref="searchInput"
              v-model="searchTerm"
              type="text"
              :class="variantSlots.searchInput({ class: ui?.slots?.searchInput })"
              :placeholder="allowCreation ? $t('forms.select.searchOrTypeToCreateNew') : $t('forms.select.search')"
              @keydown="handleSearchKeydown"
            >
            <div
              v-if="!searchTerm"
              :class="variantSlots.searchIconContainer({ class: ui?.slots?.searchIconContainer })"
            >
              <Icon
                name="heroicons:magnifying-glass-solid"
                :class="variantSlots.searchIcon({ class: ui?.slots?.searchIcon })"
              />
            </div>
            <div
              v-else
              role="button"
              :class="variantSlots.searchClearContainer({ class: ui?.slots?.searchClearContainer })"
              @click.stop="searchTerm = ''"
            >
              <Icon
                name="heroicons:backspace"
                :class="variantSlots.searchClearIcon({ class: ui?.slots?.searchClearIcon })"
              />
            </div>
          </div>
          <div
            v-if="loading"
            class="w-full py-2 flex justify-center"
          >
            <Loader class="h-6 w-6 text-blue-500 mx-auto" />
          </div>
          <div
            v-if="filteredOptions.length > 0"
            :class="variantSlots.optionsContainer({ class: ui?.slots?.optionsContainer })"
          >
            <div
              v-if="virtualizer"
              role="presentation"
              :style="{ height: virtualizer.getTotalSize() + 'px', width: '100%', position: 'relative' }"
            >
              <div
                v-for="virtualItem in virtualizer.getVirtualItems()"
                :key="filteredOptions[virtualItem.index] ? filteredOptions[virtualItem.index][optionKey] : virtualItem.index"
                :id="`option-${virtualItem.index}`"
                role="option"
                :aria-selected="filteredOptions[virtualItem.index] ? isSelected(filteredOptions[virtualItem.index]) : false"
                :data-index="virtualItem.index"
                :ref="virtualizer ? virtualizer.measureElement : null"
                :style="[
                  optionStyle,
                  {
                    position: 'absolute',
                    top: virtualItem.start + 'px',
                    left: '0px',
                    width: '100%'
                  }
                ]"
                :class="[
                  variantSlots.option({ class: ui?.slots?.option }),
                  dropdownClass,
                  { 'pr-9': multiple},
                  { 
                    'opacity-50 cursor-not-allowed': filteredOptions[virtualItem.index] && disabledOptionsMap[filteredOptions[virtualItem.index][optionKey]],
                    'cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-900': filteredOptions[virtualItem.index] && !disabledOptionsMap[filteredOptions[virtualItem.index][optionKey]],
                    'bg-gray-100 dark:bg-gray-900': highlightedIndex === virtualItem.index
                  }
                ]"
                @click.stop="filteredOptions[virtualItem.index] && select(filteredOptions[virtualItem.index])"
                @mouseenter="highlightedIndex = virtualItem.index"
              >
                <slot
                  v-if="filteredOptions[virtualItem.index]"
                  name="option"
                  :option="filteredOptions[virtualItem.index]"
                  :selected="isSelected(filteredOptions[virtualItem.index])"
                />
              </div>
            </div>
            <div v-else>
              <div
                v-for="(option, index) in filteredOptions"
                :key="option[optionKey]"
                :id="`option-${index}`"
                role="option"
                :aria-selected="isSelected(option)"
                :style="optionStyle"
                :class="[
                  variantSlots.option({ class: ui?.slots?.option }),
                  dropdownClass,
                  { 'pr-9': multiple},
                  { 
                    'opacity-50 cursor-not-allowed': disabledOptionsMap[option[optionKey]],
                    'cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-900': !disabledOptionsMap[option[optionKey]],
                    'bg-gray-100 dark:bg-gray-900': highlightedIndex === index
                  }
                ]"
                @click.stop="select(option)"
                @mouseenter="highlightedIndex = index"
              >
                <slot
                  name="option"
                  :option="option"
                  :selected="isSelected(option)"
                />
              </div>
            </div>
          </div>
          <slot
            v-else-if="!loading && !(allowCreation && searchTerm)"
            name="empty-placeholder"
          >
            <p
              :class="variantSlots.emptyMessage({ class: ui?.slots?.emptyMessage })"
            >
              {{ (allowCreation ? $t('forms.select.typeSomethingToAddAnOption') : $t('forms.select.noOptionAvailable')) }}.
            </p>
          </slot>
          <div
            v-if="allowCreation && searchTerm"
            class="border-t border-neutral-300 p-1"
          >
            <div
              role="option"
              :style="optionStyle"
              :class="[
                variantSlots.createOption({ class: ui?.slots?.createOption }),
                { 'px-3 pr-9': multiple, 'px-3': !multiple },
                dropdownClass
              ]"
              @click.stop="createOption(searchTerm)"
            >
              {{ $t('forms.select.create') }} <span :class="variantSlots.createLabel({ class: ui?.slots?.createLabel })">{{
                searchTerm
              }}</span>
            </div>
          </div>
          <slot name="after-options" />
        </div>
      </template>
    </UPopover>
  </div>
</template>

<script>
import debounce from 'debounce'
import Fuse from 'fuse.js'
import { tv } from "tailwind-variants"
import { vSelectTheme } from "~/lib/forms/themes/v-select.theme.js"
import { useVirtualizer } from '@tanstack/vue-virtual'

export default {
  name: 'VSelect',
  components: {},
  directives: {},
  props: {
    data: Array,
    modelValue: { default: null, type: [String, Number, Array, Object, Boolean] },
    inputClass: { type: String, default: null },
    dropdownClass: { type: String, default: 'w-full' },
    loading: { type: Boolean, default: false },
    required: { type: Boolean, default: false },
    multiple: { type: Boolean, default: false },
    searchable: { type: Boolean, default: false },
    clearable: { type: Boolean, default: false },
    hasError: { type: Boolean, default: false },
    remote: { type: Function, default: null },
    searchKeys: { type: Array, default: () => ['name'] },
    optionKey: { type: String, default: 'id' },
    emitKey: { type: String, default: null },
    color: { type: String, default: '#3B82F6' },
    placeholder: { type: String, default: null },
    uppercaseLabels: { type: Boolean, default: true },
    showClearButton: { type: Boolean, default: true },
    theme: {type: String, default: null},
    size: {type: String, default: null}, 
    borderRadius: {type: String, default: null},
    ui: {type: Object, default: () => ({})},
    allowCreation: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    minSelection: { type: Number, default: null },
    maxSelection: { type: Number, default: null },
    fuseOptions: { type: Object, default: () => ({}) },
    searchDebounceMs: { type: Number, default: 150 },
    minSearchLength: { type: Number, default: 1 },
    // Explicit popover width control. Accepts number (px) or CSS length string.
    popoverWidth: { type: [String, Number], default: null }
  },
  emits: ['update:modelValue', 'update-options', 'focus', 'blur'],
  data () {
    return {
      isOpen: false,
      searchTerm: '',
        debouncedTerm: '',
      defaultValue: this.modelValue ?? null,
      isFocused: false,
        virtualizer: null,
        fuse: null,
        fuseIndex: null,
        updateDebouncedTerm: null,
        highlightedIndex: -1
    }
  },
  computed: {
    // Resolve theme values with proper reactivity
    resolvedTheme() {
      return this.theme || 'default'
    },
    resolvedSize() {
      return this.size || 'md'
    },
    resolvedBorderRadius() {
      return this.borderRadius || 'small'
    },

    // Create select variants with UI prop merging
    vSelectVariants() {
      return tv(vSelectTheme, this.ui)
    },

    // Single variant computation
    variantSlots() {
      return this.vSelectVariants({
        theme: this.resolvedTheme,
        size: this.resolvedSize,
        borderRadius: this.resolvedBorderRadius,
        hasError: this.hasError,
        disabled: this.disabled,
        focused: this.isFocused,
        multiple: this.multiple,
        searchable: this.isSearchable
      })
    },

    // Use variantSlots directly in the template for classes
    borderRadiusClass() {
      return this.variantSlots.anchor().match(/rounded-\S+/)?.[0] || 'rounded-lg'
    },

    optionStyle () {
      return {
        '--bg-form-color': this.color
      }
    },
    inputStyle () {
      return {
        '--tw-ring-color': this.color
      }
    },
    popoverContentStyle () {
      const widthValue = this.popoverWidth !== null && this.popoverWidth !== undefined
        ? (typeof this.popoverWidth === 'number' ? `${this.popoverWidth}px` : String(this.popoverWidth))
        : 'var(--reka-popper-anchor-width)'
      return { width: widthValue }
    },
    debouncedRemote () {
      if (this.remote) {
        return debounce(this.remote, 300)
      }
      return null
    },
    filteredOptions () {
      if (!this.data) return []

      // If not searchable, remote search is used, or term too short/empty, return raw data
      if (!this.searchable || this.remote) {
        return this.data
      }

      const term = this.debouncedTerm
      if (!term || term.length < this.minSearchLength) {
        return this.data
      }

      // Ensure Fuse is ready
      if (!this.fuse) {
        this.buildFuse()
      }
      if (!this.fuse) return this.data

      return this.fuse.search(term).map((res) => res.item)
    },
    isSearchable () {
      return this.searchable || this.remote !== null || this.allowCreation
    },
    isEmpty () {
      return this.multiple ? !this.modelValue || this.modelValue.length === 0 : !this.modelValue
    },
    selectedCount () {
      if (!this.multiple || !Array.isArray(this.modelValue)) return 0
      return this.modelValue.length
    },
    maxSelectionReached () {
      if (!this.multiple || !this.maxSelection) return false
      return this.selectedCount >= this.maxSelection
    },
    disabledOptionsMap () {
      if (!this.multiple || !this.maxSelection || !this.data) return {}
      
      const map = {}
      for (const item of this.data) {
        const isSelected = this.isSelected(item)
        map[item[this.optionKey]] = !isSelected && this.maxSelectionReached
      }
      return map
    },
    estimatedItemSizePx () {
      switch (this.resolvedSize) {
        case 'xs': return 28
        case 'sm': return 32
        case 'lg': return 52
        case 'md':
        default: return 40
      }
    }
  },
  watch: {
    searchTerm (val) {
      // Remote search path
      if (this.debouncedRemote) {
        if ((this.remote && val) || (val === '' && !this.modelValue) || (val === '' && this.isOpen)) {
          this.debouncedRemote(val)
        }
      } else {
        // Local search path: debounce updates
        if (this.updateDebouncedTerm) this.updateDebouncedTerm(val)
      }
    },
    data () {
      // Only (re)build fuse when using local search
      if (this.searchable && !this.remote) {
        this.buildFuse()
      } else {
        this.fuse = null
        this.fuseIndex = null
      }
    },
    isOpen (val) {
      if (val) {
        this.$nextTick(() => {
          this.setupVirtualizer()
          // Focus the search input or dropdown for keyboard navigation
          if (this.isSearchable && this.$refs.searchInput) {
            this.$refs.searchInput.focus()
          } else if (this.$refs.scrollRef) {
            this.$refs.scrollRef.focus()
          }
        })
      } else {
        // Reset highlighted index when closing
        this.highlightedIndex = -1
      }
    },
    filteredOptions () {
      // Reset highlighted index when options change
      this.highlightedIndex = -1
      if (this.isOpen) {
        this.$nextTick(() => {
          this.setupVirtualizer()
        })
      }
    },
    resolvedSize () {
      if (this.isOpen) {
        this.$nextTick(() => {
          this.setupVirtualizer()
        })
      }
    },
    popoverWidth () {
      if (this.isOpen) {
        this.$nextTick(() => {
          this.setupVirtualizer()
        })
      }
    }
  },
  mounted () {
    // Initialize fuse for local search and debounce handler
    this.buildFuse()
    this.updateDebouncedTerm = debounce((val) => {
      this.debouncedTerm = val
    }, this.searchDebounceMs)
  },
  beforeUnmount () {
    // Clean up virtualizer if it exists
    if (this.virtualizer) {
      this.virtualizer = null
    }
    if (this.updateDebouncedTerm && this.updateDebouncedTerm.clear) {
      this.updateDebouncedTerm.clear()
    }
  },
  methods: {
    buildFuse () {
      if (!this.data || !Array.isArray(this.data) || this.data.length === 0) {
        this.fuse = null
        this.fuseIndex = null
        return
      }

      const options = Object.assign({
        keys: this.searchKeys,
        threshold: 0.3,
        ignoreLocation: true,
        includeScore: false
      }, this.fuseOptions || {})

      try {
        const index = Fuse.createIndex(options.keys, this.data)
        this.fuseIndex = index
        this.fuse = new Fuse(this.data, options, index)
      } catch {
        // Fallback without precomputed index
        this.fuse = new Fuse(this.data, options)
        this.fuseIndex = null
      }
    },
    setupVirtualizer () {
      const scrollEl = this.$refs.scrollRef
      const previousScrollTop = scrollEl?.scrollTop ?? null

      // Restore scroll position after DOM updates (prevents jump on multi-select)
      const restoreScroll = () => {
        if (previousScrollTop === null) return
        this.$nextTick(() => {
          if (this.$refs.scrollRef) {
            this.$refs.scrollRef.scrollTop = previousScrollTop
          }
        })
      }

      if (!scrollEl || !this.filteredOptions?.length) {
        this.virtualizer = null
        return
      }
      
      // Clean up existing virtualizer
      this.virtualizer = null
      
      // Skip virtualization if list fits in visible height
      const maxVisibleHeight = scrollEl.clientHeight || 0
      const estimatedTotal = this.filteredOptions.length * this.estimatedItemSizePx
      if (maxVisibleHeight && estimatedTotal <= maxVisibleHeight) {
        restoreScroll()
        return
      }

      this.virtualizer = useVirtualizer({
        count: this.filteredOptions.length,
        getScrollElement: () => this.$refs.scrollRef,
        estimateSize: () => this.estimatedItemSizePx,
        overscan: 5,
        measureElement: (el) => el?.getBoundingClientRect().height ?? 0
      })

      restoreScroll()
    },
    isSelected (value) {
      if (!this.modelValue) return false

      if (this.emitKey && value[this.emitKey]) {
        value = value[this.emitKey]
      }

      if (this.multiple) {
        return this.modelValue.includes(value)
      }
      return this.modelValue === value
    },
    onFocus(event) {
      if (this.disabled) return
      this.isFocused = true
      this.$emit('focus', event)
    },

    onBlur(event) {
      if (this.disabled) return
      this.isFocused = false
      this.$emit('blur', event)
    },

    toggleDropdown () {
      if (this.disabled) {
        this.isOpen = false
        return
      }
      
      this.isOpen = !this.isOpen
      
      if (!this.isOpen) {
        this.searchTerm = ''
      }
    },
    select (value) {
      // Check if option is disabled (similar to how we check for input disabled)
      if (this.disabledOptionsMap[value[this.optionKey]]) {
        return
      }

      if (!this.multiple) {
        // Close after select
        this.toggleDropdown()
      }

      if (this.emitKey) {
        value = value[this.emitKey]
      }

      if (this.multiple) {
        const emitValue = Array.isArray(this.modelValue) ? [...this.modelValue] : []

        if (this.isSelected(value)) {
          // Only remove if clearable or not the last item
          if (this.clearable || emitValue.length > 1) {
            this.$emit('update:modelValue', emitValue.filter((item) => {
              if (this.emitKey) {
                return item !== value
              }
              return item[this.optionKey] !== value && item[this.optionKey] !== value[this.optionKey]
            }))
          }
          return
        }

        emitValue.push(value)
        this.$emit('update:modelValue', emitValue)
      } else {
        // For single select, only change value if it's different or clearable
        if (this.modelValue !== value || this.clearable) {
          this.$emit('update:modelValue', this.modelValue === value && this.clearable ? null : value)
        }
      }
    },
    clear () {
      this.$emit('update:modelValue', this.multiple ? [] : null)
    },
    createOption (newOption) {
      if (newOption) {
        const newItem = {
          name: newOption,
          value: newOption,
          id: newOption
        }
        this.$emit('update-options', newItem)
        this.select(newItem)
        this.searchTerm = ''
      }
    },
    handleButtonKeydown (event) {
      if (this.disabled) return

      // ArrowDown or ArrowUp: Open dropdown and highlight first/last option
      if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault()
        if (!this.isOpen) {
          this.isOpen = true
          this.$nextTick(() => {
            if (event.key === 'ArrowDown') {
              this.highlightedIndex = 0
            } else {
              this.highlightedIndex = this.filteredOptions.length - 1
            }
            this.scrollToHighlighted()
          })
        }
      }
      
      // Space or Enter: Toggle dropdown
      if (event.key === ' ' || event.key === 'Enter') {
        event.preventDefault()
        this.toggleDropdown()
      }
      
      // Escape: Close dropdown
      if (event.key === 'Escape' && this.isOpen) {
        event.preventDefault()
        this.isOpen = false
      }
    },
    handleSearchKeydown (event) {
      // Handle arrow keys in search input for option navigation
      if (event.key === 'ArrowDown') {
        event.preventDefault()
        if (this.highlightedIndex < 0) {
          this.highlightedIndex = 0
        } else {
          this.highlightNext()
        }
      } else if (event.key === 'ArrowUp') {
        event.preventDefault()
        if (this.highlightedIndex < 0) {
          this.highlightedIndex = this.filteredOptions.length - 1
        } else {
          this.highlightPrevious()
        }
      } else if (event.key === 'Enter') {
        event.preventDefault()
        if (this.highlightedIndex >= 0 && this.filteredOptions[this.highlightedIndex]) {
          const option = this.filteredOptions[this.highlightedIndex]
          if (!this.disabledOptionsMap[option[this.optionKey]]) {
            this.select(option)
          }
        } else if (this.allowCreation && this.searchTerm) {
          // Create new option if allowCreation is enabled
          this.createOption(this.searchTerm)
        }
      } else if (event.key === 'Escape') {
        event.preventDefault()
        if (this.searchTerm) {
          this.searchTerm = ''
        } else {
          this.isOpen = false
        }
      } else if (event.key === 'Home') {
        // Don't prevent default for Home/End in text input (cursor movement)
        // But highlight first option
        if (event.ctrlKey || event.metaKey) {
          event.preventDefault()
          this.highlightedIndex = 0
          this.scrollToHighlighted()
        }
      } else if (event.key === 'End') {
        if (event.ctrlKey || event.metaKey) {
          event.preventDefault()
          this.highlightedIndex = this.filteredOptions.length - 1
          this.scrollToHighlighted()
        }
      }
    },
    handleDropdownKeydown (event) {
      if (!this.isOpen || this.filteredOptions.length === 0) return

      // If search input is focused, don't handle arrow keys (let user type)
      if (this.isSearchable && document.activeElement?.tagName === 'INPUT') {
        // Only handle Escape and Enter when search is focused
        if (event.key === 'Escape') {
          event.preventDefault()
          this.isOpen = false
          return
        }
        if (event.key === 'Enter' && this.highlightedIndex >= 0) {
          event.preventDefault()
          const option = this.filteredOptions[this.highlightedIndex]
          if (option && !this.disabledOptionsMap[option[this.optionKey]]) {
            this.select(option)
          }
          return
        }
        return
      }

      switch (event.key) {
        case 'ArrowDown':
          event.preventDefault()
          this.highlightNext()
          break
        case 'ArrowUp':
          event.preventDefault()
          this.highlightPrevious()
          break
        case 'Home':
          event.preventDefault()
          this.highlightedIndex = 0
          this.scrollToHighlighted()
          break
        case 'End':
          event.preventDefault()
          this.highlightedIndex = this.filteredOptions.length - 1
          this.scrollToHighlighted()
          break
        case 'Enter':
        case ' ':
          event.preventDefault()
          if (this.highlightedIndex >= 0) {
            const option = this.filteredOptions[this.highlightedIndex]
            if (option && !this.disabledOptionsMap[option[this.optionKey]]) {
              this.select(option)
            }
          }
          break
        case 'Escape':
          event.preventDefault()
          this.isOpen = false
          break
        case 'Tab':
          // Allow tab to close dropdown and move focus
          this.isOpen = false
          break
      }
    },
    highlightNext () {
      if (this.filteredOptions.length === 0) return
      
      let nextIndex = this.highlightedIndex + 1
      // Skip disabled options
      while (nextIndex < this.filteredOptions.length) {
        const option = this.filteredOptions[nextIndex]
        if (!this.disabledOptionsMap[option[this.optionKey]]) {
          this.highlightedIndex = nextIndex
          this.scrollToHighlighted()
          return
        }
        nextIndex++
      }
      // If we've reached the end, wrap to beginning
      if (this.highlightedIndex >= this.filteredOptions.length - 1) {
        nextIndex = 0
        while (nextIndex < this.filteredOptions.length) {
          const option = this.filteredOptions[nextIndex]
          if (!this.disabledOptionsMap[option[this.optionKey]]) {
            this.highlightedIndex = nextIndex
            this.scrollToHighlighted()
            return
          }
          nextIndex++
        }
      }
    },
    highlightPrevious () {
      if (this.filteredOptions.length === 0) return
      
      let prevIndex = this.highlightedIndex <= 0 ? this.filteredOptions.length - 1 : this.highlightedIndex - 1
      // Skip disabled options
      while (prevIndex >= 0) {
        const option = this.filteredOptions[prevIndex]
        if (!this.disabledOptionsMap[option[this.optionKey]]) {
          this.highlightedIndex = prevIndex
          this.scrollToHighlighted()
          return
        }
        prevIndex--
      }
      // If we've reached the beginning, wrap to end
      if (prevIndex < 0) {
        prevIndex = this.filteredOptions.length - 1
        while (prevIndex >= 0) {
          const option = this.filteredOptions[prevIndex]
          if (!this.disabledOptionsMap[option[this.optionKey]]) {
            this.highlightedIndex = prevIndex
            this.scrollToHighlighted()
            return
          }
          prevIndex--
        }
      }
    },
    scrollToHighlighted () {
      this.$nextTick(() => {
        const scrollContainer = this.$refs.scrollRef
        if (!scrollContainer) return

        if (this.virtualizer) {
          // For virtualized list, use virtualizer's scrollToIndex
          this.virtualizer.scrollToIndex(this.highlightedIndex, { align: 'center' })
        } else {
          // For non-virtualized list, manually scroll the highlighted element into view
          const options = scrollContainer.querySelectorAll('[role="option"]')
          const highlightedOption = options[this.highlightedIndex]
          if (highlightedOption) {
            highlightedOption.scrollIntoView({ block: 'nearest', behavior: 'smooth' })
          }
        }
      })
    }
  }
}
</script>
