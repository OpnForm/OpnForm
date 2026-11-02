export function useGithubStarPrompt() {
  const pendingFormSlug = useState('github-star-prompt-form', () => null)

  const isSelfHosted = computed(() => !!useFeatureFlag('self_hosted', false))

  function queuePrompt(response) {
    if (isSelfHosted.value && response.users_first_form === true) {
      pendingFormSlug.value = response.form.slug
    }
  }

  function dismissPrompt() {
    pendingFormSlug.value = null
  }

  return { pendingFormSlug, queuePrompt, dismissPrompt }
}
