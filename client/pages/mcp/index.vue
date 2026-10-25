<template>
  <div class="flex-1 overflow-hidden bg-white">
    <section class="relative border-b border-neutral-200 bg-[#f7f9fc]">
      <div
        aria-hidden="true"
        class="absolute inset-0 opacity-70 [background-image:linear-gradient(to_right,#dbe4f0_1px,transparent_1px),linear-gradient(to_bottom,#dbe4f0_1px,transparent_1px)] [background-size:32px_32px] [mask-image:linear-gradient(to_bottom,black,transparent_90%)]"
      />
      <div
        aria-hidden="true"
        class="absolute -left-24 top-28 h-72 w-72 rounded-full bg-blue-200/40 blur-3xl"
      />
      <div
        aria-hidden="true"
        class="absolute -right-20 bottom-8 h-64 w-64 rounded-full bg-amber-100/70 blur-3xl"
      />

      <div
        class="relative mx-auto grid max-w-7xl items-center gap-14 px-5 py-16 sm:px-8 sm:py-20 lg:grid-cols-[0.92fr_1.08fr] lg:px-12 lg:py-28"
      >
        <div class="max-w-2xl">
          <div
            class="inline-flex items-center gap-2 rounded-full border border-blue-200 bg-white/80 px-3.5 py-1.5 text-sm font-medium text-blue-700 shadow-xs backdrop-blur"
          >
            <span class="relative flex h-2 w-2">
              <span
                class="absolute inline-flex h-full w-full animate-ping rounded-full bg-blue-400 opacity-60 motion-reduce:animate-none"
              />
              <span class="relative inline-flex h-2 w-2 rounded-full bg-blue-600" />
            </span>
            OpnForm for AI agents
          </div>

          <h1
            class="mt-6 text-4xl font-semibold tracking-[-0.04em] text-neutral-950 sm:text-5xl lg:text-6xl lg:leading-[1.05]"
          >
            The MCP form builder that turns
            <span class="text-blue-600">conversations into forms</span>
          </h1>
          <p
            class="mt-6 max-w-xl text-lg leading-8 text-neutral-600 sm:text-xl sm:leading-9"
          >
            Ask your AI assistant for the form you need. OpnForm creates a private
            draft, lets you preview it, and hands it back to you in the full editor
            when you are ready.
          </p>

          <div class="mt-8 flex flex-col gap-3 sm:flex-row">
            <UButton
              to="https://docs.opnform.com/integrations/mcp"
              external
              size="lg"
              label="Connect your AI assistant"
              trailing-icon="i-heroicons-arrow-up-right-20-solid"
              class="justify-center rounded-xl px-5"
            />
            <UButton
              to="#how-it-works"
              size="lg"
              color="neutral"
              variant="outline"
              label="See how it works"
              trailing-icon="i-heroicons-arrow-down-20-solid"
              class="justify-center rounded-xl px-5"
            />
          </div>

          <ul class="mt-8 flex flex-wrap gap-x-6 gap-y-3 text-sm font-medium text-neutral-700">
            <li v-for="proof in heroProofs" :key="proof" class="flex items-center gap-2">
              <UIcon
                name="i-heroicons-check-circle-20-solid"
                class="h-5 w-5 text-emerald-600"
              />
              {{ proof }}
            </li>
          </ul>
        </div>

        <div class="relative mx-auto w-full max-w-2xl lg:mx-0">
          <div
            aria-hidden="true"
            class="absolute -inset-5 rotate-2 rounded-[2rem] border border-blue-200 bg-blue-100/50"
          />
          <div
            class="relative overflow-hidden rounded-[1.75rem] border border-neutral-200 bg-white p-4 shadow-2xl shadow-blue-950/10 sm:p-6"
          >
            <div class="flex items-center justify-between border-b border-neutral-100 pb-4">
              <div class="flex items-center gap-3">
                <div
                  class="flex h-9 w-9 items-center justify-center rounded-xl bg-neutral-950 text-white"
                >
                  <UIcon name="i-heroicons-sparkles-20-solid" class="h-4 w-4" />
                </div>
                <div>
                  <p class="text-sm font-semibold text-neutral-950">Your AI assistant</p>
                  <p class="text-xs text-neutral-500">Connected to OpnForm</p>
                </div>
              </div>
              <span
                class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium transition-colors duration-300"
                :class="
                  isHeroDemoWorking
                    ? 'bg-blue-50 text-blue-700'
                    : 'bg-emerald-50 text-emerald-700'
                "
              >
                <span
                  class="h-1.5 w-1.5 rounded-full"
                  :class="isHeroDemoWorking ? 'animate-pulse bg-blue-500' : 'bg-emerald-500'"
                />
                {{ heroDemoStatusLabel }}
              </span>
            </div>

            <div
              class="mt-5 min-h-[88px] max-w-[88%] rounded-2xl rounded-tl-sm bg-neutral-100 p-4"
            >
              <p class="text-sm leading-6 text-neutral-700" :aria-label="heroPrompt">
                <span aria-hidden="true">{{ displayedHeroPrompt }}</span>
                <span
                  v-if="heroDemoPhase === 'typing'"
                  aria-hidden="true"
                  class="ml-0.5 inline-block h-4 w-px translate-y-0.5 animate-pulse bg-neutral-500"
                />
              </p>
            </div>

            <div class="mt-4 min-h-8">
              <Transition name="hero-demo-status" mode="out-in">
                <div
                  v-if="heroDemoPhase === 'building'"
                  key="building"
                  class="flex items-center gap-3 text-sm font-medium text-neutral-600"
                >
                  <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-50">
                    <span class="relative h-4 w-4">
                      <span class="absolute inset-0 rounded-full border-2 border-blue-200" />
                      <span
                        class="absolute inset-0 animate-spin rounded-full border-2 border-transparent border-t-blue-600 motion-reduce:animate-none"
                      />
                    </span>
                  </span>
                  Building your private draft
                  <span class="flex gap-1" aria-hidden="true">
                    <span class="hero-demo-dot" />
                    <span class="hero-demo-dot [animation-delay:120ms]" />
                    <span class="hero-demo-dot [animation-delay:240ms]" />
                  </span>
                </div>
                <div
                  v-else-if="heroDemoPhase === 'complete'"
                  key="complete"
                  class="flex items-center gap-3 text-sm font-medium text-blue-700"
                >
                  <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-50">
                    <UIcon name="i-heroicons-arrow-turn-down-right-20-solid" class="h-4 w-4" />
                  </span>
                  Draft created. Here is your preview.
                </div>
              </Transition>
            </div>

            <div
              class="mt-4 min-h-[314px] overflow-hidden rounded-2xl border bg-[#fffdf9] transition-all duration-300"
              :class="
                isHeroPreviewVisible
                  ? 'translate-y-0 border-neutral-200 opacity-100 shadow-lg shadow-neutral-950/5'
                  : 'translate-y-2 border-neutral-100 opacity-0 shadow-none'
              "
            >
              <div class="flex items-center justify-between border-b border-neutral-200 px-5 py-3">
                <div class="flex items-center gap-2">
                  <span class="h-2.5 w-2.5 rounded-full bg-red-300" />
                  <span class="h-2.5 w-2.5 rounded-full bg-amber-300" />
                  <span class="h-2.5 w-2.5 rounded-full bg-emerald-300" />
                </div>
                <span class="text-[11px] font-medium uppercase tracking-[0.14em] text-neutral-400">
                  Private preview
                </span>
              </div>
              <div class="p-5 sm:p-6">
                <div
                  class="flex items-start justify-between gap-4 transition-all duration-300"
                  :class="heroDemoBlockClass(1)"
                >
                  <div>
                    <p class="text-xl font-semibold tracking-tight text-neutral-950">
                      Summer dinner registration
                    </p>
                    <p class="mt-1 text-sm text-neutral-500">We would love to see you there.</p>
                  </div>
                  <span
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700"
                  >
                    <UIcon name="i-heroicons-sun-20-solid" class="h-5 w-5" />
                  </span>
                </div>
                <div class="mt-6 grid gap-3 sm:grid-cols-2">
                  <div
                    v-for="(field, index) in previewFields"
                    :key="field"
                    class="rounded-xl border border-neutral-200 bg-white px-3.5 py-3 text-sm text-neutral-500 transition-all duration-300"
                    :class="heroDemoBlockClass(index + 2)"
                  >
                    {{ field }}
                  </div>
                </div>
                <div
                  class="mt-5 flex justify-center transition-all duration-300"
                  :class="heroDemoBlockClass(6)"
                >
                  <span
                    class="rounded-lg bg-blue-600 px-6 py-2 text-sm font-medium text-white shadow-sm"
                  >
                    Submit
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="border-b border-neutral-200 bg-white py-16 sm:py-20 lg:py-24">
      <div class="mx-auto grid max-w-6xl gap-10 px-5 sm:px-8 lg:grid-cols-[0.75fr_1.25fr] lg:px-12">
        <div>
          <p class="text-sm font-semibold uppercase tracking-[0.14em] text-blue-600">
            In plain English
          </p>
          <h2 class="mt-4 text-3xl font-semibold tracking-tight text-neutral-950 sm:text-4xl">
            What is an MCP form builder?
          </h2>
        </div>
        <div class="space-y-5 text-lg leading-8 text-neutral-600">
          <p>
            MCP is a shared way for AI assistants to work with products like OpnForm.
            Instead of copying questions between a chat and a form builder, your
            assistant can create the draft for you.
          </p>
          <p>
            You stay in control: review the real form, ask for changes in the
            conversation, then continue in OpnForm to customize, save, and publish it.
          </p>
        </div>
      </div>
    </section>

    <section id="how-it-works" class="scroll-mt-20 bg-neutral-950 py-16 text-white sm:py-20 lg:py-24">
      <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-12">
        <div class="max-w-3xl">
          <p class="text-sm font-semibold uppercase tracking-[0.14em] text-blue-300">
            From idea to live form
          </p>
          <h2 class="mt-4 text-3xl font-semibold tracking-tight !text-white sm:text-4xl lg:text-5xl">
            You describe it. Your agent builds it. You make it yours.
          </h2>
        </div>

        <ol class="mt-12 grid gap-px overflow-hidden rounded-3xl bg-white/15 lg:grid-cols-3">
          <li
            v-for="(step, index) in steps"
            :key="step.title"
            class="relative bg-neutral-950 p-7 sm:p-8"
          >
            <span
              class="text-6xl font-semibold tracking-tighter text-white/25"
              aria-hidden="true"
            >
              0{{ index + 1 }}
            </span>
            <UIcon :name="step.icon" class="mt-8 h-7 w-7 text-blue-300" />
            <h3 class="mt-4 text-xl font-semibold text-white">{{ step.title }}</h3>
            <p class="mt-3 leading-7 text-neutral-300">{{ step.description }}</p>
          </li>
        </ol>
      </div>
    </section>

    <section class="bg-[#f7f9fc] py-16 sm:py-20 lg:py-24">
      <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-12">
        <div class="mx-auto max-w-3xl text-center">
          <p class="text-sm font-semibold uppercase tracking-[0.14em] text-blue-600">
            One connection, two ways to work
          </p>
          <h2 class="mt-4 text-3xl font-semibold tracking-tight text-neutral-950 sm:text-4xl lg:text-5xl">
            Start without an account. Connect when you need more.
          </h2>
          <p class="mt-5 text-lg leading-8 text-neutral-600">
            OpnForm keeps the first experience frictionless without giving an agent
            more access than it needs.
          </p>
        </div>

        <div class="mx-auto mt-12 grid max-w-5xl gap-6 lg:grid-cols-2">
          <article
            v-for="mode in modes"
            :key="mode.title"
            class="relative overflow-hidden rounded-3xl border border-neutral-200 bg-white p-7 shadow-sm sm:p-9"
          >
            <div
              class="absolute right-0 top-0 h-36 w-36 rounded-bl-full opacity-60"
              :class="mode.accentClass"
              aria-hidden="true"
            />
            <div
              class="relative flex h-12 w-12 items-center justify-center rounded-2xl"
              :class="mode.iconClass"
            >
              <UIcon :name="mode.icon" class="h-6 w-6" />
            </div>
            <p class="relative mt-6 text-sm font-semibold uppercase tracking-[0.12em] text-neutral-500">
              {{ mode.eyebrow }}
            </p>
            <h3 class="relative mt-2 text-2xl font-semibold text-neutral-950">{{ mode.title }}</h3>
            <p class="relative mt-3 leading-7 text-neutral-600">{{ mode.description }}</p>
            <ul class="relative mt-6 space-y-3">
              <li v-for="item in mode.items" :key="item" class="flex gap-3 text-neutral-700">
                <UIcon
                  name="i-heroicons-check-20-solid"
                  class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600"
                />
                <span>{{ item }}</span>
              </li>
            </ul>
          </article>
        </div>
      </div>
    </section>

    <section class="border-y border-neutral-200 bg-white py-16 sm:py-20 lg:py-24">
      <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-12">
        <div class="grid gap-12 lg:grid-cols-[0.85fr_1.15fr] lg:items-center">
          <div>
            <p class="text-sm font-semibold uppercase tracking-[0.14em] text-blue-600">
              Useful from day one
            </p>
            <h2 class="mt-4 text-3xl font-semibold tracking-tight text-neutral-950 sm:text-4xl">
              Forms are work. Your assistant can take the first pass.
            </h2>
            <p class="mt-5 text-lg leading-8 text-neutral-600">
              Give it the context you already have — an event, a hiring process, a
              customer request, or a research goal — and start from a usable form
              instead of an empty canvas.
            </p>
            <UButton
              :to="{ name: 'ai-form-builder' }"
              variant="link"
              label="Explore OpnForm's built-in AI form builder"
              trailing-icon="i-heroicons-arrow-right-20-solid"
              class="mt-5 px-0"
            />
          </div>

          <div class="grid gap-4 sm:grid-cols-2">
            <article
              v-for="useCase in useCases"
              :key="useCase.title"
              class="rounded-2xl border border-neutral-200 p-5 transition hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md"
            >
              <UIcon :name="useCase.icon" class="h-6 w-6 text-blue-600" />
              <h3 class="mt-4 font-semibold text-neutral-950">{{ useCase.title }}</h3>
              <p class="mt-2 text-sm leading-6 text-neutral-600">{{ useCase.description }}</p>
            </article>
          </div>
        </div>
      </div>
    </section>

    <section class="bg-white py-16 sm:py-20 lg:py-24">
      <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-12">
        <div class="mx-auto max-w-3xl text-center">
          <p class="text-sm font-semibold uppercase tracking-[0.14em] text-blue-600">
            Control stays with you
          </p>
          <h2 class="mt-4 text-3xl font-semibold tracking-tight text-neutral-950 sm:text-4xl lg:text-5xl">
            Helpful automation, with sensible boundaries
          </h2>
        </div>

        <div class="mt-12 grid gap-6 md:grid-cols-2 lg:grid-cols-4">
          <article v-for="guardrail in guardrails" :key="guardrail.title" class="p-2">
            <div
              class="flex h-11 w-11 items-center justify-center rounded-full bg-emerald-50 text-emerald-700"
            >
              <UIcon :name="guardrail.icon" class="h-5 w-5" />
            </div>
            <h3 class="mt-5 font-semibold text-neutral-950">{{ guardrail.title }}</h3>
            <p class="mt-2 text-sm leading-6 text-neutral-600">{{ guardrail.description }}</p>
          </article>
        </div>
      </div>
    </section>

    <section class="border-y border-neutral-200 bg-neutral-50 py-16 sm:py-20 lg:py-24">
      <div class="mx-auto max-w-5xl px-5 sm:px-8 lg:px-12">
        <div class="grid gap-10 lg:grid-cols-[0.8fr_1.2fr]">
          <div>
            <p class="text-sm font-semibold uppercase tracking-[0.14em] text-blue-600">FAQ</p>
            <h2 class="mt-4 text-3xl font-semibold tracking-tight text-neutral-950 sm:text-4xl">
              Questions about the OpnForm MCP
            </h2>
          </div>
          <div class="divide-y divide-neutral-200 border-y border-neutral-200">
            <details v-for="faq in faqs" :key="faq.question" class="group py-5">
              <summary
                class="flex cursor-pointer list-none items-center justify-between gap-5 font-semibold text-neutral-950"
              >
                {{ faq.question }}
                <UIcon
                  name="i-heroicons-plus-20-solid"
                  class="h-5 w-5 shrink-0 text-neutral-500 transition group-open:rotate-45"
                />
              </summary>
              <p class="mt-3 max-w-2xl pr-8 leading-7 text-neutral-600">{{ faq.answer }}</p>
            </details>
          </div>
        </div>
      </div>
    </section>

    <section class="bg-white px-5 py-16 sm:px-8 sm:py-20 lg:px-12 lg:py-24">
      <div
        class="relative mx-auto max-w-6xl overflow-hidden rounded-[2rem] bg-blue-700 px-6 py-12 text-center text-white shadow-xl shadow-blue-950/15 sm:px-12 sm:py-16"
      >
        <div
          aria-hidden="true"
          class="absolute inset-0 opacity-20 [background-image:radial-gradient(circle_at_center,white_1px,transparent_1.5px)] [background-size:22px_22px]"
        />
        <div class="relative mx-auto max-w-3xl">
          <p class="text-sm font-semibold uppercase tracking-[0.14em] text-blue-100">
            Your next form can start as a conversation
          </p>
          <h2 class="mt-4 text-3xl font-semibold tracking-tight !text-white sm:text-4xl lg:text-5xl">
            Connect OpnForm to your AI assistant
          </h2>
          <p class="mx-auto mt-5 max-w-2xl text-lg leading-8 text-blue-100">
            Start with a private draft — no OpnForm account required. Connect your
            account later when you want to save, publish, or manage existing forms.
          </p>
          <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
            <UButton
              to="https://docs.opnform.com/integrations/mcp"
              external
              size="lg"
              color="neutral"
              label="Follow the setup guide"
              trailing-icon="i-heroicons-arrow-up-right-20-solid"
              class="justify-center rounded-xl bg-white px-5 text-blue-700 hover:bg-blue-50"
            />
            <UButton
              :to="createFormTarget"
              size="lg"
              variant="solid"
              color="neutral"
              label="Create a form in OpnForm"
              trailing-icon="i-heroicons-arrow-right-20-solid"
              class="justify-center rounded-xl px-5 !bg-blue-950 !text-white hover:!bg-blue-900"
            />
          </div>
        </div>
      </div>
    </section>

    <OpenFormFooter :show-cta="false" />
  </div>
