<template>
  <UPopover
    v-if="!field.multi_lines"
    arrow
    :content="{ side: 'left', align: 'center' }"
  >
    <UButton
      class="mt-4"
      block
      color="neutral"
      variant="outline"
      :trailing-icon="field.input_mask ? 'i-heroicons-check-circle' : ''"
      label="Input Mask Pattern"
    />
    <template #content>
      <div class="p-4 w-72">
        <!-- Header with help icon -->
        <div class="flex items-center justify-between mb-3">
          <h4 class="font-medium text-sm">
            Input Mask Settings
          </h4>
          <UButton
            icon="i-heroicons-question-mark-circle"
            size="xs"
            color="neutral"
            variant="ghost"
            @click="crisp.openHelpdeskArticle('how-to-set-mask-pattern-197qqps')"
          />
        </div>

        <TextInput
          name="input_mask"
          :form="field"
          label="Pattern"
          placeholder="(999) 999-9999"
          @update:model-value="onInputMaskChange"
        >
          <template #help>
            <InputHelp>
              <div class="space-y-1">
                <div><code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">9</code> = digit (0-9)</div>
                <div><code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">a</code> = letter (a-z)</div>
                <div><code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">*</code> = letter or digit</div>
                <div><code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">?</code> = makes previous optional</div>
              </div>
            </InputHelp>
          </template>
        </TextInput>

        <!-- Placeholder character -->
        <OptionSelectorInput
          v-if="field.input_mask"
          v-model="field.slot_char"
          name="slot_char"
          class="mt-3"
          label="Placeholder"
          help="Shows in empty positions"
          :options="placeholderOptions"
          :columns="5"
          seamless
        />

        <!-- Common patterns -->
        <div class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-700">
          <div class="text-xs text-gray-500 dark:text-gray-400 mb-2">
            Quick templates:
          </div>
          <div class="flex flex-wrap gap-1">
            <UButton
              v-for="template in commonPatterns"
              :key="template.pattern"
              size="xs"
              color="neutral"
              variant="soft"
              :label="template.label"
              @click="applyTemplate(template.pattern)"
            />
          </div>
        </div>

        <!-- Clear button -->
        <UButton
          v-if="field.input_mask"
          class="mt-3"
          block
          size="sm"
          color="neutral"
          variant="ghost"
          icon="i-heroicons-x-mark"
          label="Remove mask"
          @click="clearMask"
        />
      </div>
    </template>
  </UPopover>
</template>

<script setup>
const props = defineProps({
  field: {
    type: Object,
    required: true
  }
})

const crisp = useCrisp()

// Allowed characters regex (matching backend validation)
const ALLOWED_MASK_CHARS = /^[9a*().\s\-?]*$/

const placeholderOptions = [
  { name: '_', label: '_' },
  { name: '#', label: '#' },
  { name: '•', label: '•' },
  { name: '○', label: '○' },
  { name: '▢', label: '▢' },
]

const commonPatterns = [
  { label: 'Phone', pattern: '(999) 999-9999' },
  { label: 'Date', pattern: '99-99-9999' },
  { label: 'ZIP+4', pattern: '99999-9999' },
  { label: 'Credit Card', pattern: '9999 9999 9999 9999' },
]

const onInputMaskChange = (val) => {
  if (typeof val !== 'string') return

  // Sanitize: only allow valid mask characters
  if (val && !ALLOWED_MASK_CHARS.test(val)) {
    const cleanedValue = val.replace(/[^9a*().\s\-?]/g, '')
    props.field.input_mask = cleanedValue
  }

  // Set default placeholder char when mask is first set
  if (val && !props.field.slot_char) {
    props.field.slot_char = '_'
  }
}

const applyTemplate = (pattern) => {
  props.field.input_mask = pattern
  if (!props.field.slot_char) {
    props.field.slot_char = '_'
  }
}

const clearMask = () => {
  props.field.input_mask = null
  props.field.slot_char = null
}
</script>
