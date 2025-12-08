<template>
  <input-wrapper v-bind="inputWrapperProps">
    <template #label>
      <slot name="label" />
    </template>

    <textarea
      :id="id ? id : name"
      v-model="compVal"
      :disabled="disabled ? true : null"
      :class="ui.input({ class: props.ui?.slots?.input })"
      :name="name"
      :style="inputStyle"
      :placeholder="placeholder"
      :maxlength="maxCharLimit"
      @keydown.enter="onEnterPress"
    />

    <template
      v-if="$slots.help"
      #help
    >
      <slot name="help" />
    </template>

    <template
      v-if="maxCharLimit && showCharLimit"
      #bottom_after_help
    >
      <small :class="ui.help({ class: props.ui?.slots?.help })">
        {{ charCount }}/{{ maxCharLimit }}
      </small>
    </template>

    <template
      v-if="$slots.error"
      #error
    >
      <slot name="error" />
    </template>
  </input-wrapper>
</template>

<script>
import {inputProps, useFormInput} from "../useFormInput.js"
import { textAreaInputTheme } from "~/lib/forms/themes/text-area-input.theme.js"

export default {
  name: "TextAreaInput",
  components: {},
  mixins: [],

  props: {
    ...inputProps,
    maxCharLimit: {type: Number, required: false, default: null},
    preventEnter: {type: Boolean, default: false},
  },

  setup(props, context) {
    const formInput = useFormInput(props, context, {
      variants: textAreaInputTheme
    })

    const onEnterPress = (event) => {
      // Command+Enter (Mac) or Ctrl+Enter (Windows/Linux) emits input-filled event
      if (event.metaKey || event.ctrlKey) {
        event.preventDefault()
        context.emit('input-filled')
        return false
      }
      
      // Regular Enter behavior
      if (props.preventEnter) {
        event.preventDefault()
        return false
      }
    }

    return {
      ...formInput,
      onEnterPress,
      props
    }
  },

  computed: {
    charCount() {
      return this.compVal ? this.compVal.length : 0
    },
  },
}
</script>
