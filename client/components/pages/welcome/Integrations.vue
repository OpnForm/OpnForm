<template>
  <div class="px-8 pb-8 sm:pb-10 lg:px-12">
    <div
      class="mx-auto w-full max-w-336 rounded-4xl bg-linear-to-br from-neutral-950 via-neutral-900 to-neutral-950 shadow-2xl overflow-hidden"
    >
      <div
        class="grid xl:grid-cols-2 gap-12 md:gap-16 items-center py-8 md:py-10 lg:py-14 xl:py-24 px-8 md:px-10 lg:px-14 xl:px-35"
      >
        <div class="max-w-124">
          <div
            class="text-blue-400 text-base tracking-[-1.1%] font-medium leading-7"
          >
            Integrations
          </div>
          <h3 class="mt-4 text-4xl sm:text-5xl font-semibold text-white tracking-tight leading-tight">
            Connect OpnForm to
            <br>
            your workflow.
          </h3>
          <p class="mt-5 text-base sm:text-lg font-medium text-neutral-300 leading-8">
            Send responses instantly to your tools. Automate notifications. Sync data across your stack with one click.
          </p>

          <div class="mt-10">
            <UButton
              :to="{ name: 'integrations' }"
              label="Explore All Integrations"
              trailing-icon="i-heroicons-arrow-up-right-20-solid"
              class="rounded-xl px-6 py-3 text-base font-semibold"
            />
          </div>
        </div>

        <div class="flex justify-center lg:justify-end">
          <div class="grid grid-cols-5 gap-4">
            <div
              v-for="app in integrationApps"
              :key="app.icon"
              class="h-20 w-20 rounded-2xl bg-white/5 ring-1 ring-white/10 flex items-center justify-center backdrop-blur-sm"
            >
              <UIcon :name="app.icon" class="h-9 w-9" :class="app.iconClass" />
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const integrationApps = [
  { icon: 'heroicons:envelope-20-solid', iconClass: 'text-[#3B82F6]' },
  { icon: 'ic:baseline-discord', iconClass: 'text-[#5865F2]' },
  { icon: 'mdi:telegram', iconClass: 'text-[#27A7E7]' },
  { icon: 'material-symbols:webhook', iconClass: 'text-[#0061FF]' },
  { icon: 'i-simple-icons-zapier', iconClass: 'text-[#FF4A00]' },

  { icon: 'simple-icons:n8n', iconClass: 'text-[#EA4B71]' },
  { icon: 'opnform:activepieces', iconClass: 'text-[#6366F1]' },
  { icon: 'mdi:google-spreadsheet', iconClass: 'text-[#34A853]' },
  { icon: 'i-simple-icons-zoom', iconClass: 'text-[#2D8CFF]' },
  { icon: 'i-simple-icons-linear', iconClass: 'text-[#5E6AD2]' },

  { icon: 'i-simple-icons-evernote', iconClass: 'text-[#00A82D]' },
  { icon: 'i-simple-icons-notion', iconClass: 'text-white' },
  { icon: 'i-simple-icons-airtable', iconClass: 'text-[#18BFFF]' },
  { icon: 'i-simple-icons-meta', iconClass: 'text-[#0668E1]' },
  { icon: 'i-simple-icons-zendesk', iconClass: 'text-[#2694D6]' },
]

</script>
