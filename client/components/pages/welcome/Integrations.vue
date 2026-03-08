<template>
  <div class="px-8 lg:px-12">
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
          <h3
            class="my-4 text-4xl sm:text-5xl font-semibold text-white tracking-[-1%] leading-14"
          >
            Connect OpnForm to your workflow.
          </h3>
          <p
            class="text-base tracking-[-1.1%] font-medium leading-7 text-gray-400"
          >
            Send responses instantly to your tools. Automate notifications. Sync
            data across your stack with one click.
          </p>

          <div class="mt-12">
            <UButton
              :to="{ name: 'integrations' }"
              label="Explore All Integrations"
              trailing-icon="i-heroicons-arrow-up-right-20-solid"
              class="pl-4 pr-3.5 py-2.5 rounded-[12px] text-base leading-7 tracking-[-1.1%] font-medium"
            />
          </div>
        </div>

        <div class="flex justify-center xl:justify-end">
          <div class="grid grid-cols-5 gap-4">
            <div
              v-for="app in integrationApps"
              :key="app.icon"
              class="h-14 sm:h-22 w-14 sm:w-22 rounded-2xl bg-white/5 ring-1 ring-white/10 flex items-center justify-center backdrop-blur-sm"
            >
              <UIcon
                :name="app.icon"
                class="h-9 sm:h-12 w-9 sm:w-12"
                :class="app.iconClass"
              />
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const integrationApps = [
  { icon: "i-simple-icons-zendesk", iconClass: "text-[#ffffff]" },

  { icon: "heroicons:envelope-20-solid", iconClass: "text-[#3B82F6]" },
  { icon: "ic:baseline-discord", iconClass: "text-[#5865F2]" },
  { icon: "mdi:telegram", iconClass: "text-[#27A7E7]" },
  { icon: "material-symbols:webhook", iconClass: "text-[#0061FF]" },
  { icon: "i-simple-icons-zapier", iconClass: "text-[#FF4A00]" },

  { icon: "simple-icons:n8n", iconClass: "text-[#EA4B71]" },
  { icon: "opnform:activepieces", iconClass: "text-[#6366F1]" },
  { icon: "mdi:google-spreadsheet", iconClass: "text-[#34A853]" },
  { icon: "i-simple-icons-zoom", iconClass: "text-[#2D8CFF]" },
  { icon: "i-simple-icons-linear", iconClass: "text-[#5E6AD2]" },

  { icon: "i-simple-icons-evernote", iconClass: "text-[#00A82D]" },
  { icon: "i-simple-icons-notion", iconClass: "text-white" },
  { icon: "i-simple-icons-airtable", iconClass: "text-[#18BFFF]" },
  { icon: "i-simple-icons-meta", iconClass: "text-[#0668E1]" },
];
</script>