</template>

<script setup>
definePageMeta({
  layout: "default",
  middleware: ["root-redirect", "self-hosted"],
})

useOpnSeoMeta({
  title: "MCP Form Builder for AI Agents",
  description:
    "Create, preview, and refine forms with your AI assistant using OpnForm MCP. Start without an account, then save and manage forms when ready.",
})

defineRouteRules({
  swr: 3600,
})

const { isAuthenticated: authenticated } = useIsAuthenticated()
const isHydrated = ref(false)
const heroPrompt =
  "Create a friendly event registration form. Ask for a name, email, dietary requirements, and one guest."
const displayedHeroPrompt = ref(heroPrompt)
const heroDemoPhase = ref("complete")
const isHeroPreviewVisible = ref(true)
const visibleHeroBlocks = ref(6)
const heroDemoTimeouts = []
let reducedMotionQuery

onMounted(() => {
  isHydrated.value = true
  reducedMotionQuery = window.matchMedia("(prefers-reduced-motion: reduce)")
  reducedMotionQuery.addEventListener("change", handleReducedMotionChange)

  if (!reducedMotionQuery.matches) {
    scheduleHeroDemo(startHeroDemo, 1200)
  }
})

onBeforeUnmount(() => {
  clearHeroDemoTimeouts()
  reducedMotionQuery?.removeEventListener("change", handleReducedMotionChange)
})

