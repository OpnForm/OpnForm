<template>
  <div>
    <div class="mt-6 flex flex-col">
      <div class="w-full md:max-w-3xl md:mx-auto px-4 md:pt-16">
        <h1 class="sm:text-5xl">
          Privacy Policy
        </h1>
        <section class="my-8 space-y-4" aria-labelledby="plugin-privacy">
          <h2 id="plugin-privacy" class="text-2xl font-semibold">
            OpnForm plugin for ChatGPT and Codex
          </h2>
          <p>
            When you use the OpnForm plugin, OpnForm processes the tool inputs sent by ChatGPT or Codex
            and returns the results to OpenAI to carry out your request. The data involved depends on the action you take.
          </p>
          <ul class="list-disc space-y-2 pl-6">
            <li>
              Drafting and editing can involve form titles, descriptions, field labels and settings, default values,
              rules and formulas, draft versions, and temporary references and links for previews and editing.
            </li>
            <li>
              After you connect your account, the plugin can use workspace names, icons, identifiers and permissions,
              along with form identifiers, definitions, status, timestamps, links and activity counts to find and manage your forms.
              OpnForm uses OAuth to authorize access within your existing workspace permissions.
            </li>
            <li>
              Reading submissions can return response identifiers, status, dates, completion times and answers,
              including hidden or previously removed fields and temporary links to uploaded files.
              Answers depend on the form and can contain personal data entered by respondents.
              Statistics can include counts, aggregated results and per-field summaries containing answer values,
              submission identifiers or temporary file links. An export returns its job identifier, status, progress
              and a temporary link to a CSV
              containing the exported submissions, with answers for the form's configured fields and submission dates.
            </li>
          </ul>
          <p>
            OpnForm records operational details such as the tool name, whether the request was authenticated,
            its outcome and its duration to monitor reliability. Guest drafts expire after seven days.
            Download links expire at the time indicated by the export tool. Account forms and submissions are
            covered by the data retention and deletion provisions below.
          </p>
          <p>
            You can revoke the plugin's access in your OpnForm account settings. To manage data already shared
            with OpenAI, use the data controls of your ChatGPT or Codex account.
          </p>
        </section>
        <NotionPage
          :block-map="page.blocks"
          :loading="loading"
        />
      </div>
    </div>
    <open-form-footer />
  </div>
</template>

<script setup>
definePageMeta({
  middleware: ["self-hosted"]
})
useOpnSeoMeta({
  title: "Privacy Policy",
})
defineRouteRules({
  swr: 3600
})

const pageId = '9c97349ceda7455aab9b341d1ff70f79'
const notionCmsStore = useNotionCmsStore()
const loading = computed(() => notionCmsStore.loading)
await notionCmsStore.loadPage(pageId)
const page = notionCmsStore.getPage(pageId)
</script>
