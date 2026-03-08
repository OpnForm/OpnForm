<template>
  <div
    class="w-full mx-auto max-w-266 flex items-center justify-center relative z-10"
  >
    <div
      class="rounded-[12px] bg-[#D5E2FF] border border-gray-200 shadow-xl overflow-visible relative p-2.5 w-full"
    >
      <iframe
        class="min-h-117 sm:min-h-137.5 lg:min-h-175 bg-white rounded-[12px]"
        style="border: none; width: 100%"
        :src="url"
      />

      <NuxtLink
        :to="{ name: authenticated ? 'forms-create' : 'forms-create-guest' }"
        class="pl-2 py-1 pr-1 min-w-52 z-15 absolute bottom-6 left-1/2 -translate-x-1/2 inline-flex items-center rounded-full overflow-hidden bg-gray-900 text-white text-sm shadow-[0_0_32px_8px_rgba(51,92,255,0.2)]"
      >
        <span class="px-2"> Like a Demo? </span>
        <span class="px-3 py-1.25 rounded-full bg-blue-600 hover:bg-blue-700">
          Try a Form
        </span>
      </NuxtLink>
    </div>
  </div>
</template>

<script setup>
defineProps({
  url: {
    type: String,
    required: false,
    default: "https://opnform.com/forms/opnform-contact",
  },
});

const { isAuthenticated: authenticated } = useIsAuthenticated();
</script>