const isHeroDemoWorking = computed(() =>
  ["typing", "building"].includes(heroDemoPhase.value),
)

const heroDemoStatusLabel = computed(() => {
  if (heroDemoPhase.value === "typing") return "Listening"
  if (heroDemoPhase.value === "building") return "Building"

  return "Ready"
})

function scheduleHeroDemo(callback, delay) {
  const timeoutId = window.setTimeout(callback, delay)
  heroDemoTimeouts.push(timeoutId)
}

function clearHeroDemoTimeouts() {
  heroDemoTimeouts.splice(0).forEach((timeoutId) => window.clearTimeout(timeoutId))
}

function showCompletedHeroDemo() {
  displayedHeroPrompt.value = heroPrompt
  heroDemoPhase.value = "complete"
  isHeroPreviewVisible.value = true
  visibleHeroBlocks.value = 6
}

function handleReducedMotionChange(event) {
  clearHeroDemoTimeouts()

  if (event.matches) {
    showCompletedHeroDemo()
    return
  }

  startHeroDemo()
}

function startHeroDemo() {
  clearHeroDemoTimeouts()
  displayedHeroPrompt.value = ""
  heroDemoPhase.value = "typing"
  isHeroPreviewVisible.value = false
  visibleHeroBlocks.value = 0
  typeHeroPrompt(1)
}

