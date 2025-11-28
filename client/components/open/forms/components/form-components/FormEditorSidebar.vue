<template>
  <editor-right-sidebar
    :width-class="showAddFieldSidebar ? 'md:max-w-[15rem]' : 'md:max-w-[20rem]'"
    :show="isOpen"
    :resizable="true"
  >
    <VForm
      size="sm"
      @submit.prevent=""
    >
      <transition mode="out-in">
        <form-field-edit
          v-if="showEditFieldSidebar"
          :key="editFieldIndex"
          v-motion-fade="'fade'"
        />
        <add-form-block
          v-else-if="showAddFieldSidebar"
          v-motion-fade="'fade'"
        />
      </transition>
    </VForm>
  </editor-right-sidebar>
</template>

<script>
import { computed } from "vue"
import { useWorkingFormStore } from "../../../../../stores/working_form"
import { storeToRefs } from 'pinia'
import EditorRightSidebar from "../../../editors/EditorRightSidebar.vue"
import FormFieldEdit from "../../fields/FormFieldEdit.vue"
import AddFormBlock from "./AddFormBlock.vue"

export default {
  name: "FormEditorSidebar",
  components: { EditorRightSidebar, AddFormBlock, FormFieldEdit },
  props: {},
  setup() {
    const workingFormStore = useWorkingFormStore()
    
    const editFieldIndex = computed(() => workingFormStore.selectedFieldIndex)
    const form = storeToRefs(workingFormStore).content
    const showEditFieldSidebar = computed(() => workingFormStore.showEditFieldSidebar)
    const showAddFieldSidebar = computed(() => workingFormStore.showAddFieldSidebar)
    
    // The store now handles setting the page automatically in openSettingsForField
    
    return {
      workingFormStore,
      editFieldIndex,
      form,
      showEditFieldSidebar,
      showAddFieldSidebar,
    }
  },
  data() {
    return {}
  },
  computed: {
    isOpen() {
      return this.form !== null && (this.showEditFieldSidebar || this.showAddFieldSidebar)
    },
  },
  watch: {},
  mounted() {},
  methods: {},
}
</script>
