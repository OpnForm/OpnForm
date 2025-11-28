<template>
  <UButton
    variant="outline"
    icon="i-heroicons-arrow-top-right-on-square"
    :to="clarityLink"
    target="_blank"
    label="Open in Clarity"
  />
</template>

<script setup>
const props = defineProps({
  user: { type: Object, required: true }
})

const runtimeConfig = useRuntimeConfig()
const clarityProjectId = computed(() => runtimeConfig.public.clarityProjectId || null)

const clarityLink = computed(() => {
  return `https://clarity.microsoft.com/projects/view/${clarityProjectId.value}/impressions?CustomUserId=is%3B${props.user.id}`
})
</script>