function typeHeroPrompt(characterCount) {
  displayedHeroPrompt.value = heroPrompt.slice(0, characterCount)

  if (characterCount < heroPrompt.length) {
    scheduleHeroDemo(() => typeHeroPrompt(characterCount + 1), 12)
    return
  }

  scheduleHeroDemo(buildHeroPreview, 180)
}

function buildHeroPreview() {
  heroDemoPhase.value = "building"
  isHeroPreviewVisible.value = true
  scheduleHeroDemo(() => revealHeroBlock(1), 180)
}

function revealHeroBlock(blockNumber) {
  visibleHeroBlocks.value = blockNumber

  if (blockNumber < 6) {
    scheduleHeroDemo(() => revealHeroBlock(blockNumber + 1), 150)
    return
  }

  scheduleHeroDemo(completeHeroDemo, 260)
}

function completeHeroDemo() {
  heroDemoPhase.value = "complete"
  scheduleHeroDemo(resetHeroDemo, 2300)
}

function resetHeroDemo() {
  heroDemoPhase.value = "resetting"
  isHeroPreviewVisible.value = false
  visibleHeroBlocks.value = 0
  displayedHeroPrompt.value = ""
  scheduleHeroDemo(startHeroDemo, 320)
}

function heroDemoBlockClass(blockNumber) {
  return visibleHeroBlocks.value >= blockNumber
    ? "translate-y-0 scale-100 opacity-100"
    : "translate-y-2 scale-[0.985] opacity-0"
}

