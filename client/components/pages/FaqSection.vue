<template>
  <section class="border-y border-neutral-200 bg-neutral-50 py-16 sm:py-20 lg:py-24">
    <div
      class="mx-auto px-5 sm:px-8 lg:px-12"
      :class="variant === 'split' ? 'grid max-w-5xl gap-10 lg:grid-cols-[0.8fr_1.2fr]' : 'max-w-266'"
    >
      <div :class="variant === 'split' ? '' : 'mx-auto max-w-2xl text-center'">
        <p class="text-sm font-semibold uppercase tracking-[0.14em] text-blue-600">
          {{ eyebrow }}
        </p>
        <h2 class="mt-4 text-3xl font-semibold tracking-tight text-neutral-950 sm:text-4xl">
          <template v-for="(line, index) in normalizedTitleLines" :key="line">
            {{ line }}
            <br v-if="index < normalizedTitleLines.length - 1" class="hidden sm:block" />
            <template v-if="index < normalizedTitleLines.length - 1">{{ " " }}</template>
          </template>
        </h2>
        <p v-if="description" class="mt-5 text-base leading-7 text-neutral-600">
          {{ description }}
        </p>
      </div>

      <div :class="variant === 'split' ? 'divide-y divide-neutral-200 border-y border-neutral-200' : 'mt-8 space-y-3 sm:mt-12 sm:space-y-4'">
        <div
          v-for="(faq, index) in faqs"
          :key="faq.question"
          :class="variant === 'split' ? 'py-1' : 'rounded-2xl bg-white'"
        >
          <button
            type="button"
            class="w-full cursor-pointer text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
            :class="variant === 'split' ? 'py-5' : 'rounded-2xl p-4 sm:p-5'"
            :aria-expanded="openFaqIndex === index"
            :aria-controls="`${idPrefix}-${index}`"
            @click="toggleFaq(index)"
          >
            <div class="flex items-center gap-4">
              <span v-if="variant !== 'split'" class="w-6 shrink-0 text-sm font-medium text-neutral-400">
                {{ String(index + 1).padStart(2, "0") }}
              </span>
              <span class="flex flex-1 items-center justify-between gap-5 font-semibold text-neutral-950">
                {{ faq.question }}
                <UIcon
                  name="i-heroicons-plus-20-solid"
                  class="h-5 w-5 shrink-0 text-neutral-500 transition-transform duration-200"
                  :class="openFaqIndex === index ? 'rotate-45' : ''"
                />
              </span>
            </div>
          </button>

          <div
            :id="`${idPrefix}-${index}`"
            class="faq-answer"
            :class="openFaqIndex === index ? 'faq-answer-open' : 'faq-answer-closed'"
            :aria-hidden="openFaqIndex !== index"
          >
            <div class="overflow-hidden">
              <p
                class="pb-5 leading-7 text-neutral-600"
                :class="variant === 'split' ? 'pr-8' : 'px-4 pl-14 text-sm sm:px-5 sm:pl-15'"
              >
                {{ faq.answer }}
              </p>
            </div>
          </div>
        </div>

        <div v-if="showContact" class="pt-6 text-center">
          <p class="text-base text-neutral-600">
            {{ contactText }}
            <button
              type="button"
              class="cursor-pointer text-blue-600 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
              @click="$emit('contact')"
            >
              {{ contactLabel }}
            </button>
          </p>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
const props = defineProps({
  eyebrow: { type: String, default: "Frequently asked questions" },
  title: { type: String, default: "" },
  titleLines: { type: Array, default: () => [] },
  description: { type: String, default: "" },
  faqs: { type: Array, required: true },
  defaultOpenIndex: { type: Number, default: 0 },
  idPrefix: { type: String, default: "faq-answer" },
  showContact: { type: Boolean, default: true },
  contactText: { type: String, default: "Didn't find the answer?" },
  contactLabel: { type: String, default: "Contact us" },
  variant: { type: String, default: "stacked" },
})

defineEmits(["contact"])

const openFaqIndex = ref(props.defaultOpenIndex)
const normalizedTitleLines = computed(() => props.titleLines.length ? props.titleLines : [props.title])
const toggleFaq = (index) => {
  openFaqIndex.value = openFaqIndex.value === index ? null : index
}
</script>

<style scoped>
.faq-answer {
  display: grid;
  grid-template-rows: 0fr;
  opacity: 0;
  transition: grid-template-rows 180ms ease, opacity 180ms ease;
}

.faq-answer-open {
  grid-template-rows: 1fr;
  opacity: 1;
}

@media (prefers-reduced-motion: reduce) {
  .faq-answer {
    transition: none;
  }
}
</style>
