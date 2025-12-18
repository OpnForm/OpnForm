<template>
  <div class="p-4">
    <div
      v-if="userInfo"
      class="flex gap-2 items-center flex-wrap"
    >
      <UButton
        icon="i-heroicons-arrow-left"
        variant="ghost"
        color="gray"
        @click="clearUser"
      />
      <h1 class="text-xl font-semibold">
        {{ userInfo.name }}
      </h1>
      <UBadge
        color="neutral"
        variant="subtle"
        class="select-all"
      >
        {{ userInfo.id }}
      </UBadge>
      <UBadge
        color="neutral"
        variant="subtle"
        class="select-all"
      >
        {{ userInfo.email }}
      </UBadge>
      <UButton
        v-if="userInfo.stripe_id"
        :to="'https://dashboard.stripe.com/customers/'+userInfo.stripe_id"
        target="_blank"
        color="purple"
        variant="subtle"
        size="xs"
        class="select-all"
        :label="userInfo.stripe_id"
        icon="i-bxl-stripe"
      />
      <UBadge
        v-if="userPlan"
        v-bind="userPlanBadgeProps"
        class="capitalize"
      >
        {{ userPlan }}
      </UBadge>
    </div>
    <h3
      v-else
      class="font-semibold text-2xl text-neutral-900 mb-4"
    >
      Admin settings
    </h3>


    <template v-if="!userInfo">
      <VForm
        size="sm"
        class="pb-8 max-w-lg"
        @submit.prevent="fetchUser"
      >
        <TextInput
          name="identifier"
          :form="fetchUserForm"
          label="Identifier"
          :required="true"
          help="User Id, User Email, Form Slug or View Slug"
        />
        <UButton
          type="submit"
          :loading="loading"
          block
          class="mt-4"
          label="Fetch User"
        />
      </VForm>

      <VForm
        size="sm"
        class="pb-8 max-w-lg"
        @submit.prevent="createTemplate"
      >
        <TextAreaInput
          name="template_prompt"
          :form="createTemplateForm"
          label="Template Description"
          :required="true"
          help="Describe the template you want to create"
        />
        <UButton
          type="submit"
          :loading="templateLoading"
          block
          class="mt-4"
          label="Create Template"
        />
      </VForm>
    </template>

    <div
      v-else
      class="flex flex-col"
    >
      <div
        id="admin-buttons"
        class="flex gap-1 my-4"
      >
        <ImpersonateUser :user="userInfo" />
        <SendPasswordResetEmail :user="userInfo" />
        <ClarityLink :user="userInfo" />
      </div>
      <div
        class="w-full grid gap-2 grid-cols-1 lg:grid-cols-2"
      >
        <DiscountOnSubscription
          :user="userInfo"
        />
        <BlockUser
          :user="userInfo"
          @user-updated="onUserUpdate"
        />
        <TwoFactorDisable
          :user="userInfo"
          @user-updated="onUserUpdate"
        />
        <ExtendTrial
          :user="userInfo"
        />
        <BillingEmail
          :user="userInfo"
        />
        <UserWorkspaces
          :user="userInfo"
        />
        <UserSubscriptions
          :user="userInfo"
          class="lg:col-span-2"
        />
        <UserPayments
          :user="userInfo"
          class="lg:col-span-2"
        />
        <DeletedForms
          :user="userInfo"
          class="lg:col-span-2"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue'
import { adminApi } from '~/api'

definePageMeta({
  middleware: 'moderator',
  layout: 'dashboard'
})

useOpnSeoMeta({
  title: 'Admin'
})

const alert = useAlert()
const router = useRouter()

const userInfo = ref(null)
const userPlan = ref('free')
const fetchUserForm = useForm({
  identifier: ''
})
const createTemplateForm = useForm({
  template_prompt: ''
})
const loading = ref(false)
const templateLoading = ref(false)

const userPlanBadgeProps = computed(() => {
  switch (userPlan.value) {
    case 'pro':
      return { color: 'success', variant: 'subtle' }
    case 'enterprise':
      return { color: 'primary', variant: 'subtle' }
    default:
      return { color: 'neutral', variant: 'subtle' }
  }
})

onMounted(() => {
  // Shortcut link to impersonate users
  const urlSearchParams = new URLSearchParams(window.location.search)
  const params = Object.fromEntries(urlSearchParams.entries())
  if (params.impersonate) {
    fetchUserForm.identifier = params.impersonate
  }
  if (params.user_id) {
    fetchUserForm.identifier = params.user_id
  }
  if (fetchUserForm.identifier) {
    fetchUser()
  }
})

async function fetchUser() {
  if (!fetchUserForm.identifier) {
    alert.error('Identifier is required.')
    return
  }

  loading.value = true
      adminApi.fetchUser(fetchUserForm.identifier).then(async (data) => {
    loading.value = false
    userInfo.value = { ...data.user, workspaces: data.workspaces }
    getUserPlan(data.workspaces)
    alert.success(`User Fetched: ${userInfo.value.name}`)
  })
    .catch((error) => {
      alert.error(error.data.message)
      loading.value = false
    })
}

function onUserUpdate(updatedUser) {
  userInfo.value = updatedUser
}
function getUserPlan(workspaces) {
  if (workspaces.some(w => w.plan === 'enterprise')) {
    userPlan.value = 'enterprise'
  } else if (workspaces.some(w => w.plan === 'pro')) {
    userPlan.value = 'pro'
  } else {
    userPlan.value = 'free'
  }
}

function clearUser() {
  userInfo.value = null
  userPlan.value = 'free'
  fetchUserForm.reset()
}

async function createTemplate() {
  if (!createTemplateForm.template_prompt) {
    alert.error('Template prompt is required.')
    return
  }

  templateLoading.value = true
  adminApi.createTemplate({
    template_prompt: createTemplateForm.template_prompt
  }).then((data) => {
    templateLoading.value = false
    createTemplateForm.reset()
    alert.success('Template created.')
    router.push({ name: 'templates-slug', params: { slug: data.template_slug } })
  })
    .catch((error) => {
      templateLoading.value = false
      alert.error(error.data.message)
    })
}
</script>
