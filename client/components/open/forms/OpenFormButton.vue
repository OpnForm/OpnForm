<template>
  <UButton
    color="form"
    :type="nativeType"
    :disabled="loading ? true : null"
    :loading="loading"
    :class="['btn', buttonClass]"
    :size="buttonSize"
    :icon="icon"
  >
    <slot />
  </UButton>
</template>

<script setup>
const props = defineProps({
  form: {
    type: Object,
    required: true,
  },
  nativeType: {
    type: String,
    default: "submit",
  },
  loading: {
    type: Boolean,
    default: false,
  },
  icon: {
    type: String,
    default: null,
  },
})

const getTextClass = (bgColor, lightColor = "text-white", darkColor = "text-neutral-900") => {
  if (!bgColor) {
    return darkColor
  }
  const color =
    bgColor.charAt(0) === "#" ? bgColor.substring(1, 7) : bgColor
  const r = parseInt(color.substring(0, 2), 16) // hexToR
  const g = parseInt(color.substring(2, 4), 16) // hexToG
  const b = parseInt(color.substring(4, 6), 16) // hexToB
  const uicolors = [r / 255, g / 255, b / 255]
  const c = uicolors.map((col) => {
    if (col <= 0.03928) {
      return col / 12.92
    }
    return Math.pow((col + 0.055) / 1.055, 2.4)
  })
  const L = 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2]
  return L > 0.45 ? darkColor : lightColor
}

const buttonClass = computed(() => {
  const classes = [
    getTextClass(props.form.color),
  ]
  
 
  // Add border radius classes
  if (props.form.border_radius) {
    const radiusClasses = {
      'none': 'rounded-none',
      'full': 'rounded-full'
    }
    if (radiusClasses[props.form.border_radius]) {
      classes.push(radiusClasses[props.form.border_radius])
    }
  }
  
  return classes
})

const buttonSize = computed(() => {
  return {
    'sm': 'md',
    'md': 'lg',
    'lg': 'xl'
  }[props.form.size]
})

</script>
