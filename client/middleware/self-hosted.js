export default defineNuxtRouteMiddleware((to) => {
    if (useFeatureFlag('self_hosted')) {
        if (to.name === 'register' && to.query?.email && to.query?.invite_token) {
            return
        }
        if (to.name === 'ai-form-builder' && useFeatureFlag('ai_features')) {
            return
        }
        return navigateTo({ name: "index" })
    }
})
