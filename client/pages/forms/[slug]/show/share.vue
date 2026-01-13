<template>
  <div class="p-4 space-y-8">
    <div class="w-full max-w-4xl mx-auto flex flex-col gap-4">
      <!-- Status Warning Alert -->
      <UAlert
        v-if="form.visibility === 'draft'"
        icon="i-heroicons-information-circle"
        color="warning"
        variant="subtle"
        title="Draft Form"
        description="Only you can visit the form link while logged in. Visitors won't be able to access this form."
      />
      
      <UAlert
        v-else-if="form.visibility === 'closed'"
        icon="i-heroicons-lock-closed"
        color="warning"
        variant="subtle"
        title="Closed Form"
        description="People can access the form but it won't accept new submissions."
      />

      <!-- Primary Section: Share Link -->
      <UCard class="shadow">
        <div class="space-y-4">
          <div class="flex flex-wrap items-center gap-2">
            <h2 class="text-xl grow font-semibold">Share Your Form</h2>
            <div class="flex gap-2">
              <AdvancedFormUrlSettingsPopover
                v-model="shareFormConfig"
                :form="props.form"
              />
            </div>
          </div>
          <p class="text-neutral-600 text-sm">
            Share your form with anyone by copying this link. You can use it on social media,
            in messages, or send it via email to reach your audience.
          </p>
          
          <CopyContent
            :content="share_url"
            :is-draft="form.visibility == 'draft'"
            class="w-full"
          />
          
          <div class="flex gap-2 pt-2"> 
            <SocialShareButton
              :form="props.form"
              :share-url="share_url"
            />

            <FormQrCode
              :form="props.form"
              :extra-query-param="shareUrlForQueryParams"
            />
          </div>
        </div>
      </UCard>

      <div class="flex gap-2"> 
        <UrlFormPrefill
          :form="props.form"
          :extra-query-param="shareUrlForQueryParams"
        />
        <RegenerateFormLink
          :form="props.form"
        />
      </div>

      <!-- Secondary Section: Embed -->
      <UCard class="shadow-sm">        
        <div class="space-y-4">
          <div class="flex flex-wrap items-center gap-2">
            <h2 class="text-xl grow font-semibold">Embed Form</h2>
            <div class="flex gap-2">
              <UButton
                label="SDK Docs"
                icon="i-heroicons-code-bracket"
                variant="outline"
                color="neutral"
                to="https://docs.opnform.com/front-end/javascript-sdk#custom-code-integration"
                target="_blank"
              />
            </div>
          </div>
          <p class="text-neutral-600 text-sm">
            Embed your form on your website by copying the HTML code below.
          </p>

          <EmbedCode
            :form="props.form"
            :extra-query-param="shareUrlForQueryParams"
          />
          
          <EmbedFormAsPopupModal
            :form="props.form"
            class="max-w-fit"
          />
        </div>
      </UCard>
    </div>
  </div>
</template>

<script setup>
import EmbedCode from "~/components/pages/forms/show/EmbedCode.vue"
import FormQrCode from "~/components/pages/forms/show/FormQrCode.vue"
import UrlFormPrefill from "~/components/pages/forms/show/UrlFormPrefill.vue"
import AdvancedFormUrlSettingsPopover from "~/components/pages/forms/show/AdvancedFormUrlSettingsPopover.vue"
import SocialShareButton from "~/components/pages/forms/show/SocialShareButton.vue"
import EmbedFormAsPopupModal from "~/components/pages/forms/show/EmbedFormAsPopupModal.vue"
import CopyContent from "~/components/open/forms/components/CopyContent.vue"

const props = defineProps({
  form: { type: Object, required: true },
})

definePageMeta({
  middleware: "auth",
})

useOpnSeoMeta({
  title: props.form ? "Share Form - " + props.form.title : "Share Form",
})

const shareFormConfig = ref({
  auto_submit: false,
})

const shareUrlForQueryParams = computed(() => {
  let queryStr = ""
  for (const [key, value] of Object.entries(shareFormConfig.value)) {
    if (value && value !== "false" && value !== false) {
      queryStr +=
        "&" + encodeURIComponent(key) + "=" + encodeURIComponent(value)
    }
  }
  return queryStr.slice(1)
})

const share_url = computed(() => {
  return shareUrlForQueryParams.value
    ? props.form.share_url + "?" + shareUrlForQueryParams.value
    : props.form.share_url + shareUrlForQueryParams.value
})
</script>
