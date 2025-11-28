<template>
  <div
    :class="wrapperClass"
    :style="inputStyle"
  >
    <div class="flex justify-between">
      <slot name="status">
        <toggle-switch-input
          v-model="statusToggle"
          name="status"
          label="Enabled"
        />
      </slot>
    </div>

    <slot />

    <slot name="logic">
      <div class="border-t mt-6 pt-6">
        <collapse
          v-model="showLogic"
          class="w-full"
        >
          <template #title>
            <div class="flex gap-x-3 items-start pr-8">
              <div
                class="transition-colors"
                :class="{
                  'text-blue-600': showLogic,
                  'text-neutral-300': !showLogic,
                }"
              >
                <Icon
                  name="material-symbols:settings"
                  size="30px"
                />
              </div>
              <div class="flex-grow">
                <h3 class="font-semibold">
                  {{ hasLogic ? 'Logic configured' : 'Add logic' }}
                </h3>
                <p class="text-neutral-500 text-xs">
                  {{ hasLogic ? 'Conditions control when integration runs' : 'Set conditions for when to run' }}
                </p>
              </div>
            </div>
          </template>


          <p class="text-xs font-medium text-gray-600 mb-2 mt-4">When should this integration run?</p>
          <p class="text-neutral-500 text-xs mb-3">
            Set <span class="font-semibold">conditions that control when this integration executes</span>. Leave empty to always run.
          </p>
          <div class="p-3 border border-gray-200 rounded-lg bg-gray-50/50 mt-4">
            <condition-editor
              ref="filter-editor"
              v-model="modelValue.logic"
              class="integration-logic"
              :form="form"
            />
          </div>
        </collapse>
      </div>
    </slot>
  </div>
</template>

<script setup>
import ConditionEditor from "~/components/open/forms/components/form-logic-components/ConditionEditor.client.vue"
import Collapse from "~/components/app/Collapse.vue"

const props = defineProps({
  integration: { type: Object, required: true },
  modelValue: { type: Object, required: false },
  wrapperClass: { type: String, required: false },
  inputStyle: { type: Object, required: false },
  form: { type: Object, required: false },
})

defineEmits(["close"])
const showLogic = ref(!!props.modelValue.logic)

const statusToggle = computed({
  get: () => props.modelValue.status === 'active',
  set: (value) => {
    props.modelValue.status = value ? 'active' : 'inactive'
  }
})

const hasLogic = computed(() => {
  return props.modelValue.logic && (
    props.modelValue.logic.children?.length > 0 ||
    props.modelValue.logic.identifier
  )
})
</script>

<style lang="scss">
.integration-logic {
  .query-builder-group__group-children {
    margin: 4px 0px 0px 0px !important;
  }
}
</style>
