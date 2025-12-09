<template>
  <VForm size="md"
    method="POST"
    :form="form"
    @submit.prevent="register"
  >
    <!-- Name -->
    <text-input
      name="name"
      label="Name"
      placeholder="Your name"
      required
    />

    <!-- Email -->
    <text-input
      name="email"
      label="Email"
      required
      :disabled="disableEmail"
      placeholder="Your email address"
    />

    <select-input
      v-if="!disableEmail && !isSetup"
      name="hear_about_us"
      :options="hearAboutUsOptions"
      placeholder="Select option"
      label="How did you hear about us?"
      required
    />

    <!-- Password -->
    <text-input
      native-type="password"
      placeholder="Enter password"
      name="password"
      label="Password"
      required
      @focus="isPasswordFocused = true"
      @blur="isPasswordFocused = false"
    />
    <PasswordStrengthIndicator 
      v-show="isPasswordFocused" 
      :password="form.password" 
    />

    <!-- Password Confirmation-->
    <text-input
      native-type="password"
      required
      placeholder="Enter confirm password"
      name="password_confirmation"
      label="Confirm Password"
    />

    <!-- Captcha -->
    <div
      v-if="reCaptchaSiteKey"
      class="my-2 px-2 mx-auto w-max"
    >
      <CaptchaInput
        ref="captcha"
        provider="recaptcha"
        :form="form"
        language="en"
      />
    </div>

    <checkbox-input
      name="agree_terms"
      class="my-2"
      required
      v-if="!useFeatureFlag('self_hosted')"
    >
      <template #label>
        <label for="agree_terms">
          I agree with the
          <NuxtLink
            :to="{ name: 'terms-conditions' }"
            target="_blank"
            class="underline"
          >
            Terms and conditions
          </NuxtLink>
          and
          <NuxtLink
            :to="{ name: 'privacy-policy' }"
            target="_blank"
            class="underline"
          >
            Privacy policy
          </NuxtLink>
          of the website and I accept them.
        </label>
      </template>
    </checkbox-input>

    <!-- Submit Button -->
    <UButton
      class="mt-2"
      block
      size="lg"
      :loading="form.busy"
      type="submit"
      label="Create account"
    />

    <template v-if="useFeatureFlag('services.google.auth') && !useFeatureFlag('self_hosted') && !isSetup">
      <p class="text-neutral-500 text-sm text-center my-2">
        OR
      </p>
      <UButton
        color="neutral"
        variant="outline"
        size="lg"
        class="space-x-4 flex items-center"
        block
        :loading="false"
        @click.prevent="signInwithGoogle"
        icon="devicon:google"
        label="Sign in with Google"
      />
    </template>

    <p v-if="!isSetup" class="text-neutral-500 mt-2 text-sm text-center">
      Already have an account?
      <a
        v-if="isQuick"
        href="#"
        class="font-medium ml-1"
        @click.prevent="$emit('openLogin')"
      >Log In</a>
      <NuxtLink
        v-else
        :to="{ name: 'login' }"
        class="font-semibold ml-1"
      >
        Log In
      </NuxtLink>
    </p>
  </VForm>

  <!-- Google One Tap -->
  <ClientOnly>
    <GoogleOneTap v-if="!isSetup" context="signup" />
  </ClientOnly>
</template>

<script setup>
import GoogleOneTap from "~/components/vendor/GoogleOneTap.vue"
import { WindowMessageTypes } from "~/composables/useWindowMessage"

// Props
const props = defineProps({
  isQuick: {
    type: Boolean,
    required: false,
    default: false,
  },
  isSetup: {
    type: Boolean,
    required: false,
    default: false,
  },
})

// Emits
const emit = defineEmits(['openLogin', 'registered'])

// Composables
const { $utm } = useNuxtApp()
const oAuth = useOAuth()
const runtimeConfig = useRuntimeConfig()
const { register: registerMutationFactory } = useAuth()
const router = useRouter()
const route = useRoute()

