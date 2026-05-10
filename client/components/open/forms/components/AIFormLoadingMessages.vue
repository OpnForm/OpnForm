<template>
  <div class="overflow-hidden">
    <div class="flex min-w-0 items-center gap-2 rounded-lg border border-blue-100 bg-blue-50 px-3 py-2">
      <span class="relative flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-white text-blue-600 shadow-sm ring-1 ring-blue-100">
        <span class="absolute inset-1 rounded-sm border border-blue-200/80 ai-loader-scan" />
        <Icon
          :name="currentIcon"
          class="h-4 w-4"
        />
      </span>
      <span
        :key="currentMessage"
        class="min-w-0 truncate text-sm font-medium text-neutral-700 ai-message-enter"
      >
        {{ currentMessage }}
      </span>
    </div>
  </div>
</template>

<script setup>
const messages = [
  { text: "Reading your brief", icon: "i-heroicons-document-magnifying-glass" },
  { text: "Drafting the question flow", icon: "i-heroicons-queue-list" },
  { text: "Choosing the right field types", icon: "i-heroicons-squares-plus" },
  { text: "Shaping validation and copy", icon: "i-heroicons-pencil-square" },
  { text: "Checking the form structure", icon: "i-heroicons-check-badge" },
  { text: "Polishing the final draft", icon: "i-heroicons-sparkles" },
]

const currentMessage = ref(messages[0].text)
const currentIcon = ref(messages[0].icon)
const messageIndex = ref(0)

const interval = setInterval(() => {
  const nextIndex = (messageIndex.value + 1) % messages.length
  messageIndex.value = nextIndex
  currentMessage.value = messages[nextIndex].text
  currentIcon.value = messages[nextIndex].icon
}, 2600)

onUnmounted(() => {
  clearInterval(interval)
})
</script>

<style scoped>
.ai-loader-scan {
  animation: ai-loader-scan 1.6s ease-in-out infinite;
  transform-origin: center;
}

@keyframes ai-loader-scan {
  0% {
    opacity: 0.3;
    transform: scale(0.7) rotate(0deg);
  }

  50% {
    opacity: 1;
    transform: scale(1) rotate(12deg);
  }

  100% {
    opacity: 0.3;
    transform: scale(0.7) rotate(0deg);
  }
}

.ai-message-enter {
  animation: ai-message-enter 240ms ease-out;
}

@keyframes ai-message-enter {
  from {
    opacity: 0;
    transform: translateY(3px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
