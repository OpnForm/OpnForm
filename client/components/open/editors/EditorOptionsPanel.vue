<template>
  <collapse
    v-model="show"
    class="p-4 w-full border-b"
  >
    <template #title>
      <div class="flex items-center pr-8 space-x-2">
        <div
          class="mr-3"
          :class="{ 'text-blue-600': show, 'text-neutral-500': !show }"
        >
          <slot name="icon" />
        </div>
        <h3
          id="v-step-2"
          class="font-semibold flex-grow mr-2"
        >
          {{ name }}
        </h3>
        <pro-tag v-if="hasProTag" />
      </div>
    </template>

    <slot />
  </collapse>
</template>

<script>
import Collapse from "~/components/app/Collapse.vue"
import ProTag from "~/components/app/ProTag.vue"

export default {
  name: "EditorOptionsPanel",
  components: { Collapse, ProTag },
  props: {
    name: {
      type: String,
      required: true,
    },
    hasProTag: {
      type: Boolean,
      default: false,
    },
    alreadyOpened: {
      type: Boolean,
      default: false,
    },
  },
  data() {
    return {
      show: this.alreadyOpened,
    }
  },
}
</script>
