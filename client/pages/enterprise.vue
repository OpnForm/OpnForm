<template>
  <div>
    <section class="py-12 bg-white">
      <div class="px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
        <div class="max-w-4xl mx-auto text-center">
          <h1 class="text-4xl sm:text-5xl lg:text-6xl font-semibold tracking-tight text-neutral-900">
            Powerful and secure form
            <br>
            builder for all your teams
          </h1>
          <p class="mt-5 text-base sm:text-lg font-medium leading-8 text-neutral-500">
            OpnForm Enterprise gives your organization full control over your data and
            infrastructure — whether you self-host or use our managed cloud.
          </p>

          <div class="mt-10 flex flex-col sm:flex-row items-stretch sm:items-center justify-center gap-3">
            <UButton
              :to="opnformConfig.links.book_onboarding"
              target="_blank"
              label="Book a Demo"
              trailing-icon="i-heroicons-arrow-up-right-20-solid"
              class="rounded-full px-7 py-3 text-base font-semibold justify-center"
            />
            <UButton
              :to="opnformConfig.links.self_hosting"
              target="_blank"
              label="Explore Self-Hosting"
              variant="outline"
              class="rounded-full px-7 py-3 text-base font-semibold justify-center"
            />
          </div>

          <div class="mt-12">
            <div class="text-sm sm:text-base font-semibold text-neutral-600">
              Trusted by teams that value transparency, flexibility, and security.
            </div>
            <img
              src="/img/pages/welcome/trusted-teams.png"
              alt="Trusted teams"
              class="m-auto mt-8 max-w-3xl w-full h-auto opacity-80"
              loading="lazy"
            >
          </div>
        </div>
      </div>
    </section>

    <section class="py-12 bg-white">
      <div class="px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
        <div class="max-w-4xl mx-auto text-center">
          <div class="text-sm font-semibold text-blue-600">
            Security &amp; Compliance
          </div>

          <h2 class="mt-4 text-4xl sm:text-5xl lg:text-6xl font-semibold tracking-tight text-neutral-900">
            Your Data,
            <br>
            Your Infrastructure,
            <br>
            Your Rules
          </h2>

          <p class="mt-6 text-base sm:text-lg font-medium leading-8 text-neutral-500">
            Choose how and where you host your forms — without compromising on enterprise-grade security.
          </p>
        </div>

        <div class="mt-12 flex flex-wrap items-center justify-center gap-4">
          <div class="inline-flex items-center gap-2 rounded-full border border-neutral-200 bg-white px-5 py-2 text-sm font-semibold text-neutral-800 shadow-sm">
            <span class="h-8 w-8 rounded-full bg-violet-50 flex items-center justify-center">
              <UIcon name="i-heroicons-lock-closed" class="h-4 w-4 text-violet-600" />
            </span>
            <span>Single Sign-On (SSO) / SAML</span>
          </div>

          <div class="inline-flex items-center gap-2 rounded-full border border-neutral-200 bg-white px-5 py-2 text-sm font-semibold text-neutral-800 shadow-sm">
            <span class="h-8 w-8 rounded-full bg-red-50 flex items-center justify-center">
              <UIcon name="i-heroicons-shield-check" class="h-4 w-4 text-red-600" />
            </span>
            <span>GDPR SOC2</span>
          </div>

          <div class="inline-flex items-center gap-2 rounded-full border border-neutral-200 bg-white px-5 py-2 text-sm font-semibold text-neutral-800 shadow-sm">
            <span class="h-8 w-8 rounded-full bg-blue-50 flex items-center justify-center">
              <UIcon name="i-heroicons-globe-europe-africa" class="h-4 w-4 text-blue-600" />
            </span>
            <span>Hosted in Europe</span>
          </div>
        </div>

        <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
          <div
            v-for="item in securityFeatures"
            :key="item.title"
            class="rounded-3xl bg-neutral-50 p-8"
          >
            <div class="h-16 w-16 rounded-2xl bg-white shadow-sm ring-1 ring-neutral-200 flex items-center justify-center">
              <UIcon :name="item.icon" class="h-8 w-8 text-blue-600" />
            </div>

            <div class="mt-10 text-xl font-semibold text-neutral-900 leading-snug">
              {{ item.title }}
            </div>
            <div class="mt-4 text-base font-medium leading-7 text-neutral-600">
              {{ item.description }}
            </div>
          </div>
        </div>

        <div class="mt-14 flex justify-center">
          <UButton
            :to="opnformConfig.links.tech_docs"
            target="_blank"
            variant="outline"
            label="Learn About OpnForm Security"
            trailing-icon="i-heroicons-arrow-right-20-solid"
            class="rounded-full px-8 py-3 text-base font-semibold"
          />
        </div>
      </div>
    </section>

    <section class="py-12 bg-white">
      <div class="px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
        <div class="max-w-4xl mx-auto text-center">
          <div class="text-sm font-semibold text-blue-600">
            Features for Enterprise Teams
          </div>

          <h2 class="mt-4 text-4xl sm:text-5xl lg:text-6xl font-semibold tracking-tight text-neutral-900">
            Forms that adapt to
            <br>
            all your needs
          </h2>

          <p class="mt-6 text-base sm:text-lg font-medium leading-8 text-neutral-500">
            Build, automate, and integrate secure forms at scale — all without writing a single line of code.
          </p>
        </div>

        <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3 max-w-6xl mx-auto">
          <div
            v-for="item in enterpriseFeatures"
            :key="item.title"
            class="rounded-3xl bg-neutral-50 p-8"
          >
            <div class="h-16 w-16 rounded-2xl bg-white shadow-sm ring-1 ring-neutral-200 flex items-center justify-center">
              <UIcon :name="item.icon" class="h-8 w-8 text-blue-600" />
            </div>

            <div class="mt-6 text-xl font-semibold text-neutral-900">
              {{ item.title }}
            </div>
            <div class="mt-4 text-base font-medium leading-7 text-neutral-600">
              {{ item.description }}
            </div>
          </div>
        </div>

        <div class="mt-14 flex justify-center">
          <UButton
            :to="{ name: 'index', hash: '#features' }"
            variant="outline"
            label="Explore All Features"
            trailing-icon="i-heroicons-arrow-right-20-solid"
            class="rounded-full px-8 py-3 text-base font-semibold"
          />
        </div>
      </div>
    </section>

    <section class="py-12 bg-white">
      <div class="px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
        <div class="max-w-4xl mx-auto text-center">
          <h2 class="text-4xl sm:text-5xl font-semibold tracking-tight text-neutral-900">
            VIP support and
            <br>
            account management
          </h2>
        </div>

        <div class="mt-10 flex flex-wrap items-center justify-center gap-4">
          <div
            v-for="(badge, idx) in vipSupportBadges"
            :key="badge.label"
            class="w-full sm:w-auto"
            :class="idx === 2 ? 'basis-full flex justify-center' : ''"
          >
            <div class="inline-flex w-full sm:w-auto items-center justify-center gap-3 rounded-full border border-neutral-200 bg-white px-5 py-1 text-sm font-semibold text-neutral-800 shadow-sm">
              <span class="h-9 w-9 rounded-full flex items-center justify-center flex-shrink-0" :class="badge.badgeBg">
                <UIcon :name="badge.icon" class="h-4 w-4" :class="badge.iconColor" />
              </span>
              <span class="text-center">{{ badge.label }}</span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="py-12 bg-white">
      <Integrations />
    </section>

    <section class="py-12 bg-white">
      <Testimonials title="See how teams use Opnform <br> at scale" />
    </section>
    
    <OpenFormFooter />
  </div>
