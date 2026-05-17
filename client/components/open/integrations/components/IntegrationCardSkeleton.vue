<template>
  <DashboardPanel padding="sm">
    <div class="flex items-center gap-4">
      <USkeleton class="h-8 w-8 rounded-full" />
      <div class="space-y-2">
        <USkeleton class="h-4 w-[250px]" />
      </div>
      <USkeleton class="h-8 w-8 ml-auto rounded-full" />
    </div>
  </DashboardPanel>
</template>

<script setup>
import DashboardPanel from "~/components/dashboard/DashboardPanel.vue"
</script> 