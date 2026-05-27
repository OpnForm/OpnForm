<template>
  <section class="py-12 sm:py-20 px-8 lg:px-12 bg-white">
    <div class="mx-auto max-w-266">
      <div class="text-center">
        <p
          class="text-base leading-7 tracking-[-1.1%] font-semibold text-blue-600"
        >
          {{ eyebrow }}
        </p>
        <h2
          class="my-4 text-4xl sm:text-5xl sm:leading-14 tracking-[-1%] font-semibold text-gray-950"
        >
          <template v-for="(line, index) in normalizedTitleLines" :key="line">
            {{ line }}
            <br
              v-if="index < normalizedTitleLines.length - 1"
              class="hidden sm:block"
            />
            <template v-if="index < normalizedTitleLines.length - 1">
              {{ " " }}
            </template>
          </template>
        </h2>
        <p
          class="text-base leading-7 font-normal tracking-[-1.1%] text-gray-600"
        >
          {{ description }}
        </p>
      </div>

      <div class="mt-8 sm:mt-12">
        <div class="space-y-3 sm:space-y-4">
          <div
            v-for="(q, i) in faqs"
            :key="q.question"
            class="bg-gray-50 rounded-2xl"
          >
            <button
              type="button"
              class="w-full p-4 sm:p-5 text-left rounded-2xl cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
              :aria-expanded="openFaqIndex === i"
              :aria-controls="`${idPrefix}-${i}`"
              @click="toggleFaq(i)"
            >
              <div class="flex items-center gap-3 sm:gap-8">
                <span
                  class="w-6 shrink-0 text-sm sm:text-base leading-7 tracking-[-0.6%] font-medium text-gray-400"
                >
                  {{ String(i + 1).padStart(2, "0") }}
                </span>
                <div
                  class="flex items-center justify-between flex-1 gap-4 sm:gap-8"
                >
                  <p
                    class="text-base sm:text-lg leading-7 tracking-[-0.6%] font-medium text-gray-600"
                  >
                    {{ q.question }}
                  </p>
                  <span
                    class="inline-flex items-center justify-center w-5 h-5 shrink-0 rounded-full text-gray-400 transition-transform duration-200"
                    :class="{ 'rotate-45': openFaqIndex === i }"
                  >
                    <Icon
                      class="w-5 h-5"
                      name="heroicons:plus-20-solid"
                    />
                  </span>
                </div>
              </div>
            </button>

            <div
              :id="`${idPrefix}-${i}`"
              class="faq-answer px-4 sm:px-5"
              :class="
                openFaqIndex === i ? 'faq-answer-open' : 'faq-answer-closed'
              "
              :aria-hidden="openFaqIndex !== i"
            >
              <div class="overflow-hidden">
                <p
                  class="pl-9 sm:pl-14 pr-4 text-sm font-medium leading-6 text-gray-600"
                >
                  {{ q.answer }}
                </p>
              </div>
            </div>
          </div>
        </div>

        <div v-if="showContact" class="mt-8 text-center sm:mt-12">
          <p
            class="text-base leading-7 tracking-[-1.1%] font-medium text-gray-600"
          >
            {{ contactText }}
            <button
              type="button"
              class="cursor-pointer text-blue-600 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2"
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
  eyebrow: {
    type: String,
    default: "Frequently Asked Questions",
  },
  title: {
    type: String,
    default: "",
  },
  titleLines: {
    type: Array,
    default: () => [],
  },
  description: {
    type: String,
    required: true,
  },
  faqs: {
    type: Array,
    required: true,
  },
  defaultOpenIndex: {
    type: Number,
    default: 0,
  },
  idPrefix: {
    type: String,
    default: "faq-answer",
  },
  showContact: {
    type: Boolean,
    default: true,
  },
  contactText: {
    type: String,
    default: "Didn't find the answer?",
  },
  contactLabel: {
    type: String,
    default: "Contact Us",
  },
})

defineEmits(["contact"])

const openFaqIndex = ref(props.defaultOpenIndex)

const normalizedTitleLines = computed(() => {
  if (props.titleLines.length > 0) return props.titleLines
  return [props.title]
})

const toggleFaq = (index) => {
  openFaqIndex.value = openFaqIndex.value === index ? null : index
}
</script>

<style scoped>
.faq-answer {
  display: grid;
  grid-template-rows: 0fr;
  opacity: 0;
  padding-bottom: 0;
  transition:
    grid-template-rows 180ms ease,
    opacity 180ms ease,
    padding-bottom 180ms ease;
}

.faq-answer-open {
  grid-template-rows: 1fr;
  opacity: 1;
  padding-bottom: 1rem;
}
</style>
