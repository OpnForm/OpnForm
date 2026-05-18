<template>
  <div
    ref="demoFormElement"
    class="live-demo-form relative flex min-h-[460px] flex-col overflow-hidden bg-white sm:min-h-[620px]"
    :style="formStyle"
  >
    <OpenFormFocused
      v-if="isFormReady"
      :form-manager="formManager"
      class="grow"
      @submit="handleSubmit"
    >
      <template #after-submit>
        <div class="mx-auto flex w-full max-w-xl flex-col items-start px-2 text-left">
          <div
            class="mb-5 inline-flex h-12 w-12 items-center justify-center rounded-[14px] bg-blue-50 text-blue-600"
          >
            <UIcon name="i-heroicons-check-20-solid" class="h-6 w-6" />
          </div>
          <h3 class="text-3xl font-semibold leading-10 tracking-[-1%] text-neutral-950">
            {{ scenario.successTitle }}
          </h3>
          <p class="mt-3 text-base font-medium leading-7 tracking-[-1.1%] text-neutral-600">
            {{ scenario.successBody }}
          </p>
          <div class="mt-8 flex flex-col gap-3 sm:flex-row">
            <UButton
              :to="primaryCtaTo"
              size="lg"
              trailing-icon="i-heroicons-arrow-up-right-20-solid"
              :label="scenario.primaryCtaLabel"
              class="w-fit rounded-[12px] px-4 py-2.5 text-base font-medium leading-7 tracking-[-1.1%]"
            />
            <UButton
              v-if="secondaryCtaTo && scenario.secondaryCtaLabel"
              :to="secondaryCtaTo"
              size="lg"
              variant="outline"
              :label="scenario.secondaryCtaLabel"
              class="w-fit rounded-[12px] px-4 py-2.5 text-base font-medium leading-7 tracking-[-1.1%]"
            />
            <UButton
              type="button"
              size="lg"
              color="neutral"
              variant="ghost"
              icon="i-heroicons-arrow-path-20-solid"
              label="Replay demo"
              class="w-fit rounded-[12px] px-4 py-2.5 text-base font-medium leading-7 tracking-[-1.1%]"
              @click="restartDemo"
            />
          </div>
        </div>
      </template>
    </OpenFormFocused>
  </div>
</template>

<script setup>
import { tailwindcssPaletteGenerator } from "~/lib/colors.js"
import { useFormManager } from "~/lib/forms/composables/useFormManager"
import { FormMode } from "~/lib/forms/FormModeStrategy.js"
import OpenFormFocused from "~/components/open/forms/OpenFormFocused.vue"

const props = defineProps({
  scenario: {
    type: Object,
    required: true,
  },
  primaryCtaTo: {
    type: [String, Object],
    required: true,
  },
  secondaryCtaTo: {
    type: [String, Object],
    default: null,
  },
})

const scenario = computed(() => props.scenario)
const primaryCtaTo = computed(() => props.primaryCtaTo)
const secondaryCtaTo = computed(() => props.secondaryCtaTo)

provide("formTheme", computed(() => props.scenario.form.theme || "default"))
provide("formSize", computed(() => props.scenario.form.size || "lg"))
provide("formBorderRadius", computed(() => props.scenario.form.border_radius || "small"))
provide("formPresentationStyle", computed(() => props.scenario.form.presentation_style || "focused"))

const formManager = useFormManager(props.scenario.form, FormMode.DEMO, {
  mode: ref(FormMode.DEMO),
})
const demoFormElement = ref(null)

formManager.initialize({
  skipPendingSubmission: true,
  skipUrlParams: true,
  eagerStructure: true,
})
  .catch((error) => {
    console.error(error)
  })

const isFormReady = computed(() => !!formManager.structure.value)

const formStyle = computed(() => {
  const color = props.scenario.form.color || "#2563EB"
  const colorPalette = tailwindcssPaletteGenerator(color).primary
  const style = {
    "--font-family": props.scenario.form.font_family,
    "--form-color": color,
    "--color-form": color,
    "--form-focused-step-height": "100%",
    "--form-focused-mobile-media-height": "clamp(120px, 24svh, 180px)",
    "contain": "layout paint",
  }

  Object.entries(colorPalette).forEach(([shade, colorValue]) => {
    style[`--color-form-${shade}`] = colorValue
  })

  return style
})

function handleSubmit() {
  formManager.submit()
    .catch((error) => {
      console.error(error)
    })
}

function restartDemo() {
  formManager.restart({
    skipPendingSubmission: true,
    skipUrlParams: true,
  })
    .catch((error) => {
      console.error(error)
    })
}
</script>

<style scoped>
.live-demo-form :deep(.nf-text .text-block > div:last-child) {
  display: flex;
  flex-direction: column;
  gap: 0.625rem;
}

.live-demo-form :deep(.nf-text .text-block h2),
.live-demo-form :deep(.nf-text .text-block p) {
  margin: 0;
}

.live-demo-form :deep(.nf-text .text-block h2) {
  line-height: 1.22;
}

.live-demo-form :deep(.nf-text .text-block p) {
  line-height: 1.6;
}

.live-demo-form :deep([role="listbox"] [role="option"] > span:first-child) {
  pointer-events: none;
  user-select: none;
}

</style>
