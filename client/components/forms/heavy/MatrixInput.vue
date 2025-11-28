<template>
  <input-wrapper v-bind="inputWrapperProps">
    <template #label>
      <slot name="label" />
    </template>
    <div :class="ui.container({ class: props.ui?.slots?.container })">
      <table class="w-full table-auto">
        <thead class="">
          <tr>
            <th class="ltr:text-left rtl:text-right w-auto max-w-xs" :class="ui.headerCell({ class: props.ui?.slots?.headerCell })" />
            <td
              v-for="column in columns"
              :key="column"
              :class="[
                ['minimal', 'transparent'].includes(resolvedTheme) ? '' : 'ltr:border-l rtl:border-r rtl:!border-l-0',
                'max-w-24 overflow-hidden',
                ui.cell({ class: props.ui?.slots?.cell })
              ]"
            >
              <div :class="ui.headerCell({ class: props.ui?.slots?.headerCell })">
                {{ column }}
              </div>
            </td>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(row, rowIndex) in rows"
            :key="rowIndex"
            class="border-t border-neutral-300 dark:border-neutral-600"
            role="radiogroup"
            :aria-label="`${row} options`"
          >
            <td class="ltr:text-left rtl:text-right w-auto max-w-24 overflow-hidden">
              <div :class="ui.rowCell({ class: props.ui?.slots?.rowCell })">
                {{ row }}
              </div>
            </td>
            <td
              v-for="column in columns"
              :key="row + column"
              role="radio"
              :tabindex="props.disabled ? -1 : 0"
              :aria-checked="compVal && compVal[row] === column"
              :class="[
                ['minimal', 'transparent'].includes(resolvedTheme) ? '' : 'ltr:border-l rtl:border-r rtl:!border-l-0',
                ui.cell({ class: props.ui?.slots?.cell }),
                ui.cellHover({ class: props.ui?.slots?.cellHover }),
                ui.option({ class: props.ui?.slots?.option })
              ]"
              @click="onSelect(row, column)"
              @keydown="onKeyDown($event, row, column)"
            >
              <div :class="ui.iconWrapper({ class: props.ui?.slots?.iconWrapper })">
                <RadioButtonIcon
                  v-if="compVal"
                  :key="row+column"
                  :is-checked="compVal[row] === column"
                  :color="color"
                  :theme="resolvedTheme"
                />
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <template #help>
      <slot name="help" />
    </template>
    <template #error>
      <slot name="error" />
    </template>
  </input-wrapper>
</template>
<script setup>
import { watch } from "vue"
import { inputProps, useFormInput } from "../useFormInput.js"
import RadioButtonIcon from "../core/components/RadioButtonIcon.vue"
import { matrixInputTheme } from '~/lib/forms/themes/matrix-input.theme.js'

const props = defineProps({
  ...inputProps,
  rows: { type: Array, required: true },
  columns: { type: Array, required: true },
})

const emit = defineEmits(['update:modelValue', 'focus', 'blur'])

const { compVal, inputWrapperProps, ui, resolvedTheme } = useFormInput(props, { emit }, {
  variants: matrixInputTheme
})

const onSelect = (row, column) => {
  if (props.disabled) {
    return
  }

  // Create a new object to ensure reactivity is triggered correctly.
  const newValue = { ...(compVal.value || {}) }

  if (newValue[row] === column && !props.required) {
    // Unset the value if it's already selected and not required
    newValue[row] = null
  } else {
    newValue[row] = column
  }
  
  // Assigning a new object to compVal.value will trigger the setter in useFormInput
  compVal.value = newValue
}

const onKeyDown = (event, row, column) => {
  if (event.key === 'Enter' || event.key === ' ') {
    event.preventDefault()
    onSelect(row, column)
  }
}

watch(compVal, (val) => {
  if (!val || typeof val !== 'object') {
    compVal.value = {}
  }
}, { immediate: true, deep: true })
</script>
