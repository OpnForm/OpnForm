<template>
  <UModal
    v-model:open="isOpen"
    :ui="{ content: 'sm:max-w-3xl' }"
    :dismissible="!aiForm.busy"
  >
    <template #content>
      <div class="overflow-hidden p-6">
        <SlidingTransition
          :style="transitionContainerStyle"
          direction="horizontal"
          :step="currentStep"
          :speed="transitionDurationMs"
        >
          <div
            :key="currentStep"
            class="w-full"
          >
            <!-- Step 1: Choose style -->
            <div
              v-if="currentStep === 1"
              key="step1"
              class="px-2"
              ref="step1Ref"
            >
              <div class="text-center mb-4">
                <h2 class="text-xl font-bold text-slate-800">Choose a form style</h2>
                <p class="text-slate-500 text-sm">Choose how your form appears to respondents.<br/>You can change this later.</p>
              </div>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div
                  role="button"
                  class="group rounded-md border p-6 flex flex-col items-center cursor-pointer hover:bg-neutral-50"
                  @click="selectStyle('classic')"
                >
                  <div class="p-4">
                    <Icon
                      name="opnform:form-style-classic"
                      mode="svg"
                      class="w-[140px] h-[100px] rounded-md shadow **:transition-colors duration-100 ease-out [--icon-fg:#737373] [--icon-muted:#D4D4D4] group-hover:[--icon-fg:#2563eb] group-hover:[--icon-muted:#93c5fd]"
                    />
                  </div>
                  <p class="font-medium">Classic</p>
                  <p class="text-xs text-neutral-500 text-center mt-1">Multiple inputs per page; supports layout blocks and multi-page flows.</p>
                </div>
                <div
                  role="button"
                  class="group rounded-md border p-6 flex flex-col items-center cursor-pointer hover:bg-neutral-50"
                  @click="selectStyle('focused')"
                >
                  <div class="p-4">
                    <Icon
                      name="opnform:form-style-focused"
                      mode="svg"
                      class="w-[140px] h-[100px] rounded-md shadow **:transition-colors duration-100 ease-out [--icon-fg:#737373] [--icon-muted:#D4D4D4] group-hover:[--icon-fg:#2563eb] group-hover:[--icon-muted:#93c5fd]"
                    />
                  </div>
                  <p class="font-medium">Focused</p>
                  <p class="text-xs text-neutral-500 text-center mt-1">Typeform-style: one question per step for a streamlined flow.</p>
                </div>
              </div>
            </div>

            <!-- Step 2: Choose base -->
            <div
              v-else-if="currentStep === 2"
              key="step2"
              class="px-2"
              ref="step1Ref"
            >
              <div class="text-center mb-4">
                <h2 class="text-xl font-bold text-slate-800">Choose a base for your form</h2>
              </div>
              <div class="flex gap-2 mb-2">
                <UButton
                  variant="ghost"
                  color="neutral"
                  icon="i-heroicons-arrow-left"
                  @click="goBackToStep1"
                  label="Back to styles"
                />
              </div>
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <TrackClick name="select_form_base" :properties="{ base: 'contact-form' }">
                  <div
                    role="button"
                    class="rounded-md border p-6 flex flex-col items-center cursor-pointer hover:bg-neutral-50"
                    @click="$emit('close')"
                  >
                    <div class="p-4">
                      <UIcon
                        name="i-heroicons-envelope"
                        class="w-8 h-8 text-blue-500"
                      />
                    </div>
                    <p class="font-medium">
                      Simple contact form
                    </p>
                  </div>
                </TrackClick>
                <TrackClick v-if="useFeatureFlag('ai_features')" name="select_form_base" :properties="{ base: 'ai' }">
                  <div
                    class="rounded-md border p-6 flex flex-col items-center cursor-pointer hover:bg-neutral-50"
                    role="button"
                    @click="currentStep = 3"
                  >
                    <div class="p-4">
                      <UIcon
                        name="i-heroicons-bolt"
                        class="w-8 h-8 text-blue-500"
                      />
                    </div>
                    <p class="font-medium text-blue-700">
                      AI Form Generator
                    </p>
                  </div>
                </TrackClick>
                <div
                  class="rounded-md border p-6 flex flex-col items-center cursor-pointer hover:bg-neutral-50 relative"
                >
                  <div class="p-4">
                    <UIcon
                      name="i-heroicons-squares-2x2"
                      class="w-8 h-8 text-blue-500"
                    />
                  </div>
                  <p class="font-medium">
                    Browse templates <Icon name="heroicons:arrow-top-right-on-square-20-solid" class="w-3 h-3 text-neutral-500" />
                  </p>
                  <TrackClick name="select_form_base" :properties="{ base: 'template' }">
                    <NuxtLink
                      :to="{ name: 'templates' }"
                      class="absolute inset-0"
                    />
                  </TrackClick>
                </div>
              </div>
            </div>

            <!-- Step 3: AI generation -->
            <div
              v-else-if="currentStep === 3"
              key="step3"
              class="px-2"
              ref="step1Ref"
            >
              <div class="text-center mb-4">
                <h2 class="text-xl font-bold text-slate-800">AI-powered form generator</h2>
              </div>
              <text-area-input
                label="Form Description"
                :disabled="loading ? true : null"
                :form="aiForm"
                name="form_prompt"
                help="Give us a description of the form you want to build (the more details the better)"
                placeholder="A simple contact form, with a name, email and message field"
              />
              <UButton
                class="mt-4"
                block
                :loading="loading"
                @click="generateForm"
                label="Generate a form"
              />
              <p class="text-neutral-500 text-xs text-center mt-1">
                ~30 sec
              </p>
              <div
                v-if="loading"
                class="my-4"
              >
                <AIFormLoadingMessages />
              </div>
              <div class="flex gap-2 mt-4">
                <UButton
                  variant="ghost"
                  color="neutral"
                  icon="i-heroicons-arrow-left"
                  @click="currentStep = 2"
                  label="Back to form types"
                />
              </div>
            </div>
          </div>
        </SlidingTransition>
      </div>
    </template>
  </UModal>
