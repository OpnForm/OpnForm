<template>
  <div class="mx-auto max-w-266">
    <div class="text-center">
      <h2
        class="text-4xl sm:text-5xl sm:leading-14 tracking-[-1%] font-semibold text-gray-950"
        v-html="title"
      ></h2>
    </div>

    <div class="mt-12 sm:mt-16 grid gap-6 lg:grid-cols-3">
      <div
        v-for="item in testimonials"
        :key="item.name"
        class="relative rounded-3xl border border-gray-200 bg-white p-6 sm:p-8 shadow-sm flex flex-col justify-between"
      >
        <div
          class="absolute left-0 top-10 h-10 w-0.5 bg-blue-600 rounded-r-full"
          aria-hidden="true"
        ></div>
        <div class="text-2xl leading-8 font-medium text-gray-950">
          “{{ item.quote }}”
        </div>

        <div class="mt-10 flex items-center gap-4">
          <div
            class="h-10 w-10 rounded-full bg-gray-100 flex items-center justify-center"
          >
            <span
              class="text-base leading-7 traking-[-1.1%] font-medium text-blue-700"
            >
              {{ getInitial(item.name) }}
            </span>
          </div>
          <div>
            <div
              class="text-base leading-7 traking-[-1.1%] font-medium text-gray-950"
            >
              {{ item.name }}
            </div>
            <div
              class="mt-1 text-sm leading-5 tracking-[-0.6%] font-medium text-gray-600"
            >
              {{ item.role }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: {
    type: String,
    required: false,
    default: "Loved by builders and <br> teams worldwide",
  },
})

const testimonials = [
  {
    quote:
      "We switched from Typeform in under 10 minutes — and instantly saved hundreds per month.",
    name: "Lena C.",
    role: "Marketing Manager",
  },
  {
    quote:
      "OpnForm gives us complete control over design and data. It's everything Typeform does — without limits.",
    name: "Andre M.",
    role: "Developer",
  },
  {
    quote:
      "I love that it’s open source. We self-hosted and customized the UX completely.",
    name: "Ravi P.",
    role: "CTO",
  },
]

function getInitial(name) {
  return (name || "?").trim().charAt(0).toUpperCase() || "?"
}
</script>
