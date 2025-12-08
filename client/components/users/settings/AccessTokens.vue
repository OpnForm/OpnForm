<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col flex-wrap items-start justify-between gap-4 sm:flex-row sm:items-center">
      <div>
        <h3 class="text-lg font-medium text-neutral-900">API Access Tokens</h3>
        <p class="mt-1 text-sm text-neutral-500">
          Manage your API access tokens for programmatic access.
        </p>
      </div>

      <div class="flex shrink-0 items-center gap-2">
        <UButton
          label="API Docs"
          icon="i-heroicons-book-open"
          variant="outline"
          color="primary"
          :to="opnformConfig.links.api_docs"
          target="_blank"
        />

        <UButton
          label="Create New Token"
          icon="i-heroicons-plus"
          :loading="loading"
          @click="accessTokenModal = true"
        />
      </div>
    </div>

    <!-- Tokens List -->
    <div class="space-y-4">
      <div v-if="tokens?.length === 0 && !loading" class="text-center py-12">
        <UIcon 
          name="i-heroicons-key" 
          class="w-12 h-12 text-neutral-400 mx-auto mb-4" 
        />
        <h4 class="text-lg font-medium text-neutral-900 mb-2">
          No access tokens yet
        </h4>
        <p class="text-neutral-500 mb-4">
          Create your first API access token to start using our API programmatically.
        </p>
        <UButton
          label="Create Your First Token"
          icon="i-heroicons-plus"
          @click="accessTokenModal = true"
        />
      </div>

      <UTable 
        v-if="tokens?.length > 0"
        v-model:column-pinning="columnPinning"
        :data="tokens" 
        :columns="tableColumns"
        :loading="loading"
        class="w-full"
      >
        <template #name-cell="{ row: { original: item } }">
          <span class="font-semibold">{{ item.name }}</span>
        </template>

        <template #abilities-cell="{ row: { original: item } }">
          <AbilitiesBadges :abilities="item.abilities" />
        </template>

        <template #actions-cell="{ row: { original: item } }">
          <div class="flex justify-end">
            <UButton 
              color="error" 
              variant="soft"
              icon="i-heroicons-trash"
              square
              size="sm"
              @click="deleteToken(item)"
            />
          </div>
        </template>
      </UTable>
    </div>

    <!-- API Information -->
    <div class="space-y-4 pt-8 border-t border-neutral-200">
      <div>
        <h3 class="text-lg font-medium text-neutral-900">API Information</h3>
        <p class="text-sm text-neutral-500 mt-1">
          Learn how to use your access tokens with our API.
        </p>
      </div>

      <div class="bg-neutral-50 border border-neutral-200 rounded-lg p-4">
        <div class="space-y-3">
          <div class="flex items-start gap-3">
            <UIcon name="i-heroicons-information-circle" class="w-5 h-5 text-blue-500 mt-0.5 flex-shrink-0" />
            <div>
              <h4 class="text-sm font-medium text-neutral-900">Getting Started</h4>
              <p class="text-sm text-neutral-600 mt-1">
                Use your access tokens in the Authorization header: <code class="bg-neutral-200 px-1 rounded text-xs">Bearer YOUR_TOKEN</code>
              </p>
            </div>
          </div>
          
          <div class="flex items-start gap-3">
            <UIcon name="i-heroicons-shield-check" class="w-5 h-5 text-green-500 mt-0.5 flex-shrink-0" />
            <div>
              <h4 class="text-sm font-medium text-neutral-900">Security</h4>
              <p class="text-sm text-neutral-600 mt-1">
                Keep your tokens secure and never share them publicly. Rotate them regularly for better security.
              </p>
            </div>
          </div>
          
          <div class="flex items-start gap-3">
            <UIcon name="i-heroicons-clock" class="w-5 h-5 text-yellow-500 mt-0.5 flex-shrink-0" />
            <div>
              <h4 class="text-sm font-medium text-neutral-900">Rate Limits</h4>
              <p class="text-sm text-neutral-600 mt-1">
                API requests are rate limited. <a href="https://docs.opnform.com/api-reference/introduction#rate-limits" target="_blank" class="text-blue-500 hover:underline">Check our documentation</a> for current limits and best practices.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Access Token Modal -->
    <UsersSettingsAccessTokenModal
      v-model="accessTokenModal"
      @close="accessTokenModal = false"
    />
  </div>
</template>

<script setup>
import opnformConfig from '~/opnform.config.js'
import AbilitiesBadges from '~/components/users/settings/access-tokens/AbilitiesBadges.vue'

const accessTokenModal = ref(false)
const alert = useAlert()

// Use TanStack Query instead of Pinia store
const { list, remove: removeToken } = useTokens()

// Fetch tokens
const { data: tokens, isLoading: loading } = list({})

// Delete token mutation
const deleteTokenMutation = removeToken()

// Column pinning state
const columnPinning = ref({
  left: [],
  right: ['actions']
})

// Table columns configuration
const tableColumns = [
  {
    id: 'name',
    accessorKey: 'name',
    header: 'Name',
    enableSorting: true
  },
  {
    id: 'abilities',
    accessorKey: 'abilities',
    header: 'Abilities',
    enableSorting: false
  },
  {
    id: 'actions',
    header: '',
    enableSorting: false,
    enableHiding: false
  }
]

const deleteToken = (token) => {
  alert.confirm("Do you really want to delete this token?", () => {
    deleteTokenMutation.mutateAsync(token.id).then(() => {
      alert.success("Token deleted successfully")
    }).catch(() => {
      alert.error("An error occurred while deleting the token")
    })
  })
}
</script> 