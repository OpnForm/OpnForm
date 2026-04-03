<template>
  <section class="bg-white">
    <Integrations />
  </section>
  <section class="bg-white py-14 sm:py-28 px-8 lg:px-12">
    <div class="max-w-266 mx-auto">
      <div class="text-center">
        <h3
          class="text-3xl sm:text-5xl sm:leading-14 font-semibold text-gray-950 tracking-[-1%]"
        >
          Share your form anywhere.
        </h3>
        <p
          class="mt-4 text-base font-normal text-gray-600 leading-7 tracking-[-1.1%]"
        >
          Choose the distribution method that fits your workflow.
        </p>
      </div>

      <div class="mt-12 sm:mt-16 grid gap-6">
        <div class="grid gap-6 lg:grid-cols-2">
          <div
            v-for="card in shareCards"
            :key="card.key"
            class="rounded-[24px] bg-gray-50 border border-gray-200/70 pt-8 md:pt-10 lg:pt-6 pl-8 md:pl-10 lg:pl-6 overflow-hidden"
            :class="card.size === 'large' ? 'lg:col-span-2' : ''"
          >
            <div
              class="grid gap-3 relative"
              :class="
                card.size === 'large'
                  ? 'items-end lg:grid-cols-12 gap-0 sm:gap-2 lg:gap-6'
                  : 'items-start sm:grid-cols-12 gap-2 sm:gap-6'
              "
            >
              <div
                class="flex flex-col items-start lg:pb-6"
                :class="
                  card.size === 'large'
                    ? 'sm:col-span-4 lg:col-span-3 gap-2 lg:gap-6'
                    : 'sm:col-span-6 md:col-span-5 gap-2 sm:gap-6'
                "
              >
                <div
                  class="h-16 w-16 rounded-[20px] flex items-center justify-center bg-white shadow-xs"
                >
                  <UIcon :name="card.icon" class="h-8 w-8 text-blue-600" />
                </div>
                <div class="text-xl leading-7 font-medium text-neutral-950">
                  {{ card.title }}
                </div>
              </div>

              <div
                :class="
                  card.size === 'large'
                    ? '-ml-8 sm:ml-0 sm:col-span-8 lg:col-span-9'
                    : 'sm:col-span-6 md:col-span-7 max-w-[278.16px] ml-auto'
                "
              >
                <img
                  :src="card.imageSrc"
                  :alt="card.title"
                  class="w-full translate-y-4"
                  loading="lazy"
                />
              </div>
            </div>
          </div>
        </div>
      </div>

      <div
        class="mt-12 sm:mt-16 text-center text-base font-normal text-gray-600 leading-7 tracking-[-1.1%]"
      >
        Your forms are accessible wherever your audience is.
      </div>
      <div class="mt-6 text-center">
        <div class="flex items-center justify-center">
          <UButton
            size="lg"
            :to="{
              name: authenticated ? 'forms-create' : 'forms-create-guest',
            }"
            trailing-icon="i-heroicons-arrow-up-right-20-solid"
            label="Get started. It's FREE!"
            class="pl-4 pr-3.5 py-2.5 rounded-[12px] text-base leading-7 tracking-[-1.1%] font-medium"
          />
        </div>
      </div>
    </div>
  </section>

  <section class="bg-white">
    <div class="px-8 lg:px-12">
      <div
        class="mx-auto w-full max-w-336 rounded-4xl bg-gray-50 border border-gray-200 overflow-hidden"
      >
        <div
          class="relative py-8 sm:py-10 lg:py-14 xl:py-24 px-8 md:px-10 lg:px-14 xl:px-35"
        >
          <img
            src="/img/pages/welcome/Graphic.svg"
            class="absolute top-[45%] left-0 right-0 min-w-full object-cover min-h-[55%]"
          />
          <div
            class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-16 relative z-10"
          >
            <div class="flex flex-col items-start gap-2">
              <h3
                class="text-3xl sm:text-5xl sm:leading-14 font-semibold text-gray-950 tracking-[-1%] xl:w-138"
              >
                Open-source. Secure. Yours to host anywhere.
              </h3>
              <p
                class="my-4 text-base font-normal text-gray-600 leading-7 tracking-[-1.1%]"
              >
                OpnForm is fully open-source and built for privacy-first
                organizations.
              </p>
              <p
                class="text-base font-normal text-gray-600 leading-7 tracking-[-1.1%]"
              >
                Use our EU-based cloud or deploy on-premise to keep full control
                of your data.
              </p>

              <UButton
                :to="{
                  name: authenticated ? 'forms-create' : 'forms-create-guest',
                }"
                size="lg"
                class="my-4 lg:my-10 pl-4 pr-3.5 py-2.5 rounded-[12px] text-base leading-7 tracking-[-1.1%] font-medium"
                trailing-icon="i-heroicons-arrow-up-right-20-solid"
                label="Create a Form"
              />

              <div class="flex flex-wrap gap-4 sm:gap-8 text-blue-600">
                <a
                  class="inline-flex items-center gap-1 hover:no-underline hover:text-blue-700 text-base font-medium leading-7 tracking-[-1.1%]"
                  :href="opnformConfig.links.github_url"
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  View GitHub
                  <UIcon
                    name="i-heroicons-arrow-up-right-20-solid"
                    class="h-5 w-5"
                  />
                </a>
                <a
                  class="inline-flex items-center gap-1 hover:no-underline hover:text-blue-700 text-base font-medium leading-7 tracking-[-1.1%]"
                  :href="opnformConfig.links.self_hosting"
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  Self-host OpnForm
                  <UIcon
                    name="i-heroicons-arrow-up-right-20-solid"
                    class="h-5 w-5"
                  />
                </a>
                <br />
                <a
                  class="inline-flex items-center gap-1 hover:no-underline hover:text-blue-700 text-base font-medium leading-7 tracking-[-1.1%]"
                  :href="opnformConfig.links.tech_docs"
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  Open-source Benefits
                  <UIcon
                    name="i-heroicons-arrow-up-right-20-solid"
                    class="h-5 w-5"
                  />
                </a>
              </div>
            </div>
            <div class="flex flex-col gap-3 xl:pl-13">
              <div
                v-for="pill in securityPills"
                :key="pill.label"
                class="flex items-center gap-2.5 w-fit rounded-full bg-white border border-neutral-200/80 py-1.5 pl-1.5 pr-4 text-sm leading-5 tracking-[-0.6%] font-medium text-gray-950 shadow-sm"
              >
                <div
                  class="h-8 w-8 shrink-0 rounded-full flex items-center justify-center"
                  :class="pill.iconWrapClass"
                >
                  <UIcon
                    :name="pill.icon"
                    class="h-5.5 w-5.5"
                    :class="pill.iconClass"
                  />
                </div>
                <span>{{ pill.label }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="bg-white py-14 sm:py-28 px-8 lg:px-12">
    <div class="max-w-266 mx-auto">
      <div class="text-center">
        <h3
          class="text-3xl sm:text-5xl sm:leading-14 font-semibold text-gray-950 tracking-[-1%]"
        >
          Flexible for individuals —
          <br />
          reliable for teams.
        </h3>
        <p
          class="mt-4 text-base font-normal text-gray-600 leading-7 tracking-[-1.1%]"
        >
          Manage forms at scale with powerful collaboration and compliance
          features.
        </p>
      </div>

      <div class="my-12 sm:my-16 max-w-6xl mx-auto space-y-6">
        <div class="grid gap-6 grid-cols-1 sm:grid-cols-2">
          <div
            v-for="block in teamFeatureBlocks.slice(0, 2)"
            :key="block.title"
            class="overflow-hidden relative rounded-[24px] border border-gray-200 bg-gray-50 p-8 shadow-sm"
          >
            <h4
              class="text-xl leading-7 tracking-[-0.6%] font-semibold text-gray-950"
            >
              {{ block.title }}
            </h4>
            <p
              class="mt-4 text-base font-normal text-gray-600 leading-7 tracking-[-1.1%]"
            >
              {{ block.description }}
            </p>
            <div class="mt-4 -mx-8 -mb-8">
              <img
                :src="block.image"
                :alt="block.title"
                class="w-full max-w-[500px] mx-auto rounded-t-xl object-cover"
                loading="lazy"
              />
            </div>
            <div
              class="absolute bottom-0 left-0 right-0 min-h-1/5 bg-linear-to-b from-transparent to-[#F8FAFC]"
            ></div>
          </div>
        </div>
        <div class="grid gap-6 sm:grid-cols-1 lg:grid-cols-3">
          <div
            v-for="block in teamFeatureBlocks.slice(2)"
            :key="block.title"
            class="overflow-hidden relative rounded-3xl border border-gray-200 bg-gray-50 p-8 shadow-sm"
          >
            <h4 class="text-xl font-semibold text-gray-950">
              {{ block.title }}
            </h4>
            <p class="mt-4 text-base font-medium text-gray-600 leading-7">
              {{ block.description }}
            </p>
            <div
              class="flex lg:block justify-end items-end mt-6 -mr-8 lg:-ml-2 -mb-12"
            >
              <img
                :src="block.image"
                :alt="block.title"
                class="w-full max-w-78.5 rounded-t-xl object-cover"
                loading="lazy"
              />
            </div>
            <div
              class="absolute bottom-0 left-0 right-0 min-h-1/5 bg-linear-to-b from-transparent to-[#F8FAFC]"
            ></div>
          </div>
        </div>
      </div>

      <div class="text-center">
        <div
          class="flex flex-col sm:flex-row items-center justify-center gap-6"
        >
          <UButton
            size="lg"
            :to="{ name: 'enterprise' }"
            trailing-icon="i-heroicons-arrow-up-right-20-solid"
            label="Learn about Enterprise"
            class="pl-4 pr-3.5 py-2.5 rounded-[12px] text-base leading-7 tracking-[-1.1%] font-medium"
          />
          <UButton
            :to="{ name: 'pricing' }"
            label="View Pricing"
            variant="outline"
            color="neutral"
            size="lg"
            class="px-4 py-2.5 rounded-[12px] text-base leading-7 tracking-[-1.1%] font-medium"
          />
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import opnformConfig from "~/opnform.config.js"

const { isAuthenticated: authenticated } = useIsAuthenticated()

const shareCards = [
  {
    key: "link",
    size: "large",
    title: "Share with a simple link",
    icon: "i-heroicons-link",
    imageSrc: "/img/pages/welcome/share-1.png",
  },
  {
    key: "embed",
    size: "small",
    title: "Embed on your website or app",
    icon: "i-heroicons-code-bracket-square",
    imageSrc: "/img/pages/welcome/share-2.png",
  },
  {
    key: "notion",
    size: "small",
    title: "Add forms directly into Notion",
    icon: "i-simple-icons-notion",
    imageSrc: "/img/pages/welcome/share-3.png",
  },
  {
    key: "domain",
    size: "small",
    title: "Use your own custom domain",
    icon: "i-heroicons-globe-alt",
    imageSrc: "/img/pages/welcome/share-4.png",
  },
  {
    key: "qr",
    size: "small",
    title: "Generate QR codes for offline collection",
    icon: "i-heroicons-qr-code",
    imageSrc: "/img/pages/welcome/share-5.png",
  },
]

const securityPills = [
  {
    label: "100% open-source & auditable",
    icon: "i-heroicons-code-bracket-square",
    iconWrapClass: "bg-blue-50",
    iconClass: "text-blue-600",
  },
  {
    label: "EU hosting & GDPR-friendly architecture",
    icon: "i-heroicons-globe-europe-africa",
    iconWrapClass: "bg-red-50",
    iconClass: "text-red-600",
  },
  {
    label: "Self-hosting / on-premise deployments",
    icon: "i-heroicons-server-stack",
    iconWrapClass: "bg-pink-50",
    iconClass: "text-pink-600",
  },
  {
    label: "SSO & SAML support",
    icon: "i-heroicons-lock-closed",
    iconWrapClass: "bg-violet-50",
    iconClass: "text-violet-600",
  },
  {
    label: "Form & submission audit logs",
    icon: "i-heroicons-clipboard-document-check",
    iconWrapClass: "bg-sky-50",
    iconClass: "text-sky-600",
  },
  {
    label: "SOC2 compliance underway",
    icon: "i-heroicons-shield-check",
    iconWrapClass: "bg-orange-50",
    iconClass: "text-orange-600",
  },
  {
    label: "Two-factor authentication (2FA) support",
    icon: "i-heroicons-key",
    iconWrapClass: "bg-fuchsia-50",
    iconClass: "text-fuchsia-600",
  },
  {
    label: "End-to-end data encryption",
    icon: "i-heroicons-lock-closed",
    iconWrapClass: "bg-emerald-50",
    iconClass: "text-emerald-600",
  },
]

const teamFeatureBlocks = [
  {
    title: "Collaboration & Management",
    description:
      "Coordinate work across teams with shared workspaces, clear roles, and full audit visibility.",
    image: "/img/pages/welcome/team-1.png",
  },
  {
    title: "Security & Compliance",
    description:
      "Stay aligned with internal requirements through SSO access, domain control, and elevated support.",
    image: "/img/pages/welcome/team-2.png",
  },
  {
    title: "Branding Control",
    description: "Fully brand your forms or white-label them for clients.",
    image: "/img/pages/welcome/team-3.png",
  },
  {
    title: "Audit History",
    description: "Full visibility into form and submission activity.",
    image: "/img/pages/welcome/team-4.png",
  },
  {
    title: "Priority Support",
    description: "Get help fast with dedicated enterprise support.",
    image: "/img/pages/welcome/team-5.png",
  },
]
</script>
