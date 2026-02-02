<template>
  <div>
    <section class="py-12 bg-white">
      <div class="px-4 mx-auto sm:px-6 lg:px-8 max-w-7xl">
        <div class="max-w-3xl mx-auto text-center">
          <h1 class="text-4xl font-semibold tracking-tight text-neutral-950 sm:text-5xl lg:text-6xl">
            Simple pricing
            <br class="hidden sm:block">
            based on your needs
          </h1>
          <p class="max-w-2xl mx-auto mt-4 text-base font-medium leading-7 text-neutral-600 sm:mt-6 sm:text-lg sm:leading-8">
            No locked-in contracts. Upgrade or cancel anytime.
          </p>

          <div class="flex items-center justify-center gap-3 mt-10">
            <span class="text-sm font-semibold text-neutral-700">
              Monthly
            </span>
            <button
              type="button"
              class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
              :class="pricingIsYearly ? 'bg-blue-600' : 'bg-neutral-200'"
              @click="pricingIsYearly = !pricingIsYearly"
              aria-label="Toggle yearly billing"
            >
              <span
                class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform"
                :class="pricingIsYearly ? 'translate-x-5' : 'translate-x-1'"
              />
            </button>
            <span class="text-sm font-semibold text-neutral-700">
              Annually
            </span>
            <span class="hidden sm:inline-flex items-center px-3 py-1 text-xs font-semibold text-blue-700 bg-blue-50 rounded-full">
              Save 15% with yearly billing
            </span>
          </div>
        </div>

        <div class="grid grid-cols-1 gap-6 mt-12 lg:grid-cols-4">
          <!-- Free -->
          <div class="p-6 bg-white border shadow-sm rounded-3xl border-neutral-200">
            <div class="flex items-center gap-3">
              <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-blue-50">
                <Icon class="w-5 h-5 text-blue-600" name="heroicons:bolt-20-solid" />
              </span>
              <h3 class="text-lg font-semibold text-neutral-950">Free</h3>
            </div>

            <p class="mt-3 text-sm font-medium leading-6 text-neutral-600">
              Start collecting unlimited responses with no friction.
            </p>

            <div class="mt-6">
              <p class="flex items-end gap-2">
                <span class="text-4xl font-semibold tracking-tight text-neutral-950">$0</span>
              </p>
            </div>

            <div class="mt-6">
              <UButton
                v-if="!authenticated"
                class="w-full justify-center"
                variant="soft"
                :to="{ name: 'register' }"
                label="Get started free"
              />
              <UButton
                v-else
                class="w-full justify-center"
                :to="{ name: 'home' }"
                label="Go to app"
              />
            </div>

            <div class="pt-6 mt-8 border-t border-neutral-200">
              <p class="text-sm font-semibold text-neutral-950">Includes</p>
              <ul class="mt-4 space-y-3 text-sm font-medium text-neutral-700">
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Unlimited forms & submissions
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  File uploads (basic quota)
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Form logic & validation
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Computed fields (calculations)
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Pre-fills, URL parameters
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Multi-user access (all admins, no roles)
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  1 workspace only
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Branding required
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Community support
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  API
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Basic integrations
                </li>
              </ul>
            </div>
          </div>

          <!-- Pro (Most popular) -->
          <div class="relative p-6 bg-white border-2 shadow-sm rounded-3xl border-blue-600">
            <div class="absolute top-6 right-6">
              <span class="inline-flex items-center px-3 py-1 text-xs font-semibold text-blue-700 bg-blue-50 rounded-full ring-1 ring-blue-200">
                Most popular
              </span>
            </div>

            <div class="flex items-center gap-3">
              <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-blue-50">
                <Icon class="w-5 h-5 text-blue-600" name="heroicons:sparkles-20-solid" />
              </span>
              <h3 class="text-lg font-semibold text-neutral-950">Pro</h3>
            </div>

            <p class="mt-3 text-sm font-medium leading-6 text-neutral-600">
              A polished, professional experience for serious work.
            </p>

            <div class="mt-6">
              <p class="flex items-end gap-2">
                <span class="text-4xl font-semibold tracking-tight text-neutral-950">
                  {{ pricingIsYearly ? '$25' : '$29' }}
                </span>
                <span class="pb-1 text-sm font-semibold text-neutral-600">/mo</span>
              </p>
            </div>

            <div class="mt-6">
              <UButton
                class="w-full justify-center"
                label="Get started free"
                @click.prevent="handleProCta"
              />
            </div>

            <div class="pt-6 mt-8 border-t border-neutral-200">
              <p class="text-sm font-semibold text-neutral-950">Everything in Free, plus</p>
              <ul class="mt-4 space-y-3 text-sm font-medium text-neutral-700">
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Remove branding
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Custom domains
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Custom SMTP
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Discord, Slack, Telegram
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Password-protected forms
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Form expiration
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Captcha
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Multiple workspaces
                </li>
              </ul>
            </div>
          </div>

          <!-- Business -->
          <div class="p-6 bg-white border shadow-sm rounded-3xl border-neutral-200">
            <div class="flex items-center gap-3">
              <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-blue-50">
                <Icon class="w-5 h-5 text-blue-600" name="heroicons:building-office-20-solid" />
              </span>
              <h3 class="text-lg font-semibold text-neutral-950">Business</h3>
            </div>

            <p class="mt-3 text-sm font-medium leading-6 text-neutral-600">
              Built for teams and agencies managing forms at scale.
            </p>

            <div class="mt-6">
              <p class="flex items-end gap-2">
                <span class="text-4xl font-semibold tracking-tight text-neutral-950">
                  {{ pricingIsYearly ? '$67' : '$79' }}
                </span>
                <span class="pb-1 text-sm font-semibold text-neutral-600">/mo</span>
              </p>
            </div>

            <div class="mt-6">
              <UButton
                class="w-full justify-center"
                variant="soft"
                label="Get started free"
                @click.prevent="contactUs"
              />
            </div>

            <div class="pt-6 mt-8 border-t border-neutral-200">
              <p class="text-sm font-semibold text-neutral-950">Everything in Pro, plus</p>
              <ul class="mt-4 space-y-3 text-sm font-medium text-neutral-700">
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Multi-user with roles & permissions
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Advanced branding (CSS, fonts, favicons)
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Higher file upload size limits
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Priority support
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Partial submissions
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Versioning
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Analytics dashboard
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Advanced integrations
                </li>
              </ul>
            </div>
          </div>

          <!-- Enterprise -->
          <div class="p-6 bg-white border shadow-sm rounded-3xl border-neutral-200">
            <div class="flex items-center gap-3">
              <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-blue-50">
                <Icon class="w-5 h-5 text-blue-600" name="heroicons:globe-alt-20-solid" />
              </span>
              <h3 class="text-lg font-semibold text-neutral-950">Enterprise</h3>
            </div>

            <p class="mt-3 text-sm font-medium leading-6 text-neutral-600">
              Enterprise-grade security, compliance, and control.
            </p>

            <div class="mt-6">
              <p class="flex items-end gap-2">
                <span class="text-4xl font-semibold tracking-tight text-neutral-950">
                  {{ pricingIsYearly ? '$213+' : '$250+' }}
                </span>
                <span class="pb-1 text-sm font-semibold text-neutral-600">/mo</span>
              </p>
            </div>

            <div class="mt-6">
              <UButton
                class="w-full justify-center"
                variant="soft"
                label="Request a quote"
                @click.prevent="contactUs"
              />
            </div>

            <div class="pt-6 mt-8 border-t border-neutral-200">
              <p class="text-sm font-semibold text-neutral-950">Everything in Business, plus</p>
              <ul class="mt-4 space-y-3 text-sm font-medium text-neutral-700">
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  SSO (SAML, OIDC, LDAP)
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  Audit logs & compliance features
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  External storage support
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  White-label hosting option
                </li>
                <li class="flex gap-3">
                  <Icon class="w-5 h-5 text-emerald-600" name="heroicons:check-20-solid" />
                  SLA & onboarding support
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </section>
    
    <section
      class="relative py-12 bg-gradient-to-b from-white to-neutral-100 sm:py-16 lg:py-20 xl:py-24"
    >
      <div class="relative px-4 mx-auto sm:px-6 lg:px-8 max-w-7xl">
        <div class="max-w-4xl mx-auto text-center">
          <h1
            class="text-4xl font-semibold tracking-tight text-neutral-900 sm:text-5xl lg:text-6xl"
          >
            Simple, transparent pricing. No surprises.
          </h1>
          <p
            class="max-w-2xl mx-auto mt-4 text-base font-medium leading-7 text-neutral-500 sm:mt-5 sm:text-xl sm:leading-9"
          >
            Just like our codebase, our pricing is 100% transparent. One flat
            price for all features. No hidden fees.
          </p>
        </div>
      </div>
    </section>

    <pricing-table>
      <template #pricing-table="{isYearly}">
        <div class="flex gap-x-2 items-center">
          <Icon
            class="inline w-5 h-5 text-blue-500"
            name="heroicons:user-plus-16-solid"
          />
          <p>
            Extra users for {{ isYearly?'$5/month':'$6/month' }}
          </p>
        </div>
      </template>
    </pricing-table>

    <section class="py-12 bg-white sm:py-16 lg:py-24 xl:py-24">
      <div class="px-4 mx-auto sm:px-6 lg:px-8 max-w-7xl">
        <div class="max-w-2xl mx-auto text-center">
          <h2
            class="text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl lg:leading-tight"
          >
            <span class="text-blue-600">99%</span> of features are available to
            all users for free and without limits.
          </h2>
        </div>

        <div
          class="grid max-w-5xl grid-cols-2 mx-auto mt-12 text-center gap-y-8 gap-x-4 sm:grid-cols-3 md:gap-x-12 md:text-left sm:mt-16"
        >
          <div class="flex flex-col items-center gap-3 md:flex-row">
            <svg
              aria-hidden="true"
              class="w-6 h-6 shrink-0 stroke-blue-600"
              viewBox="0 0 24 24"
              fill="none"
              xmlns="http://www.w3.org/2000/svg"
            >
              <path
                d="M3 9H21M7.8 3H16.2C17.8802 3 18.7202 3 19.362 3.32698C19.9265 3.6146 20.3854 4.07354 20.673 4.63803C21 5.27976 21 6.11984 21 7.8V16.2C21 17.8802 21 18.7202 20.673 19.362C20.3854 19.9265 19.9265 20.3854 19.362 20.673C18.7202 21 17.8802 21 16.2 21H7.8C6.11984 21 5.27976 21 4.63803 20.673C4.07354 20.3854 3.6146 19.9265 3.32698 19.362C3 18.7202 3 17.8802 3 16.2V7.8C3 6.11984 3 5.27976 3.32698 4.63803C3.6146 4.07354 4.07354 3.6146 4.63803 3.32698C5.27976 3 6.11984 3 7.8 3Z"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
            <p
              class="text-base font-semibold sm:text-lg lg:text-xl text-neutral-950"
            >
              Unlimited forms
            </p>
          </div>

          <div class="flex flex-col items-center gap-3 md:flex-row">
            <svg
              aria-hidden="true"
              class="w-6 h-6 shrink-0 stroke-blue-600"
              viewBox="0 0 24 24"
              fill="none"
              xmlns="http://www.w3.org/2000/svg"
            >
              <path
                d="M9 3.5V2M5.06066 5.06066L4 4M5.06066 13L4 14.0607M13 5.06066L14.0607 4M3.5 9H2M8.5 8.5L12.6111 21.2778L15.5 18.3889L19.1111 22L22 19.1111L18.3889 15.5L21.2778 12.6111L8.5 8.5Z"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
            <p
              class="text-base font-semibold sm:text-lg lg:text-xl text-neutral-950"
            >
              Unlimited submissions
            </p>
          </div>

          <div class="flex flex-col items-center gap-3 md:flex-row">
            <svg
              aria-hidden="true"
              class="w-6 h-6 shrink-0 stroke-blue-600"
              viewBox="0 0 24 24"
              fill="none"
              xmlns="http://www.w3.org/2000/svg"
            >
              <path
                d="M17.8 10C18.9201 10 19.4802 10 19.908 9.78201C20.2843 9.59027 20.5903 9.28431 20.782 8.90798C21 8.48016 21 7.92011 21 6.8V6.2C21 5.0799 21 4.51984 20.782 4.09202C20.5903 3.7157 20.2843 3.40973 19.908 3.21799C19.4802 3 18.9201 3 17.8 3L6.2 3C5.0799 3 4.51984 3 4.09202 3.21799C3.71569 3.40973 3.40973 3.71569 3.21799 4.09202C3 4.51984 3 5.07989 3 6.2L3 6.8C3 7.9201 3 8.48016 3.21799 8.90798C3.40973 9.28431 3.71569 9.59027 4.09202 9.78201C4.51984 10 5.07989 10 6.2 10L17.8 10Z"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
              <path
                d="M17.8 21C18.9201 21 19.4802 21 19.908 20.782C20.2843 20.5903 20.5903 20.2843 20.782 19.908C21 19.4802 21 18.9201 21 17.8V17.2C21 16.0799 21 15.5198 20.782 15.092C20.5903 14.7157 20.2843 14.4097 19.908 14.218C19.4802 14 18.9201 14 17.8 14L6.2 14C5.0799 14 4.51984 14 4.09202 14.218C3.71569 14.4097 3.40973 14.7157 3.21799 15.092C3 15.5198 3 16.0799 3 17.2L3 17.8C3 18.9201 3 19.4802 3.21799 19.908C3.40973 20.2843 3.71569 20.5903 4.09202 20.782C4.51984 21 5.07989 21 6.2 21H17.8Z"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
            <p
              class="text-base font-semibold sm:text-lg lg:text-xl text-neutral-950"
            >
              Unlimited fields
            </p>
          </div>

          <div class="flex flex-col items-center gap-3 md:flex-row">
            <svg
              aria-hidden="true"
              class="w-6 h-6 shrink-0 stroke-blue-600"
              viewBox="0 0 24 24"
              fill="none"
              xmlns="http://www.w3.org/2000/svg"
            >
              <path
                d="M9 11L12 14L22 4M16 3H7.8C6.11984 3 5.27976 3 4.63803 3.32698C4.07354 3.6146 3.6146 4.07354 3.32698 4.63803C3 5.27976 3 6.11984 3 7.8V16.2C3 17.8802 3 18.7202 3.32698 19.362C3.6146 19.9265 4.07354 20.3854 4.63803 20.673C5.27976 21 6.11984 21 7.8 21H16.2C17.8802 21 18.7202 21 19.362 20.673C19.9265 20.3854 20.3854 19.9265 20.673 19.362C21 18.7202 21 17.8802 21 16.2V12"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
            <p
              class="text-base font-semibold sm:text-lg lg:text-xl text-neutral-950"
            >
              Multiple input types
            </p>
          </div>

          <div class="flex flex-col items-center gap-3 md:flex-row">
            <svg
              aria-hidden="true"
              class="w-6 h-6 shrink-0 stroke-blue-600"
              viewBox="0 0 24 24"
              fill="none"
              xmlns="http://www.w3.org/2000/svg"
            >
              <path
                d="M22 11V8.2C22 7.0799 22 6.51984 21.782 6.09202C21.5903 5.71569 21.2843 5.40973 20.908 5.21799C20.4802 5 19.9201 5 18.8 5H5.2C4.0799 5 3.51984 5 3.09202 5.21799C2.71569 5.40973 2.40973 5.71569 2.21799 6.09202C2 6.51984 2 7.0799 2 8.2V11.8C2 12.9201 2 13.4802 2.21799 13.908C2.40973 14.2843 2.71569 14.5903 3.09202 14.782C3.51984 15 4.0799 15 5.2 15H11M12 10H12.005M17 10H17.005M7 10H7.005M19.25 17V15.25C19.25 14.2835 18.4665 13.5 17.5 13.5C16.5335 13.5 15.75 14.2835 15.75 15.25V17M12.25 10C12.25 10.1381 12.1381 10.25 12 10.25C11.8619 10.25 11.75 10.1381 11.75 10C11.75 9.86193 11.8619 9.75 12 9.75C12.1381 9.75 12.25 9.86193 12.25 10ZM17.25 10C17.25 10.1381 17.1381 10.25 17 10.25C16.8619 10.25 16.75 10.1381 16.75 10C16.75 9.86193 16.8619 9.75 17 9.75C17.1381 9.75 17.25 9.86193 17.25 10ZM7.25 10C7.25 10.1381 7.13807 10.25 7 10.25C6.86193 10.25 6.75 10.1381 6.75 10C6.75 9.86193 6.86193 9.75 7 9.75C7.13807 9.75 7.25 9.86193 7.25 10ZM15.6 21H19.4C19.9601 21 20.2401 21 20.454 20.891C20.6422 20.7951 20.7951 20.6422 20.891 20.454C21 20.2401 21 19.9601 21 19.4V18.6C21 18.0399 21 17.7599 20.891 17.546C20.7951 17.3578 20.6422 17.2049 20.454 17.109C20.2401 17 19.9601 17 19.4 17H15.6C15.0399 17 14.7599 17 14.546 17.109C14.3578 17.2049 14.2049 17.3578 14.109 17.546C14 17.7599 14 18.0399 14 18.6V19.4C14 19.9601 14 20.2401 14.109 20.454C14.2049 20.6422 14.3578 20.7951 14.546 20.891C14.7599 21 15.0399 21 15.6 21Z"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
            <p
              class="text-base font-semibold sm:text-lg lg:text-xl text-neutral-950"
            >
              Form password
            </p>
          </div>

          <div class="flex flex-col items-center gap-3 md:flex-row">
            <svg
              aria-hidden="true"
              class="w-6 h-6 shrink-0 stroke-blue-600"
              viewBox="0 0 24 24"
              fill="none"
              xmlns="http://www.w3.org/2000/svg"
            >
              <path
                d="M18 15C16.3431 15 15 16.3431 15 18C15 19.6569 16.3431 21 18 21C19.6569 21 21 19.6569 21 18C21 16.3431 19.6569 15 18 15ZM18 15V8C18 7.46957 17.7893 6.96086 17.4142 6.58579C17.0391 6.21071 16.5304 6 16 6H13M6 9C7.65685 9 9 7.65685 9 6C9 4.34315 7.65685 3 6 3C4.34315 3 3 4.34315 3 6C3 7.65685 4.34315 9 6 9ZM6 9V21"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
            <p
              class="text-base font-semibold sm:text-lg lg:text-xl text-neutral-950"
            >
              Webhooks
            </p>
          </div>

          <div class="flex flex-col items-center gap-3 md:flex-row">
            <svg
              aria-hidden="true"
              class="w-6 h-6 shrink-0 stroke-blue-600"
              viewBox="0 0 24 24"
              fill="none"
              xmlns="http://www.w3.org/2000/svg"
            >
              <path
                d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
            <p
              class="text-base font-semibold sm:text-lg lg:text-xl text-neutral-950"
            >
              API Access Tokens
            </p>
          </div>

          <div class="flex flex-col items-center gap-3 md:flex-row">
            <svg
              aria-hidden="true"
              class="w-6 h-6 shrink-0 stroke-blue-600"
              viewBox="0 0 24 24"
              fill="none"
              xmlns="http://www.w3.org/2000/svg"
            >
              <path
                d="M21 8H3M16 2V5M8 2V5M12 18V12M9 15H15M7.8 22H16.2C17.8802 22 18.7202 22 19.362 21.673C19.9265 21.3854 20.3854 20.9265 20.673 20.362C21 19.7202 21 18.8802 21 17.2V8.8C21 7.11984 21 6.27976 20.673 5.63803C20.3854 5.07354 19.9265 4.6146 19.362 4.32698C18.7202 4 17.8802 4 16.2 4H7.8C6.11984 4 5.27976 4 4.63803 4.32698C4.07354 4.6146 3.6146 5.07354 3.32698 5.63803C3 6.27976 3 7.11984 3 8.8V17.2C3 18.8802 3 19.7202 3.32698 20.362C3.6146 20.9265 4.07354 21.3854 4.63803 21.673C5.27976 22 6.11984 22 7.8 22Z"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
            <p
              class="text-base font-semibold sm:text-lg lg:text-xl text-neutral-950"
            >
              Closing date
            </p>
          </div>

          <div
            class="flex flex-col items-center col-span-2 gap-3 md:flex-row sm:col-span-1"
          >
            <svg
              aria-hidden="true"
              class="w-6 h-6 shrink-0 stroke-blue-600"
              viewBox="0 0 24 24"
              fill="none"
              xmlns="http://www.w3.org/2000/svg"
            >
              <path
                d="M12 6C12.5523 6 13 5.55228 13 5C13 4.44772 12.5523 4 12 4C11.4477 4 11 4.44772 11 5C11 5.55228 11.4477 6 12 6Z"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
              <path
                d="M12 13C12.5523 13 13 12.5523 13 12C13 11.4477 12.5523 11 12 11C11.4477 11 11 11.4477 11 12C11 12.5523 11.4477 13 12 13Z"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
              <path
                d="M12 20C12.5523 20 13 19.5523 13 19C13 18.4477 12.5523 18 12 18C11.4477 18 11 18.4477 11 19C11 19.5523 11.4477 20 12 20Z"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
              <path
                d="M19 6C19.5523 6 20 5.55228 20 5C20 4.44772 19.5523 4 19 4C18.4477 4 18 4.44772 18 5C18 5.55228 18.4477 6 19 6Z"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
              <path
                d="M19 13C19.5523 13 20 12.5523 20 12C20 11.4477 19.5523 11 19 11C18.4477 11 18 11.4477 18 12C18 12.5523 18.4477 13 19 13Z"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
              <path
                d="M19 20C19.5523 20 20 19.5523 20 19C20 18.4477 19.5523 18 19 18C18.4477 18 18 18.4477 18 19C18 19.5523 18.4477 20 19 20Z"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
              <path
                d="M5 6C5.55228 6 6 5.55228 6 5C6 4.44772 5.55228 4 5 4C4.44772 4 4 4.44772 4 5C4 5.55228 4.44772 6 5 6Z"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
              <path
                d="M5 13C5.55228 13 6 12.5523 6 12C6 11.4477 5.55228 11 5 11C4.44772 11 4 11.4477 4 12C4 12.5523 4.44772 13 5 13Z"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
              <path
                d="M5 20C5.55228 20 6 19.5523 6 19C6 18.4477 5.55228 18 5 18C4.44772 18 4 18.4477 4 19C4 19.5523 4.44772 20 5 20Z"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
            <p
              class="text-base font-semibold sm:text-lg lg:text-xl text-neutral-950"
            >
              And much more...
            </p>
          </div>
        </div>

        <div
          class="max-w-3xl p-6 mx-auto mt-12 sm:mt-16 bg-yellow-50 ring ring-inset ring-yellow-200 rounded-2xl"
        >
          <div class="flex items-start gap-4">
            <svg
              aria-hidden="true"
              class="w-8 h-8 shrink-0 stroke-yellow-500"
              viewBox="0 0 24 24"
              fill="none"
              xmlns="http://www.w3.org/2000/svg"
            >
              <path
                d="M12 21L11.8999 20.8499C11.2053 19.808 10.858 19.287 10.3991 18.9098C9.99286 18.5759 9.52476 18.3254 9.02161 18.1726C8.45325 18 7.82711 18 6.57482 18H5.2C4.07989 18 3.51984 18 3.09202 17.782C2.71569 17.5903 2.40973 17.2843 2.21799 16.908C2 16.4802 2 15.9201 2 14.8V6.2C2 5.07989 2 4.51984 2.21799 4.09202C2.40973 3.71569 2.71569 3.40973 3.09202 3.21799C3.51984 3 4.07989 3 5.2 3H5.6C7.84021 3 8.96031 3 9.81596 3.43597C10.5686 3.81947 11.1805 4.43139 11.564 5.18404C12 6.03968 12 7.15979 12 9.4M12 21V9.4M12 21L12.1001 20.8499C12.7947 19.808 13.142 19.287 13.6009 18.9098C14.0071 18.5759 14.4752 18.3254 14.9784 18.1726C15.5467 18 16.1729 18 17.4252 18H18.8C19.9201 18 20.4802 18 20.908 17.782C21.2843 17.5903 21.5903 17.2843 21.782 16.908C22 16.4802 22 15.9201 22 14.8V6.2C22 5.07989 22 4.51984 21.782 4.09202C21.5903 3.71569 21.2843 3.40973 20.908 3.21799C20.4802 3 19.9201 3 18.8 3H18.4C16.1598 3 15.0397 3 14.184 3.43597C13.4314 3.81947 12.8195 4.43139 12.436 5.18404C12 6.03968 12 7.15979 12 9.4"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
            <div>
              <p class="text-lg font-semibold text-yellow-600">
                Nonprofit & Student Discount — 50%
              </p>
              <p class="mt-1 text-base font-medium leading-7 text-yellow-600">
                Whether your nonprofit is large or small, OpnForm's online Form
                Builder helps your organization help others. It takes just a few
                minutes to create and publish your forms online. As an exclusive
                benefit, we offer nonprofits & students a 50-percent discount!
              </p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="py-12 bg-white">
      <FeatureComparison />
    </section>

    <section class="py-12 bg-white">
      <Testimonials />
    </section>

    <section class="py-12 bg-white">
      <div class="px-4 mx-auto sm:px-6 lg:px-8 max-w-7xl">
        <div class="max-w-3xl mx-auto text-center">
          <h2 class="text-4xl font-semibold tracking-tight text-neutral-950 sm:text-5xl">
            Self-host OpnForm
          </h2>
          <p class="max-w-2xl mx-auto mt-4 text-base font-medium leading-7 text-neutral-600 sm:text-lg sm:leading-8">
            The self-hosted commercial licenses are the same price as hosted plans.
          </p>
        </div>

        <div class="max-w-5xl mx-auto mt-12 space-y-8 sm:mt-16">
          <div class="p-8 bg-white border shadow-sm rounded-3xl border-neutral-200">
            <div class="grid grid-cols-1 gap-10 lg:grid-cols-2 lg:gap-12">
              <div>
                <div class="flex items-center gap-3">
                  <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-blue-50">
                    <Icon class="w-5 h-5 text-blue-600" name="heroicons:users-20-solid" />
                  </span>
                  <h3 class="text-xl font-semibold text-neutral-950">
                    Community Edition
                  </h3>
                </div>

                <p class="mt-4 text-base font-medium leading-7 text-neutral-600">
                  Perfect for individuals and teams who want full control and community-driven software.
                </p>

                <div class="mt-8">
                  <p class="text-5xl font-semibold tracking-tight text-neutral-950">
                    Free OSS
                  </p>
                </div>

                <div class="mt-8">
                  <UButton
                    variant="outline"
                    label="Request a quote"
                    @click.prevent="contactUs"
                  />
                </div>
              </div>

              <div class="lg:pt-2">
                <ul class="space-y-4 text-sm font-medium text-neutral-700">
                  <li
                    v-for="feature in communityEditionFeatures"
                    :key="feature"
                    class="flex gap-3"
                  >
                    <Icon
                      class="w-5 h-5 text-emerald-600"
                      name="heroicons:check-20-solid"
                    />
                    {{ feature }}
                  </li>
                </ul>
              </div>
            </div>
          </div>

          <div class="p-8 bg-white border shadow-sm rounded-3xl border-neutral-200">
            <div class="grid grid-cols-1 gap-10 lg:grid-cols-2 lg:gap-12">
              <div>
                <div class="flex items-center gap-3">
                  <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-blue-50">
                    <Icon class="w-5 h-5 text-blue-600" name="heroicons:shield-check-20-solid" />
                  </span>
                  <h3 class="text-xl font-semibold text-neutral-950">
                    Enterprise License
                  </h3>
                </div>

                <p class="mt-4 text-base font-medium leading-7 text-neutral-600">
                  Built for organizations that need governance, customization, and long-term reliability.
                </p>

                <div class="mt-8">
                  <p class="flex items-end gap-3">
                    <span class="text-5xl font-semibold tracking-tight text-neutral-950">
                      $1,990
                    </span>
                    <span class="pb-2 text-sm font-semibold text-neutral-600">
                      /year per instance
                    </span>
                  </p>
                </div>

                <div class="mt-8">
                  <UButton
                    variant="outline"
                    label="Request a quote"
                    @click.prevent="contactUs"
                  />
                </div>
              </div>

              <div class="lg:pt-2">
                <ul class="space-y-4 text-sm font-medium text-neutral-700">
                  <li
                    v-for="feature in enterpriseLicenseFeatures"
                    :key="feature"
                    class="flex gap-3"
                  >
                    <Icon
                      class="w-5 h-5 text-emerald-600"
                      name="heroicons:check-20-solid"
                    />
                    {{ feature }}
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="py-12 bg-white sm:py-16 lg:py-20 xl:py-24">
      <div class="px-4 mx-auto sm:px-6 lg:px-8 max-w-7xl">
        <div class="max-w-3xl mx-auto text-center">
          <p class="text-sm font-semibold tracking-wide text-blue-600 uppercase">
            Frequently Asked Questions
          </p>
          <h2 class="mt-4 text-4xl font-semibold tracking-tight text-neutral-950 sm:text-5xl">
            Everything you need to
            <br class="hidden sm:block">
            know
          </h2>
          <p class="max-w-2xl mx-auto mt-4 text-base font-medium leading-7 text-neutral-600 sm:text-lg sm:leading-8">
            Find answers about plans, onboarding, roles, and how teams use our tool every day.
          </p>
        </div>

        <div class="max-w-4xl mx-auto mt-12 sm:mt-16">
          <div class="space-y-4">
            <div
              v-for="(q, i) in faqs"
              :key="q.question"
              class="bg-neutral-50 rounded-2xl"
            >
              <button
                type="button"
                class="w-full px-6 py-5 text-left rounded-2xl focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                @click="toggleFaq(i)"
              >
                <div class="flex items-center gap-4">
                  <span class="w-10 text-sm font-semibold text-neutral-400">
                    {{ String(i + 1).padStart(2, '0') }}
                  </span>
                  <div class="flex items-center justify-between flex-1 gap-4">
                    <p class="text-base font-semibold text-neutral-900">
                      {{ q.question }}
                    </p>
                    <span class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-white text-neutral-500">
                      <Icon
                        v-if="openFaqIndex !== i"
                        class="w-5 h-5"
                        name="heroicons:plus-20-solid"
                      />
                      <Icon
                        v-else
                        class="w-5 h-5"
                        name="heroicons:x-mark-20-solid"
                      />
                    </span>
                  </div>
                </div>
              </button>

              <div
                v-if="openFaqIndex === i"
                class="px-6 pb-6"
              >
                <div class="pl-14">
                  <p class="text-sm font-medium leading-6 text-neutral-600">
                    {{ q.answer }}
                  </p>
                </div>
              </div>
            </div>
          </div>

          <div class="mt-10 text-center sm:mt-12">
            <p class="text-sm font-medium text-neutral-600">
              Didn't find the answer?
              <a
                href="#"
                class="font-semibold text-blue-600 hover:underline"
                @click.prevent="contactUs"
              >Contact Us</a>
            </p>
          </div>
        </div>
      </div>
    </section>

    <OpenFormFooter />
  </div>
