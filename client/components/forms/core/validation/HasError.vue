<template>
  <transition name="fade">
    <div
      v-if="errorMessage"
      :class="errorClasses"
      v-html="errorMessage"
    />
  </transition>
</template>

<script>
export default {
  name: 'HasError',
  props: {
    form: {
      type: Object,
      required: false,
      default: null,
    },
    fieldId: {
      type: String,
      required: true,
    },
    fieldName: {
      type: String,
      required: false,
    },
    errorClasses: {
      type: String,
      default: 'has-error text-xs text-red-500 break-words whitespace-break-spaces',
    },
  },
  computed: {
    errorMessage() {
      if (!this.form || !this.form.errors || !this.form.errors.any())
        return null
      const subErrorsKeys = Object.keys(this.form.errors.all()).filter(
        (key) => {
          return key.startsWith(this.fieldId) && key !== this.fieldId
        },
      )
      let baseError
        = this.form.errors.get(this.fieldId)
        ?? (subErrorsKeys.length ? 'This field has some errors:' : null)
      // If no error and no sub errors, return
      if (!baseError)
        return null

      // Check if baseError starts with "The {field.name} field" and replace if necessary
      if (baseError.startsWith(`The ${this.fieldName} field`)) {
        baseError = baseError.replace(`The ${this.fieldName} field`, 'This field')
      }

      const coreError = `<p class='text-red-500'>${baseError}</p>`
      if (subErrorsKeys.length) {
        return coreError + `<ul class='list-disc list-inside'>${subErrorsKeys.map(
          (key) => {
            return `<li>${this.getSubError(key)}</li>`
          },
        )}</ul>`
      }

      return coreError
    },
  },
  methods: {
    getSubError(subErrorKey) {
      return this.form.errors.get(subErrorKey).replace(subErrorKey, 'item')
    },
  },
}
</script>
