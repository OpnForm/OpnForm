<template>
  <div
    v-if="logic"
    :key="resetKey"
  >
    <div class="flex gap-1 border-b pb-2">
      <UButton
        color="neutral"
        variant="ghost"
        size="xs"
        icon="i-heroicons-arrow-down-on-square"
        class="text-neutral-500"
        @click="showCopyFormModal = true"
      >
        Copy from
      </UButton>
      <UButton
        color="neutral"
        variant="ghost"
        size="xs"
        icon="i-heroicons-arrow-up-on-square"
        class="text-neutral-500"
        @click="showCopyToModal = true"
      >
        Copy to
      </UButton>
      <UButton
        color="neutral"
        variant="ghost"
        size="xs"
        icon="i-mdi-clear-outline"
        class="text-neutral-500"
        @click="clearAll"
      >
        Clear
      </UButton>
      <UButton
        color="neutral"
        variant="ghost"
        class="text-neutral-500"
        size="xs"
        icon="i-heroicons-question-mark-circle"
        @click="openHelpArticle"
      />
    </div>

    <!-- Conditions Card -->
    <div class="mt-4">
      <p class="text-xs font-medium text-gray-600 mb-2">When following condition(s) are true</p>
      <div class="p-3 border border-gray-200 rounded-lg bg-gray-50/50 hover:bg-gray-50 transition-colors">
      <UPopover
        :content="{ 
          align: 'start', 
          side: 'left', 
          sideOffset: 8 
        }"
        :ui="{ 
          content: 'w-[650px] overflow-hidden' 
        }"
        arrow
      >
        <UButton
          :color="hasConditions ? 'primary' : 'neutral'"
          :variant="hasConditions ? 'subtle' : 'outline'"
          :icon="hasConditions ? 'i-heroicons-cog-8-tooth-16-solid' : 'i-heroicons-plus'"
          size="sm"
          class="w-full justify-start font-medium hover:bg-white transition-colors"
        >
          {{ hasConditions ? `${conditionsCount} rule${conditionsCount > 1 ? 's' : ''}` : 'Add rule' }}
        </UButton>

        <template #content>
            <ScrollableContainer
              ref="scrollableContainer"
              direction="both"
              max-width-class="max-w-[650px]"
              max-height-class="max-h-96"
              :fade-class="'from-white via-white/80 to-transparent'"
              left-fade-width="w-4"
              right-fade-width="w-4"
              top-fade-height="h-4"
              bottom-fade-height="h-4"
              :scroll-tolerance="5"
            >
              <condition-editor
                class="w-full p-4"
                ref="filter-editor"
                v-model="logic.conditions"
                :form="form"
              />
            </ScrollableContainer>
        </template>
      </UPopover>
      </div>
    </div>

    <!-- Divider Line -->
    <div class="flex items-center my-5">
      <div class="flex-1 border-b"></div>
      <span class="px-4 py-1 text-xs font-medium text-gray-600 bg-white border rounded-full">then</span>
      <div class="flex-1 border-b"></div>
    </div>

    <div>
      <p class="text-xs font-medium text-gray-600 mb-2">Apply the following action(s)</p>
      <div class="p-3 border border-gray-200 rounded-lg bg-gray-50/50 hover:bg-gray-50 transition-colors">
        <flat-select-input
          :key="resetKey"
          v-model="logic.actions"
          name="actions"
          :multiple="true"
          placeholder="Select actions..."
          :options="actionOptions"
          @update:model-value="onActionInput"
          clearable
        />
      </div>
    </div>

    <p class="text-neutral-400 text-xs mt-2">
      Note that hidden fields can never be required.
    </p>

    <UModal
      v-model:open="showCopyFormModal"
      title="Copy logic from another field"
      :description="`Select another field/block to copy its logic and apply it to '${field.name}'.`"
    >
      <template #body>
        <USelectMenu
          v-model="copyFrom"
          :items="copyFromOptions"
          value-key="value"
          placeholder="Choose a field/block..."
          searchable
        />
      </template>

      <template #footer>
        <UButton
          color="neutral"
          variant="outline"
          label="Close"
          @click="showCopyFormModal = false"
        />
        <UButton
          color="primary"
          @click="copyLogic"
          label="Confirm & Copy"
        />
      </template>
    </UModal>

    <UModal
      v-model:open="showCopyToModal"
      title="Copy logic to other fields"
      :description="`Select other fields to copy the logic from '${field.name}' to.`"
    >
      <template #body>
        <USelectMenu
          v-model="copyTo"
          :items="copyToOptions"
          value-key="value"
          placeholder="Choose fields..."
          :multiple="true"
          searchable
        />
      </template>

      <template #footer>
        <UButton
          color="neutral"
          variant="outline"
          label="Close"
          @click="showCopyToModal = false"
        />
        <UButton
          color="primary"
          @click="copyLogicToFields"
          label="Confirm & Copy"
        />
      </template>
    </UModal>
  </div>
</template>

<script>
import ConditionEditor from "./ConditionEditor.client.vue"
import ScrollableContainer from "~/components/dashboard/ScrollableContainer.vue"
import clonedeep from "clone-deep"
import { default as _has } from "lodash/has"

