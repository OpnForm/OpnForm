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
      v-if="!workspace.is_pro"
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
          :disabled="!workspace.is_pro"
          :variant="workspace.is_pro ? 'outline' : 'subtle'"
          placeholder="yourdomain.com"
          class="flex-1"
          @keydown.enter.prevent="addDomain"
        />
        <UButton
          :disabled="!workspace.is_pro || !newDomain.trim()"
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
            :disabled="!workspace.is_pro"
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
    </div>
    
    <UButton
      type="submit"
      :loading="isLoading"
      :disabled="!workspace.is_pro || !isChanged"
      @click="saveChanges"
    >
      Save Domain(s)
    </UButton>
  
  </div>
</template>

<script setup>
const { updateCustomDomains } = useWorkspaces()
const alert = useAlert()
const crisp = useCrisp()
const { current: workspace } = useCurrentWorkspace()

const { openSubscriptionModal } = useAppModals()

const newDomain = ref('')
const domains = ref([])
const isLoading = ref(false)
const isChanged = ref(false)

onMounted(() => {
  initCustomDomains()
})

watch(
  () => workspace.value,
  () => {
    initCustomDomains()
  },
  { deep: true },
)

const addDomain = () => {
  const domainToAdd = newDomain.value.trim()
  if (domainToAdd) {
    // Remove protocol and path to get the clean domain
    const cleanedDomain = domainToAdd
      .replace(/^https?:\/\//i, '')
      .split('/')[0]

    // Domain validation - matches backend regex: /^[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,20}$/
    // Supports: example.com, test.co.uk, subdomain.example.co.uk, etc.
    const domainRegex = /^[a-z0-9]+([-.][a-z0-9]+)*\.[a-z]{2,20}$/i
    if (!domainRegex.test(cleanedDomain)) {
      return alert.error('Invalid domain format. Please use a format like "domain.com" or "subdomain.example.co.uk".')
    }

    if (domains.value.includes(cleanedDomain)) {
      return alert.info('Domain already in the list.')
    }

    domains.value.push(cleanedDomain)
    newDomain.value = ''
    isChanged.value = true
  }
}

const removeDomain = (index) => {
  domains.value.splice(index, 1)
  isChanged.value = true
}

const updateMutation = updateCustomDomains(workspace.value?.id)

const saveChanges = () => {
  if (!workspace.value?.id) return
  
  isLoading.value = true
  updateMutation.mutateAsync({
    custom_domains: domains.value,
  }).then(() => {
      alert.success('Custom domains saved.')
      isChanged.value = false
      isLoading.value = false
  }).catch((error) => {
      alert.error(error.response?._data?.message ?? 'Failed to update custom domains')
      isLoading.value = false
    })
}

const initCustomDomains = () => {
  if (workspace.value?.custom_domains) {
    domains.value = [...workspace.value.custom_domains]
  } else {
    domains.value = []
  }
  isChanged.value = false
}
</script> 