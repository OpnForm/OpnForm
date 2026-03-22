<template>
  <div class="relative px-6 py-10 sm:px-8 sm:py-14">
    <div class="pointer-events-none absolute left-1/2 top-0 h-[28rem] w-[72rem] max-w-[140%] -translate-x-1/2 rounded-full bg-blue-100/70 blur-3xl"></div>

    <div class="relative mx-auto max-w-266">
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
            class="absolute left-0 top-10 h-10 w-0.5 rounded-r-full"
            :class="item.accentBarClass"
            aria-hidden="true"
          ></div>
          <div
            class="text-2xl leading-8 font-medium text-gray-950"
            v-html="item.quote"
          ></div>

          <div class="mt-10 flex items-center gap-4">
            <div
              class="h-10 w-10 rounded-full flex items-center justify-center"
              :class="item.avatarClass"
            >
              <span
                class="text-sm leading-7 traking-[-1.1%] font-semibold"
                :class="item.avatarTextClass"
              >
                {{ getInitials(item.name) }}
              </span>
            </div>

            <div>
              <div
                class="text-base leading-7 traking-[-1.1%] font-medium text-gray-950"
              >
                {{ formatDisplayName(item.name) }}
              </div>
              <div
                class="text-sm leading-5 tracking-[-0.6%] font-medium text-gray-600"
              >
                {{ item.role }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="mt-8 text-center">
        <a
          href="https://www.trustpilot.com/review/opnform.com"
          target="_blank"
          rel="noopener noreferrer"
          class="text-sm font-medium text-gray-500 hover:text-gray-700 hover:underline"
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
    default: 'Loved by builders and <br> teams worldwide',
  },
})

const testimonials = [
  {
    quote:
      "Using OpnForm for client workflow tools saved a <span class='font-semibold text-blue-800'>huge amount of time</span> thanks to built-in <span class='font-semibold text-blue-800'>form logic</span>, field management, and webhooks.",
    name: "Alexandre Nahum",
    role: "Consultant",
    avatarClass: "bg-blue-50",
    avatarTextClass: "text-blue-700",
    accentBarClass: "bg-blue-600",
  },
  {
    quote:
      "The setup felt <span class='font-semibold text-emerald-800'>easy and intuitive</span>, and the mix of classic forms plus a more immersive focused mode made it feel like the <span class='font-semibold text-emerald-800'>best of both worlds</span>.",
    name: "Axel Amer",
    role: "Agency owner",
    avatarClass: "bg-emerald-50",
    avatarTextClass: "text-emerald-700",
    accentBarClass: "bg-emerald-600",
  },
  {
    quote:
      "For a small business, it has been <span class='font-semibold text-amber-800'>reliable</span>, flexible for real-world data collection, and <span class='font-semibold text-amber-800'>more affordable</span> than the alternatives reviewed.",
    name: "Ethan D",
    role: "Small business owner",
    avatarClass: "bg-amber-50",
    avatarTextClass: "text-amber-700",
    accentBarClass: "bg-amber-500",
  },
]

function getInitials(name) {
  const parts = (name || "").trim().split(/\s+/).filter(Boolean)
  if (parts.length === 0) return "?"
  if (parts.length === 1) return parts[0].charAt(0).toUpperCase()

  return `${parts[0].charAt(0)}${parts[parts.length - 1].charAt(0)}`.toUpperCase()
}

function formatDisplayName(name) {
  const parts = (name || "").trim().split(/\s+/).filter(Boolean)
  if (parts.length === 0) return "?"
  if (parts.length === 1) return parts[0]

  const firstName = parts[0]
  const lastInitial = parts[parts.length - 1].charAt(0).toUpperCase()
  return `${firstName} ${lastInitial}.`
}

</script>
