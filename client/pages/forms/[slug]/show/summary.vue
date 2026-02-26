<template>
  <PageContainer>
    <FormSummary :form="form" />
  </PageContainer>
</template>

<script setup>
import PageContainer from "~/components/dashboard/PageContainer.vue"
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

