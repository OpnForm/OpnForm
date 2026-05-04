<template>
  <div class="relative px-6 py-8 sm:px-8 sm:py-10">
    <div
      class="pointer-events-none absolute left-1/2 top-8 h-[21.75rem] w-[72rem] max-w-[145%] -translate-x-1/2 rounded-full bg-blue-100/70 blur-3xl"
      aria-hidden="true"
    ></div>

    <div class="relative z-10 mx-auto max-w-266">
      <div class="grid gap-6 lg:grid-cols-3">
        <div
          v-for="item in testimonials"
          :key="item.name"
          class="relative rounded-3xl border border-gray-200 bg-white p-6 sm:p-8 shadow-sm flex flex-col justify-between"
        >
          <div
            :class="item.accentClass"
            class="absolute left-0 top-10 h-10 w-0.5 rounded-r-full"
            aria-hidden="true"
          ></div>
          <div class="text-2xl leading-8 font-medium text-gray-950">
            “{{ item.quote }}”
          </div>

          <div class="mt-10 flex items-center gap-4">
            <div
              :class="item.avatarClass"
              class="flex h-10 w-10 items-center justify-center rounded-full ring-1"
            >
              <span
                :class="item.avatarTextClass"
                class="text-sm leading-7 tracking-[-1.1%] font-medium"
              >
                {{ getInitials(item.name) }}
              </span>
            </div>
            <div>
              <div class="flex items-baseline justify-between gap-3">
                <div
                  class="text-base leading-7 traking-[-1.1%] font-medium text-gray-950"
                >
                  {{ item.name }}
                </div>
                <span
                  class="shrink-0 text-sm leading-7 tracking-[-0.6%] font-medium text-gray-600 whitespace-nowrap"
                >
                  {{ item.date }}
                </span>
              </div>
              <div
                class="mt-1 text-sm leading-5 tracking-[-0.6%] font-medium text-gray-600"
              >
                <div class="flex items-center gap-0.5">
                  <Icon
                    v-for="starIndex in item.rating"
                    :key="`${item.name}-star-${starIndex}`"
                    name="i-heroicons-star-solid"
                    class="h-4 w-4 text-amber-400"
                  />
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="mt-8 flex justify-center">
        <a
          href="https://www.trustpilot.com/review/opnform.com"
          target="_blank"
          rel="noopener noreferrer"
          class="inline-flex items-center text-sm font-medium text-gray-600 hover:text-gray-800 hover:underline"
        >
          More reviews on Trustpilot
        </a>
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
      "Easy integrations, lots of flexibility, a responsive team, fast product updates, and the bonus of being open source.",
    name: "kristelle F.",
    rating: 5,
    date: "Mar 2026",
    accentClass: "bg-blue-600",
    avatarClass: "bg-blue-50 ring-blue-100",
    avatarTextClass: "text-blue-700",
  },
  {
    quote:
      "A strong fit for workflow tools: field management, conditional logic, and webhooks are already there and save serious build time.",
    name: "Alexandre N.",
    rating: 5,
    date: "Nov 2025",
    accentClass: "bg-emerald-600",
    avatarClass: "bg-emerald-50 ring-emerald-100",
    avatarTextClass: "text-emerald-700",
  },
  {
    quote:
      "Setup felt easy from day one, and Focused mode makes it feel like the best of classic forms and Typeform-style experiences.",
    name: "Axel A.",
    rating: 5,
    date: "Oct 2025",
    accentClass: "bg-violet-600",
    avatarClass: "bg-violet-50 ring-violet-100",
    avatarTextClass: "text-violet-700",
  },
]

function getInitials(name) {
  const parts = (name || "")
    .trim()
    .split(/\s+/)
    .filter(Boolean)

  if (parts.length === 0) {
    return "??"
  }

  if (parts.length === 1) {
    return parts[0].slice(0, 2).toUpperCase()
  }

  return `${parts[0].charAt(0)}${parts[parts.length - 1].charAt(0)}`.toUpperCase()
}
</script>