const createFormTarget = computed(() => ({
  name: isHydrated.value && authenticated.value ? "forms-create" : "forms-create-guest",
}))

const heroProofs = ["No account to start", "Preview before saving", "Open source"]

const previewFields = ["Full name", "Email address", "Dietary requirements", "Bringing a guest?"]

const steps = [
  {
    icon: "i-heroicons-chat-bubble-left-right-20-solid",
    title: "Describe what you need",
    description:
      "Tell your AI assistant who the form is for, what you want to learn, and any questions you already have in mind.",
  },
  {
    icon: "i-heroicons-eye-20-solid",
    title: "Review the real form",
    description:
      "Open an interactive preview, test the flow, and ask the agent to add, remove, or rewrite questions before anything goes live.",
  },
  {
    icon: "i-heroicons-pencil-square-20-solid",
    title: "Finish it in OpnForm",
    description:
      "Move the draft into the visual editor, adjust the design, sign in when you want to save, and publish only when you decide.",
  },
]

const modes = [
  {
    eyebrow: "Guest mode",
    title: "Create first, sign up later",
    description:
      "Try the MCP form builder from a compatible AI assistant without creating an OpnForm account.",
    icon: "i-heroicons-sparkles-20-solid",
    iconClass: "bg-amber-100 text-amber-700",
    accentClass: "bg-amber-100",
    items: [
      "Create a private draft that lasts seven days",
      "Preview and revise the form in your conversation",
      "Open the draft in OpnForm's visual editor",
    ],
  },
  {
    eyebrow: "Connected account",
    title: "Keep managing the work",
    description:
      "Securely connect your account when you want your assistant to help with existing forms and responses.",
    icon: "i-heroicons-link-20-solid",
    iconClass: "bg-blue-100 text-blue-700",
    accentClass: "bg-blue-100",
    items: [
      "Create, update, review, and publish forms",
      "Find and inspect the submissions you need",
      "See form results without changing response data",
    ],
  },
]

