<template>
  <UModal
    v-model:open="isOpen"
    title="Your first form is ready!"
    description="Help more people discover OpnForm by starring the project on GitHub."
    :ui="{ content: 'sm:max-w-md', footer: 'flex-col gap-2' }"
  >
    <template #body>
      <div class="flex flex-col items-center text-center">
        <div class="mb-5 flex size-16 items-center justify-center rounded-2xl bg-amber-50 dark:bg-amber-950">
          <Icon name="i-heroicons-star" class="size-8 text-amber-500" />
        </div>
        <p class="text-sm leading-6 text-neutral-600 dark:text-neutral-300">
          OpnForm is built by an open-source community. If it's been useful to you,
          a GitHub star is a small way to say thanks and support the project.
        </p>
      </div>
    </template>
    <template #footer>
      <UButton
        :to="opnformConfig.links.github_url"
        target="_blank"
        rel="noopener noreferrer"
        size="lg"
        block
        @click="dismissPrompt"
      >
        <Icon name="i-simple-icons-github" class="size-5" />
        Star OpnForm on GitHub
        <Icon name="i-heroicons-arrow-up-right" class="size-4" />
      </UButton>
      <UButton color="neutral" variant="ghost" size="lg" block @click="dismissPrompt">
        Maybe later
      </UButton>
    </template>
  </UModal>
</template>

<script setup>
import opnformConfig from '~/opnform.config'

const props = defineProps({
  formSlug: { type: String, required: true },
})

const { pendingFormSlug, dismissPrompt } = useGithubStarPrompt()
const isOpen = computed({
  get: () => !!useFeatureFlag('self_hosted', false) && pendingFormSlug.value === props.formSlug,
  set: (open) => {
    if (!open) dismissPrompt()
  },
})
</script>
