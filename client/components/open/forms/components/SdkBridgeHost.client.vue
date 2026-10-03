<template>
  <span class="hidden" aria-hidden="true" />
</template>

<script setup>
import { useSdkBridge } from '~/lib/sdk/useSdkBridge'

const props = defineProps({
  form: { type: Object, required: true },
  formManager: { type: Object, required: true },
  darkMode: { type: Boolean, default: false },
})

const emit = defineEmits(['ready'])

const bridge = useSdkBridge({
  formConfig: toRef(props, 'form'),
  formData: computed(() => props.formManager.form.data()),
  formErrors: computed(() => props.formManager.form.errors?.all?.() || {}),
  formManager: props.formManager,
  darkMode: toRef(props, 'darkMode'),
  attribution: props.formManager.attribution,
})

emit('ready', bridge)

defineExpose(bridge)
</script>
