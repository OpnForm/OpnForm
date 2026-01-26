<template>
  <UModal v-model:open="isOpen" @close="handleCancel">
    <template #header>
      <h3 class="text-lg font-semibold">{{ modalTitle }}</h3>
    </template>

    <template #body>
      <div class="flex justify-center">
        <VForm :form="form" size="sm" @submit.prevent="handleSave" class="w-full max-w-md space-y-6">
          <div class="space-y-4 rounded-xl border border-neutral-200 bg-neutral-50 p-4 text-sm text-neutral-600">
            <p class="text-xs uppercase tracking-wide text-neutral-500">General</p>
            <TextInput
              name="name"
              :form="form"
              label="Connection Name"
              :required="true"
              placeholder="Company SSO"
            />

            <TextInput
              name="slug"
              :form="form"
              label="Slug"
              :required="true"
              placeholder="company-sso"
              help="Lowercase, hyphens-only identifier that shows up in the redirect URLs."
            />

            <TextInput
              name="domain"
              :form="form"
              label="Email Domain"
              :required="true"
              :placeholder="userEmailDomain || 'example.com'"
            >
              <template #help>
                <div class="space-y-1">
                  <p v-if="userEmailDomain" class="text-xs text-neutral-500">
                    Must match your email domain ('{{ userEmailDomain }}' or another subdomain of it).
                  </p>
                  <p v-else class="text-xs text-neutral-500">
                    Must match your email domain (e.g., if your email is user@company.com, use 'company.com' or another subdomain of it).
                  </p>
                  <p class="text-xs text-neutral-500">
                    Common email providers are not allowed.
                    <button
                      type="button"
                      class="text-blue-600 hover:text-blue-700 underline"
                      @click="openSupportChat"
                    >
                      Contact support
                    </button>
                    if you need help.
                  </p>
                </div>
              </template>
            </TextInput>
          </div>

          <div class="space-y-4 rounded-xl border border-neutral-200 bg-neutral-50 p-4 text-sm text-neutral-600">
            <p class="text-xs uppercase tracking-wide text-neutral-500">Security</p>
            
            <!-- Redirect URI Display -->
            <div v-if="redirectUri" class="rounded-lg border border-blue-200 bg-blue-50 p-3">
              <p class="text-xs font-medium text-blue-900 mb-1">Redirect URI</p>
              <p class="text-xs text-blue-700 mb-2">
                Copy this URL and add it to your OIDC provider's allowed redirect URIs:
              </p>
              <CopyContent
                :content="redirectUri"
                label="Copy"
              />
            </div>

            <TextInput
              name="issuer"
              :form="form"
              label="Issuer URL"
              :required="true"
              placeholder="https://idp.example.com"
              help="Issued by your OIDC provider. Must match the redirect registered in that provider."
            />

            <TextInput
              name="client_id"
              :form="form"
              label="Client ID"
              :required="true"
              placeholder="your-client-id"
            />

            <TextInput
              name="client_secret"
              :form="form"
              label="Client Secret"
              :required="true"
              type="password"
              placeholder="your-client-secret"
            />

            <ToggleSwitchInput
              name="options.require_state"
              :form="form"
              label="Require state parameter"
              help="Recommended: validates the state parameter on callback. Disable only if your IdP cannot send state."
            />

            <ToggleSwitchInput
              name="enabled"
              class="mt-2"
              :form="form"
              label="Enabled"
            />
          </div>

          <Collapse v-model="showFieldMappings" class="rounded-xl border border-neutral-200 bg-neutral-50 p-4 text-sm text-neutral-600">
            <template #title>
              <p class="text-xs uppercase tracking-wide text-neutral-500">Field Mappings</p>
            </template>
            <div class="space-y-4 pt-2">
              <p class="text-xs text-neutral-500">
                Map IdP claim field names to OpnForm fields. Use this if your IdP uses different field names (e.g., "preferred_username" for email).
              </p>
              
              <div class="space-y-2">
                <TextInput
                  name="options.field_mappings.email"
                  :form="form"
                  label="Email Field"
                  placeholder="email"
                  help="IdP claim name for email (e.g., 'email', 'preferred_username', 'mail'). Leave empty to use default 'email'."
                  size="sm"
                />
                <TextInput
                  name="options.field_mappings.name"
                  :form="form"
                  label="Name Field"
                  placeholder="name"
                  help="IdP claim name for full name (e.g., 'name', 'display_name'). Leave empty to use default 'name'."
                  size="sm"
                />
              </div>
            </div>
          </Collapse>

          <Collapse v-model="showRoleMapping" class="rounded-xl border border-neutral-200 bg-neutral-50 p-4 text-sm text-neutral-600">
            <template #title>
              <p class="text-xs uppercase tracking-wide text-neutral-500">Role Mapping</p>
            </template>
            <div class="space-y-4 pt-2">
              <p class="text-xs text-neutral-500">
                Map IdP groups to workspace roles. Users will be assigned the highest privilege role from their groups.
              </p>
              
              <div v-if="roleMappings.length === 0" class="text-center py-4 text-sm text-neutral-400">
                No role mappings configured. Users will be assigned the default "member" role.
              </div>
              
              <div v-else class="space-y-2">
                <div
                  v-for="(mapping, index) in roleMappings"
                  :key="index"
                  class="flex items-start gap-2 p-2 rounded-md border border-neutral-200 bg-white"
                >
                  <div class="flex-1 grid grid-cols-2 gap-2">
                    <TextInput
                      :name="`options.group_role_mappings.${index}.idp_group`"
                      :form="form"
                      label="IdP Group"
                      placeholder="opnform_admins"
                      :required="true"
                      size="sm"
                    />
                    <SelectInput
                      :name="`options.group_role_mappings.${index}.role`"
                      :form="form"
                      label="Role"
                      :required="true"
                      size="sm"
                      :options="roleOptions"
                    />
                  </div>
                  <UButton
                    size="xs"
                    variant="ghost"
                    color="red"
                    icon="i-heroicons-trash"
                    square
                    class="mt-6"
                    @click="removeRoleMapping(index)"
                  />
                </div>
              </div>
              
              <div v-if="showRoleMapping" class="flex justify-end">
                <UButton
                  size="xs"
                  variant="ghost"
                  icon="i-heroicons-plus"
                  @click="addRoleMapping"
                >
                  Add Mapping
                </UButton>
              </div>
            </div>
          </Collapse>
        </VForm>
      </div>
    </template>

    <template #footer>
      <div class="flex justify-between gap-2 w-full">
        <UButton variant="ghost" color="neutral" @click="handleCancel">Cancel</UButton>
        <UButton type="submit" :loading="isBusy" :disabled="isBusy" @click="handleSave">
          {{ actionLabel }}
        </UButton>
      </div>
    </template>
  </UModal>
