<template>
  <input-wrapper v-bind="inputWrapperProps">
    <template #label>
      <span />
    </template>

    <div class="flex items-center">
      <input
        :id="id ? id : name"
        v-model="compVal"
        :disabled="disabled ? true : null"
        type="color"
        class="mr-2"
        :name="name"
        @keydown.enter="onEnterPress"
      >
      <slot name="label">
        <InputLabel
          :label="label"
          :required="required"
          :theme="theme"
        />
      </slot>
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
import { inputProps, useFormInput } from "../useFormInput.js"
import { colorInputTheme } from "~/lib/forms/themes/color-input.theme.js"

const props = defineProps({
  ...inputProps,
  preventEnter: { type: Boolean, default: true }
})

const emit = defineEmits(['update:modelValue', 'focus', 'blur'])

const { compVal, inputWrapperProps } = useFormInput(props, { emit }, {
  variants: colorInputTheme
})

const onEnterPress = (event) => {
  if (props.preventEnter) {
    event.preventDefault()
    return false
  }
}
</script>
