<template>
  <div
    class="w-full mt-6 relative px-6 mx-auto max-w-4xl sm:px-10 lg:px-0 z-10 flex items-center justify-center"
  >
    <div
      class="-m-2 rounded-xl bg-blue-900/5 p-2 backdrop-blur-xs ring ring-inset ring-blue-900/10 lg:-m-4 lg:rounded-2xl lg:p-4 w-full"
    >
      <div
        class="rounded-3xl bg-white shadow-xl overflow-visible ring-1 ring-blue-100 relative"
      >
        <iframe
          class="mt-4 min-h-117 sm:min-h-137.5 lg:min-h-175"
          style="border: none; width: 100%"
          :src="url"
        />

        <NuxtLink
          :to="{ name: authenticated ? 'forms-create' : 'forms-create-guest' }"
          class="pl-2 py-1 pr-1 min-w-52 absolute bottom-6 left-1/2 -translate-x-1/2 inline-flex items-center rounded-full overflow-hidden bg-gray-900 text-white text-sm"
        >
          <span class="px-2"> Like a Demo? </span>
          <span class="px-3 py-1.25 rounded-full bg-blue-600 hover:bg-blue-700">
            Try a Form
          </span>
        </NuxtLink>
      </div>
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
