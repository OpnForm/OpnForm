<template>
  <AdminCard
    v-if="props.user.stripe_id"
    title="Billing email"
    icon="heroicons:envelope-16-solid"
  >
    <p class="text-xs text-neutral-500">
      You can update the billing email of the subscriber.
    </p>
    <div
      v-if="loadingBillingEmail"
      class="text-neutral-600 dark:text-neutral-400"
    >
      <Loader class="h-6 w-6 mx-auto m-10" />
    </div>
    <form
      v-else
      class="mt-6 space-y-6 flex flex-col justify-between"
      @submit.prevent="updateUserBillingEmail"
    >
      <div>
        <TextInput
          name="billing_email"
          :form="form"
          label="Billing email"
          native-type="email"
          :required="true"
          help="Billing email"
          placeholder="Billing email"
          :disabled="!userCreated"
        />
        <UButton
          :loading="form.busy"
          type="submit"
          block
          :disabled="!userCreated"
          label="Update billing email"
        />
      </div>
    </form>
  </AdminCard>
</template>

<script setup>
import { adminApi } from '~/api'

const props = defineProps({
    user: { type: Object, required: true }
})

const loadingBillingEmail = ref(false)
const userCreated = ref(false)
const form = useForm({
    billing_email: '',
    user_id: props.user.id
})

onMounted(() => {
  if (!props.user.stripe_id) return
    loadingBillingEmail.value = true
    adminApi.billing.getEmail(props.user.id).then(data => {
        loadingBillingEmail.value = false
        userCreated.value = true
        form.billing_email = data.billing_email
    }).catch(() => {
        loadingBillingEmail.value = false
        userCreated.value = false
    })
})

const updateUserBillingEmail = () => {
    form.patch('/moderator/billing/email')
        .then(async (data) => {
            useAlert().success(data.message)
        })
        .catch((error) => {
            useAlert().error(error.data.message)
        })
}
</script>
