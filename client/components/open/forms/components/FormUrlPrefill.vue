<template>
  <CopyContent
    :content="preFillUrl"
    label="Copy URL"
    tracking-event="url_form_prefill_copy"
    :tracking-properties="{
      form_id: form.id,
      form_slug: form.slug,
    }"
  />
</template>

<script setup>
import { defineProps, computed } from "vue"
import { default as _has } from "lodash/has"
import CopyContent from "~/components/open/forms/components/CopyContent.vue"

const props = defineProps({
  form: {
    type: Object,
    required: true,
  },
  formData: {
    type: Object,
    required: true,
  },
  extraQueryParam: {
    type: String,
    default: "",
  },
})

const preFillUrl = computed(() => {
  const url = props.form.share_url
  const uriComponents = new URLSearchParams()
  props.form.properties
    .filter((property) => {
      return (
        _has(props.formData, property.id) &&
        props.formData[property.id] !== null
      )
    })
    .forEach((property) => {
      if (Array.isArray(props.formData[property.id])) {
        props.formData[property.id].forEach((value) => {
          uriComponents.append(property.id + "[]", value)
        })
      } else if (typeof props.formData[property.id] === 'object') {
        uriComponents.append(property.id, JSON.stringify(props.formData[property.id]))
      } else {
        uriComponents.append(property.id, props.formData[property.id])
      }
    })

  if (uriComponents.toString() !== "") {
    return props.extraQueryParam
      ? url + "?" + uriComponents + "&" + props.extraQueryParam
      : url + "?" + uriComponents
  } else {
    return props.extraQueryParam ? url + "?" + props.extraQueryParam : url
  }
})
</script>
