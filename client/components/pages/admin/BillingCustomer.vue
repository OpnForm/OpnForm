<template>
  <AdminCard
    v-if="props.user.stripe_id"
    title="Billing info"
    icon="heroicons:credit-card-16-solid"
  >
    <p class="text-xs text-neutral-500">
      You can update the billing info of the subscriber in Stripe.
    </p>
    <div
      v-if="loading"
      class="text-neutral-600 dark:text-neutral-400"
    >
      <Loader class="h-6 w-6 mx-auto m-10" />
    </div>
    <form
      v-else
      class="mt-6 space-y-6 flex flex-col justify-between"
      @submit.prevent="updateBillingCustomer"
    >
      <div class="space-y-4">
        <TextInput
          name="billing_name"
          :form="form"
          label="Billing name"
          :required="true"
          placeholder="Billing name"
          :disabled="!customerLoaded"
        />
        <TextInput
          name="billing_email"
          :form="form"
          label="Billing email"
          native-type="email"
          :required="true"
          placeholder="Billing email"
          :disabled="!customerLoaded"
        />
        <UButton
          :loading="form.busy"
          type="submit"
          block
          :disabled="!customerLoaded"
          label="Update billing info"
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

const loading = ref(false)
const customerLoaded = ref(false)
const form = useForm({
    billing_name: '',
    billing_email: '',
    user_id: props.user.id
})

onMounted(() => {
  if (!props.user.stripe_id) return
    loading.value = true
    adminApi.billing.getCustomer(props.user.id).then(data => {
        loading.value = false
        customerLoaded.value = true
        form.billing_name = data.billing_name || ''
        form.billing_email = data.billing_email || ''
    }).catch(() => {
        loading.value = false
        customerLoaded.value = false
    })
})

const updateBillingCustomer = () => {
    form.patch('/moderator/billing/customer')
        .then(async (data) => {
            useAlert().success(data.message)
        })
        .catch((error) => {
            useAlert().error(error.data.message)
        })
}
</script>
