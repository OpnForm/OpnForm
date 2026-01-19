<template>
  <div
    v-if="field"
    class="pb-20"
  >
    <!-- General -->
    <div class="px-4">
      <text-input
        name="name"
        class="mt-2"
        :form="field"
        :required="true"
        wrapper-class="mb-2"
        label="Field Name"
      />
      <HiddenRequiredDisabled
        class="mt-4"
        :field="field"
      />
    </div>

    <!-- Focused Mode: Media settings (high priority under general) -->
    <div v-if="isFocused" class="mt-2">
      <BlockMediaOptions :model="field" :form="form" />
    </div>

    <!-- Checkbox -->
    <div
      v-if="field.type === 'checkbox'"
      class="px-4"
    >
      <EditorSectionHeader
        icon="i-heroicons-check-circle"
        title="Checkbox"
      />
      <toggle-switch-input
        v-if="!isFocused"
        :form="field"
        name="use_toggle_switch"
        label="Use toggle switch"
        help="If enabled, checkbox will be replaced with a toggle switch"
      />
      <template v-else>
        <flat-select-input
          v-model="field.focused_checkbox_style"
          name="focused_checkbox_style"
          class="mt-3"
          :form="field"
          :options="focusedCheckboxStyleOptions"
          label="Checkbox style"
          help="Choose how the checkbox appears in focused mode"
          @update:model-value="onFieldFocusedCheckboxStyleChange"
        />
      </template>
    </div>

    <!-- File Uploads -->
    <div
      v-if="field.type === 'files'"
      class="px-4"
    >
      <EditorSectionHeader
        icon="i-heroicons-paper-clip"
        title="File uploads"
      />
      <toggle-switch-input
        :form="field"
        name="multiple"
        label="Allow multiple files"
      />
      <toggle-switch-input
        :form="field"
        name="camera_upload"
        label="Allow Camera uploads"
      />
      <text-input
        name="allowed_file_types"
        class="mt-3"
        :form="field"
        label="Allowed file types"
        placeholder="jpg,jpeg,png,gif"
        help="Comma separated values, leave blank to allow all file types"
      />

      <text-input
        name="max_file_size"
        class="mt-3"
        :form="field"
        native-type="number"
        :min="1"
        :max="mbLimit"
        label="Maximum file size (in MB)"
        :placeholder="`1MB - ${mbLimit}MB`"
        help="Set the maximum file size that can be uploaded"
      />
    </div>

    <!-- Barcode Reader -->
    <div
      v-if="field.type === 'barcode'"
      class="px-4"
    >
      <EditorSectionHeader
        icon="i-material-symbols-barcode-scanner-rounded"
        title="Barcode Reader"
      />
      <select-input
        name="decoders"
        class="mt-4"
        :form="field"
        :options="barcodeDecodersOptions"
        label="Decoders"
        :searchable="true"
        :multiple="true"
        help="Select the decoders you want to use"
      />
    </div>

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
      />
    </div>

    <div
      v-if="field.type === 'scale'"
      class="px-4"
    >
      <EditorSectionHeader
        icon="i-heroicons-scale-20-solid"
        title="Scale"
      />
      <text-input
        name="scale_min_value"
        native-type="number"
        class="mt-4"
        :form="field"
        required
        label="Min scale value"
      />
      <text-input
        name="scale_max_value"
        native-type="number"
        :min="1"
        class="mt-4"
        :form="field"
        required
        label="Max scale value"
      />
      <text-input
        name="scale_step_value"
        native-type="number"
        :min="1"
        class="mt-4"
        :form="field"
        required
        label="Scale steps value"
      />
    </div>

    <div
      v-if="field.type === 'slider'"
      class="px-4"
    >
      <EditorSectionHeader
        icon="i-heroicons-adjustments-horizontal"
        title="Slider"
      />
      <text-input
        name="slider_min_value"
        native-type="number"
        class="mt-4"
        :form="field"
        required
        label="Min slider value"
      />
      <text-input
        name="slider_max_value"
        native-type="number"
        :min="1"
        class="mt-4"
        :form="field"
        required
        label="Max slider value"
      />
      <text-input
        name="slider_step_value"
        native-type="number"
        :min="1"
        class="mt-4"
        :form="field"
        required
        label="Slider steps value"
      />
    </div>

    <MatrixFieldOptions
      :model-value="field"
      @update:model-value="field = $event"
    />

    <PaymentFieldOptions
      v-if="field.type === 'payment'"
      :field="field"
      :form="form"
    />

    <!--   Text Options   -->
    <div
      v-if="field.type === 'text' && displayBasedOnAdvanced"
      class="px-4"
    >
      <EditorSectionHeader
        icon="i-heroicons-bars-3-bottom-left"
        title="Text Options"
      />
      <toggle-switch-input
        :form="field"
        name="multi_lines"
        label="Multi-lines input"
        @update:model-value="onFieldMultiLinesChange"
      />
      <toggle-switch-input
        :form="field"
        name="secret_input"
        help="Hide input content with * for privacy"
        @update:model-value="onFieldSecretInputChange"
      >
        <template #label>
          <span class="text-sm">
            Secret input
          </span>
          <pro-tag
            upgrade-modal-title="Upgrade today to enable secret input"
            class="-mt-1"
          />
        </template>
      </toggle-switch-input>
    </div>

    <!--   Date Options   -->
    <div
      v-if="field.type === 'date'"
      class="px-4"
    >
      <EditorSectionHeader
        icon="i-heroicons-calendar-20-solid"
        title="Date Options"
      />
      <toggle-switch-input
        :form="field"
        class="mt-3"
        name="date_range"
        label="Include end date"
        @update:model-value="onFieldDateRangeChange"
      />
      <toggle-switch-input
        :form="field"
        name="prefill_today"
        label="Prefill with 'today'"
        @update:model-value="onFieldPrefillTodayChange"
      />
      <toggle-switch-input
        :form="field"
        name="disable_past_dates"
        label="Disable past dates"
        @update:model-value="onFieldDisablePastDatesChange"
      />
      <toggle-switch-input
        :form="field"
        name="disable_future_dates"
        label="Disable future dates"
        @update:model-value="onFieldDisableFutureDatesChange"
      />
      <toggle-switch-input
        :form="field"
        name="with_time"
        label="Include time"
      />
      <select-input
        v-if="field.with_time"
        name="timezone"
        class="mt-4"
        :form="field"
        :options="timezonesOptions"
        label="Timezone"
        :searchable="true"
        help="Make sure to select the same timezone you're using in Notion. Leave blank otherwise."
      />
      <flat-select-input
        v-if="field.with_time"
        name="time_format"
        class="mt-4"
        :form="field"
        :options="timeFormatOptions"
        label="Time format"
      />
      <flat-select-input
        name="date_format"
        class="mt-4"
        :form="field"
        :options="dateFormatOptions"
        label="Date format"
      />
    </div>

    <!-- select/multiselect Options   -->
    <div
      v-if="['select', 'multi_select'].includes(field.type)"
      class="px-4"
    >
      <EditorSectionHeader
        icon="i-heroicons-chevron-up-down-20-solid"
        title="Select Options"
      />
      <text-area-input
        v-model="optionsText"
        :name="field.id + '_options_text'"
        class="mt-3"
        label="Set selection options"
        help="Add one option per line"
        @update:model-value="onFieldOptionsChange"
      />
      <toggle-switch-input
        v-if="isFocused"
        :model-value="field.use_focused_selector === false"
        label="Use dropdown instead"
        help="Use classic dropdown instead of focused selector with keyboard shortcuts"
        @update:model-value="onFieldUseDropdownInFocusedChange"
      />
      <toggle-switch-input
        v-if="!isFocusedSelectorActive"
        :form="field"
        name="allow_creation"
        label="Allow respondent to create new options"
        @update:model-value="onFieldAllowCreationChange"
      />
      <toggle-switch-input
        v-if="!isFocusedSelectorActive"
        :form="field"
        name="without_dropdown"
        label="Use radio buttons"
        @update:model-value="onFieldWithoutDropdownChange"
      />
      <toggle-switch-input
        :form="field"
        name="shuffle_options"
        label="Randomize options order"
      />
      
      <!-- Min/Max Selection Constraints for multi_select only -->
      <template v-if="field.type === 'multi_select'">
        <div class="flex gap-1">
        <text-input
          name="min_selection"
          native-type="number"
          :min="0"
          class="flex-1"
          :form="field"
          label="Min. required"
          placeholder="1"
          @update:model-value="onFieldMinSelectionChange"
        />
        <text-input
          name="max_selection"
          native-type="number"
          :min="1"
          class="flex-1"
          :form="field"
          label="Max. allowed"
          placeholder="2"
          @update:model-value="onFieldMaxSelectionChange"
        />
        <UButton
          icon="i-heroicons-backspace"
          color="neutral"
          variant="outline"
          class="self-end mb-1"
          title="Clear both values"
          @click="clearMinMaxSelection"
        />
      </div>
      <InputHelp help="Set min/max options allowed, or leave empty for unlimited. Save form to test changes." />
      </template>
    </div>

    <!-- Customization - Placeholder, Prefill, Relabel, Field Help    -->
    <div
      v-if="displayBasedOnAdvanced"
      class="px-4"
    >
      <EditorSectionHeader
        icon="i-heroicons-adjustments-horizontal"
        title="Customization"
      />

      <toggle-switch-input
        :form="field"
        name="hide_field_name"
        label="Hide field name"
      />

      <toggle-switch-input
        v-if="field.type === 'phone_number'"
        :form="field"
        name="use_simple_text_input"
        label="Use simple text input"
      />

      <template v-if="field.type === 'phone_number' && !field.use_simple_text_input">
        <select-input
          class="mt-3"
          v-model="field.unavailable_countries"
          popover-width="full"
          input-class="ltr-only:rounded-r-none rtl:rounded-l-none!"
          :options="allCountries"
          :multiple="true"
          :searchable="true"
          :search-keys="['name']"
          :option-key="'code'"
          :emit-key="'code'"
          label="Disabled countries"
          :placeholder="'Select a country'"
          help="Remove countries from the phone input"
        >
          <template #selected="{ option }">
            <div class="flex items-center space-x-2 justify-center overflow-hidden">
              {{ option.length }} selected
            </div>
          </template>
          <template #option="{ option, selected }">
            <div class="flex items-center gap-2 max-w-full">
              <country-flag
                size="normal"
                class="-mt-[9px]! rounded"
                :country="option.code"
              />
              <span class="truncate">{{ option.name }}</span>
              <span class="text-gray-500">{{ option.dial_code }}</span>
            </div>
            <span
              v-if="selected"
              class="absolute inset-y-0 right-0 flex items-center pr-2 dark:text-white"
            >
              <svg
                class="h-5 w-5"
                viewBox="0 0 20 20"
                fill="currentColor"
              >
                <path
                  fill-rule="evenodd"
                  d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                  clip-rule="evenodd"
                />
              </svg>
            </span>
          </template>
        </select-input>
        <small class="flex -mt-2">
          <a
            href="#"
            class="grow"
            @click.prevent="selectAllCountries"
          >Select All</a>
          <a
            href="#"
            @click.prevent="field.unavailable_countries = null"
          >Un-select All</a>
        </small>
      </template>

      <!-- Pre-fill depends on type -->
      <toggle-switch-input
        v-if="field.type == 'checkbox'"
        :form="field"
        name="prefill"
        label="Pre-filled value"
        @update:model-value="field.prefill = $event"
      />
      <select-input
        v-else-if="['select', 'multi_select'].includes(field.type)"
        name="prefill"
        class="mt-3"
        :form="field"
        :options="prefillSelectsOptions"
        label="Pre-filled value"
        :searchable="shouldEnableSelectSearch"
        :multiple="field.type === 'multi_select'"
      />
      <template v-else-if="field.type === 'matrix'">
        <MatrixInput
          :form="field"
          :rows="field.rows"
          :columns="field.columns"
          name="prefill"
          label="Pre-filled value"
        />
      </template>
      <date-input
        v-else-if="field.type === 'date' && field.prefill_today !== true"
        name="prefill"
        class="mt-3"
        :form="field"
        :time-format="field.time_format"
        :with-time="field.with_time === true"
        :date-range="field.date_range === true"
        label="Pre-filled value"
      />
      <text-input
        v-else-if="field.type==='date' && field.prefill_today===true"
        name="prefill"
        class="mt-4"
        disabled
        :form="field"
        label="Pre-filled value"
        placeholder="Pre-filled with current date"
      />
      <phone-input
        v-else-if="field.type === 'phone_number' && !field.use_simple_text_input"
        name="prefill"
        class="mt-3"
        :form="field"
        :can-only-country="true"
        :unavailable-countries="field.unavailable_countries ?? []"
        label="Pre-filled value"
      />
      <text-area-input
        v-else-if="field.type === 'text' && field.multi_lines"
        name="prefill"
        class="mt-3"
        :form="field"
        label="Pre-filled value"
      />
      <file-input
        v-else-if="field.type === 'files'"
        name="prefill"
        class="mt-4"
        :form="field"
        label="Pre-filled file"
        :multiple="field.multiple === true"
        :move-to-form-assets="true"
      />
      <rich-text-area-input
        v-else-if="field.type === 'rich_text'"
        :allow-fullscreen="true"
        name="prefill"
        class="mt-3"
        :form="field"
        label="Pre-filled value"
      />
      <text-input
        v-else-if="!['files', 'signature', 'rich_text', 'payment'].includes(field.type)"
        name="prefill"
        class="mt-3"
        :form="field"
        label="Pre-filled value"
      />
      <div
        v-if="['select', 'multi_select'].includes(field.type)"
        class="-mt-3 mb-3 text-neutral-400 dark:text-neutral-500"
      >
        <small>
          A problem? <a
            href="#"
            @click.prevent="field.prefill = null"
          >Click here to clear your pre-fill</a>
        </small>
      </div>

      <!-- Placeholder -->
      <text-area-input
        v-if="hasPlaceholder && ((field.type === 'text' && field.multi_lines) || field.type === 'rich_text')"
        name="placeholder"
        class="mt-3"
        :form="field"
        label="Empty Input Text - Placeholder"
      />
      <text-input
        v-else-if="hasPlaceholder"
        name="placeholder"
        class="mt-3"
        :form="field"
        label="Empty Input Text - Placeholder"
      />

      <OptionSelectorInput
        v-model="field.width"
        name="width"
        class="mt-4"
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

      <!--   Help  -->
      <RichTextAreaInput
        name="help"
        class="mt-3"
        :allow-fullscreen="true"
        :form="field"
        label="Help Text"
        :editor-options="{
          formats: [
            'bold',
            'color',
            'font',
            'italic',
            'link',
            'underline',
            'list',
            'strike'
          ],
          modules: {
            toolbar: [
              ['bold', 'italic', 'underline', 'strike'],
              ['link'],
              [{ list: 'ordered' }, { list: 'bullet' }]
            ]
          }
        }"
        help="Displayed below/above the field, like this text"
        :help-position="field.help_position"
      />
      <OptionSelectorInput
        v-model="field.help_position"
        name="help_position"
        class="mt-4 w-2/3"
        :form="field"
        label="Help Text Position"
        seamless
        :options="[
          { name: 'below_input', label: 'Below input'},
          { name: 'above_input', label: 'Above input'},
        ]"
        :multiple="false"
        :columns="2"
        @update:model-value="onFieldHelpPositionChange"
      />

      <template v-if="['text', 'rich_text', 'number', 'url', 'email'].includes(field.type)">
        <text-input
          name="max_char_limit"
          native-type="number"
          :min="1"
          :form="field"
          label="Max character limit"
          :required="false"
          class="mt-3"
          @update:model-value="onFieldMaxCharLimitChange"
        />
        <toggle-switch-input
          v-if="field.max_char_limit"
          name="show_char_limit"
          :form="field"
          class="mt-3"
          label="Always show character limit"
        />
      </template>
    </div>

    <!--  Advanced Options   -->
    <div
      v-if="field.type === 'text'"
      class="px-4"
    >
      <EditorSectionHeader
        icon="i-heroicons-bars-3-bottom-left"
        title="Advanced Options"
      />
      
      <toggle-switch-input
        :form="field"
        name="generates_uuid"
        label="Generates a unique id"
        help="If you enable this, we will hide this field and fill it with a unique id (UUID format) on each new form submission"
        @update:model-value="onFieldGenUIdChange"
      />
      <toggle-switch-input
        :form="field"
        name="generates_auto_increment_id"
        label="Generates an auto-incremented id"
        help="If you enable this, we will hide this field and fill it a unique incrementing number on each new form submission"
        @update:model-value="onFieldGenAutoIdChange"
      />
    </div>

  <!--  (moved above for focused mode)  -->
  </div>
