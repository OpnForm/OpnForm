<template>
  <div class="py-8 sm:py-10">
    <div class="mx-auto max-w-lg text-center">
      <h2 class="font-semibold tracking-[-0.5%] text-gray-950">
        Trusted by teams worldwide
      </h2>
      <p
        class="mt-3 text-base font-normal leading-7 tracking-[-1.1%] text-gray-600"
      >
        Join thousands of companies and creators using OpnForm to collect
        secure, high-quality data every day.
      </p>
    </div>

    <div class="mt-8 flex flex-wrap items-center justify-center gap-10 sm:gap-12">
      <div
        v-for="logo in logos"
        :key="logo.alt"
        class="w-fit opacity-45 grayscale"
      >
        <img
          :src="logo.src"
          :alt="logo.alt"
          class="h-auto max-h-9 w-auto"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
const logos = [
  { src: "/img/pages/welcome/vanguard-1.png", alt: "Vanguard" },
  { src: "/img/pages/welcome/logo-berkeley-2.png", alt: "UC Berkeley" },
  { src: "/img/pages/welcome/UN_emblem_blue-2.png", alt: "United Nations" },
  { src: "/img/pages/welcome/Frame-47.png", alt: "American Red Cross" },
  { src: "/img/pages/welcome/Zomato_Logo-2.png", alt: "Zomato" },
]
</script>
