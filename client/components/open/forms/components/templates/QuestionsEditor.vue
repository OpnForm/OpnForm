<template>
  <div :class="inputWrapperProps.wrapperClass">
    <InputLabel
      :label="inputWrapperProps.label"
      :required="inputWrapperProps.required"
      :theme="inputWrapperProps.theme"
    />

    <Loader
      v-if="loading"
      key="loader"
              class="h-6 w-6 text-blue-500 mx-auto"
    />
    <div
      v-else
      class="my-3"
    >
      <div
        v-for="(questionForm, quesKey) in allQuestions"
        :key="quesKey"
        class="bg-neutral-100 p-2 mb-4"
      >
        <UButton
          color="error"
          variant="outline"
          size="sm"
          class="mb-2"
          @click.prevent="onRemove(quesKey)"
          icon="i-heroicons-trash"
          label="Remove"
        />
        <text-input
          name="question"
          :form="questionForm"
          placeholder="Question title"
        />
        <rich-text-area-input
          name="answer"
          :allow-fullscreen="true"
          :form="questionForm"
          class="mt-4"
          placeholder="Question response"
        />
      </div>
      <UButton
        v-if="addNew"
        color="success"
        size="sm"
        class="mt-2 flex"
        @click.prevent="onAdd"
        icon="i-heroicons-plus"
        label="Add New"
      />
    </div>

    <small
      v-if="inputWrapperProps.help"
      class="text-neutral-500"
    >
      <slot name="help">{{ inputWrapperProps.help }}</slot>
    </small>
    <has-error
      v-if="hasValidation"
      :form="inputWrapperProps.form"
      :field-id="inputWrapperProps.name"
    />
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue"
import { useFormInput, inputProps } from "~/components/forms/useFormInput.js"
import Loader from "~/components/global/Loader.vue"

const props = defineProps({
  ...inputProps,
  loading: { type: Boolean, default: false },
  addNew: { type: Boolean, default: true },
  questions: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue'])

// Use form input composable
const { hasValidation, inputWrapperProps } = useFormInput(props, { emit })

// Component-specific state
const allQuestions = ref(null)
const newQuestion = ref({
  question: "",
  answer: "",
})

// Methods
const onAdd = () => {
  allQuestions.value.push({ ...newQuestion.value })
}

const onRemove = (key) => {
  allQuestions.value.splice(key, 1)
}

// Initialize on mount
onMounted(() => {
  allQuestions.value = props.questions.length > 0 ? props.questions : [{ ...newQuestion.value }]
})
</script>