</template>

<script>
import timezones from '~/data/timezones.json'
import countryCodes from '~/data/country_codes.json'
import CountryFlag from 'vue-country-flag-next'
import MatrixFieldOptions from './MatrixFieldOptions.vue'
import PaymentFieldOptions from './PaymentFieldOptions.vue'
import HiddenRequiredDisabled from './HiddenRequiredDisabled.vue'
import EditorSectionHeader from '~/components/open/forms/components/form-components/EditorSectionHeader.vue'
import ProTag from '~/components/app/ProTag.vue'
import { format } from 'date-fns'
import { default as _has } from 'lodash/has'
import blocksTypes from '~/data/blocks_types.json'
import BlockMediaOptions from '~/components/open/forms/components/media/BlockMediaOptions.vue'

export default {
  name: 'FieldOptions',
  components: { CountryFlag, MatrixFieldOptions, HiddenRequiredDisabled, EditorSectionHeader, PaymentFieldOptions, ProTag, BlockMediaOptions },
  props: {
    field: {
      type: Object,
      required: false
    },
    form: {
      type: Object,
      required: false
    }
  },
  setup() {
    const { current: currentWorkspace } = useCurrentWorkspace()
    return { currentWorkspace }
  },
  data() {
    return {
      typesWithoutPlaceholder: ['date', 'checkbox', 'files', 'payment', 'matrix', 'signature', 'barcode', 'scale', 'slider', 'rating'],
      allCountries: countryCodes,
      barcodeDecodersOptions: [
        { name: 'QR Code', value: 'qr_reader' },
        { name: 'EAN-13 (European Article Number)', value: 'ean_reader' },
        { name: 'EAN-8 (European Article Number)', value: 'ean_8_reader' },
        { name: 'UPC-A (Universal Product Code)', value: 'upc_reader' },
        { name: 'UPC-E (Universal Product Code)', value: 'upc_e_reader' },
        { name: 'Code 128', value: 'code_128_reader' },
        { name: 'Code 39', value: 'code_39_reader' }
      ]
    }
  },

  computed: {
    isFocused() {
      return this.form?.presentation_style === 'focused'
    },
    isFocusedSelectorActive() {
      // Focused selector is active when in focused mode AND not explicitly disabled
      return this.isFocused && this.field.use_focused_selector !== false
    },
    hasPlaceholder() {
      return !this.typesWithoutPlaceholder.includes(this.field.type)
    },
    focusedCheckboxStyleOptions() {
      return [
        { name: 'Yes/No selector (Y/N shortcuts)', value: 'focused_toggle' },
        { name: 'Toggle switch', value: 'toggle_switch' },
        { name: 'Classic checkbox', value: 'checkbox' }
      ]
    },
    mbLimit() {
      return  (this.form?.workspace && this.form?.workspace.max_file_size) ? this.form?.workspace?.max_file_size : 10
    },
    optionsText() {
      return this.field[this.field.type].options.map(option => option.name).join('\n')
    },
    prefillSelectsOptions() {
      if (!['select', 'multi_select'].includes(this.field.type)) return {}

      return this.field[this.field.type].options.map(option => {
        return {
          name: option.name,
          value: option.id
        }
      })
    },
    selectionOptionsCount() {
      if (!['select', 'multi_select'].includes(this.field.type)) return 0
      return Array.isArray(this.field[this.field.type]?.options) ? this.field[this.field.type].options.length : 0
    },
    shouldEnableSelectSearch() {
      return ['select', 'multi_select'].includes(this.field.type) && this.selectionOptionsCount > 5
    },
    timezonesOptions() {
      if (this.field.type !== 'date') return []
      return timezones.map((timezone) => {
        return {
          name: timezone.text,
          value: timezone.utc[0]
        }
      })
    },
    dateFormatOptions () {
      const date = new Date()
      return ['dd/MM/yyyy', 'MM-dd-yyyy'].map(dateFormat => {
        return {
          name: format(date, dateFormat),
          value: dateFormat
        }
      })
    },
    timeFormatOptions() {
      return [{ name: '13:00', value: '24', },
      { name: '01:00 PM', value: '12', },]
    },
    displayBasedOnAdvanced() {
      if (this.field.generates_uuid || this.field.generates_auto_increment_id) {
        return false
      }
      return true
    },
  },

  watch: {
    'field.width': {
      handler(val) {
        if (val === undefined || val === null) {
          this.field.width = 'full'
        }
      },
      immediate: true
    },
    'field.align': {
      handler(val) {
        if (val === undefined || val === null) {
          this.field.align = 'left'
        }
      },
      immediate: true
    },
    'field.type': {
      handler() {
        this.setDefaultFieldValues()
      },
      immediate: true
    },
    isFocused: {
      handler(val) {
        // When switching to focused mode for checkbox, set default style if not set
        if (val && this.field.type === 'checkbox' && !this.field.focused_checkbox_style) {
          this.field.focused_checkbox_style = 'focused_toggle'
          this.field.use_focused_toggle = true
        }
      },
      immediate: true
    },
    isFocusedSelectorActive: {
      handler(val) {
        // When focused selector becomes active, ensure conflicting options are disabled
        if (val && ['select', 'multi_select'].includes(this.field.type)) {
          this.field.without_dropdown = false
          this.field.allow_creation = false
        }
      },
      immediate: true
    }
  },

  created() {
    if (this.field?.width === undefined || this.field?.width === null) {
      this.field.width = 'full'
    }
  },

  mounted() {
    this.setDefaultFieldValues()
  },

  methods: {
    onFieldDateRangeChange(val) {
      this.field.date_range = val
      if (this.field.date_range) {
        this.field.prefill_today = false
      }
    },
    onFieldGenUIdChange(val) {
      this.field.generates_uuid = val
      if (this.field.generates_uuid) {
        this.field.generates_auto_increment_id = false
        this.field.hidden = true
      }
    },
    onFieldGenAutoIdChange(val) {
      this.field.generates_auto_increment_id = val
      if (this.field.generates_auto_increment_id) {
        this.field.generates_uuid = false
        this.field.hidden = true
      }
    },
    onFieldOptionsChange(val) {
      const vals = (val) ? val.trim().split('\n') : []
      const tmpOpts = vals.map(name => {
        return {
          name: name,
          id: name
        }
      })
      this.field[this.field.type] = { options: tmpOpts }
    },
    onFieldPrefillTodayChange(val) {
      this.field.prefill_today = val
      if (this.field.prefill_today) {
        this.field.prefill = null
        this.field.date_range = false
        this.field.disable_future_dates = false
        this.field.disable_past_dates = false
      } else {
        this.field.prefill = this.field.prefill ?? null
      }
    },
    onFieldAllowCreationChange(val) {
      this.field.allow_creation = val
      if (this.field.allow_creation) {
        this.field.without_dropdown = false
      }
    },
    onFieldWithoutDropdownChange(val) {
      this.field.without_dropdown = val
      if (this.field.without_dropdown) {
        this.field.allow_creation = false
        this.field.use_focused_selector = false
      }
    },
    onFieldUseDropdownInFocusedChange(val) {
      // Inverted logic: when "use dropdown instead" is ON, disable focused selector
      this.field.use_focused_selector = !val
      if (!this.field.use_focused_selector) {
        // When disabling focused selector (using dropdown instead), no need to disable other options
        // User can choose dropdown with creation or without_dropdown
      } else {
        // When enabling focused selector, force disable conflicting options
        this.field.without_dropdown = false
        this.field.allow_creation = false
      }
    },
    onFieldDisablePastDatesChange(val) {
      this.field.disable_past_dates = val
      if (this.field.disable_past_dates) {
        this.field.disable_future_dates = false
        this.field.prefill_today = false
      }
    },
    onFieldDisableFutureDatesChange(val) {
      this.field.disable_future_dates = val
      if (this.field.disable_future_dates) {
        this.field.disable_past_dates = false
        this.field.prefill_today = false
      }
    },
    onFieldHelpPositionChange(val) {
      if (!val) {
        this.field.help_position = 'below_input'
      }
    },
    onFieldMultiLinesChange(val) {
      this.field.multi_lines = val
      if (this.field.multi_lines) {
        this.field.secret_input = false
      }
    },
    onFieldSecretInputChange(val) {
      this.field.secret_input = val
      if (this.field.secret_input) {
        this.field.multi_lines = false
      }
    },
    selectAllCountries() {
      this.field.unavailable_countries = this.allCountries.map(item => {
        return item.code
      })
    },
    setDefaultFieldValues() {
      const defaultFieldValues = {
        files: {
          max_file_size: Math.min((this.field.max_file_size ?? this.mbLimit), this.mbLimit)
        },
        date: {
          date_format: this.dateFormatOptions[0].value,
          time_format: this.timeFormatOptions[0].value
        }
      }

      // Apply type-specific defaults from blocks_types.json if available
      if (this.field.type in blocksTypes && blocksTypes[this.field.type]?.default_values) {
        Object.keys(blocksTypes[this.field.type].default_values).forEach(key => {
          if (!_has(this.field, key)) {
            this.field[key] = blocksTypes[this.field.type].default_values[key]
          }
        })
      }

      // Apply additional defaults from defaultFieldValues if needed
      if (this.field.type in defaultFieldValues) {
        Object.keys(defaultFieldValues[this.field.type]).forEach(key => {
          if (!_has(this.field, key)) {
            this.field[key] = defaultFieldValues[this.field.type][key]
          }
        })
      }

      // Ensure critical defaults for specific types
      if (this.field.type === "rating" && !this.field.rating_max_value) {
        this.field.rating_max_value = 5
      } else if (this.field.type === "scale" && (!this.field.scale_min_value || !this.field.scale_max_value || !this.field.scale_step_value)) {
        this.field.scale_min_value = 1
        this.field.scale_max_value = 5
        this.field.scale_step_value = 1
      } else if (this.field.type === "slider" && (!this.field.slider_min_value || !this.field.slider_max_value || !this.field.slider_step_value)) {
        this.field.slider_min_value = 0
        this.field.slider_max_value = 50
        this.field.slider_step_value = 1
      } else if (["select", "multi_select"].includes(this.field.type) && !this.field[this.field.type]?.options) {
        this.field[this.field.type] = { options: [] }
      } else if (this.field.type === "checkbox" && this.isFocused && !this.field.focused_checkbox_style) {
        // Default to focused toggle in focused mode
        this.field.focused_checkbox_style = 'focused_toggle'
        this.field.use_focused_toggle = true
      }
    },
    updateMatrixField(newField) {
      this.field = newField
    },
    onFieldMaxCharLimitChange(val) {
      this.field.max_char_limit = val
      if(!this.field.max_char_limit) {
        this.field.show_char_limit = false
      }
    },
    onFieldMinSelectionChange(val) {
      this.field.min_selection = val ? parseInt(val) : null
    },
    onFieldMaxSelectionChange(val) {
      this.field.max_selection = val ? parseInt(val) : null
    },
    clearMinMaxSelection() {
      this.field.min_selection = null
      this.field.max_selection = null
    },
    onFieldFocusedCheckboxStyleChange(val) {
      this.field.focused_checkbox_style = val
      // Update field flags based on selection
      if (val === 'focused_toggle') {
        this.field.use_focused_toggle = true
        this.field.use_toggle_switch = false
      } else if (val === 'toggle_switch') {
        this.field.use_focused_toggle = false
        this.field.use_toggle_switch = true
      } else {
        this.field.use_focused_toggle = false
        this.field.use_toggle_switch = false
      }
    }
  }
}
</script>