</template>

<script setup>
import opnformConfig from '~/opnform.config.js'

definePageMeta({
  layout: 'default',
})

useOpnSeoMeta({
  title: 'Enterprise',
  description: 'Powerful and secure form builder for teams that need full control over data, security, and deployment.',
})

const securityFeatures = [
  {
    icon: 'i-heroicons-check-badge',
    title: 'Granular Access Control',
    description: 'Assign roles and permissions across teams, projects, and forms.',
  },
  {
    icon: 'i-heroicons-circle-stack',
    title: 'Data Residency Options',
    description: 'Keep data in your own servers or select EU/US data centers for compliance.',
  },
  {
    icon: 'i-heroicons-lock-closed',
    title: 'Encryption at Rest & In Transit',
    description: 'Every submission is securely stored and transmitted.',
  },
  {
    icon: 'i-heroicons-document-text',
    title: 'Audit Logs & Security SLAs',
    description: 'Get visibility and accountability at every step.',
  },
]

const enterpriseFeatures = [
  {
    icon: 'i-heroicons-credit-card',
    title: 'Payments',
    description: 'Collect payments securely with Stripe or PayPal, and automate invoices instantly.',
  },
  {
    icon: 'i-heroicons-pencil-square',
    title: 'E-Signatures',
    description: 'Capture legally binding signatures directly within your forms.',
  },
  {
    icon: 'i-heroicons-squares-2x2',
    title: 'Automations',
    description: 'Connect OpnForm to your existing tools — send submissions to CRMs, trigger webhooks, or automate workflows with Zapier or Make.',
  },
  {
    icon: 'i-heroicons-paint-brush',
    title: 'Custom Branding & Themes',
    description: 'Fully white-label your forms to match your brand.',
  },
  {
    icon: 'i-heroicons-user-group',
    title: 'Collaboration',
    description: 'Invite your entire team, manage permissions, and review submissions together.',
  },
  {
    icon: 'i-heroicons-cloud',
    title: 'Flexible Deployment',
    description: 'Use OpnForm Cloud for simplicity — or self-host for complete control over data and uptime.',
  },
]

const vipSupportBadges = [
  {
    icon: 'i-heroicons-lock-closed',
    label: 'Help migrating from another tool',
    badgeBg: 'bg-violet-50',
    iconColor: 'text-violet-600',
  },
  {
    icon: 'i-heroicons-wrench-screwdriver',
    label: 'Technical help for self hosting',
    badgeBg: 'bg-red-50',
    iconColor: 'text-red-600',
  },
  {
    icon: 'i-heroicons-user-circle',
    label: 'A dedicated Customer Success Manager will guide you to success',
    badgeBg: 'bg-blue-50',
    iconColor: 'text-blue-600',
  },
]

</script>
