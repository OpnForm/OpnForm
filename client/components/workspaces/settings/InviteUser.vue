<template>
  <UModal
    v-model:open="isOpen"
    :content="{
      onPointerDownOutside: (event) => { if (event.target?.closest('.crisp-client')) {return event.preventDefault()}}
    }"
  >
    <template #header>
      <div class="flex items-center w-full gap-4 px-2">
        <h2 class="font-semibold">
          Invite a new user
        </h2>
      </div>
      <UButton
        color="neutral"
        variant="outline"
        icon="i-heroicons-question-mark-circle"
        size="sm"
        @click="crisp.openHelpdeskArticle('how-to-invite-users-team-members-to-my-workspace-qyw16g')"
      >
        Help
      </UButton>
    </template>

    <template #body>
      <UAlert
        v-if="paidPlansEnabled && !hasActiveLicense && !canInviteUsers"
        icon="i-heroicons-user-group-20-solid"
        class="mb-4"
        color="warning"
        variant="subtle"
        title="Pro plan required"
        description="Please upgrade your account to invite users to your workspace."
        :actions="[{
          label: 'Upgrade to Pro',
          color: 'warning',
          variant: 'solid',
          onClick: () => openSubscriptionModal({
            modal_title: 'Upgrade to invite users to your workspace',
            modal_description: 'Upgrade to our Pro plan to unlock team collaboration features along with customized branding, form analytics, custom domains, and more!'
          })
        }]"
      />

      <VForm
        size="sm"
        class="my-2"
        @submit.prevent="addUser"
      >
        <TextInput
          :form="inviteUserForm"
          name="email"
          label="Email"
          :required="true"
          :disabled="!canInviteUsers"
          placeholder="Add a new user by email"
        />
        <FlatSelectInput
          :form="inviteUserForm"
          name="role"
          :options="roleOptions"
          :disabled="!canInviteUsers"
          placeholder="Select User Role"
          label="Role"
          :required="true"
        />
        <div class="flex justify-center mt-4">
          <UButton
            type="submit"
            :disabled="!canInviteUsers"
            :loading="inviteUserMutation.isPending.value"
            icon="i-heroicons-envelope"
          >
            Invite User
          </UButton>
        </div>
      </VForm>
    </template>
  </UModal>
</template>

<script setup>
const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  }
})

const { data: user } = useAuth().user()
const { addUser: addUserMutation } = useWorkspaceUsers()

// Local computed for active license check
const hasActiveLicense = computed(() => {
  return user.value !== null && user.value !== undefined && user.value.active_license !== null
})
const crisp = useCrisp()
const { openSubscriptionModal: openModal } = useAppModals()
const { currentId: workspaceId } = useCurrentWorkspace()
const alert = useAlert()
const { hasFeature } = usePlanFeatures()
const canInviteUsers = computed(() => hasFeature('invite_user'))

const emit = defineEmits(['update:modelValue', 'user-added'])

const roleOptions = [
  {name: "User", value: "user"},
  {name: "Admin", value: "admin"},
  {name: "Read Only", value: "readonly"}
]

// Modal state
const isOpen = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

// Create mutation during setup
const inviteUserMutation = addUserMutation(workspaceId)

// Methods
const closeModal = () => {
  isOpen.value = false
}

const openUpgradeModal = () => {
  openSubscriptionModal({
    plan: isSelfHosted.value ? 'self_hosted' : 'pro',
    modal_title: 'Upgrade to invite users to your workspace'
  })
}

const paidPlansEnabled = ref(useFeatureFlag('billing.enabled'))
const isSelfHosted = computed(() => useFeatureFlag('self_hosted'))

const inviteUserForm = useForm({
  email: '',
  role: 'user'
})

const addUser = () => {
  if (!workspaceId.value) return

  inviteUserMutation.mutateAsync({
    email: inviteUserForm.email,
    role: inviteUserForm.role
  }).then((data) => {
    inviteUserForm.reset()
    alert.success(data.message || 'User invited successfully')
    emit('user-added')
    closeModal()
  }).catch((error) => {
    alert.error(error?.data?.message || "There was an error adding user")
  })
}
</script>
