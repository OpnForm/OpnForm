import { ref, onUnmounted } from "vue"

export const useConfetti = () => {
  let timeoutId = ref(null)
  const nuxtApp = useNuxtApp()
  let confettiPromise = null

  function loadConfetti() {
    const existingConfetti = nuxtApp.vueApp.config.globalProperties.$confetti
    if (existingConfetti) return Promise.resolve(existingConfetti)
    if (confettiPromise) return confettiPromise

    confettiPromise = import('vue-confetti').then(({ default: moduleDefault }) => {
      // The UMD build can expose its plugin under a second default export.
      const VueConfetti = moduleDefault.install ? moduleDefault : moduleDefault.default
      nuxtApp.vueApp.use(VueConfetti)
      return nuxtApp.vueApp.config.globalProperties.$confetti
    })

    return confettiPromise
  }

  function play(duration = 3000) {
    return loadConfetti().then((confetti) => {
      confetti.start({ defaultSize: 6 })
      timeoutId.value = setTimeout(() => {
        confetti.stop()
      }, duration)
    })
  }

  onUnmounted(() => {
    if (timeoutId.value) clearTimeout(timeoutId.value)
  })

  return {
    play,
  }
}