</template>

<script setup>
import SlidingTransition from '~/components/global/transitions/SlidingTransition.vue'
import AIFormLoadingMessages from "~/components/open/forms/components/AIFormLoadingMessages.vue"
import { formsApi } from "~/api/forms"
import { useElementSize } from '@vueuse/core'
import TrackClick from '~/components/global/TrackClick.vue'
import seedFocusedFirstBlockImage from '~/lib/forms/seed-focused-image'
import { ensureSettingsObject } from '~/composables/forms/initForm'

const props = defineProps({
  show: { type: Boolean, required: true },
})

const emit = defineEmits(["close", "form-generated"])

// Modal state
const isOpen = computed({
  get() {
    return props.show
  },
  set(value) {
    if (!value) {
      emit("close")
    }
  }
})

// Steps: 1) style, 2) base, 3) ai
const currentStep = ref(1)
const selectedStyle = ref('classic')

const aiForm = useForm({
  form_prompt: "",
})
const loading = ref(false)

const transitionDurationMs = 300
const step1Ref = ref(null)
const { height: step1Height } = useElementSize(step1Ref)
const cachedStep1Height = ref(0)
watchEffect(() => {
  if (step1Height?.value) {
    cachedStep1Height.value = step1Height.value
  }
})
const transitionContainerStyle = computed(() => {
  const h = cachedStep1Height.value
  return h ? { height: h + 'px' } : {}
})

watch(() => props.show, (open) => {
  if (open) {
    currentStep.value = 1
    selectedStyle.value = 'classic'
  }
})

function selectStyle(style) {
  selectedStyle.value = style
  // Apply immediately to working form
  const workingFormStore = useWorkingFormStore()
  if (workingFormStore?.content) {
    workingFormStore.content.presentation_style = style
    if (style === 'focused') {
      workingFormStore.content.size = 'lg'
      // Ensure settings object is initialized
      ensureSettingsObject(workingFormStore.content)
      // Enable navigation arrows by default in focused mode
      workingFormStore.content.settings.navigation_arrows = true
      // Seed first block image to highlight focused mode
      seedFocusedFirstBlockImage(workingFormStore.content)
    }
  }
  currentStep.value = 2
}

function goBackToStep1() {
  currentStep.value = 1
}

const generateForm = () => {
  if (loading.value) return

  loading.value = true
  aiForm
    .post("/forms/ai/generate", {
      body: {
        generation_params: { presentation_style: selectedStyle.value }
      }
    })
    .then((response) => {
      useAlert().success(response.message)
      fetchGeneratedForm(response.ai_form_completion_id)
    })
    .catch((error) => {
      console.error(error)
      loading.value = false
      currentStep.value = 3
    })
}

const fetchGeneratedForm = (generationId) => {
  // check every 4 seconds if form is generated
  setTimeout(() => {
    formsApi.ai.get(generationId)
      .then((data) => {
        if (data.ai_form_completion.status === "completed") {
          useAlert().success(data.message)
          const generated = JSON.parse(data.ai_form_completion.result)
          // Apply seeding based on user's style choice in the modal
          if (selectedStyle.value === 'focused') {
            seedFocusedFirstBlockImage(generated)
          }
          emit("form-generated", generated)
          emit("close")
        } else if (data.ai_form_completion.status === "failed") {
          useAlert().error("Something went wrong, please try again.")
          currentStep.value = 2
          loading.value = false
        } else {
          fetchGeneratedForm(generationId)
        }
      })
      .catch((error) => {
        if (error?.data?.message) {
          useAlert().error(error.data.message)
        }
        currentStep.value = 2
        loading.value = false
      })
  }, 4000)
}
</script>
