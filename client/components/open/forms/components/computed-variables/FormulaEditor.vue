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
        @blur="applyHighlighting"
      />
      
      <!-- Insert Buttons -->
      <div class="absolute right-2 top-2 flex gap-1">
        <UPopover :content="{ side: 'bottom', align: 'end' }">
          <UButton
            size="xs"
            color="neutral"
            variant="soft"
            icon="i-heroicons-at-symbol"
            @mousedown="saveSelection"
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
            @mousedown="saveSelection"
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
import { validateFormula, getFunctionNames } from '~/lib/formulas/index.js'

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
const hasError = ref(false)

// Save selection/cursor position before it's lost
let savedRange = null

function saveSelection() {
  const selection = window.getSelection()
  if (selection.rangeCount > 0 && editorRef.value?.contains(selection.anchorNode)) {
    savedRange = selection.getRangeAt(0).cloneRange()
  }
}

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

// Get list of known function names for syntax highlighting
const knownFunctions = computed(() => {
  try {
    const names = getFunctionNames()
    return names.length > 0 ? names : defaultFunctionNames
  } catch {
    return defaultFunctionNames
  }
})

// Default function names as fallback
const defaultFunctionNames = [
  'SUM', 'AVERAGE', 'MIN', 'MAX', 'ROUND', 'FLOOR', 'CEIL', 'ABS', 'MOD', 'POWER', 'SQRT',
  'CONCAT', 'UPPER', 'LOWER', 'TRIM', 'LEFT', 'RIGHT', 'MID', 'LEN', 'SUBSTITUTE', 'REPLACE', 'FIND', 'SEARCH', 'REPT', 'TEXT',
  'IF', 'AND', 'OR', 'NOT', 'XOR', 'ISBLANK', 'ISNUMBER', 'ISTEXT', 'IFERROR', 'IFBLANK', 'COALESCE', 'SWITCH', 'IFS', 'CHOOSE'
]

// Escape HTML entities
function escapeHtml(text) {
  const div = document.createElement('div')
  div.textContent = text
  return div.innerHTML
}

// Tokenize formula into parts for safe highlighting
function tokenizeFormula(formula, fieldMap) {
  const tokens = []
  let remaining = formula
  
  // Build function pattern with fallback
  const funcNames = knownFunctions.value
  const funcPatternStr = funcNames.length > 0 
    ? `^(${funcNames.join('|')})(?=\\s*\\()`
    : '^$' // Never match if no functions
  
  while (remaining.length > 0) {
    // Try to match field reference {fieldId}
    const fieldMatch = remaining.match(/^\{([^}]+)\}/)
    if (fieldMatch) {
      const fieldId = fieldMatch[1]
      const field = fieldMap.get(fieldId)
      if (field) {
        tokens.push({ type: 'pill', id: fieldId, name: field.name, fieldType: field.type })
      } else {
        tokens.push({ type: 'text', value: fieldMatch[0] })
      }
      remaining = remaining.slice(fieldMatch[0].length)
      continue
    }
    
    // Try to match function name (followed by parenthesis)
    if (funcNames.length > 0) {
      const funcPattern = new RegExp(funcPatternStr, 'i')
      const funcMatch = remaining.match(funcPattern)
      if (funcMatch) {
        tokens.push({ type: 'function', value: funcMatch[1] })
        remaining = remaining.slice(funcMatch[1].length)
        continue
      }
    }
    
    // Try to match string literals
    const stringMatch = remaining.match(/^("[^"]*"|'[^']*')/)
    if (stringMatch) {
      tokens.push({ type: 'string', value: stringMatch[0] })
      remaining = remaining.slice(stringMatch[0].length)
      continue
    }
    
    // Try to match numbers (including decimals)
    const numberMatch = remaining.match(/^\d+\.?\d*/)
    if (numberMatch) {
      tokens.push({ type: 'number', value: numberMatch[0] })
      remaining = remaining.slice(numberMatch[0].length)
      continue
    }
    
    // Try to match comparison operators (multi-char first)
    const compMatch = remaining.match(/^(<=|>=|<>|!=|==|<|>|=)/)
    if (compMatch) {
      tokens.push({ type: 'operator', value: compMatch[0] })
      remaining = remaining.slice(compMatch[0].length)
      continue
    }
    
    // Try to match arithmetic operators and parentheses
    const opMatch = remaining.match(/^[+\-*/(),]/)
    if (opMatch) {
      tokens.push({ type: 'operator', value: opMatch[0] })
      remaining = remaining.slice(opMatch[0].length)
      continue
    }
    
    // Take one character as plain text
    tokens.push({ type: 'text', value: remaining[0] })
    remaining = remaining.slice(1)
  }
  
  return tokens
}

