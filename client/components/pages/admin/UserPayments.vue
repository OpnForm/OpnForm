<template>
  <AdminCard
    v-if="props.user.stripe_id"
    title="Payments"
    icon="heroicons:currency-dollar-16-solid"
  >
    <UTable
      :loading="loading"
      :loading-state="{ icon: 'i-heroicons-arrow-path-20-solid', label: 'Loading...' }"
      :progress="{ color: 'primary', animation: 'carousel' }"
      :empty-state="{ icon: 'i-heroicons-circle-stack-20-solid', label: 'No items.' }"
      :columns="columns"
      :data="rows"
      class="-mx-6"
    >
      <template #id-cell="{ row }">
        <a
          :href="'https://dashboard.stripe.com/invoices/' + row.original.id"
          target="_blank"
          class="text-xs select-all bg-purple-50 border-purple-200 text-purple-500 rounded-md px-2 py-1 border"
        >
          <Icon
            name="bx:bxl-stripe"
            class="h-4 w-4 inline-block"
          />
          {{ row.original.id }}
        </a>
      </template>
      <template #amount_paid-cell="{ row }">
        <span class="font-semibold">${{ parseFloat(row.original.amount_paid / 100).toFixed(2) }}</span>
      </template>
      <template #status-cell="{ row }">
        <span
          class="text-xs select-all rounded-md px-2 py-1 border"
          :class="row.original.status == 'paid' ? 'bg-green-50 border-green-200 text-green-500' : 'bg-yellow-50 border-yellow-200 text-yellow-500'"
        >
          {{ row.original.status }}
        </span>
      </template>
      <template #actions-cell="{ row, index }">
        <UButton
          v-if="row.original.status == 'paid' && isLastPayment(row.original)"
          color="error"
          variant="outline"
          size="sm"
          icon="heroicons:arrow-uturn-left-16-solid"
          label="Refund"
          @click="openRefundModal(row.original)"
        />
      </template>
    </UTable>
    <div
      v-if="payments?.length > pageCount"
      class="flex justify-end px-3 py-3.5 border-t border-neutral-200 dark:border-neutral-700"
    >
      <UPagination
        v-model:page="page"
        :items-per-page="pageCount"
        :total="payments?.length"
      />
    </div>

    <UModal
      v-model:open="showRefundModal"
      :ui="{ content: 'sm:max-w-lg' }"
      title="Refund payment"
    >
      <template #body>
        <form @submit.prevent="askRefund">
            <p class="text-xs text-neutral-500">
              We will not update to user. Please inform them manually. <br/>
              After refund it will take some time to update this page.
            </p>
            <div class="mt-4">
              <TextInput
                name="refund_reason"
                :form="form"
                label="Refund reason"
                native-type="reason"
                :required="true"
                help="Please provide a clear reason for refunding this payment. This will be logged for future reference."
              />

              <UButton
                class="mt-4"
                :loading="form.busy"
                type="submit"
                block
                icon="heroicons:arrow-uturn-left-16-solid"
                label="Refund payment now"
              />
            </div>
          </form>
        </template>
    </UModal>
  </AdminCard>
</template>

<script setup>
import { adminApi } from '~/api'

const props = defineProps({
  user: {type: Object, required: true}
})

const alert = useAlert()
const loading = ref(true)
const payments = ref([])
const page = ref(1)
const pageCount = 5

const rows = computed(() => {
  return payments.value.slice((page.value - 1) * pageCount, (page.value) * pageCount)
})
onMounted(() => {
  getPayments()
})

const getPayments = () => {
  if (!props.user.stripe_id) return
  loading.value = true
  adminApi.billing.getPayments(props.user.id).then(data => {
    loading.value = false
    payments.value = data.payments
  }).catch(error => {
    useAlert().error(error.data.message)
    loading.value = false
  })
}

const isLastPayment = (payment) => {
  if (!payments.value || payments.value.length === 0) return false
  
  // Find the most recent payment by creation date
  const sortedPayments = [...payments.value].sort((a, b) => {
    return new Date(b.creation_date) - new Date(a.creation_date)
  })
  
  return sortedPayments[0].id === payment.id
}


const columns = [{
  accessorKey: 'id',
  header: 'ID'
}, {
  accessorKey: 'amount_paid',
  header: 'Amount paid',
  sortable: true
}, {
  accessorKey: 'name',
  header: 'Name',
  sortable: true
}, {
  accessorKey: 'status',
  header: 'Status',
  sortable: true
}, {
  accessorKey: 'creation_date',
  header: 'Creation date',
  sortable: true
}, {
  accessorKey: 'actions',
  header: '',
}]

const showRefundModal = ref(false)
const form = useForm({
  user_id: props.user.id,
  invoice_id: null,
  refund_reason: null
})

watch(showRefundModal, (value) => {
  if (!value) {
    form.invoice_id = null
    form.refund_reason = null
  }
})

const openRefundModal = (payment) => {
  form.invoice_id = payment.id
  showRefundModal.value = true
}

const askRefund = () => {
  alert.confirm('Are you sure? This will refund the payment for this user.', refundPayment)
}

const refundPayment = () => {
  if (!props.user.stripe_id) return
  form
    .patch('/moderator/refund-payment')
    .then(async (data) => {
      alert.success(data.message)
      showRefundModal.value = false
      getPayments()
    })
    .catch((error) => {
      alert.error(error.data.message)
    })
}
</script>