const registerMutation = registerMutationFactory()

// Reactive data
const form = useForm({
  name: "",
  email: "",
  hear_about_us: "",
  password: "",
  password_confirmation: "",
  agree_terms: false,
  appsumo_license: null,
  utm_data: null,
  'g-recaptcha-response': null
})

const disableEmail = ref(false)
const captcha = ref(null)

// Password field focus state
const isPasswordFocused = ref(false)

// Computed
const reCaptchaSiteKey = computed(() => {
  return runtimeConfig.public.reCaptchaSiteKey
})

const hearAboutUsOptions = computed(() => {
  const options = [
    {name: "Facebook", value: "facebook"},
    {name: "Twitter", value: "twitter"},
    {name: "Reddit", value: "reddit"},
    {name: "Github", value: "github"},
    {
      name: "Search Engine (Google, DuckDuckGo...)",
      value: "search_engine",
    },
    {name: "Friend or Colleague", value: "friend_colleague"},
    {name: "Blog/Article", value: "blog_article"},
  ]
    .map((value) => ({value, sort: Math.random()}))
    .sort((a, b) => a.sort - b.sort)
    .map(({value}) => value)
  options.push({name: "Other", value: "other"})
  return options
})

// Lifecycle
onMounted(() => {
  // Use the window message composable
  const windowMessage = useWindowMessage(WindowMessageTypes.LOGIN_COMPLETE)
  
  // Listen for login complete messages
  windowMessage.listen(() => {
    redirect()
  })

  // Set appsumo license
  if (
    route.query.appsumo_license !== undefined &&
    route.query.appsumo_license
  ) {
    form.appsumo_license = route.query.appsumo_license
  }

  if (route.query?.invite_token) {
    if (route.query?.email) {
      form.email = route.query?.email
      form.hear_about_us = 'invite'
      disableEmail.value = true
    }
    form.invite_token = route.query?.invite_token
  }

  // Set default hear_about_us for setup mode
  if (props.isSetup) {
    form.hear_about_us = 'setup'
  }
})

// Methods
const register = () => {
  form.utm_data = {...$utm.value}
  $utm.value = null
  
  form.mutate(registerMutation, {
    data: {
      formData: form.data(),
      source: form.hear_about_us
    }
  }).then(() => {
    if (props.isSetup) {
      // In setup mode, emit registered event instead of showing alert/redirecting
      emit('registered')
    } else {
    useAlert().success({
      title: "Welcome to OpnForm 👋",
      ...!props.isQuick ? {description: "Time to create your first form!"} : {}
    })
    redirect()
    }
  }).catch((err) => {
    console.error(err)
    useAlert().error(err.response?._data?.message ?? "Something went wrong. Please try again.")
  }).finally(() => {
    // Reset captcha after submission
    if (import.meta.client && reCaptchaSiteKey.value) {
      captcha.value.reset()
    }
  })
}

const redirect = () => {
  if (props.isQuick) {
    // Use window message instead of event
    const afterLoginMessage = useWindowMessage(WindowMessageTypes.AFTER_LOGIN)
    afterLoginMessage.send(window)
  } else {
    // If is invite just redirect to home
    if (form.invite_token) {
      useAlert().success("You have successfully accepted the invite and joined this workspace.")
      router.push({name: "home"})
    } else {
      router.push({name: "forms-create"})
    }
  }
}

const showOAuthError = (error) => {
  if (error.response?.status === 422 && error.response?.data?.message) {
    useAlert().error(error.response.data.message)
  } else {
    useAlert().error("Sign-in failed. Please try again.")
  }
}

const signInwithGoogle = () => {
  try {
    // guestConnect now always captures and sends utm_data
    const additionalData = {
      ...(form.invite_token ? { invite_token: form.invite_token } : {})
    }
    oAuth.guestConnect('google', true, additionalData)
  } catch (error) {
    showOAuthError(error)
  }
}
</script>
