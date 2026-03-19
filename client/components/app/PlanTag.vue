<template>
  <UTooltip
    v-if="shouldDisplayTag"
    :text="tooltipText"
    class="inline normal-case"
  >
    <TrackClick
      name="plan_tag_click"
      :properties="{ tier: displayTier, title: upgradeModalTitle }"
    >
      <div
        :class="tagClasses"
        @click.stop="onClick"
      >
        {{ tagLabel }}
      </div>
    </TrackClick>
  </UTooltip>
</template>

<script setup>
import { computed } from "vue"
import TrackClick from "~/components/global/TrackClick.vue"

const props = defineProps({
  /**
   * Feature key to check (e.g., 'custom_domain', 'integrations.slack')
   * If provided, the tag will show the required tier for this feature.
   */
  feature: {
    type: String,
    default: null
  },
  requiredTier: {
    type: String,
    default: 'pro'
  },
  upgradeModalTitle: {
    type: String,
    default: null
  },
  upgradeModalDescription: {
    type: String,
    default: null
  }
})

const { openSubscriptionModal } = useAppModals()
const { data: user } = useAuth().user()
const { current: workspace } = useCurrentWorkspace()
const {
  currentWorkspaceTier,
  getRequiredTier,
  getTierDisplayName,
  tierMeetsRequirement
} = usePlanFeatures()

// Determine the display tier (either explicit or from feature)
const displayTier = computed(() => {
  if (props.requiredTier) return props.requiredTier
  if (props.feature) return getRequiredTier(props.feature)
  return 'pro' // Default fallback
})

// Check if we should display the tag
const shouldDisplayTag = computed(() => {
  if (!useFeatureFlag('billing.enabled')) return false
  if (!user.value || !workspace.value) return true

  // Show tag if current tier doesn't meet requirement
  return !tierMeetsRequirement(currentWorkspaceTier.value, displayTier.value)
})

// Tag label based on tier
const tagLabel = computed(() => {
  return getTierDisplayName(displayTier.value).toUpperCase()
})

// Tooltip text
const tooltipText = computed(() => {
  if (props.upgradeModalTitle) return props.upgradeModalTitle

  const tierName = getTierDisplayName(displayTier.value)
  return `You need a ${tierName} plan to use this feature`
})

// Dynamic tag classes based on tier
const tagClasses = computed(() => {
  const base = 'px-2 text-xs uppercase inline rounded-full font-semibold cursor-pointer'

  switch (displayTier.value) {
    case 'enterprise':
      return `${base} bg-purple-600 text-white`
    case 'business':
      return `${base} bg-orange-500 text-white`
    case 'pro':
    default:
      return `${base} bg-blue-500 text-white`
  }
})

function onClick() {
  openSubscriptionModal({
    modal_title: props.upgradeModalTitle,
    modal_description: props.upgradeModalDescription,
    plan: displayTier.value
  })
}
</script>
