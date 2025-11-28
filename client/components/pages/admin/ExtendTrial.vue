<template>
  <AdminCard
    v-if="props.user.stripe_id"
    title="Extend trial"
    icon="heroicons:calendar-16-solid"
  >
    <form
      class="space-y-6 flex flex-col justify-between"
      @submit.prevent="extendTrial"
    >
      <p class="text-xs text-neutral-500">
        You can extend the trial of subscribers that are still in the trial
        period. Usually, you should not offer more than 7 days of trial, but
        you can add up to 14 days if needed.
      </p>
      <div>
        <TextInput
          name="number_of_day"
          :form="form"
          label="Number of days"
          native-type="day"
          :required="true"
          help="Number Of Days"
          placeholder="7"
        />
        <UButton
          :loading="form.busy"
          type="submit"
          block
          label="Apply Extend Trial"
        />
      </div>
    </form>
  </AdminCard>
</template>

<script setup>
const props = defineProps({
  user: { type: Object, required: true }
})

const form = useForm({
  user_id: props.user.id,
  number_of_day: ''
})

const extendTrial = () => {
  if (!props.user.stripe_id) return
  form
    .patch('/moderator/extend-trial')
    .then(async (data) => {
      useAlert().success(data.message)
    })
    .catch((error) => {
      useAlert().error(error.data.message)
    })
}

</script>
