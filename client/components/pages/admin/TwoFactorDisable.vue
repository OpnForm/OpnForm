<template>
  <AdminCard
    v-if="props.user.two_factor_enabled"
    title="Disable Two-Factor Authentication"
    icon="i-heroicons-shield-exclamation-20-solid"
  >
    <div class="space-y-6 flex flex-col justify-between">
      <UAlert
        icon="i-heroicons-exclamation-triangle"
        color="error"
        variant="subtle"
      >
        <template #description>
          <div>
            Disabling two-factor authentication is a critical security action. 
            Make sure you have a solid reason and strong proof of account ownership (such as verified identity or written consent) before proceeding. 
            The user will be required to re-enable 2FA on their next login.
          </div>
        </template>
      </UAlert>

      <VForm @submit.prevent="submit">
        <TextAreaInput
          label="Reason"
          name="reason"
          :form="form"
          :required="true"
          help="Reason will be sent to slack for internal use only."
        />
        <div class="flex space-x-2 mt-4">
          <UButton
            block
            :loading="form.busy"
            type="submit"
            class="grow"
            :label="'Disable Two-Factor Authentication'"
          />
        </div>
      </VForm>
    </div>
  </AdminCard>
</template>

<script setup>
const props = defineProps({
  user: { type: Object, required: true }
})
const emit = defineEmits(['user-updated'])

const alert = useAlert()

const form = useForm({
  user_id: props.user.id,
  reason: ''
})

async function submit() {
  try {
    let response
    response = await form.post('/moderator/disable-two-factor-authentication')
    alert.success(response.message)
    emit('user-updated', response.user)
    form.reset()
  } catch (error) {
    alert.error(error.data?.message || 'An error occurred.')
  }
}
</script> 