</template>

<script setup>
import FeatureComparison from "~/components/pages/pricing/FeatureComparison.vue"
import PricingTable from "../components/pages/pricing/PricingTable.vue"
import { useIsAuthenticated } from "~/composables/useAuthFlow"

definePageMeta({
  layout: "default",
  middleware: ["self-hosted"],
})

useOpnSeoMeta({
  title: "Pricing",
  description:
    "All of our core features are free, and there is no quantity limit. You can also created more advanced and customized forms with OpnForms Pro.",
})

const { openSubscriptionModal } = useAppModals()
const { isAuthenticated: authenticated } = useIsAuthenticated()

const pricingIsYearly = ref(true)

const communityEditionFeatures = [
  "Unlimited forms & submissions",
  "File uploads, logic, computed fields",
  "Pre-fills, URL parameters",
  "Multi-user access allowed (all admins, no roles)",
  "Unlimited workspaces",
  "Branding required",
  "Community support",
]

const enterpriseLicenseFeatures = [
  "Branding removal",
  "Custom SMTP",
  "Advanced integrations (when ready)",
  "Multi-workspace support",
  "Multi-user with roles & permissions",
  "SSO, audit logs, compliance features",
  "White-labeling & theming",
  "Packaged updates + migration tooling",
  "Priority support",
]

