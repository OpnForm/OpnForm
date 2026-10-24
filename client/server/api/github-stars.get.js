export default defineCachedEventHandler(async () => {
  try {
    const repository = await $fetch("https://api.github.com/repos/OpnForm/OpnForm", {
      headers: {
        accept: "application/vnd.github+json",
        "user-agent": "OpnForm",
        "x-github-api-version": "2022-11-28",
      },
    })

    return {
      stars: Number.isInteger(repository?.stargazers_count)
        ? repository.stargazers_count
        : null,
    }
  } catch (error) {
    console.warn("Failed to fetch OpnForm GitHub stars", error?.message)
    return { stars: null }
  }
}, {
  maxAge: 60 * 60,
  name: "opnform-github-stars",
  getKey: () => "OpnForm/OpnForm",
  swr: true,
})
