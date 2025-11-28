<template>
  <InputWrapper v-bind="inputWrapperProps">
    <template #label>
      <slot name="label" />
    </template>

    <div class="relative">
      <div
        ref="editableDiv"
        :contenteditable="!disabled"
        class="mention-input"
        :style="inputStyle"
        :class="ui.input({ class: props.ui?.slots?.input })"
        :placeholder="placeholder"
        @input="onInput"
      />
      <MentionDropdown
        v-if="!disabled"
        :mention-state="mentionState"
        :mentions="mentions"
        :disabled="disabled"
      >
        <UButton
          type="button"
          color="neutral"
          variant="outline"
          class="absolute right-1 top-1/2 transform -translate-y-1/2 p-1 px-2"
          icon="i-heroicons-at-symbol-16-solid"
          @click="openMentionDropdown"
        />
      </MentionDropdown>
      <UButton
        v-else
        :disabled="disabled"
        type="button"
        color="neutral"
        variant="soft"
        class="absolute right-1 top-1/2 transform -translate-y-1/2 p-1 px-2"
        icon="i-heroicons-at-symbol-16-solid"
        @click="openMentionDropdown"
      />
    </div>

    <template
      v-if="$slots.help"
      #help
    >
      <slot name="help" />
    </template>

    <template
      v-if="$slots.error"
      #error
    >
      <slot name="error" />
    </template>
  </InputWrapper>
</template>

<script setup>
import { ref, onMounted, watch, reactive } from 'vue'
import { inputProps, useFormInput } from '../useFormInput.js'
import MentionDropdown from './components/MentionDropdown.vue'
import { mentionInputTheme } from '~/lib/forms/themes/mention-input.theme.js'

const props = defineProps({
  ...inputProps,
  mentions: { type: Array, default: () => [] },
  disableMention: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const { compVal, inputStyle, inputWrapperProps, ui } = useFormInput(props, { emit }, {
  variants: mentionInputTheme
})
const editableDiv = ref(null)
const savedRange = ref(null)
const { openSubscriptionModal } = useAppModals()

// Create a reactive state object for the mention dropdown
const mentionState = reactive({
  open: false,
  onInsert: (mention) => {
    insertMention(mention)
    mentionState.open = false
  },
  onCancel: () => {
    mentionState.open = false
    restoreSelection()
  },
})

const createMentionSpan = (mention) => {
  const mentionSpan = document.createElement('span')
  mentionSpan.setAttribute('mention', 'true')
  mentionSpan.setAttribute('mention-field-id', mention.field.id)
  mentionSpan.setAttribute('mention-field-name', mention.field.name)
  mentionSpan.setAttribute('mention-fallback', mention.fallback || '')
  mentionSpan.setAttribute('contenteditable', 'false')
  mentionSpan.setAttribute('class', 'mention-item')
  mentionSpan.textContent = mention.field.name.length > 25 ? `${mention.field.name.slice(0, 25)}...` : mention.field.name
  return mentionSpan
}

const insertMention = (mention) => {
  const mentionSpan = createMentionSpan(mention)

  restoreSelection()

  const range = window.getSelection().getRangeAt(0)

  // Insert the mention span
  range.insertNode(mentionSpan)
  
  // Move the cursor after the inserted mention
  range.setStartAfter(mentionSpan)
  range.collapse(true)

  // Apply the new selection
  const selection = window.getSelection()
  selection.removeAllRanges()
  selection.addRange(range)

  // Ensure the editableDiv is focused
  editableDiv.value.focus()

  updateCompVal()
}

const openMentionDropdown = () => {
  if (props.disableMention || props.disabled) {
    openSubscriptionModal({ modal_title: 'Upgrade to Pro', modal_description: 'Upgrade to Pro to use mentions' })
    return
  }

  saveSelection()
  if (!savedRange.value) {
    // If no previous selection, move cursor to the end
    const range = document.createRange()
    range.selectNodeContents(editableDiv.value)
    range.collapse(false)
    const selection = window.getSelection()
    selection.removeAllRanges()
    selection.addRange(range)
    savedRange.value = range
  }
  mentionState.open = true
}

const saveSelection = () => {
  const selection = window.getSelection()
  if (selection.rangeCount > 0) {
    savedRange.value = selection.getRangeAt(0)
  }
}

const restoreSelection = () => {
  if (savedRange.value) {
    const selection = window.getSelection()
    selection.removeAllRanges()
    selection.addRange(savedRange.value)
    editableDiv.value.focus()
  }
}

const getPlainText = (html) => {
  if (!html) return ''
  // Create a temporary div to parse HTML
  const temp = document.createElement('div')
  temp.innerHTML = html
  // Get text content and trim
  return temp.textContent.trim()
}

const updateCompVal = () => {
  const html = editableDiv.value.innerHTML
  const plainText = getPlainText(html)
  
  // If plain text is empty, store empty string; otherwise store HTML
  compVal.value = plainText ? html : ''
}

const onInput = () => {
  updateCompVal()
}

onMounted(() => {
  if (compVal.value) {
    editableDiv.value.innerHTML = compVal.value
  }
})

watch(compVal, (newVal) => {
  if (editableDiv.value && editableDiv.value.innerHTML !== newVal) {
    editableDiv.value.innerHTML = newVal
  }
})

defineExpose({
  editableDiv,
  compVal,
  mentionState,
  openMentionDropdown,
  onInput,
})
</script>

<style scoped>
.mention-input {
  min-height: 1.5rem;
  white-space: pre-wrap;
  word-break: break-word;
}

.mention-input:empty::before {
  content: attr(placeholder);
  color: #9ca3af;
}
</style>
