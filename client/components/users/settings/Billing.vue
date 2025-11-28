<template>
  <div class="space-y-4">
    <!-- Header -->
    <div class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
      <div>
        <h3 class="text-lg font-medium text-neutral-900">Billing Details</h3>
        <p class="mt-1 text-sm text-neutral-500">
          Manage your billing. Download invoices, update your plan, or cancel it at any time.
        </p>
      </div>
    </div>

    <!-- Billing Management -->
    <template v-if="user.has_customer_id">
      <div class="space-y-4">
        <p
          v-if="usersCount"
          class="text-neutral-600"
        >
          You currently have <span class="font-medium">{{ usersCount }} users</span> in your different workspaces.
        </p>

        <div class="flex flex-wrap gap-3">
          <UButton
            icon="i-heroicons-credit-card"
            :loading="billingLoading"
            :to="{ name: 'redirect-billing-portal' }"
            target="_blank"
          >

            Billing & Invoices
          </UButton>
        </div>
      </div>
    </template>

    <!-- AppSumo Billing -->
    <AppSumoBilling />
  </div>
</template>

<script setup>
import AppSumoBilling from "../../vendor/appsumo/AppSumoBilling.vue"
import { billingApi } from '~/api'

const alert = useAlert()

const { data: user } = useAuth().user()
const billingLoading = ref(false)
const usersCount = ref(0)

onMounted(() => {
  loadUsersCount()
})

const loadUsersCount = () => {
      billingApi.getUsersCount()
    .then((data) => {
      usersCount.value = data.count
    })
    .catch((error) => {
      alert.error(error.data.message)
    })
}

</script> 