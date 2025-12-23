<template>
  <div class="p-4">
    <FormSummary :form="form" />
  </div>
</template>

<script setup>
import FormSummary from "~/components/open/forms/components/FormSummary.vue"

const props = defineProps({
  form: { type: Object, required: true },
})

definePageMeta({
  middleware: "auth",
})

useOpnSeoMeta({
  title: props.form ? "Form Summary - " + props.form.title : "Form Summary",
})
</script>

