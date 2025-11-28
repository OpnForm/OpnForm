<template>
  <AdminCard
    v-if="props.user.stripe_id"
    title="Apply discount"
    icon="heroicons:tag-20-solid"
  >
    <form
      class="space-y-6 flex flex-col justify-between"
      @submit.prevent="applyDiscount"
    >
      <p class="text-xs text-neutral-500">
        This is only for students, academics and NGOs. Make sure to verify
        their status before applying discount (student/university email, NGO
        website, proof of non-profit, etc). They need to create their
        subscriptions before you can apply the 40% discount.
      </p>
      <UButton
        :loading="form.busy"
        type="submit"
        block
        label="Apply Discount"
      />
    </form>
  </AdminCard>
</template>

<script setup>
const props = defineProps({
  user: { type: Object, required: true }
})

const form = useForm({
  user_id: props.user.id
})

const applyDiscount = () => {
  if (!props.user.stripe_id) return
  form
    .patch('/moderator/apply-discount')
    .then(async (data) => {
      useAlert().success(data.message)
    })
    .catch((error) => {
      useAlert().error(error.data.message)
    })
}

</script>