const openFaqIndex = ref(2)
const faqs = [
  {
    question: "Is there any submission limit?",
    answer:
      "No — submissions are unlimited on all plans. The Free plan gives you access to most features without restrictive usage caps.",
  },
  {
    question: "Are integrations included in the Free plan?",
    answer:
      "Yes — basic integrations like webhooks and API access are available on the Free plan. Some advanced integrations are available on higher tiers.",
  },
  {
    question: "Can I hide the OpnForm branding?",
    answer:
      "Yes. You can remove the “Made with OpnForm” footer and add your own branding on the Pro plan or higher.",
  },
  {
    question: "Is there a difference between monthly and yearly billing?",
    answer:
      "Yearly billing is discounted compared to paying monthly. You’ll be billed once per year and save versus the monthly plan.",
  },
  {
    question: "How can I pay for my subscription?",
    answer:
      "We support card payments via Stripe. You’ll get invoices/receipts automatically for your records.",
  },
  {
    question: "Do you offer discounts for non-profits or education?",
    answer:
      "Yes — we offer discounted pricing for non-profits and students. Contact us and we’ll help you get set up.",
  },
  {
    question: "Can I cancel my subscription anytime?",
    answer:
      "Yes. You can cancel anytime from the billing portal. Your subscription remains active until the end of the current billing period.",
  },
  {
    question: "Can I switch between plans?",
    answer:
      "Yes — you can upgrade or downgrade at any time. Changes apply immediately, and billing adjusts accordingly.",
  },
  {
    question: "Do you offer refunds?",
    answer:
      "If something isn’t working as expected, reach out and we’ll do our best to help. Refunds are handled case-by-case.",
  },
  {
    question: "What’s included when I self-host OpnForm?",
    answer:
      "Self-hosting includes the core OpnForm app and lets you run it on your own infrastructure. Some hosted-only services (like managed billing) may not apply.",
  },
  {
    question: "Do you offer a free trial of paid features?",
    answer:
      "We don’t currently offer an automated trial, but you can contact us if you’d like to evaluate a paid plan for your team.",
  },
  {
    question: "Is there an API, and is it free?",
    answer:
      "Yes — OpnForm has an API and API access tokens. They’re available on the Free plan, with higher tiers unlocking more advanced capabilities.",
  },
  {
    question: "Can I collaborate with my team?",
    answer:
      "Yes — multi-user collaboration is supported. Higher tiers add roles and permissions for larger teams.",
  },
]

const handleProCta = () => {
  if (!authenticated.value) {
    return navigateTo({ name: "register" })
  }
  openSubscriptionModal({ plan: "default", yearly: pricingIsYearly.value })
}

const contactUs = () => {
  useCrisp().openAndShowChat()
}

const toggleFaq = (index) => {
  openFaqIndex.value = openFaqIndex.value === index ? null : index
}
</script>