const useCases = [
  {
    icon: "i-heroicons-calendar-days-20-solid",
    title: "Events and registrations",
    description: "Turn a date, audience, and agenda into a complete registration flow.",
  },
  {
    icon: "i-heroicons-user-group-20-solid",
    title: "Hiring and onboarding",
    description: "Build application forms and internal check-ins from an existing brief.",
  },
  {
    icon: "i-heroicons-chat-bubble-bottom-center-text-20-solid",
    title: "Feedback and research",
    description: "Shape clear surveys, interviews, and customer feedback forms with less setup.",
  },
  {
    icon: "i-heroicons-inbox-arrow-down-20-solid",
    title: "Requests and intake",
    description: "Capture the details your team needs for leads, support, projects, or approvals.",
  },
]

const guardrails = [
  {
    icon: "i-heroicons-eye-20-solid",
    title: "Preview before saving",
    description: "A generated form stays a private draft until you choose what happens next.",
  },
  {
    icon: "i-heroicons-hand-raised-20-solid",
    title: "Confirmation before publishing",
    description: "Your agent asks before publishing or moving a form to trash.",
  },
  {
    icon: "i-heroicons-lock-closed-20-solid",
    title: "Secure account connection",
    description: "Connect with OpnForm directly instead of sharing a password in the conversation.",
  },
  {
    icon: "i-heroicons-document-magnifying-glass-20-solid",
    title: "Read-only responses",
    description: "Agents can help find and analyze submissions, but cannot alter or delete them.",
  },
]

