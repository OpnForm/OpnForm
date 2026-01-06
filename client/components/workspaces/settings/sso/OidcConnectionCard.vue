<template>
  <div class="group h-full rounded-xl border border-neutral-200 bg-white p-4 shadow-sm transition hover:shadow-md">
    <div class="flex items-center justify-between gap-2">
      <div>
        <p class="text-sm font-semibold text-neutral-900">{{ connection.name }}</p>
        <p class="text-xs uppercase tracking-wide text-neutral-400">{{ connection.slug }}</p>
      </div>
      <div class="flex items-center gap-2">
        <UButton
          v-if="canEdit"
          icon="i-heroicons-trash"
          color="error"
          variant="ghost"
          size="xs"
          square
          class="opacity-0 transition group-hover:opacity-100"
          @click="emit('delete', connection)"
        />
        <UButton
          v-if="canEdit"
          icon="i-heroicons-pencil"
          color="neutral"
          variant="ghost"
          size="xs"
          square
          @click="emit('edit', connection)"
        />
        <UBadge :color="connection.enabled ? 'success' : 'neutral'" variant="subtle" size="sm">
          {{ connection.enabled ? 'Enabled' : 'Disabled' }}
        </UBadge>
      </div>
    </div>

    <p class="mt-3 text-sm text-neutral-600">
      Issuer URL
      <span class="block font-medium text-neutral-900 truncate">{{ connection.issuer }}</span>
    </p>

    <p class="mt-2 text-sm text-neutral-600">
      Redirect URL
      <span class="block text-xs text-neutral-500 truncate">{{ connection.redirect_url }}</span>
    </p>

    <p class="mt-2 text-sm text-neutral-600">
      Email domain
      <span class="block font-medium text-neutral-900">{{ connection.domain ?? '—' }}</span>
    </p>
  </div>
</template>

<script setup>
import { toRef } from 'vue'

const props = defineProps({
  connection: { type: Object, required: true },
  canEdit: { type: Boolean, default: false },
})

const connection = toRef(props, 'connection')
const canEdit = toRef(props, 'canEdit')

const emit = defineEmits(['edit', 'delete'])
</script>

