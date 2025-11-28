<template>
  <nav
    v-if="hasNavbar"
    class="bg-white dark:bg-notion-dark border-b"
  >
    <div class="max-w-7xl mx-auto px-8">
      <div class="flex items-center justify-between h-14">
        <div class="flex items-center gap-2">
          <NuxtLink
            :to="{ name: user ? 'home' : 'index' }"
            class="flex-shrink-0 font-semibold hover:no-underline flex items-center"
          >
            <img
              src="/img/logo.svg"
              alt="notion tools logo"
              class="w-6 h-6"
            >
            <span
              class="ml-2 text-md hidden sm:inline text-black dark:text-white"
            >OpnForm</span>
          </NuxtLink>
          <WorkspaceDropdown class="ml-6">
            <template #default="{ workspace }">
              <button
                v-if="workspace"
                :class="navLinkClasses"
                class="flex items-center"
              >
                <WorkspaceIcon :workspace="workspace" />
                <p
                  class="hidden md:block max-w-10 truncate text-sm ml-2 text-neutral-800 dark:text-neutral-200"
                >
                  {{ workspace.name }}
                </p>
              </button>
            </template>
          </WorkspaceDropdown>
        </div>
        <div 
          class="hidden md:flex gap-x-2 ml-auto"
        >
          <NuxtLink
            v-if="user"
            :to="{ name: 'home' }"
            :class="navLinkClasses"
            class="hidden lg:block"
          >
            My Forms
          </NuxtLink>  
          <NuxtLink
            v-if="$route.name !== 'templates'"
            :to="{ name: 'templates' }"
            :class="navLinkClasses"
          >
            Templates
          </NuxtLink>
          <template v-if="appStore.featureBaseEnabled">
            <button
              v-if="user"
              :class="navLinkClasses"
              @click.prevent="openChangelog"
            >
              What's new? <span
                v-if="hasNewChanges"
                id="fb-update-badge"
                class="bg-blue-500 rounded-full px-2 ml-1 text-white"
              />
            </button>
            <a
              v-else
              :href="opnformConfig.links.changelog_url"
              target="_blank"
              :class="navLinkClasses"
            >
              What's new?
            </a>
          </template>
          <NuxtLink
            v-if="($route.name !== 'ai-form-builder' && user === null) && (!useFeatureFlag('self_hosted') && useFeatureFlag('ai_features'))"
            :to="{ name: 'ai-form-builder' }"
            :class="navLinkClasses"
            class="hidden lg:inline"
          >
            AI Form Builder
          </NuxtLink>
          <NuxtLink
            v-if="
              (useFeatureFlag('billing.enabled') &&
                (user === null || (user && workspace && !workspace.is_pro)) &&
                $route.name !== 'pricing') && !isSelfHosted
            "
            :to="{ name: 'pricing' }"
            :class="navLinkClasses"
          >
            <span
              v-if="user"
              class="text-primary"
            >Upgrade</span>
            <span v-else>Pricing</span>
          </NuxtLink>

          <NuxtLink
            :href="helpUrl"
            :class="navLinkClasses"
            target="_blank"
          >
            Help
          </NuxtLink>
        </div>
        <div
          class="hidden md:block pl-5 border-neutral-300 border-r h-5"
        />
        <div
          class="block"
        >
          <div class="flex items-center">
            <div class="ml-4 relative">
              <div class="relative inline-block text-left">
                <UserDropdown v-if="user">
                  <template #default="{ user }">
                    <button
                      id="dropdown-menu-button"
                      type="button"
                      :class="navLinkClasses"
                      class="flex items-center"
                      dusk="nav-dropdown-button"
                    >
                      <img
                        :src="user.photo_url"
                        class="rounded-full w-6 h-6"
                      >
                      <p class="ml-2 hidden sm:inline max-w-20 truncate">
                        {{ user.name }}
                      </p>
                    </button>
                  </template>
                </UserDropdown>
                <div
                  v-else
                  class="flex gap-2"
                >
                  <NuxtLink
                    v-if="$route.name !== 'login'"
                    :to="{ name: 'login' }"
                    :class="navLinkClasses"
                    active-class="text-neutral-800 dark:text-white"
                  >
                    Login
                  </NuxtLink>

                  <TrackClick
                    class="flex items-center"
                    v-if="!isSelfHosted"
                    name="nav_create_form_click"
                  >
                    <UButton
                      :to="{ name: 'forms-create-guest' }"
                      variant="outline"
                      color="primary"
                      trailing-icon="i-heroicons-arrow-right"
                      label="Create a form"
                    />
                  </TrackClick>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </nav>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from '#imports'

import WorkspaceDropdown from '../dashboard/WorkspaceDropdown.vue'
import WorkspaceIcon from '~/components/workspaces/WorkspaceIcon.vue'
import UserDropdown from '../dashboard/UserDropdown.vue'

import opnformConfig from '~/opnform.config.js'
import { useFeatureFlag } from '~/composables/useFeatureFlag'
import TrackClick from '~/components/global/TrackClick.vue'


// Stores & composables
const { current: workspace } = useCurrentWorkspace()
const appStore = useAppStore()

const { data: user } = useAuth().user()
const isIframe = useIsIframe()
const isSelfHosted = computed(() => useFeatureFlag('self_hosted'))
const route = useRoute()

// Get current form for forms-slug routes
const isFormSlugRoute = computed(() => route.name && route.name.startsWith('forms-slug'))
const formSlug = computed(() => isFormSlugRoute.value ? route.params.slug : null)
const { data: form } = useForms().detail(formSlug.value, {
  usePrivate: true,
  enabled: computed(() => !!formSlug.value)
})

// Constants / classes
const navLinkClasses =
  'border border-transparent hover:border-neutral-200 text-neutral-500 hover:text-neutral-800 hover:no-underline dark:hover:text-white py-1.5 px-3 hover:bg-neutral-50 rounded-md text-sm font-medium transition-colors w-full md:w-auto text-center md:text-left'

// Computed values
const helpUrl = computed(() => opnformConfig.links.help_url)

const hasNavbar = computed(() => {
  if (isIframe.value) return false

  if (route.name && route.name === 'forms-slug') {
    if (form.value || import.meta.server) {
      return false
    }
    // Form not found/404 case - show the navbar
    return true
  }
  return true
})

const hasNewChanges = computed(() => {
  if (import.meta.server || !window.Featurebase || !appStore.featureBaseEnabled) return false
  return window.Featurebase('unviewed_changelog_count') > 0
})

// Methods
function openChangelog() {
  if (import.meta.server || !window.Featurebase) return
  window.Featurebase('manually_open_changelog_popup')
}
</script>
