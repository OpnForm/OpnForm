<template>
  <InputWrapper v-bind="inputWrapperProps">
    <template #label>
      <span />
    </template>

    <div class="flex space-x-2 items-center">
      <VCheckbox
        :id="id ? id : name"
        v-model="compVal"
        :value="value"
        :disabled="disabled ? true : null"
        :name="name"
        :color="color"
      />
      <div>
        <slot
          v-if="helpPosition === 'above_input'"
          name="help"
        >
          <InputHelp
            :help="help"
            :help-classes="ui.help({ class: props.ui?.slots?.help })"
          >
            <template #after-help>
              <slot name="bottom_after_help" />
            </template>
          </InputHelp>
        </slot>
        <slot name="label">
          <InputLabel
            :label="label"
            :required="required"
            :theme="theme"
          />
        </slot>
        <slot
          v-if="helpPosition === 'below_input'"
          name="help"
        >
          <InputHelp
            :help="help"
            :help-classes="ui.help({ class: props.ui?.slots?.help })"
          >
            <template #after-help>
              <slot name="bottom_after_help" />
            </template>
          </InputHelp>
        </slot>
      </div>
    </div>

    <template #help>
      <span class="hidden" />
    </template>

    <template #error>
      <slot name="error" />
    </template>
  </InputWrapper>
</template>

<script>
import { inputProps, useFormInput } from '../useFormInput.js'
import VCheckbox from './components/VCheckbox.vue'
import { checkboxInputTheme } from "~/lib/forms/themes/checkbox-input.theme.js"

export default {
  name: 'CheckboxInput',

  components: {VCheckbox },
  props: {
    ...inputProps,
    value: { type: [Boolean, String, Number, Object], required: false },
  },

  setup(props, context) {
    const formInput = useFormInput(props, context, {
      variants: checkboxInputTheme
    })
    return {
      ...formInput,
      props
    }
  },

  mounted() {
    if (!this.compVal)
      this.compVal = false
  },
}
</script>
