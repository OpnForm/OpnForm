<template>
  <div class="space-y-4">
    <div class="flex flex-col flex-wrap items-start justify-between gap-4 sm:flex-row sm:items-center">
      <div>
        <h3 class="text-lg font-medium text-neutral-900">Custom Domains Settings</h3>
        <p class="mt-1 text-sm text-neutral-500">
          Manage your custom domains.
        </p>
      </div>

      <div class="flex shrink-0 items-center gap-2">
        <UButton
          label="Help"
          icon="i-heroicons-question-mark-circle"
          variant="outline"
          color="neutral"
          @click="crisp.openHelpdeskArticle('how-to-use-my-own-domain-9m77g7')"
        />
      </div>
    </div>

    <UAlert
      v-if="!canAccessDomains"
      icon="i-heroicons-user-group-20-solid"
      class="mb-4"
      color="warning"
      variant="subtle"
      title="Pro plan required"
      description="Please upgrade your account to setup a custom domain."
      :actions="[{
        label: 'Upgrade to Pro',
        color: 'warning',
        variant: 'solid',
        onClick: () => openSubscriptionModal({
          modal_title: 'Upgrade to use your own domain',
          modal_description: 'Upgrade to our Pro plan to unlock custom domains and other premium features such as advanced customization, forms analytics, integrations, and more!'
        })
      }]"
    />

    <div class="space-y-4">
      <div class="flex max-w-sm items-center gap-2">
        <UInput
          v-model="newDomain"
          :disabled="!canAccessDomains"
          :variant="canAccessDomains ? 'outline' : 'subtle'"
          placeholder="yourdomain.com"
          class="flex-1"
          @keydown.enter.prevent="addDomain"
        />
        <UButton
          :disabled="!canAccessDomains || !newDomain.trim() || isLoading"
          :loading="verificationMutation.isPending.value"
          icon="i-heroicons-plus"
          @click="addDomain"
        >
          Add
        </UButton>
      </div>

      <div v-if="domains.length > 0" class="max-w-sm space-y-2">
        <div
          v-for="(domain, index) in domains"
          :key="index"
          class="group flex items-center justify-between rounded-md border border-neutral-200 bg-white p-2"
        >
          <span class="text-sm text-neutral-800">{{ domain }}</span>
          <UButton
            :disabled="!canAccessDomains"
            icon="i-heroicons-x-mark"
            color="red"
            variant="ghost"
            class="opacity-0 group-hover:opacity-100"
            @click="removeDomain(index)"
          />
        </div>
      </div>
      <div v-else class="max-w-sm rounded-md border border-dashed border-neutral-300 p-4 text-center">
        <p class="text-sm text-neutral-500">
          No custom domains added yet.
        </p>
      </div>
      <div v-for="(record, domain) in verificationRecords" :key="domain" class="max-w-xl rounded-md border border-neutral-200 p-4 text-sm">
        <p class="font-medium">Verify ownership of {{ domain }}</p>
        <p class="mt-1 text-neutral-600">Add this TXT record in your DNS provider, wait for propagation, then save your domains. Keep your existing CNAME pointing to OpnForm.</p>
        <dl class="mt-3 space-y-2">
          <div><dt class="font-medium">TXT name / host</dt><dd class="break-all font-mono select-all">{{ record.host }}</dd></div>
          <div><dt class="font-medium">TXT value</dt><dd class="break-all font-mono select-all">{{ record.value }}</dd></div>
        </dl>
      </div>
    </div>
    
    <UButton
      type="submit"
      :loading="isLoading"
      :disabled="!canAccessDomains || !isChanged || verificationMutation.isPending.value"
      @click="saveChanges"
    >
      Save Domain(s)
    </UButton>
  
  </div>
</template>

<script setup>
const { updateCustomDomains, customDomainVerification } = useWorkspaces()
const alert = useAlert()
const crisp = useCrisp()
const { current: workspace } = useCurrentWorkspace()
const workspaceId = computed(() => workspace.value?.id)
const { openSubscriptionModal } = useAppModals()
const { hasFeature } = usePlanFeatures()
const canAccessDomains = computed(() => hasFeature('custom_domain'))

const newDomain = ref('')
const domains = ref([])
const verificationRecords = ref({})
const isLoading = ref(false)
const isChanged = ref(false)
const verificationMutation = customDomainVerification()
const updateMutation = updateCustomDomains(workspaceId)

const initCustomDomains = () => {
  domains.value = [...(workspace.value?.custom_domains ?? [])]
  verificationRecords.value = {}
  newDomain.value = ''
  isChanged.value = false
}

watch(() => [workspace.value?.id, workspace.value?.custom_domains], initCustomDomains, { immediate: true })

const showError = (error) => {
  const data = error.response?._data ?? error.data
  alert.error(data?.errors?.custom_domains?.[0] ?? data?.message ?? 'Failed to update custom domains')
}

const addDomain = () => {
  const domain = newDomain.value.trim().toLowerCase().replace(/^https?:\/\//i, '').split('/')[0]
  if (!domain || !workspaceId.value || verificationMutation.isPending.value || isLoading.value) return
  if (domains.value.includes(domain)) return alert.info('Domain already in the list.')
  const requestedWorkspaceId = workspaceId.value
  // Previously saved domains can be restored without changing their verification status.
  if (workspace.value.custom_domains?.includes(domain)) {
    domains.value.push(domain)
    newDomain.value = ''
    isChanged.value = true
    return
  }
  verificationMutation.mutateAsync({ workspaceId: requestedWorkspaceId, domain }).then((record) => {
    if (workspaceId.value !== requestedWorkspaceId) return
    domains.value.push(domain)
    verificationRecords.value[domain] = record
    newDomain.value = ''
    isChanged.value = true
  }).catch(showError)
}

const removeDomain = (index) => {
  delete verificationRecords.value[domains.value[index]]
  domains.value.splice(index, 1)
  isChanged.value = true
}

const saveChanges = () => {
  if (!workspaceId.value || isLoading.value) return
  isLoading.value = true
  updateMutation.mutateAsync({ workspaceId: workspaceId.value, custom_domains: [...domains.value] }).then(() => {
    alert.success('Custom domains saved.')
  }).catch(showError).finally(() => {
    isLoading.value = false
  })
}
</script>