// Convert storage format to display HTML with syntax highlighting
function toDisplayFormat(formula) {
  if (!formula) return ''
  
  // Build field map
  const fieldMap = new Map()
  availableFields.value.forEach(f => fieldMap.set(f.id, f))
  availableVariables.value.forEach(v => fieldMap.set(v.id, v))
  
  // Tokenize the formula to apply highlighting safely
  const tokens = tokenizeFormula(formula, fieldMap)
  
  // Build HTML from tokens
  return tokens.map(token => {
    switch (token.type) {
      case 'pill': {
        const isVariable = token.fieldType === 'computed'
        return `<span class="formula-pill ${isVariable ? 'formula-pill-variable' : ''}" data-field-id="${escapeHtml(token.id)}" contenteditable="false">${escapeHtml(token.name)}</span>`
      }
      case 'function':
        return `<span class="formula-function">${escapeHtml(token.value)}</span>`
      case 'number':
        return `<span class="formula-number">${escapeHtml(token.value)}</span>`
      case 'string':
        return `<span class="formula-string">${escapeHtml(token.value)}</span>`
      case 'operator':
        return `<span class="formula-operator">${escapeHtml(token.value)}</span>`
      default:
        return escapeHtml(token.value)
    }
  }).join('')
}

// Get current cursor position as text offset
function getCursorOffset() {
  const selection = window.getSelection()
  if (!selection.rangeCount || !editorRef.value) return 0
  
  const range = selection.getRangeAt(0)
  const preCaretRange = range.cloneRange()
  preCaretRange.selectNodeContents(editorRef.value)
  preCaretRange.setEnd(range.endContainer, range.endOffset)
  
  // Get text length before cursor
  return preCaretRange.toString().length
}

// Set cursor position by text offset
function setCursorOffset(offset) {
  if (!editorRef.value) return
  
  const selection = window.getSelection()
  const range = document.createRange()
  
  let currentOffset = 0
  let found = false
  
  function walkNodes(node) {
    if (found) return
    
    if (node.nodeType === Node.TEXT_NODE) {
      const nodeLength = node.textContent.length
      if (currentOffset + nodeLength >= offset) {
        range.setStart(node, offset - currentOffset)
        range.setEnd(node, offset - currentOffset)
        found = true
        return
      }
      currentOffset += nodeLength
    } else if (node.nodeType === Node.ELEMENT_NODE) {
      // For pill elements, count as single character in terms of cursor position
      if (node.classList?.contains('formula-pill')) {
        const pillName = node.textContent
        if (currentOffset + pillName.length >= offset) {
          // Position cursor after the pill
          range.setStartAfter(node)
          range.setEndAfter(node)
          found = true
          return
        }
        currentOffset += pillName.length
      } else {
        for (const child of node.childNodes) {
          walkNodes(child)
          if (found) return
        }
      }
    }
  }
  
  walkNodes(editorRef.value)
  
  if (!found) {
    // Position at end if offset not found
    range.selectNodeContents(editorRef.value)
    range.collapse(false)
  }
  
  selection.removeAllRanges()
  selection.addRange(range)
}

// Apply syntax highlighting while preserving cursor position
function applyHighlighting() {
  if (!editorRef.value) return
  
  const cursorOffset = getCursorOffset()
  const formula = toStorageFormat(editorRef.value.innerHTML)
  const highlighted = toDisplayFormat(formula)
  
  // Only update if content changed
  if (editorRef.value.innerHTML !== highlighted) {
    editorRef.value.innerHTML = highlighted
    setCursorOffset(cursorOffset)
  }
}

