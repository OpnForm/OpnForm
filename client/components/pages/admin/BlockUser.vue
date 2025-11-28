<template>
  <AdminCard
    :title="isBlocked ? 'Unblock User' : 'Block User'"
    :icon="isBlocked ? 'heroicons:lock-open-20-solid' : 'heroicons:no-symbol-20-solid'"
  >
    <div class="space-y-6 flex flex-col justify-between">
      <UAlert
        :icon="alertContent.icon"
        :color="alertContent.color"
        :variant="alertContent.variant"
      >
        <template #description>
          <div v-html="alertContent.content" />
        </template>
      </UAlert>

      <VForm @submit.prevent="submit">
        <TextAreaInput
          label="Reason"
          name="reason"
          :form="form"
          :required="true"
          help="Reason will be sent to the user via email."
        />
        <div class="flex space-x-2 mt-4">
          <UButton
            block
            :loading="form.busy"
            type="submit"
            class="grow"
            :label="isBlocked ? 'Unblock User' : 'Block User'"
          />
          <UButton
            v-if="blockingHistory && blockingHistory.length"
            variant="outline"
            icon="i-heroicons-clock"
            @click="isModalOpen = true"
            label="View History"
          />
        </div>
      </VForm>
    </div>
    <UModal 
      v-model:open="isModalOpen"
      :ui="{ content: 'sm:max-w-4xl' }"
      title="Blocking History"
    >
      <template #body>
        <UTable 
          :columns="historyColumns"
          :data="blockingHistory"
        />
      </template>
    </UModal>
  </AdminCard>
</template>

<script setup>


const props = defineProps({
  user: { type: Object, required: true }
})
const emit = defineEmits(['user-updated'])

const alert = useAlert()
const isModalOpen = ref(false)

const form = useForm({
  user_id: props.user.id,
  reason: ''
})

const historyColumns = [
  {
    accessorKey: 'blocked_at',
    header: 'Blocked At',
    cell: ({ row }) => {
      return row.original.blocked_at ? new Date(row.original.blocked_at).toLocaleString() : ''
    }
  },
  {
    accessorKey: 'blocked_by',
    header: 'Blocked By',
    cell: ({ row }) => {
      return row.original.blocked_by ? row.original.blocked_by : 'AI'
    }
  },
  {
    accessorKey: 'reason',
    header: 'Reason'
  },
  {
    accessorKey: 'unblocked_at',
    header: 'Unblocked At',
    cell: ({ row }) => {
      return row.original.unblocked_at ? new Date(row.original.unblocked_at).toLocaleString() : ''
    }
  },
  {
    accessorKey: 'unblocked_by',
    header: 'Unblocked By'
  },
  {
    accessorKey: 'unblock_reason',
    header: 'Unblock Reason'
  }
]

const isBlocked = computed(() => props.user.is_blocked)
const blockingHistory = computed(() => props.user.meta?.blocking_history || [])
const lastBlock = computed(() => {
  if (!blockingHistory.value.length) {
    return null
  }
  return blockingHistory.value[blockingHistory.value.length - 1]
})

const alertContent = computed(() => {
  if (isBlocked.value) {
    const blockedBy = lastBlock.value?.blocked_by || 'Automatically blocked by our AI'
    return {
      icon: 'i-heroicons-exclamation-triangle',
      color: 'error',
      variant: 'subtle',
      content: `
        This will unblock the user and allow them to log in again. Their forms will remain in draft status.
        <div class="mt-2">
          <b>Blocked on:</b> ${new Date(props.user.blocked_at).toLocaleString()}
          <br>
          <b>Blocked by:</b> ${blockedBy}
          <br>
          <b>Reason:</b> ${lastBlock.value?.reason || ''}
        </div>
      `
    }
  } else {
    return {
      icon: 'i-heroicons-exclamation-triangle',
      color: 'warning',
      variant: 'subtle',
      content: 'This will block the user from accessing their account and set all their forms to draft.'
    }
  }
})


async function submit() {
  try {
    let response
    if (isBlocked.value) {
      response = await form.post('/moderator/unblock-user')
    } else {
      response = await form.post('/moderator/block-user')
    }
    alert.success(response.message)
    emit('user-updated', response.user)
    form.reset()
  } catch (error) {
    alert.error(error.data?.message || 'An error occurred.')
  }
}
</script> 