<template>
  <div class="px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
    <div class="max-w-4xl mx-auto text-center">
      <h2 class="text-5xl font-semibold tracking-tight text-neutral-900" v-html="title" />
    </div>

    <div class="mt-14 grid gap-8 lg:grid-cols-3 max-w-6xl mx-auto">
      <div
        v-for="item in testimonials"
        :key="item.name"
        class="relative rounded-3xl border border-neutral-200 bg-white p-10 shadow-sm"
      >
        <div
          class="absolute left-0 top-10 h-10 w-0.5 bg-blue-600 rounded-r-full"
          aria-hidden="true"
        />
        <div class="text-xl font-semibold leading-tight text-neutral-900">
          “{{ item.quote }}”
        </div>

        <div class="mt-10 flex items-center gap-4">
          <div class="h-12 w-12 rounded-full bg-blue-50 ring-1 ring-blue-100 flex items-center justify-center">
            <span class="text-base font-semibold text-blue-700">
              {{ getInitial(item.name) }}
            </span>
          </div>
          <div>
            <div class="text-base font-semibold text-neutral-900">
              {{ item.name }}
            </div>
            <div class="mt-1 text-sm font-medium text-neutral-500">
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
    default: 'Loved by builders and <br> teams worldwide',
  },
})

const testimonials = [
  {
    quote: 'We switched from Typeform in under 10 minutes — and instantly saved hundreds per month.',
    name: 'Lena C.',
    role: 'Marketing Manager',
  },
  {
    quote: 'OpnForm gives us complete control over design and data. It\'s everything Typeform does — without limits.',
    name: 'Andre M.',
    role: 'Developer',
  },
  {
    quote: 'I love that it’s open source. We self-hosted and customized the UX completely.',
    name: 'Ravi P.',
    role: 'CTO',
  },
]

function getInitial(name) {
  return (name || '?').trim().charAt(0).toUpperCase() || '?'
}

</script>
