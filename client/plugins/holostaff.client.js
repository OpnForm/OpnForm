/**
 * Holostaff: an in-product success manager for form creators.
 *
 * Off by default. The plugin does nothing, loads nothing, and talks to
 * nobody unless an operator sets both NUXT_PUBLIC_HOLOSTAFF_TENANT_ID
 * and NUXT_PUBLIC_HOLOSTAFF_SOURCE_ID. The SDK sits behind a dynamic
 * import, so with the ids unset no visitor ever fetches its chunk.
 *
 * What it does when it is on: reports which part of the journey the
 * creator is in, so the copilot knows whether someone is signing up,
 * building their first form, or looking at plans. Everything else
 * (stall detection, what to say, whether to say anything at all) is
 * decided from the journey map, not from this file.
 *
 * Docs: https://docs.holostaff.ai
 */

// Journey stages, by the route the creator is on. First match wins.
const STAGE_ROUTES = [
  [/^\/(login|register)$/, "mutual_commit"],
  [/^\/forms\/create\/guest$/, "onboarding"],
  [/^\/(home|forms\/create)$/, "adoption"],
  [/^\/forms\/[^/]+\/show\/(share|submissions)$/, "adoption"],
  [/^\/pricing$/, "expansion"],
]

function stageForPath(path) {
  const match = STAGE_ROUTES.find(([pattern]) => pattern.test(path))
  return match ? match[1] : null
}

export default defineNuxtPlugin(() => {
  const { holostaffTenantId, holostaffSourceId } = useRuntimeConfig().public

  // Not configured: stay out of the way entirely.
  if (!holostaffTenantId || !holostaffSourceId) {
    return
  }

  // Creator surfaces only. A public form page belongs to the person
  // answering it, and embedded forms live on someone else's site.
  const router = useRouter()
  if (useIsIframe() || router.currentRoute.value.name === "forms-slug") {
    return
  }

  import("@holostaff/sdk")
    .then(({ holostaff }) => {
      holostaff.init({
        tenantId: holostaffTenantId,
        sourceId: holostaffSourceId,
      })

      let currentStage = null
      const markStage = (path) => {
        const stage = stageForPath(path)
        if (stage && stage !== currentStage) {
          currentStage = stage
          holostaff.markStageEntry(stage)
        }
      }

      markStage(router.currentRoute.value.path)
      router.afterEach((to) => markStage(to.path))
    })
    .catch((error) => {
      console.warn("Holostaff did not load", error)
    })
})
