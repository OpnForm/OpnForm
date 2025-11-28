<template>
  <UButton
    variant="outline"
    icon="i-heroicons-key-16-solid"
    :loading="form.busy"
    @click="resetPassword"
    label="Reset Password"
  />
</template>

<script setup>
const props = defineProps({
    user: { type: Object, required: true }
})

const form = useForm({
  user_id: props.user.id
})

const resetPassword = () => {
    return useAlert().confirm(
        "Are you sure you want to send a password reset email?",
        () => {
            form
                .patch('/moderator/send-password-reset-email')
                .then(async (data) => {
                    useAlert().success(data.message)
                })
                .catch((error) => {
                    useAlert().error(error.data?.message || 'Failed to send password reset email')
                })
        })
}
</script>