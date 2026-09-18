<template>
  <UModal
    v-model:open="isOpen"
    @close="closeModal"
  >
    <template #header>
      <div class="flex items-center w-full gap-4 px-2">
        <h2 class="font-semibold">
          Create an access token
        </h2>
      </div>
      <UButton
        color="neutral"
        variant="outline"
        icon="i-heroicons-question-mark-circle"
        size="sm"
        @click="crisp.openHelpdesk()"
      >
        Help
      </UButton>
    </template>
 
    <template #body>
      <template v-if="token">
        <UAlert
          icon="i-heroicons-key-20-solid"
          color="success"
          variant="subtle"
          title="Copy your access token"
          description="Your token will only be shown once. Make sure to save it safely."
        />
        <CopyContent
          class="mt-4"
          :content="token"
          label="Copy Token"
        />
      </template>

      <VForm v-else size="sm">
        <form
          @submit.prevent="createToken"
        >
          <div v-if="!token">
            <TextInput
              :form="tokenForm"
              name="name"
              :required="true"
              label="Name"
            />

            <UFormField v-if="hasAdminAbilities" label="Expires in days" :error="tokenForm.errors.get('expires_at')">
              <UInput v-model="expiryDays" type="number" min="1" max="90" />
            </UFormField>
            <FlatSelectInput
              :form="tokenForm"
              name="abilities"
              label="Abilities"
              :options="abilitiesOptions"
              multiple
            />
          </div>
        </form>
      </VForm>
    </template>

    <template #footer>
      <UButton
        color="neutral"
        variant="outline"
        @click="closeModal"
      >
        Close
      </UButton>
      <UButton
        v-if="!token"
        type="submit"
        block
        size="lg"
        :loading="tokenForm.busy"
        @click="createToken"
      >
        Create Token
      </UButton>
    </template>
  </UModal>
</template>

<script setup>
import { useQuery } from '@tanstack/vue-query'
import { tokensApi } from '~/api/tokens'
import CopyContent from "~/components/open/forms/components/CopyContent.vue"

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['close'])

const { abilities, create } = useTokens()
const alert = useAlert()
const crisp = useCrisp()

const { data: adminAbilities } = useQuery({ queryKey: ['tokens', 'abilities'], queryFn: tokensApi.abilities })
const abilitiesOptions = computed(() => [...abilities, ...(adminAbilities.value ?? []).map(name => ({ name, title: name.replace('admin:', 'Admin – ').replaceAll(':', ' – ').replaceAll('-', ' ') }))].map(ability => ({
  name: ability.title,
  value: ability.name
})))

const token = ref('')
const expiryDays = ref(30)
const tokenForm = useForm({
  name: "",
  abilities: abilities.map(ability => ability.name),
  expires_at: "",
})
const hasAdminAbilities = computed(() => tokenForm.abilities.some(ability => ability.startsWith('admin:')))

// Create token mutation
const createTokenMutation = create()

// Modal state
const isOpen = computed({
  get: () => props.modelValue,
  set: (value) => emit('close', value)
})

// Methods
const closeModal = () => {
  tokenForm.reset()
  expiryDays.value = 30
  token.value = ''
  isOpen.value = false
}

function createToken() {
  const days = Number(expiryDays.value)
  if (hasAdminAbilities.value && (!Number.isInteger(days) || days < 1 || days > 90)) {
    tokenForm.errors.set('expires_at', 'Choose an expiration between 1 and 90 days.')
    return
  }
  tokenForm.expires_at = hasAdminAbilities.value ? new Date(Date.now() + days * 86400000).toISOString() : null
  tokenForm.mutate(createTokenMutation).then((response) => {
    // Assuming the response contains the token
    token.value = response.token || response.data?.token || response
  }).catch(() => {
    alert.error("An error occurred while creating the token")
  })
}
</script>
