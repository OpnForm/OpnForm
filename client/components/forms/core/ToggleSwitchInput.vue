<template>
  <input-wrapper v-bind="inputWrapperProps">
    <template #label>
      <span />
    </template>

    <div class="flex space-x-2 items-center">
      <USwitch
        :id="id ? id : name"
        v-model="compVal"
        :disabled="disabled ? true : null"
        :style="colorStyle"
        :ui="{
          base: 'data-[state=checked]:bg-[var(--form-color,#3B82F6)] focus-visible:outline-[var(--form-color,#3B82F6)]'
        }"
        @keydown="handleKeydown"
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
            :native-for="id ? id : name"
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
  </input-wrapper>
</template>

<script>
import {inputProps, useFormInput} from "../useFormInput.js"
import InputHelp from "~/components/forms/core/components/InputHelp.vue"
import { toggleSwitchInputTheme } from "~/lib/forms/themes/toggle-switch-input.theme.js"

export default {
  name: "ToggleSwitchInput",

  components: {InputHelp},
  props: {
    ...inputProps,
  },

  setup(props, context) {
    const formInput = useFormInput(props, context, {
      variants: toggleSwitchInputTheme
    })

    const colorStyle = computed(() => ({
      '--form-color': props.color
    }))

    const handleKeydown = (event) => {
      if (props.disabled) return

      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault()
        formInput.compVal.value = !formInput.compVal.value
      }
    }

    return {
      ...formInput,
      colorStyle,
      handleKeydown,
      props
    }
  },

  mounted() {
    this.compVal = !!this.compVal
  },
}
</script>
