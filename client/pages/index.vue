<template>
  <div>
    <section
      class="bg-gradient-to-b relative from-white to-neutral-100 py-8 sm:py-16"
    >
      <div class="absolute inset-0">
        <img
          class="w-full h-full object-cover object-top"
          src="/img/pages/ai_form_builder/background-pattern.svg"
          alt="Page abstract background"
        >
      </div>

      <div
        class="px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto relative -mb-32 md:-mb-52 lg:-mb-72"
      >
        <div class="flex justify-center mb-5">
          <div class="relative flex items-center shadow-xs bg-white gap-x-4 rounded-full px-4 py-1 text-sm leading-6 text-neutral-600 ring ring-neutral-900/10 hover:ring-neutral-900/20"
          >
            <span class="font-semibold text-neutral-500">We're Open-Source</span><span
              class="h-4 w-px bg-neutral-900/10"
              aria-hidden="true"
            />
            <TrackClick
              name="welcome_github_click"
            >
              <a
                target="_blank"
                class="flex items-center gap-x-1 hover:no-underline"
                href="https://github.com/OpnForm/OpnForm"
              >
              <span
                class="absolute inset-0"
                aria-hidden="true"
              />
              Star us on GitHub
              <svg
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
                aria-hidden="true"
                class="-mr-2 h-5 w-5 text-neutral-400"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M8.25 4.5l7.5 7.5-7.5 7.5"
                />
              </svg>
              </a>
            </TrackClick>
          </div>
        </div>
        <div class="max-w-4xl mx-auto text-center">
          <h1
            class="text-4xl sm:text-5xl lg:text-6xl font-semibold text-neutral-900 tracking-tight"
          >
            Build
            <span
              class="bg-clip-text text-transparent bg-gradient-to-r from-blue-600 to-blue-400"
            >beautiful forms</span>
            <br>
            in seconds
          </h1>
          <p
            class="mt-4 sm:mt-5 text-base leading-7 sm:text-xl sm:leading-9 font-medium text-neutral-500"
          >
            Create beautiful forms and share them anywhere. It's super fast, you
            don't need to know how to code. Get started
            <span class="font-semibold">for free</span>!
          </p>

          <div class="mt-8 flex justify-center">
            <UButton
              v-if="!authenticated"
              class="mr-1"
              :to="{ name: 'forms-create-guest' }"
              trailing-icon="i-heroicons-arrow-right-20-solid"
              label="Create a form for FREE"
            />
            <UButton
              v-else
              class="mr-1"
              :to="{ name: 'forms-create' }"
              trailing-icon="i-heroicons-arrow-right-20-solid"
              label="Create a form for FREE"
            />
          </div>

          <div class="justify-center flex gap-2 mt-10">
            <div class="flex items-center text-neutral-400 text-sm">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="2"
                stroke="currentColor"
                class="w-4 h-4 mr-1 ticks"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M4.5 12.75l6 6 9-13.5"
                />
              </svg>
              <span>Unlimited forms</span>
            </div>
            <div class="flex items-center text-neutral-400 text-sm">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="2"
                stroke="currentColor"
                class="w-4 h-4 mr-1 ticks"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M4.5 12.75l6 6 9-13.5"
                />
              </svg>
              <span> Unlimited fields </span>
            </div>
            <div class="flex text-neutral-400 text-sm">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="2"
                stroke="currentColor"
                class="w-4 h-4 mr-1 ticks"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M4.5 12.75l6 6 9-13.5"
                />
              </svg>
              <span>Unlimited responses</span>
            </div>
          </div>
        </div>

        <div
          class="w-full mt-12 relative px-6 mx-auto max-w-4xl sm:px-10 lg:px-0 z-10 flex items-center justify-center"
        >
          <div
                          class="-m-2 rounded-xl bg-blue-900/5 p-2 backdrop-blur-xs ring ring-inset ring-blue-900/10 lg:-m-4 lg:rounded-2xl lg:p-4 w-full"
          >
            <img
              src="/img/pages/welcome/product-cover.jpg"
              sizes="320px sm:650px lg:896px"
              alt="Product screenshot"
              loading="lazy"
                                class="rounded-sm w-full shadow-2xl ring ring-neutral-900/10"
            >
          </div>
        </div>
      </div>
    </section>

    <div class="flex flex-col bg-neutral-50 dark:bg-notion-dark">
      <div
        class="bg-white dark:bg-notion-dark-light pt-32 md:pt-52 lg:pt-72 pb-8"
      >
        <div class="md:max-w-5xl md:mx-auto w-full">
          <features class="pb-8" />
        </div>
      </div>

      <ai-feature class="bg-white -mb-56" />

      <more-features class="pt-56" />

      <pricing-table
        v-if="useFeatureFlag('billing.enabled')"
        class="pb-20"
        :home-page="true"
      >
        <template #pricing-table>
          <li class="flex gap-x-3">
            <NuxtLink
              :to="{ name: 'pricing' }"
              class="flex gap-3"
            >
              <div class="w-5" />
              Read more about our pricing
            </NuxtLink>
          </li>
        </template>
      </pricing-table>

      <!--      <div class="pt-20 pb-5 text-center bg-white dark:bg-notion-dark-light">-->
      <!--        <h3 class="font-semibold text-3xl">See what people are saying</h3>-->
      <!--        <p class="w-full mt-2 mb-8">-->
      <!--          These are the stories of our customers who have joined us with great pleasure when using this crazy feature.-->
      <!--        </p>-->
      <!--        <testimonials/>-->
      <!--      </div>-->

      <templates-slider class="max-w-full mb-12" />

      <div class="w-full bg-blue-900 p-12 md:p-24 text-center">
        <h4 class="font-semibold text-3xl text-white">
          Take your forms to the next level
        </h4>
        <p class="text-neutral-300 my-8">
          Generous, unlimited free plan.
        </p>
        <div class="mt-6 flex justify-center">
          <TrackClick
            name="welcome_create_form_click"
          >
            <UButton
              :to="{ name: 'forms-create-guest' }"
              trailing-icon="i-heroicons-arrow-right-20-solid"
              label="Create a form for FREE"
            />
          </TrackClick>
        </div>
        <div class="flex justify-center mt-6">
          <a
            target="_blank"
            :href="configLinks.twitter"
            class="mr-4"
          >
            <svg
              class="w-6 h-6 text-white"
              viewBox="0 0 24 20"
              fill="none"
              xmlns="http://www.w3.org/2000/svg"
            >
              <path
                d="M7.55016 19.7502C16.6045 19.7502 21.5583 12.2469 21.5583 5.74211C21.5583 5.53117 21.5536 5.31554 21.5442 5.1046C22.5079 4.40771 23.3395 3.5445 24 2.55554C23.1025 2.95484 22.1496 3.21563 21.1739 3.32898C22.2013 2.71315 22.9705 1.74572 23.3391 0.606011C22.3726 1.1788 21.3156 1.58286 20.2134 1.80085C19.4708 1.01181 18.489 0.48936 17.4197 0.314295C16.3504 0.13923 15.2532 0.321295 14.2977 0.832341C13.3423 1.34339 12.5818 2.15495 12.1338 3.14156C11.6859 4.12816 11.5754 5.23486 11.8195 6.29054C9.86249 6.19233 7.94794 5.68395 6.19998 4.79834C4.45203 3.91274 2.90969 2.66968 1.67297 1.14976C1.0444 2.23349 0.852057 3.51589 1.13503 4.73634C1.418 5.95678 2.15506 7.02369 3.19641 7.72023C2.41463 7.69541 1.64998 7.48492 0.965625 7.10617V7.1671C0.964925 8.30439 1.3581 9.40683 2.07831 10.287C2.79852 11.1672 3.80132 11.7708 4.91625 11.9952C4.19206 12.1934 3.43198 12.2222 2.69484 12.0796C3.00945 13.0577 3.62157 13.9131 4.44577 14.5266C5.26997 15.14 6.26512 15.4808 7.29234 15.5015C5.54842 16.8714 3.39417 17.6144 1.17656 17.6109C0.783287 17.6103 0.390399 17.5861 0 17.5387C2.25286 18.984 4.87353 19.7516 7.55016 19.7502Z"
                fill="currentColor"
              />
            </svg>
          </a>
          <a
            target="_blank"
            :href="configLinks.discord"
            class="mr-4"
          >
            <svg
              class="w-6 h-6 text-white"
              role="img"
              viewBox="0 0 24 24"
              xmlns="http://www.w3.org/2000/svg"
            >
              <title>Discord</title>
              <path
                fill="currentColor"
                d="M20.317 4.3698a19.7913 19.7913 0 00-4.8851-1.5152.0741.0741 0 00-.0785.0371c-.211.3753-.4447.8648-.6083 1.2495-1.8447-.2762-3.68-.2762-5.4868 0-.1636-.3933-.4058-.8742-.6177-1.2495a.077.077 0 00-.0785-.037 19.7363 19.7363 0 00-4.8852 1.515.0699.0699 0 00-.0321.0277C.5334 9.0458-.319 13.5799.0992 18.0578a.0824.0824 0 00.0312.0561c2.0528 1.5076 4.0413 2.4228 5.9929 3.0294a.0777.0777 0 00.0842-.0276c.4616-.6304.8731-1.2952 1.226-1.9942a.076.076 0 00-.0416-.1057c-.6528-.2476-1.2743-.5495-1.8722-.8923a.077.077 0 01-.0076-.1277c.1258-.0943.2517-.1923.3718-.2914a.0743.0743 0 01.0776-.0105c3.9278 1.7933 8.18 1.7933 12.0614 0a.0739.0739 0 01.0785.0095c.1202.099.246.1981.3728.2924a.077.077 0 01-.0066.1276 12.2986 12.2986 0 01-1.873.8914.0766.0766 0 00-.0407.1067c.3604.698.7719 1.3628 1.225 1.9932a.076.076 0 00.0842.0286c1.961-.6067 3.9495-1.5219 6.0023-3.0294a.077.077 0 00.0313-.0552c.5004-5.177-.8382-9.6739-3.5485-13.6604a.061.061 0 00-.0312-.0286zM8.02 15.3312c-1.1825 0-2.1569-1.0857-2.1569-2.419 0-1.3332.9555-2.4189 2.157-2.4189 1.2108 0 2.1757 1.0952 2.1568 2.419 0 1.3332-.9555 2.4189-2.1569 2.4189zm7.9748 0c-1.1825 0-2.1569-1.0857-2.1569-2.419 0-1.3332.9554-2.4189 2.1569-2.4189 1.2108 0 2.1757 1.0952 2.1568 2.419 0 1.3332-.946 2.4189-2.1568 2.4189Z"
              />
            </svg>
          </a>
          <TrackClick
            name="welcome_github_click"
          >
            <a
              target="_blank"
              :href="configLinks.github_url"
              class="mr-4"
            >
            <svg
              class="w-6 h-6 text-white"
              viewBox="0 0 24 24"
              fill="none"
              xmlns="http://www.w3.org/2000/svg"
            >
              <path
                fill-rule="evenodd"
                clip-rule="evenodd"
                d="M12 0C5.3724 0 0 5.3808 0 12.0204C0 17.3304 3.438 21.8364 8.2068 23.4252C8.8068 23.5356 9.0252 23.1648 9.0252 22.8456C9.0252 22.5612 9.0156 21.804 9.0096 20.802C5.6712 21.528 4.9668 19.1904 4.9668 19.1904C4.422 17.8008 3.6348 17.4312 3.6348 17.4312C2.5452 16.6872 3.7176 16.7016 3.7176 16.7016C4.9212 16.7856 5.5548 17.94 5.5548 17.94C6.6252 19.776 8.364 19.2456 9.0468 18.9384C9.1572 18.162 9.4668 17.6328 9.81 17.3328C7.146 17.0292 4.344 15.9972 4.344 11.3916C4.344 10.08 4.812 9.006 5.5788 8.166C5.4552 7.8624 5.0436 6.6396 5.6964 4.986C5.6964 4.986 6.7044 4.662 8.9964 6.2172C9.97532 5.95022 10.9853 5.81423 12 5.8128C13.02 5.8176 14.046 5.9508 15.0048 6.2172C17.2956 4.662 18.3012 4.9848 18.3012 4.9848C18.9564 6.6396 18.5436 7.8624 18.4212 8.166C19.1892 9.006 19.6548 10.08 19.6548 11.3916C19.6548 16.0092 16.848 17.0256 14.1756 17.3232C14.6064 17.694 14.9892 18.4272 14.9892 19.5492C14.9892 21.1548 14.9748 22.452 14.9748 22.8456C14.9748 23.1672 15.1908 23.5416 15.8004 23.424C18.19 22.6225 20.2672 21.0904 21.7386 19.0441C23.2099 16.9977 24.001 14.5408 24 12.0204C24 5.3808 18.6264 0 12 0Z"
                fill="currentColor"
              />
            </svg>
            </a>
          </TrackClick>
        </div>

        <p class="mt-12 text-white text-lg">
          The form below is an OpnForm, give it a try !
        </p>
        <div
          class="md:max-w-5xl md:mx-auto w-full bg-white rounded-md mt-6 p-4 shadow-lg"
        >
          <iframe
            class="mt-4"
            style="border: none; width: 100%"
            height="480px"
            src="https://opnform.com/forms/opnform-contact"
          />
        </div>
      </div>

      <open-form-footer class="dark:border-t border-t" />
    </div>
  </div>
</template>

<script setup>
import Features from "~/components/pages/welcome/Features.vue"
import MoreFeatures from "../components/pages/welcome/MoreFeatures.vue"
import PricingTable from "../components/pages/pricing/PricingTable.vue"
import AiFeature from "../components/pages/welcome/AiFeature.vue"
import TemplatesSlider from "../components/pages/welcome/TemplatesSlider.vue"
import opnformConfig from "~/opnform.config.js"
import TrackClick from "~/components/global/TrackClick.vue"
import { useIsAuthenticated } from '~/composables/useAuthFlow'

definePageMeta({
  layout: "default",
  middleware: ['root-redirect']
})

const { isAuthenticated: authenticated } = useIsAuthenticated()

const config = opnformConfig
const configLinks = computed(() => config.links)
</script>

<style lang="scss" scoped>
.customer-logo-container {
  max-width: 130px;
  width: 100%;
}

.ticks {
  color: #2563eb;
}

@screen md {
  #macbook-video {
    position: absolute;
    max-width: 84.8% !important;
    right: 0px;
    top: 6.8%;
  }
}
</style>
