<template>
  <div class="relative h-[calc(100vh-55px)] overflow-y-auto">
    <div class="flex gap-2 sticky top-0 bg-white border-b z-10 p-4">
      <UTooltip
        text="Shortcut"
        :kbds="['meta', 'B']"
        arrow
      >
        <UButton
          color="neutral"
          variant="subtle"
          icon="i-heroicons-plus"
          class="flex-grow justify-center"
          @click.prevent="openAddFieldSidebar"
        >
          Add Block
        </UButton>
      </UTooltip>
    </div>

    <div class="p-4">
      <VueDraggable
        :model-value="form.properties"
        group="form-elements"
        item-key="id"
        class="mx-auto w-full overflow-hidden rounded-md border border-neutral-300 bg-white transition-colors dark:bg-notion-dark-light"
        ghost-class="bg-blue-100"
        :animation="200"
        @add="handleDragAdd"
        @update="handleDragUpdate"
      >
        <template #default>
          <div
            v-for="(element, index) in form.properties"
            :key="element.id || index"
            class="mx-auto w-full border-neutral-300 transition-colors cursor-grab"
            :class="{
              'bg-neutral-100 ': element.hidden && !isBeingEdited(index),
              'bg-white ': !element.hidden && !isBeingEdited(index),
              'border-b': index !== form.properties.length - 1,
              ' !border-blue-400 border-b-2': element.type === 'nf-page-break',
              'bg-blue-50 dark:bg-neutral-700': isBeingEdited(index),
            }"
          >
            <div
              v-if="element"
              class="group flex items-center gap-x-0.5 py-1.5 pr-1"
            >
              <BlockTypeIcon
                v-if="element.type && typeof element.type === 'string'"
                :type="element.type"
                class="ml-2"
              />
              <!-- Field name and type -->
              <div class="flex grow flex-col truncate">
                <EditableTag
                  class="truncate text-neutral-700 min-w-16 min-h-6"
                  :model-value="element.name"
                  @update:model-value="onChangeName(element, $event)"
                >
                  <label class="w-full cursor-pointer truncate">
                    {{ element.name }}
                  </label>
                </EditableTag>
              </div>

              <UTooltip arrow :text="element.hidden ? 'Show Block' : 'Hide Block'">
                <button
                  class="hidden !cursor-pointer rounded-sm p-1 transition-colors hover:bg-blue-100 items-center justify-center"
                  :class="{
                    'text-neutral-300 hover:text-blue-500 md:group-hover:flex': !element.hidden,
                    'text-neutral-300 hover:text-neutral-500 md:flex': element.hidden,
                  }"
                  @click="toggleHidden(element)"
                >
                  <template v-if="!element.hidden">
                    <Icon
                      name="heroicons:eye-solid"
                      class="h-5 w-5"
                    />
                  </template>
                  <template v-else>
                    <Icon
                      name="heroicons:eye-slash-solid"
                      class="h-5 w-5"
                    />
                  </template>
                </button>
              </UTooltip>
              <UTooltip
                v-if="element.type && typeof element.type === 'string' && !element.type.startsWith('nf-')"
                :text="element.required ? 'Make it optional' : 'Make it required'"
                arrow
              >
                <button
                  class="hidden cursor-pointer rounded-sm p-0.5 transition-colors hover:bg-blue-100 items-center px-1 justify-center"
                  :class="{
                    'md:group-hover:flex text-neutral-300 hover:text-red-500': !element.required,
                    'md:flex text-red-500': element.required,
                  }"
                  @click="toggleRequired(element)"
                >
                  <div
                    class="h-6 text-center text-3xl font-bold text-inherit -mt-0.5"
                  >
                    *
                  </div>
                </button>
              </UTooltip>
              <UTooltip arrow text="Open settings">
                <button
                  class="cursor-pointer rounded-sm p-1 transition-colors hover:bg-blue-100 text-neutral-300 hover:text-blue-500 flex items-center justify-center field-settings-button"
                  @click="editOptions(index)"
                >
                  <Icon
                    name="heroicons:cog-8-tooth-solid"
                    class="h-5 w-5"
                  />
                </button>
              </UTooltip>
            </div>
          </div>
        </template>
      </VueDraggable>
    </div>
  </div>
</template>

<script>
import { VueDraggable } from 'vue-draggable-plus'
import EditableTag from '~/components/app/EditableTag.vue'
import BlockTypeIcon from './BlockTypeIcon.vue'

export default {
  name: 'FormFieldsEditor',
  components: {
    VueDraggable,
    EditableTag,
    BlockTypeIcon
  },
  setup () {
    const workingFormStore = useWorkingFormStore()
    return {
      workingFormStore,
      form: storeToRefs(workingFormStore).content
    }
  },

  data () {
    return {

    }
  },

  computed: {
    // Expose the form structure service created by useFormManager
    structure () {
      return this.workingFormStore?.structureService || null
    },
    // Numeric current page index derived from the structure service
    currentPageIndex () {
      return this.structure?.currentPage?.value ?? 0
    }
  },

  mounted() {
    this.init()
  },

  methods: {
    init() {
      if (!this.form.properties) {
        return
      }
      this.form.properties = this.form.properties.map((field) => {
        // Add more field properties
        field.placeholder = field.placeholder || null
        field.prefill = field.prefill || null
        field.help = field.help || null
        field.help_position = field.help_position || "below_input"

        return field
      })
    },
    openAddFieldSidebar () {
      this.workingFormStore.openAddFieldSidebar(null)
    },
    editOptions (index) {
      this.workingFormStore.openSettingsForField(index)
    },
    onChangeName (field, newName) {
      field.name = newName
    },
    toggleHidden (field) {
      field.hidden = !field.hidden
      if (field.hidden) {
        field.required = false
      } else {
        field.generates_uuid = false
        field.generates_auto_increment_id = false
      }
    },
    toggleRequired (field) {
      field.required = !field.required
      if (field.required)
        field.hidden = false
    },
    isBeingEdited (index) {
      if (!this.workingFormStore?.showEditFieldSidebar) return false
      return index === this.workingFormStore.selectedFieldIndex
    },
    getAbsoluteIndex (relativeIndex) {
      if (!this.structure) return relativeIndex
      return this.structure.getTargetDropIndex(relativeIndex, this.currentPageIndex)
    },
    handleDragAdd (evt) {
      if (!this.structure) return
      const targetIndex = this.getAbsoluteIndex(evt.newIndex)
      const payload = evt?.clonedData
      this.workingFormStore.addBlock(payload, targetIndex, false)
    },
    handleDragUpdate (evt) {
      if (!this.structure) return
      const oldTargetIndex = this.getAbsoluteIndex(evt.oldIndex)
      const newTargetIndex = this.getAbsoluteIndex(evt.newIndex)
      if (oldTargetIndex !== newTargetIndex) {
        this.workingFormStore.moveField(oldTargetIndex, newTargetIndex)
      }
    }
  }
}
</script>

<style lang='scss'>
.v-popover {
  .trigger {
    @apply truncate w-full;
  }
}
</style>
