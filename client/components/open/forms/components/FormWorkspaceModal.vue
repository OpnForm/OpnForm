<template>
  <UModal
    v-model:open="isOpen"
    :ui="{ content: 'sm:max-w-lg' }"
  >
    <template #header>
      <div class="flex items-center w-full gap-4 px-2">
        <h2 class="font-semibold">
          Change form's workspace
        </h2>
      </div>
      <UButton
        color="neutral"
        variant="outline"
        icon="i-heroicons-question-mark-circle"
        size="sm"
        @click="crisp.openHelpdeskArticle('how-to-move-a-form-to-another-workspace-1twq0kg')"
      >
        Help
      </UButton>
    </template>

    <template #body>
      <div class="flex space-x-4 items-center">
        <p>Current workspace:</p>
        <div class="flex items-center cursor group p-2 rounded-sm border">
          <WorkspaceIcon :workspace="workspace" />
          <p
            class="lg:block max-w-10 truncate ml-2 text-neutral-800 dark:text-neutral-200"
          >
            {{ workspace.name }}
          </p>
        </div>
      </div>
      <form @submit.prevent="onSubmit">
        <div class="my-4">
          <select-input
            v-model="selectedWorkspace"
            name="workspace"
            class=""
            :options="workspacesSelectOptions"
            :required="true"
            label="Select destination workspace"
          />
        </div>
      </form>
    </template>

    <template #footer>
      <div class="flex justify-end gap-x-2">
        <UButton
          color="neutral"
          variant="outline"
          @click="close"
          label="Close"
        />
        <UButton
          :loading="loading"
          @click="onSubmit"
          label="Change workspace"
        />
      </div>
    </template>
  </UModal>
</template>

<script setup>
import { ref, defineProps, defineEmits, computed } from "vue"
import WorkspaceIcon from "~/components/workspaces/WorkspaceIcon.vue"

const emit = defineEmits(["close"])
const { switchTo } = useCurrentWorkspace()
const crisp = useCrisp()

const selectedWorkspace = ref(null)
const props = defineProps({
  show: { type: Boolean, required: true }, 
  form: { type: Object, required: true },
})

// Modal state
const isOpen = computed({
  get() {
    return props.show
  },
  set(value) {
    if (!value) {
      close()
    }
  }
})

const { data: workspaces } = useWorkspaces().list()
const workspace = computed(() =>
  workspaces.value?.find(w => w.id === props.form?.workspace_id)
)
const loading = ref(false)
const workspacesSelectOptions = computed(() =>
  workspaces.value
    .filter((w) => {
      return w.id !== workspace.value.id
    })
    .map((workspace) => ({ name: workspace.name, value: workspace.id })),
)

const { updateWorkspace } = useForms()
const updateWorkspaceMutation = updateWorkspace()

const close = () => {
  emit("close")
}

const onSubmit = () => {
  if (!selectedWorkspace.value) {
    useAlert().error("Please select a workspace!")
    return
  }
  
  loading.value = true
  
  updateWorkspaceMutation.mutateAsync({
    id: props.form.id,
    workspaceId: selectedWorkspace.value,
    data: {}
  }).then(() => {
    loading.value = false
    emit("close")
    useAlert().success("Form workspace updated successfully.")
    
    // Switch to the new workspace
    switchTo(selectedWorkspace.value)
    
    // Navigate to home if not already there
    const router = useRouter()
    const route = useRoute()
    if (route.name !== "home") {
      router.push({ name: "home" })
    }
  }).catch((error) => {
    useAlert().error(
      error?.data?.message ?? "Something went wrong, please try again!",
    )
    loading.value = false
  })
}
</script>
