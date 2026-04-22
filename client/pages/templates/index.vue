<template>
  <div class="of-page flex flex-col min-h-full border-t border-of-border">
    <section class="py-12 sm:py-16 bg-of-surface-muted border-b border-of-border">
      <div class="px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
        <div class="text-center max-w-xl mx-auto">
          <h1
            class="of-heading text-3xl sm:text-4xl lg:text-5xl font-bold tracking-tight"
          >
            Form Templates
          </h1>
          <p class="of-copy mt-4 text-lg font-normal">
            Our collection of beautiful templates to create your own forms!
          </p>
        </div>
      </div>
    </section>

    <templates-list
      class="px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto py-12"
      :templates="templates"
      :loading="loading"
    />

    <open-form-footer class="mt-8 border-t border-of-border" />
  </div>
</template>

<script setup>
defineRouteRules({
  swr: 3600,
})

useOpnSeoMeta({
  title: "Form Templates",
  description:
    "Our collection of beautiful templates to create your own forms!",
})

const { data: templates, isLoading: loading } = useTemplates().list()
</script>
