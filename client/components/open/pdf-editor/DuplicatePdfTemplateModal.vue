<template>
  <UModal v-model:open="open" :dismissible="!isPending" :close="!isPending" title="Duplicate PDF template" description="Create an independent copy in this form or another form in the same workspace.">
    <template #body>
      <form id="duplicate-pdf-template" class="space-y-4" @submit.prevent="submit">
        <TextInput v-model="name" name="copy_name" label="Template name" :required="true" :max-char-limit="255" :disabled="isPending" />
        <SelectInput
          v-model="targetId"
          name="target_form"
          label="Destination form"
          :options="formOptions"
          :searchable="true"
          :loading="formsLoading || fetchingMoreForms"
          :clearable="false"
          :required="true"
          :disabled="isPending"
        />
        <UAlert v-if="formsError" color="error" title="Could not load destination forms." />
        <template v-if="isOtherForm">
          <p v-if="sourceFields.length" class="text-sm text-neutral-600">
            Choose which destination field each PDF field should use. Text, images and page layouts are copied as they are.
          </p>
          <p v-if="targetLoading" class="text-sm text-neutral-500">Loading destination fields…</p>
          <UAlert v-else-if="targetError" color="error" title="Could not load destination fields." />
          <template v-else-if="destinationForm && sourceFields.length">
            <SelectInput
              v-for="field in sourceFields"
              :key="field.id"
              v-model="fieldMappings[field.id]"
              :name="`mapping_${field.id}`"
              :label="field.name"
              :options="fieldOptions"
              placeholder="Select a field or skip this field"
              :searchable="true"
              :clearable="false"
              :disabled="isPending"
            />
            <UAlert
              v-if="skippedCount"
              color="warning"
              :title="`${skippedCount} field${skippedCount === 1 ? '' : 's'} will be left out of the copy.`"
              description="The text labels next to these fields will still be copied."
            />
          </template>
        </template>
        <UAlert v-if="submitError" color="error" :title="submitError" />
      </form>
    </template>
    <template #footer>
      <div class="flex justify-end gap-2 w-full">
        <UButton color="neutral" variant="outline" :disabled="isPending" @click="open = false">Cancel</UButton>
        <UButton type="submit" form="duplicate-pdf-template" :loading="isPending" :disabled="!canSubmit">Duplicate template</UButton>
      </div>
    </template>
  </UModal>
</template>

<script setup>
import { useFormsList } from '~/composables/query/forms/useFormsList'
import { usePdfTemplates } from '~/composables/query/forms/usePdfTemplates'
import { getPdfDestinationFields, getPdfTemplateFields, suggestPdfFieldMappings } from '~/lib/pdf-template-duplication'

const props = defineProps({
  form: { type: Object, required: true },
  template: { type: Object, required: true },
})
const open = defineModel('open', { type: Boolean, default: false })
const emit = defineEmits(['duplicated'])
const SKIP_FIELD = '__skip__'
const name = ref(`Copy of ${props.template.name}`.slice(0, 255))
const targetId = ref(props.form.id)
const fieldMappings = ref({})
const submitError = ref('')
const isOtherForm = computed(() => targetId.value !== props.form.id)
const workspaceId = computed(() => props.form.workspace_id)
const { forms, isLoading: formsLoading, isFetchingNextPage: fetchingMoreForms, error: formsError } = useFormsList(workspaceId, {
  fetchAll: true,
  enabled: open,
})
const formOptions = computed(() => [
  { name: `${props.form.title} (this form)`, value: props.form.id },
  ...forms.value.filter(form => form.id !== props.form.id).map(form => ({ name: form.title, value: form.id })),
])
const { detailById } = useForms()
const { data: destinationForm, isLoading: targetLoading, error: targetError } = detailById(targetId, {
  usePrivate: true,
  enabled: computed(() => open.value && isOtherForm.value),
})
const sourceFields = computed(() => getPdfTemplateFields(props.template, props.form))
const destinationFields = computed(() => getPdfDestinationFields(destinationForm.value))
const fieldOptions = computed(() => [
  { name: 'Skip this field', value: SKIP_FIELD },
  ...destinationFields.value.map(field => ({
    name: field.type === 'computed' ? `${field.name} (Variable)` : field.name,
    value: field.id,
  })),
])

watch([targetId, () => destinationForm.value?.id], () => {
  fieldMappings.value = suggestPdfFieldMappings(sourceFields.value, destinationFields.value)
  submitError.value = ''
})

const skippedCount = computed(() => Object.values(fieldMappings.value).filter(value => value === SKIP_FIELD).length)
const canSubmit = computed(() => name.value.trim() && targetId.value && !isPending.value
  && (!isOtherForm.value || (!targetLoading.value && !targetError.value && destinationForm.value?.id === targetId.value
    && sourceFields.value.every(field => !!fieldMappings.value[field.id]))))
const { duplicate } = usePdfTemplates()
const { mutateAsync, isPending } = duplicate(() => props.form.id, () => props.template.id)

function submit() {
  if (!canSubmit.value) return
  submitError.value = ''
  const destination = isOtherForm.value ? destinationForm.value : props.form
  const mappings = Object.fromEntries(Object.entries(fieldMappings.value)
    .map(([id, value]) => [id, value === SKIP_FIELD ? null : value]))
  mutateAsync({ target_form_id: targetId.value, name: name.value.trim(), field_mappings: mappings })
    .then(response => {
      open.value = false
      emit('duplicated', { template: response.data, form: destination })
    })
    .catch(error => {
      submitError.value = error?.data?.message || error?.message || 'Failed to duplicate PDF template.'
    })
}
</script>
