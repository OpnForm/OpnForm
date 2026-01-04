<template>
  <div class="formula-editor">
    <!-- Editor Container -->
    <div
      class="relative border rounded-lg overflow-hidden"
      :class="hasError ? 'border-red-300' : 'border-gray-300'"
    >
      <!-- Editable Area -->
      <div
        ref="editorRef"
        class="formula-input min-h-[80px] p-3 pr-20 text-sm font-mono focus:outline-none"
        contenteditable="true"
        @input="onInput"
        @keydown="onKeydown"
        @paste="onPaste"
      />
      
      <!-- Insert Buttons -->
      <div class="absolute right-2 top-2 flex gap-1">
        <UPopover :content="{ side: 'bottom', align: 'end' }">
          <UButton
            size="xs"
            color="neutral"
            variant="soft"
            icon="i-heroicons-at-symbol"
            @click="showFieldPicker = !showFieldPicker"
          >
            Field
          </UButton>
          <template #content>
            <FormulaFieldPicker
              :fields="availableFields"
              :variables="availableVariables"
              @select="insertField"
            />
          </template>
        </UPopover>
        
        <UPopover :content="{ side: 'bottom', align: 'end' }">
          <UButton
            size="xs"
            color="neutral"
            variant="soft"
          >
            <span class="font-mono">fx</span>
          </UButton>
          <template #content>
            <FormulaFunctionPicker
              @select="insertFunction"
            />
          </template>
        </UPopover>
      </div>
    </div>
    
    <!-- Helper Text -->
    <p class="mt-1 text-xs text-gray-500">
      Click "Field" to insert form fields • Click "fx" to insert functions
    </p>
  </div>
</template>

<script setup>
import FormulaFieldPicker from './FormulaFieldPicker.vue'
import FormulaFunctionPicker from './FormulaFunctionPicker.vue'
import { validateFormula, formulaToDisplay } from '~/lib/formulas/index.js'

const props = defineProps({
  modelValue: {
    type: String,
    default: ''
  },
  form: {
    type: Object,
    required: true
  },
  currentVariableId: {
    type: String,
    default: null
  },
  otherVariables: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['update:modelValue', 'validation'])

const editorRef = ref(null)
const showFieldPicker = ref(false)
const hasError = ref(false)

// Available fields from form
const availableFields = computed(() => {
  return (props.form?.properties || [])
    .filter(p => p.type && !p.type.startsWith('nf-'))
    .map(p => ({
      id: p.id,
      name: p.name,
      type: p.type
    }))
})

// Available computed variables (excluding current)
const availableVariables = computed(() => {
  return props.otherVariables.map(v => ({
    id: v.id,
    name: v.name,
    type: 'computed'
  }))
})

// Convert formula to storage format (IDs)
function toStorageFormat(html) {
  if (!html) return ''
  
  // Create a temporary div to parse HTML
  const temp = document.createElement('div')
  temp.innerHTML = html
  
  // Replace pill elements with {id} format
  const pills = temp.querySelectorAll('.formula-pill')
  pills.forEach(pill => {
    const fieldId = pill.getAttribute('data-field-id')
    if (fieldId) {
      pill.replaceWith(`{${fieldId}}`)
    }
  })
  
  // Get text content
  return temp.textContent.trim()
}

// Convert storage format to display HTML
function toDisplayFormat(formula) {
  if (!formula) return ''
  
  let html = formula
  
  // Replace {fieldId} with pill elements
  const fieldMap = new Map()
  availableFields.value.forEach(f => fieldMap.set(f.id, f))
  availableVariables.value.forEach(v => fieldMap.set(v.id, v))
  
  html = html.replace(/\{([^}]+)\}/g, (match, fieldId) => {
    const field = fieldMap.get(fieldId)
    if (field) {
      const isVariable = field.type === 'computed'
      return `<span class="formula-pill ${isVariable ? 'formula-pill-variable' : ''}" data-field-id="${fieldId}" contenteditable="false">${field.name}</span>`
    }
    return match
  })
  
  return html
}

