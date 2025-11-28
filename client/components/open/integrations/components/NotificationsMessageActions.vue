<template>
  <div>
    <MentionInput
      v-model="compVal.message"
      :mentions="form.properties"
      name="message"
      class="mt-4"
      label="Notification Message"
      help="Customize the text of the notification message. Click @ to include form field values."
    />
    <toggle-switch-input
      v-model="compVal.include_submission_data"
      name="include_submission_data"
      class="mt-4"
      label="Include submission data"
      help="With form submission answers"
    />
    <toggle-switch-input
      v-if="compVal.include_submission_data"
      v-model="compVal.include_hidden_fields_submission_data"
      name="include_hidden_fields_submission_data"
      class="mt-4"
      label="Include hidden fields"
      help="If enabled then hidden fields will be included in the notification message"
    />
    <toggle-switch-input
      v-model="compVal.link_open_form"
      name="link_open_form"
      class="mt-4"
      label="'Open Form' Link"
      help="Link to the form public page"
    />
    <toggle-switch-input
      v-model="compVal.link_edit_form"
      name="link_edit_form"
      class="mt-4"
      label="'Edit Form' Link"
      help="Link to the form admin page"
    />
    <toggle-switch-input
      v-model="compVal.views_submissions_count"
      name="views_submissions_count"
      class="mt-4"
      label="Form Analytics"
      help="Form views and submissions count"
    />
    <toggle-switch-input
      v-if="form.editable_submissions"
      v-model="compVal.link_edit_submission"
      name="link_edit_submission"
      class="mt-4"
      label="Edit Submission Link"
    />
  </div>
</template>

<script>
export default {
  name: "NotificationsMessageActions",
  components: {},
  props: {
    modelValue: { type: Object, required: false },
    form: { type: Object, required: true },
  },
  emits:  ['modelValue',  'input'],
  data() {
    return {
      content: this.modelValue ?? {},
    }
  },

  computed: {
    compVal: {
      set(val) {
        this.content = val
        this.$emit("input", this.compVal)
      },
      get() {
        return this.content
      },
    },
  },

  watch: {
    modelValue(val) {
      this.content = val
    },
  },

  created() {
    if (this.compVal === undefined || this.compVal === null) {
      this.compVal = {}
    }
    [
      "message",
      "include_submission_data",
      'include_hidden_fields_submission_data',
      "link_open_form",
      "link_edit_form",
      "views_submissions_count",
      "link_edit_submission",
    ].forEach((keyname) => {
      if (this.compVal[keyname] === undefined) {
        if (keyname === 'message') {
          this.compVal[keyname] = 'New form submission'
        } else if (['include_hidden_fields_submission_data'].includes(keyname)) {
          this.compVal[keyname] = false
        } else {
          this.compVal[keyname] = true
        }
      }
    })
  },

  methods: {},
}
</script>