const faqs = [
  {
    question: "What is the OpnForm MCP form builder?",
    answer:
      "It connects a compatible AI assistant to OpnForm. You can describe a form in a conversation, receive a working preview, refine it, and then continue in OpnForm's visual editor.",
  },
  {
    question: "Do I need an OpnForm account to create a form with an AI agent?",
    answer:
      "No. Guest mode can create a private draft without an account. The draft lasts seven days, and you only need to sign in when you want to save it to a workspace.",
  },
  {
    question: "Can the agent publish or delete a form by itself?",
    answer:
      "No. Publishing and moving a form to trash require your confirmation. Guest drafts are never published automatically.",
  },
  {
    question: "Can an AI assistant access my form submissions?",
    answer:
      "Only after you securely connect your OpnForm account. It can search, read, summarize, and export accessible submissions, but it cannot edit or delete response data.",
  },
  {
    question: "Which AI assistants work with OpnForm MCP?",
    answer:
      "OpnForm is designed for ChatGPT and other AI assistants or agent clients that support remote MCP servers or Agent Plugins. Setup details vary by client, so check the integration guide for current instructions.",
  },
]

const faqSchema = {
  "@context": "https://schema.org",
  "@type": "FAQPage",
  mainEntity: faqs.map((faq) => ({
    "@type": "Question",
    name: faq.question,
    acceptedAnswer: {
      "@type": "Answer",
      text: faq.answer,
    },
  })),
}

useHead({
  script: [
    {
      key: "mcp-faq-schema",
      type: "application/ld+json",
      textContent: JSON.stringify(faqSchema),
    },
  ],
})
</script>

<style scoped>
.hero-demo-status-enter-active,
.hero-demo-status-leave-active {
  transition:
    opacity 180ms ease,
    transform 180ms ease;
}

.hero-demo-status-enter-from,
.hero-demo-status-leave-to {
  opacity: 0;
  transform: translateY(4px);
}

.hero-demo-dot {
  width: 4px;
  height: 4px;
  border-radius: 9999px;
  background: rgb(96 165 250);
  animation: hero-demo-dot 720ms ease-in-out infinite alternate;
}

@keyframes hero-demo-dot {
  from {
    opacity: 0.35;
    transform: translateY(1px);
  }

  to {
    opacity: 1;
    transform: translateY(-1px);
  }
}

@media (prefers-reduced-motion: reduce) {
  .hero-demo-status-enter-active,
  .hero-demo-status-leave-active,
  .hero-demo-dot {
    animation: none;
    transition: none;
  }
}
</style>
