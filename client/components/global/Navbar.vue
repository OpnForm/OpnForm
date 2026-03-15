<template>
  <nav v-if="hasNavbar" class="bg-white dark:bg-notion-dark">
    <div class="max-w-7xl mx-auto px-8">
      <div class="flex items-center justify-between h-14">
        <div class="flex items-center gap-2">
          <NuxtLink
            :to="{ name: user ? 'home' : 'index' }"
            class="shrink-0 font-semibold hover:no-underline flex items-center"
          >
            <img src="/img/logo.svg" alt="notion tools logo" class="w-6 h-6" />
            <span
              class="ml-2 text-md hidden sm:inline text-gray-950 dark:text-white"
              >OpnForm</span
            >
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
                  class="hidden md:block max-w-10 truncate text-sm ml-2 text-gray-800 dark:text-gray-200"
                >
                  {{ workspace.name }}
                </p>
              </button>
            </template>
          </WorkspaceDropdown>
        </div>
        <div class="hidden md:flex gap-x-2 ml-auto">
          <NuxtLink
            v-if="user"
            :to="{ name: 'home' }"
            :class="navLinkClasses"
            class="hidden lg:block"
          >
            My Forms
          </NuxtLink>
          <div class="relative z-20" @mouseleave="isOpen = false">
            <button
              :class="navLinkClasses"
              class="flex items-center gap-1"
              @mouseenter="isOpen = true"
            >
              <span>Features</span>
              <Icon name="heroicons:chevron-down" class="w-2.5 h-4" />
            </button>

            <div
              v-if="isOpen"
              @mouseenter="isOpen = true"
              @mouseleave="isOpen = false"
              class="absolute left-0 top-full pt-2 w-56 bg-white shadow-lg rounded-md transition-all duration-150"
            >
              <NuxtLink to="#" :class="navLinkClasses" class="block px-4 py-2">
                Some links
              </NuxtLink>
              <NuxtLink to="#" :class="navLinkClasses" class="block px-4 py-2">
                Some links
              </NuxtLink>
              <NuxtLink to="#" :class="navLinkClasses" class="block px-4 py-2">
                Some links
              </NuxtLink>
            </div>
          </div>
          <NuxtLink
            v-if="$route.name !== 'enterprise'"
            :to="{ name: 'enterprise' }"
            :class="navLinkClasses"
          >
            Enterprise
          </NuxtLink>
          <NuxtLink
            v-if="$route.name !== 'integrations'"
            :to="{ name: 'integrations' }"
            :class="navLinkClasses"
          >
            Integrations
          </NuxtLink>
          <NuxtLink
            v-if="$route.name !== 'pricing'"
            :to="{ name: 'pricing' }"
            :class="navLinkClasses"
          >
            Pricing
          </NuxtLink>
          <NuxtLink
            v-if="
              $route.name !== 'ai-form-builder' &&
              user === null &&
              !useFeatureFlag('self_hosted') &&
              useFeatureFlag('ai_features')
            "
            :to="{ name: 'ai-form-builder' }"
            :class="navLinkClasses"
            class="hidden lg:inline"
          >
            AI Form Builder
          </NuxtLink>
          <NuxtLink
            v-if="
              useFeatureFlag('billing.enabled') &&
              $route.name !== 'pricing' &&
              !isSelfHosted
            "
            :to="{ name: 'pricing' }"
            :class="navLinkClasses"
          >
            <span
              v-if="user && workspace && !workspace.is_pro"
              class="text-primary"
              >Upgrade</span
            >
            <span v-else>Pricing</span>
          </NuxtLink>

          <NuxtLink
            :href="opnformConfig.links.tech_docs"
            :class="navLinkClasses"
            target="_blank"
          >
            Documentation
          </NuxtLink>

          <!-- <template v-if="appStore.featureBaseEnabled">
            <button
              v-if="user"
              :class="navLinkClasses"
              @click.prevent="openChangelog"
            >
              What's new?
              <span
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
          </template> -->
        </div>

        <div class="block">
          <div class="flex items-center">
            <div class="ml-12 relative">
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
                      <img :src="user.photo_url" class="rounded-full w-6 h-6" />
                      <p class="ml-2 hidden sm:inline max-w-20 truncate">
                        {{ user.name }}
                      </p>
                    </button>
                  </template>
                </UserDropdown>
                <div v-else class="flex gap-4">
                  <UButton
                    v-if="$route.name !== 'login'"
                    :to="{ name: 'login' }"
                    class="bg-gray-100 text-gray-600 text-sm leading-5 tracking-[-0.6%] font-medium border border-transparent hover:border-gray-200 hover:text-gray-950 dark:hover:text-white hover:bg-gray-50"
                    label="Login"
                  />

                  <TrackClick
                    class="flex items-center"
                    v-if="!isSelfHosted"
                    name="nav_create_form_click"
                  >
                    <UButton
                      :to="{ name: 'forms-create-guest' }"
                      color="primary"
                      trailing-icon="i-heroicons-arrow-up-right-20-solid"
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
import { computed } from "vue";
import { useRoute } from "#imports";

import WorkspaceDropdown from "../dashboard/WorkspaceDropdown.vue";
import WorkspaceIcon from "~/components/workspaces/WorkspaceIcon.vue";
import UserDropdown from "../dashboard/UserDropdown.vue";

import opnformConfig from "~/opnform.config.js";
import { useFeatureFlag } from "~/composables/useFeatureFlag";
import TrackClick from "~/components/global/TrackClick.vue";
import { ref } from "vue";

const isOpen = ref(false);

// Stores & composables
const { current: workspace } = useCurrentWorkspace();
const appStore = useAppStore();

const { data: user } = useAuth().user();
const isIframe = useIsIframe();
const isSelfHosted = computed(() => useFeatureFlag("self_hosted"));
const route = useRoute();

// Get current form for forms-slug routes
const isFormSlugRoute = computed(
  () => route.name && route.name.startsWith("forms-slug"),
);
const formSlug = computed(() =>
  isFormSlugRoute.value ? route.params.slug : null,
);
const { data: form } = useForms().detail(formSlug.value, {
  usePrivate: true,
  enabled: computed(() => !!formSlug.value),
});

// Constants / classes
const navLinkClasses =
  "border border-transparent hover:border-gray-200 text-gray-600 hover:text-gray-950 hover:no-underline dark:hover:text-white py-2.5 px-3 hover:bg-gray-50 rounded-md text-sm leading-5 tracking-[-0.6%] font-medium transition-colors w-full md:w-auto text-center md:text-left";

const hasNavbar = computed(() => {
  if (isIframe.value) return false;

  if (route.name && route.name === "forms-slug") {
    if (form.value || import.meta.server) {
      return false;
    }
    // Form not found/404 case - show the navbar
    return true;
  }
  return true;
});

const hasNewChanges = computed(() => {
  if (import.meta.server || !window.Featurebase || !appStore.featureBaseEnabled)
    return false;
  return window.Featurebase("unviewed_changelog_count") > 0;
});

// Methods
function openChangelog() {
  if (import.meta.server || !window.Featurebase) return;
  window.Featurebase("manually_open_changelog_popup");
}
</script>