// Initialize editor content
onMounted(() => {
  if (editorRef.value && props.modelValue) {
    editorRef.value.innerHTML = toDisplayFormat(props.modelValue)
    validateAndEmit()
  }
})

// Watch for external changes
watch(() => props.modelValue, (newVal, oldVal) => {
  if (editorRef.value) {
    const currentFormula = toStorageFormat(editorRef.value.innerHTML)
    if (currentFormula !== newVal) {
      editorRef.value.innerHTML = toDisplayFormat(newVal)
    }
  }
})

function onInput() {
  const formula = toStorageFormat(editorRef.value.innerHTML)
  emit('update:modelValue', formula)
  validateAndEmit()
}

function onKeydown(e) {
  // Prevent enter key from creating new lines
  if (e.key === 'Enter') {
    e.preventDefault()
  }
}

function onPaste(e) {
  e.preventDefault()
  const text = e.clipboardData.getData('text/plain')
  document.execCommand('insertText', false, text)
}

function validateAndEmit() {
  const formula = toStorageFormat(editorRef.value?.innerHTML || '')
  
  if (!formula) {
    hasError.value = false
    emit('validation', { valid: true, errors: [] })
    return
  }
  
  const result = validateFormula(formula, {
    availableFields: availableFields.value,
    availableVariables: availableVariables.value,
    currentVariableId: props.currentVariableId
  })
  
  hasError.value = !result.valid
  emit('validation', result)
}

function insertField(field) {
  if (!editorRef.value) return
  
  // Focus the editor
  editorRef.value.focus()
  
  // Create pill element
  const isVariable = field.type === 'computed'
  const pill = document.createElement('span')
  pill.className = `formula-pill ${isVariable ? 'formula-pill-variable' : ''}`
  pill.setAttribute('data-field-id', field.id)
  pill.setAttribute('contenteditable', 'false')
  pill.textContent = field.name
  
  // Insert at cursor or append
  const selection = window.getSelection()
  if (selection.rangeCount > 0) {
    const range = selection.getRangeAt(0)
    range.deleteContents()
    range.insertNode(pill)
    
    // Move cursor after pill
    range.setStartAfter(pill)
    range.setEndAfter(pill)
    selection.removeAllRanges()
    selection.addRange(range)
  } else {
    editorRef.value.appendChild(pill)
  }
  
  // Add a space after the pill
  const space = document.createTextNode(' ')
  pill.after(space)
  
  onInput()
}

function insertFunction(func) {
  if (!editorRef.value) return
  
  editorRef.value.focus()
  
  // Insert function name with opening parenthesis
  const text = `${func.name}()`
  
  const selection = window.getSelection()
  if (selection.rangeCount > 0) {
    const range = selection.getRangeAt(0)
    range.deleteContents()
    const textNode = document.createTextNode(text)
    range.insertNode(textNode)
    
    // Move cursor inside parentheses
    range.setStart(textNode, text.length - 1)
    range.setEnd(textNode, text.length - 1)
    selection.removeAllRanges()
    selection.addRange(range)
  } else {
    editorRef.value.appendChild(document.createTextNode(text))
  }
  
  onInput()
}

// Expose for parent component
defineExpose({
  validate: validateAndEmit
})
</script>

<style scoped>
.formula-input {
  white-space: pre-wrap;
  word-break: break-word;
  line-height: 1.6;
}

.formula-input:empty::before {
  content: 'Enter formula...';
  color: #9ca3af;
}

.formula-input :deep(.formula-pill) {
  display: inline-flex;
  align-items: center;
  padding: 2px 8px;
  margin: 0 2px;
  background-color: #dbeafe;
  color: #1d4ed8;
  border-radius: 4px;
  font-size: 0.875rem;
  font-family: inherit;
  font-weight: 500;
  cursor: default;
  user-select: none;
}

.formula-input :deep(.formula-pill-variable) {
  background-color: #f3e8ff;
  color: #7c3aed;
}

.formula-input :deep(.formula-pill)::before {
  content: '';
  display: none;
}
</style>
