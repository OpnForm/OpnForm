<template>
  <div class="space-y-8">
    <!-- Profile Information Section -->
    <div class="space-y-4">
      <div>
        <h3 class="text-lg font-medium text-neutral-900">Profile Information</h3>
        <p class="text-sm text-neutral-500 mt-1">
          Update your account profile information and email address.
        </p>
      </div>

      <VForm size="sm">
        <form
          @submit.prevent="updateProfile"
        >
          <div class="max-w-sm">
            <text-input
              :form="profileForm"
              name="name"
              label="Full Name"
              placeholder="Enter your full name"
              :required="true"
            />
            <text-input
              :form="profileForm"
              name="email"
              label="Email Address"
              type="email"
              placeholder="Enter your email"
              :required="true"
              :disabled="user?.can_change_email === false"
            >
              <template
                v-if="user?.can_change_email === false"
                #help
              >
                Your email address is managed by your sign-in provider.
              </template>
            </text-input>
            <TextInput
              v-if="isEmailChanged && user?.can_change_email !== false"
              :form="profileForm"
              name="current_password"
              label="Current Password"
              native-type="password"
              placeholder="Enter current password"
              :required="true"
            />
          </div>

          <div class="mt-4">
            <UButton
              type="submit"
              :loading="profileForm.busy"
              color="primary"
            >
              Save Changes
            </UButton>
          </div>
        </form>
      </VForm>
    </div>

    <div class="pt-8 border-t border-neutral-200">
      <div class="flex flex-col gap-2 items-start">
        <div>
          <h4 class="font-medium text-red-800">Delete Account</h4>
          <p class="mt-1 text-sm text-neutral-500">
            This will permanently delete your entire account. This cannot be undone.
          </p>
        </div>
        
          <UButton
            color="error"
            :loading="deleteMutation.isPending.value"
            @click="confirmDeleteAccount"
          >
            Delete Account
          </UButton>
        
      </div>
    </div>
  </div>
</template>

<script setup>
// Use useAuth composable for all user-related mutations
const alert = useAlert()

const {
  deleteAccount: deleteAccountFactory,
  invalidateUser
} = useAuth()

// Query mutations
const deleteMutation = deleteAccountFactory()

const { data: user } = useAuth().user()

// Profile form
const profileForm = useForm({
  name: '',
  email: '',
  current_password: '',
})

const isEmailChanged = computed(() => {
  return Boolean(user.value) && profileForm.email.trim().toLowerCase() !== user.value.email.toLowerCase()
})

// Update profile
const updateProfile = () => {
  profileForm.patch('/settings/profile')
    .then(() => {
      invalidateUser()
      alert.success('Your info has been updated!')
    })
    .catch((error) => {
      console.error(error)
      alert.error(error?.data?.message || 'Error updating profile')
    })
    .finally(() => {
      profileForm.current_password = ''
    })
}

// Delete account confirmation
const confirmDeleteAccount = () => {
  alert.confirm(
    'Do you really want to delete your account?',
    deleteAccount
  )
}

// Delete account
const deleteAccount = () => {
  deleteMutation.mutateAsync()
    .then((data) => {
      alert.success(data?.message || 'Your account has been deleted')
      // Navigation handled by deleteAccount mutation
    })
    .catch((error) => {
      alert.error(error?.data?.message || 'Error deleting account')
    })
}

function syncProfileForm(currentUser) {
  profileForm.name = currentUser.name
  profileForm.email = currentUser.email
  profileForm.current_password = ''
}

// Initialize form with user data
onBeforeMount(() => {
  if (user.value) {
    syncProfileForm(user.value)
  }
})

// Watch for user changes
watch(user, (newUser) => {
  if (newUser) {
    syncProfileForm(newUser)
  }
}, { immediate: true })

watch(isEmailChanged, (emailChanged) => {
  if (!emailChanged) {
    profileForm.current_password = ''
  }
})

</script>