</template>

<script setup>
import Collapse from '@/components/app/Collapse.vue'
import CopyContent from '@/components/open/forms/components/CopyContent.vue'
import { appUrl } from '~/lib/utils.js'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  form: { type: Object, required: true },
  connection: { type: Object, default: null },
  isBusy: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'save', 'cancel'])

const isEditing = computed(() => !!props.connection)
const modalTitle = computed(() => (isEditing.value ? 'Edit OIDC Connection' : 'Add OIDC Connection'))
const actionLabel = computed(() => (isEditing.value ? 'Update' : 'Create'))

const isOpen = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
})

// Get user email domain
const { data: user } = useAuth().user()
const userEmailDomain = computed(() => {
  if (!user.value?.email) return null
  const email = user.value.email
  const parts = email.split('@')
  return parts.length === 2 ? parts[1] : null
})

// Pre-fill domain field with user's email domain when creating new connection
watch([isOpen, userEmailDomain], ([isOpenVal, domain]) => {
  if (isOpenVal && !isEditing.value && domain && !props.form.domain) {
    props.form.domain = domain
  }
})

// Open Crisp chat for support
const openSupportChat = () => {
  useCrisp().openChat()
}

const roleOptions = [
  { value: 'member', name: 'Member' },
  { value: 'editor', name: 'Editor' },
  { value: 'admin', name: 'Admin' },
  { value: 'owner', name: 'Owner' },
]

const roleMappings = computed({
  get: () => {
    return props.form.options?.group_role_mappings || []
  },
  set: (value) => {
    if (!props.form.options) {
      props.form.options = {}
    }
    props.form.options.group_role_mappings = value
  }
})

const hasFieldMappings = computed(() => {
  const mappings = props.form.options?.field_mappings || {}
  return !!(mappings.email || mappings.name)
})

const showFieldMappings = ref(hasFieldMappings.value)

const showRoleMapping = ref(roleMappings.value.length > 0)

const redirectUri = computed(() => {
  const slug = props.form.slug
  if (!slug) return null
  
  // Use appUrl helper to construct the redirect URI
  return appUrl(`/auth/${slug}/callback`)
})

watch(hasFieldMappings, (newVal) => {
  if (!showFieldMappings.value && newVal) {
    showFieldMappings.value = true
  }
})

watch(roleMappings, (newVal) => {
  if (!showRoleMapping.value && newVal.length > 0) {
    showRoleMapping.value = true
  }
}, { deep: true })

const addRoleMapping = () => {
  const current = roleMappings.value || []
  roleMappings.value = [...current, { idp_group: '', role: 'member' }]
  if (!showRoleMapping.value) {
    showRoleMapping.value = true
  }
}

const removeRoleMapping = (index) => {
  const current = roleMappings.value || []
  roleMappings.value = current.filter((_, i) => i !== index)
}

const handleSave = () => emit('save')
const handleCancel = () => {
  emit('cancel')
  emit('update:modelValue', false)
}
</script>