export default {
  name: "FormBlockLogicEditor",
  components: { ConditionEditor, ScrollableContainer },
  props: {
    field: {
      type: Object,
      required: false,
    },
    form: {
      type: Object,
      required: false,
    },
  },

  setup() {
    const crisp = useCrisp()
    return {
      crisp
    }
  },

  data() {
    return {
      resetKey: 0,
      logic: this.field.logic || {
        conditions: null,
        actions: [],
      },
      showCopyFormModal: false,
      copyFrom: null,
      showCopyToModal: false,
      copyTo: [],
    }
  },

  computed: {
    conditionsCount() {
      if (this.logic.conditions === null || this.logic.conditions === undefined) return 0
      // Count the number of rules/conditions recursively
      return this.countConditions(this.logic.conditions)
    },
    hasConditions() {
      return this.conditionsCount > 0
    },
    copyFromOptions() {
      return this.form.properties
        .filter((field) => {
          return (
            field.id !== this.field.id &&
            _has(field, "logic") &&
            field.logic !== null &&
            Object.keys(field.logic || {}).length > 0
          )
        })
        .map((field) => {
          return { label: field.name, value: field.id }
        })
    },
    copyToOptions() {
      return this.form.properties
        .filter((field) => {
          return field.id !== this.field.id
        })
        .map((field) => {
          return { label: field.name, value: field.id }
        })
    },
    actionOptions() {
      if (
        [
          "nf-text",
          "nf-code",
          "nf-page-break",
          "nf-divider",
          "nf-image",
          "nf-video",
        ].includes(this.field.type)
      ) {
        if (this.field.hidden) {
          return [{ name: "Show Block", value: "show-block" }]
        } else {
          return [{ name: "Hide Block", value: "hide-block" }]
        }
      }

      if (this.field.hidden) {
        return [
          { name: "Show Block", value: "show-block" },
          { name: "Require answer", value: "require-answer" },
        ]
      } else if (this.field.disabled) {
        return [
          { name: "Enable Block", value: "enable-block" },
          this.field.required
            ? { name: "Make it optional", value: "make-it-optional" }
            : {
                name: "Require answer",
                value: "require-answer",
              },
        ]
      } else {
        return [
          { name: "Hide Block", value: "hide-block" },
          { name: "Disable Block", value: "disable-block" },
          this.field.required
            ? { name: "Make it optional", value: "make-it-optional" }
            : {
                name: "Require answer",
                value: "require-answer",
              },
        ]
      }
    },
  },

  watch: {
    logic: {
      handler() {
        this.field.logic = this.logic
      },
      deep: true,
    },
    "field.id": {
      handler() {
        // On field change, reset logic
        this.logic = this.field.logic || {
          conditions: null,
          actions: [],
        }
      },
    },
    "field.required": "cleanConditions",
    "field.disabled": "cleanConditions",
    "field.hidden": "cleanConditions",
  },

  mounted() {
    if (!_has(this.field, "logic")) {
      this.field.logic = this.logic
    }
  },

  methods: {
    countConditions(conditions) {
      if (!conditions) return 0
      
      // If it's a group with children
      if (conditions.children && Array.isArray(conditions.children)) {
        return conditions.children.reduce((count, child) => {
          // If child has an identifier, it's a rule
          if (child.identifier) {
            return count + 1
          }
          // If child has children, it's a nested group - count recursively
          if (child.children) {
            return count + this.countConditions(child)
          }
          return count
        }, 0)
      }
      
      // If it's a single rule with identifier
      if (conditions.identifier) {
        return 1
      }
      
      return 0
    },
    clearAll() {
      this.logic.conditions = null
      this.logic.actions = []
      this.refreshActions()
    },
    onActionInput() {
      if (this.logic.actions.length >= 2) {
        if (
          this.logic.actions[1] === "require-answer" &&
          this.logic.actions[0] === "hide-block"
        ) {
          this.logic.actions = ["require-answer"]
        } else if (
          this.logic.actions[1] === "hide-block" &&
          this.logic.actions[0] === "require-answer"
        ) {
          this.logic.actions = ["hide-block"]
        }
        this.refreshActions()
      }
    },
    cleanConditions() {
      const availableActions = this.actionOptions.map(function (op) {
        return op.value
      })
      this.logic.actions = availableActions.filter((value) =>
        this.logic.actions.includes(value),
      )
      this.refreshActions()
    },
    refreshActions() {
      this.resetKey++
    },
    openHelpArticle() {
      this.crisp.openHelpdeskArticle('how-do-i-add-logic-to-my-form-1lmguq5')
    },
    copyLogic() {
      if (this.copyFrom) {
        const property = this.form.properties.find((property) => {
          return property.id === this.copyFrom
        })
        if (property && property.logic) {
          this.logic = clonedeep(property.logic)
          this.cleanConditions()
        }
      }
      this.showCopyFormModal = false
    },
    copyLogicToFields() {
      if (this.copyTo.length) {
        this.copyTo.forEach((fieldId) => {
          const targetField = this.form.properties.find(
            (property) => property.id === fieldId
          )
          if (targetField) {
            targetField.logic = clonedeep(this.logic)
          }
        })
      }
      this.showCopyToModal = false
      this.copyTo = []
    },
  },
}
</script>