// Initialize editor content
onMounted(() => {
  if (editorRef.value && props.modelValue) {
    editorRef.value.innerHTML = toDisplayFormat(props.modelValue)
    validateAndEmit()
  }
})

// Watch for external changes
watch(() => props.modelValue, (newVal) => {
  if (editorRef.value) {
    const currentFormula = toStorageFormat(editorRef.value.innerHTML)
    if (currentFormula !== newVal) {
      editorRef.value.innerHTML = toDisplayFormat(newVal)
    }
  }
})

// Debounce timer for highlighting
let highlightTimer = null

function onInput() {
  const formula = toStorageFormat(editorRef.value.innerHTML)
  emit('update:modelValue', formula)
  validateAndEmit()
  
  // Debounced highlighting
  clearTimeout(highlightTimer)
  highlightTimer = setTimeout(() => {
    applyHighlighting()
  }, 500)
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
  
  // Create pill element
  const isVariable = field.type === 'computed'
  const pill = document.createElement('span')
  pill.className = `formula-pill ${isVariable ? 'formula-pill-variable' : ''}`
  pill.setAttribute('data-field-id', field.id)
  pill.setAttribute('contenteditable', 'false')
  pill.textContent = field.name
  
  // Restore saved selection or insert at end
  if (savedRange && editorRef.value.contains(savedRange.startContainer)) {
    editorRef.value.focus()
    const selection = window.getSelection()
    selection.removeAllRanges()
    selection.addRange(savedRange)
    
    savedRange.deleteContents()
    savedRange.insertNode(pill)
    
    // Move cursor after pill
    savedRange.setStartAfter(pill)
    savedRange.setEndAfter(pill)
    selection.removeAllRanges()
    selection.addRange(savedRange)
  } else {
    editorRef.value.focus()
    editorRef.value.appendChild(pill)
  }
  
  // Add a space after the pill
  const space = document.createTextNode(' ')
  pill.after(space)
  
  // Move cursor after the space
  const selection = window.getSelection()
  if (selection.rangeCount > 0) {
    const range = selection.getRangeAt(0)
    range.setStartAfter(space)
    range.setEndAfter(space)
    selection.removeAllRanges()
    selection.addRange(range)
  }
  
  savedRange = null
  onInput()
}

function insertFunction(func) {
  if (!editorRef.value) return
  
  // Insert function name with opening parenthesis
  const text = `${func.name}()`
  const textNode = document.createTextNode(text)
  
  // Restore saved selection or insert at end
  if (savedRange && editorRef.value.contains(savedRange.startContainer)) {
    editorRef.value.focus()
    const selection = window.getSelection()
    selection.removeAllRanges()
    selection.addRange(savedRange)
    
    savedRange.deleteContents()
    savedRange.insertNode(textNode)
    
    // Move cursor inside parentheses
    savedRange.setStart(textNode, text.length - 1)
    savedRange.setEnd(textNode, text.length - 1)
    selection.removeAllRanges()
    selection.addRange(savedRange)
  } else {
    editorRef.value.focus()
    editorRef.value.appendChild(textNode)
    
    // Move cursor inside parentheses
    const selection = window.getSelection()
    const range = document.createRange()
    range.setStart(textNode, text.length - 1)
    range.setEnd(textNode, text.length - 1)
    selection.removeAllRanges()
    selection.addRange(range)
  }
  
  savedRange = null
  onInput()
  
  // Apply highlighting immediately for functions
  setTimeout(() => applyHighlighting(), 50)
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

/* Syntax highlighting */
.formula-input :deep(.formula-function) {
  color: #0891b2;
  font-weight: 600;
}

.formula-input :deep(.formula-operator) {
  color: #dc2626;
  font-weight: 600;
}

.formula-input :deep(.formula-number) {
  color: #059669;
  font-weight: 500;
}

.formula-input :deep(.formula-string) {
  color: #d97706;
}
</style>
