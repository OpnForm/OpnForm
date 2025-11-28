<template>
    <copy-content
      :content="embedCode"
      label="Copy Code"
      tracking-event="embed_code_copy_click"
      :tracking-properties="{form_id: form.id, form_slug: form.slug}"
    />
</template>

<script>
/* eslint-disable */
import CopyContent from "../../../open/forms/components/CopyContent.vue"
import { appUrl } from "~/lib/utils.js"

export default {
  name: "EmbedCode",
  components: { CopyContent },
  props: {
    form: { type: Object, required: true },
    extraQueryParam: { type: String, default: "" },
  },

  data: () => ({
    autoresizeIframe: false,
  }),

  computed: {
    embedCode() {
      // eslint-disable no-useless-escape
      const isFocused = this.form?.presentation_style === 'focused'
      return isFocused
        ? this.iframeCode
        : `${this.iframeCode}<script type="text/javascript" onload="initEmbed('${this.form.slug}')" src="${appUrl("/widgets/iframe.min.js")}"><\/script>`
    },
    iframeCode() {
      const share_url = this.extraQueryParam
        ? this.form.share_url + "?" + this.extraQueryParam
        : this.form.share_url + this.extraQueryParam
      const isFocused = this.form?.presentation_style === 'focused'
      const style = isFocused
        ? 'border:none;width:100%;height:700px;max-height:90vh;'
        : 'border:none;width:100%;'
      return (
        '<iframe style="' + style + '" id="' + this.form.slug + '" src="' + share_url + '"></iframe>'
      )
    }
  },

  methods: {},
}
</script>
