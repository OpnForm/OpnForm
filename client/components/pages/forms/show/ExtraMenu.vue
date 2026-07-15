<template>
  <div v-if="form">
    <UDropdownMenu
      class="z-50"
      arrow
      :items="items"
      :content="content"
      :modal="false"
      :portal="portal"
    >
      <slot :loading="deleteFormMutation.isPending.value || duplicateFormMutation.isPending.value">
      <UButton
        color="neutral"
        variant="outline"
        icon="i-heroicons-ellipsis-horizontal"
        size="md"
        :loading="deleteFormMutation.isPending.value || duplicateFormMutation.isPending.value"
      />
      </slot>
    </UDropdownMenu>

    <!-- Delete Form Modal -->
    <UModal
      v-model:open="showDeleteFormModal"
      :ui="{ content: 'sm:max-w-sm' }"
      title="Delete form"
    >
      <template #body>
        <p>
          If you want to permanently delete this form and all of its data, you
          can do so below.
        </p>
      </template>

      <template #footer>
        <div class="flex justify-between gap-3 w-full">
          <UButton
            color="neutral"
            variant="outline"
            @click="showDeleteFormModal = false"
            label="Cancel"
          />
          <UButton
            color="error"
            :loading="deleteFormMutation.isPending.value"
            @click="deleteForm"
            label="Yes, delete it"
          />
        </div>
      </template>
    </UModal>
    <form-template-modal
      v-if="!isMainPage && user"
      :form="form"
      :show="showFormTemplateModal"
      @close="showFormTemplateModal = false"
    />
    <form-workspace-modal
      v-if="user"
      :form="form"
      :show="showFormWorkspaceModal"
      @close="showFormWorkspaceModal = false"
    />
  </div>
</template>

<script setup>
import { ref, defineProps, computed } from "vue"
import FormTemplateModal from "../../../open/forms/components/templates/FormTemplateModal.vue"
import FormWorkspaceModal from "../../../open/forms/components/FormWorkspaceModal.vue"

const { copy } = useClipboard()
const router = useRouter()

const props = defineProps({
  form: { type: Object, required: true },
  isMainPage: { type: Boolean, required: false, default: false },
  content: { 
    type: Object, 
    required: false, 
    default: () => ({side: 'bottom', align: 'end'}) 
  },
  portal: { type: [Boolean, String], required: false, default: false }
})

const { data: user } = useAuth().user()
const { current: workspace } = useCurrentWorkspace()

const { remove, duplicate } = useForms()

const showDeleteFormModal = ref(false)
const showFormTemplateModal = ref(false)
const showFormWorkspaceModal = ref(false)

const deleteFormMutation = remove()
const duplicateFormMutation = duplicate()
const { emitFormDeleted } = useEditorEmbedBridge()

const items = computed(() => {
  return [
    [
      ...props.isMainPage ? [{
        label: 'Open form',
        icon: 'i-heroicons-arrow-top-right-on-square',
        onClick: () => {
          if (props.isMainPage && props.form.visibility === 'draft') {
            showDraftFormWarningNotification()
          } else {
            window.open(props.form.share_url, '_blank')
          }
        }
      }] : [],
      {
        label: 'Copy link to share',
        icon: 'i-heroicons-clipboard-document-check-20-solid',
        onClick: copyLink
      }
    ],
    ...workspace.value?.is_readonly ? [] : [
      [
        ...props.isMainPage ? [{
        label: 'Edit',
        icon: 'i-heroicons-pencil-square-20-solid',
        to: { name: 'forms-slug-edit', params: { slug: props.form.slug } }
      }] : [],
      {
        label: 'Duplicate form',
        icon: 'i-heroicons-document-duplicate-20-solid',
        onClick: duplicateForm
        
      }], 
    [
      ...props.isMainPage ? [] : [{
        label: 'Create Template',
        icon: 'i-heroicons-document-plus-20-solid',
        onClick: () => {
          showFormTemplateModal.value = true
        }
      }],
      {
        label: 'Change workspace',
        icon: 'i-heroicons-building-office-2-20-solid',
        onClick: () => {
          showFormWorkspaceModal.value = true
        }
      },
    ],[
      {
        label: 'Delete form',
        icon: 'i-heroicons-trash-20-solid',
        onClick: () => {
          showDeleteFormModal.value = true
        },
        class: 'text-red-800 hover:bg-red-50 hover:text-red-600 group',
        iconClass: 'text-red-900 group-hover:text-red-800'
      }
      ]
    ]
  ].filter((group) => group.length > 0)
})

const copyLink = () => {
  copy(props.form.share_url)
  useAlert().success("Copied!")
}

const duplicateForm = () => {
  duplicateFormMutation.mutateAsync(props.form.id).then((data) => {
    router.push({
      name: "forms-slug-show",
      params: { slug: data.new_form.slug },
    })
    useAlert().success(data.message)
  }).catch((error) => {
    useAlert().error(error.data?.message || "Failed to duplicate form")
  })
}

const deleteForm = () => {
  deleteFormMutation.mutateAsync(props.form.id).then((data) => {
    useAlert().success(data.message)
    showDeleteFormModal.value = false
    emitFormDeleted(props.form)
    router.push({ name: "home" })
  }).catch((error) => {
    useAlert().error(error.data?.message || "Failed to delete form")
  })
}

const showDraftFormWarningNotification = () => {
  useAlert().warning(
    "This form is currently in Draft mode and is not publicly accessible, You can change the form status on the edit form page.",
  )
}
</script>
