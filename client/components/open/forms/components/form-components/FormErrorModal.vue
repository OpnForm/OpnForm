<template>
  <UModal
    v-model:open="isOpen"
    :ui="{ content: 'sm:max-w-xl' }"
    :title="modalTitle"
  >
    <template #body>
      <div
        data-testid="form-save-error-modal"
        class="space-y-4"
        role="alert"
        aria-live="assertive"
      >
        <p
          v-if="hasStructuredErrors"
          class="text-sm text-neutral-600"
        >
          Review the items below, then try saving your form again.
        </p>

        <div
          v-for="group in fieldGroups"
          :key="group.key"
          :data-testid="`form-save-error-field-${group.fieldId || group.fieldIndex}`"
          class="rounded-lg border border-neutral-200 bg-white p-4"
        >
          <div class="flex items-start gap-3">
            <div class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-red-50 text-red-600">
              <Icon
                name="i-heroicons-exclamation-circle"
                class="size-5"
              />
            </div>

            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                  <p class="truncate font-medium text-neutral-900">
                    {{ group.fieldName }}
                  </p>
                  <p
                    v-if="fieldTypeLabel(group.fieldType)"
                    class="mt-0.5 text-xs text-neutral-500"
                  >
                    {{ fieldTypeLabel(group.fieldType) }}
                  </p>
                </div>

                <UButton
                  v-if="group.canNavigate"
                  color="primary"
                  variant="soft"
                  size="sm"
                  icon="i-heroicons-pencil-square"
                  :aria-label="`Edit ${group.fieldName}`"
                  :data-testid="`edit-form-field-${group.fieldId || group.fieldIndex}`"
                  @click="editField(group)"
                >
                  Edit field
                </UButton>
              </div>

              <ul class="mt-3 space-y-1 text-sm text-red-700">
                <li
                  v-for="message in group.messages"
                  :key="message"
                  class="flex gap-2"
                >
                  <span aria-hidden="true">•</span>
                  <span>{{ message }}</span>
                </li>
              </ul>
            </div>
          </div>
        </div>

        <div
          v-for="group in computedVariableGroups"
          :key="group.key"
          :data-testid="`form-save-error-variable-${group.variableId || group.variableIndex}`"
          class="rounded-lg border border-neutral-200 bg-white p-4"
        >
          <div class="flex items-start gap-3">
            <div class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-red-50 text-red-600">
              <Icon
                name="i-heroicons-variable"
                class="size-5"
              />
            </div>

            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                  <p class="truncate font-medium text-neutral-900">
                    {{ group.variableName }}
                  </p>
                  <p class="mt-0.5 text-xs text-neutral-500">
                    Computed variable
                  </p>
                </div>

                <UButton
                  v-if="group.canNavigate"
                  color="primary"
                  variant="soft"
                  size="sm"
                  icon="i-heroicons-pencil-square"
                  :aria-label="`Edit ${group.variableName}`"
                  :data-testid="`edit-computed-variable-${group.variableId || group.variableIndex}`"
                  @click="editComputedVariable(group)"
                >
                  Edit variable
                </UButton>
              </div>

              <ul class="mt-3 space-y-1 text-sm text-red-700">
                <li
                  v-for="message in group.messages"
                  :key="message"
                  class="flex gap-2"
                >
                  <span aria-hidden="true">•</span>
                  <span>{{ message }}</span>
                </li>
              </ul>
            </div>
          </div>
        </div>

        <div
          v-if="generalErrors.length > 0"
          data-testid="form-save-general-errors"
          class="rounded-lg border border-red-200 bg-red-50 p-4"
        >
          <div
            v-for="error in generalErrors"
            :key="error.key"
            class="not-last:mb-4"
          >
            <p class="font-medium text-red-900">
              {{ error.label }}
            </p>
            <ul class="mt-2 space-y-1 text-sm text-red-700">
              <li
                v-for="message in error.messages"
                :key="message"
                class="flex gap-2"
              >
                <span aria-hidden="true">•</span>
                <span>{{ message }}</span>
              </li>
            </ul>
          </div>
        </div>

        <div
          v-if="!hasStructuredErrors && fallbackMessage"
          class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"
        >
          {{ fallbackMessage }}
        </div>
      </div>
    </template>

    <template #footer>
      <div class="flex w-full justify-end">
        <UButton
          color="neutral"
          variant="outline"
          @click="closeModal"
        >
          Close
        </UButton>
      </div>
    </template>
  </UModal>
</template>

<script setup>
import blocksTypes from '~/data/blocks_types.json'

const props = defineProps({
  show: { type: Boolean, required: true },
  errorSummary: {
    type: Object,
    default: () => ({
      fieldGroups: [],
      computedVariableGroups: [],
      generalErrors: [],
      issueCount: 0,
      fieldCount: 0,
      computedVariableCount: 0,
      fallbackMessage: null,
    }),
  },
})

const emit = defineEmits(['close', 'edit-field', 'edit-computed-variable'])

const fieldGroups = computed(() => props.errorSummary?.fieldGroups || [])
const computedVariableGroups = computed(() => props.errorSummary?.computedVariableGroups || [])
const generalErrors = computed(() => props.errorSummary?.generalErrors || [])
const fallbackMessage = computed(() => props.errorSummary?.fallbackMessage || null)
const hasStructuredErrors = computed(() => {
  return fieldGroups.value.length > 0
    || computedVariableGroups.value.length > 0
    || generalErrors.value.length > 0
})

const modalTitle = computed(() => {
  const fieldCount = fieldGroups.value.length
  const variableCount = computedVariableGroups.value.length
  const issueCount = fieldCount + variableCount + generalErrors.value.length

  if (issueCount === 0) {
    return "We couldn't save your form"
  }

  if (variableCount === 0 && generalErrors.value.length === 0) {
    return `Fix ${fieldCount} ${fieldCount === 1 ? 'field' : 'fields'} before saving`
  }

  if (fieldCount === 0 && generalErrors.value.length === 0) {
    return `Fix ${variableCount} ${variableCount === 1 ? 'variable' : 'variables'} before saving`
  }

  return `Fix ${issueCount} ${issueCount === 1 ? 'issue' : 'issues'} before saving`
})

const isOpen = computed({
  get: () => props.show,
  set: (value) => {
    if (!value) {
      emit('close')
    }
  },
})

function fieldTypeLabel(fieldType) {
  return blocksTypes[fieldType]?.title || fieldType || null
}

function editField(group) {
  emit('edit-field', group)
}

function editComputedVariable(group) {
  emit('edit-computed-variable', group)
}

function closeModal() {
  isOpen.value = false
}
</script>
