<template>
  <!-- This component doesn't render anything visible -->
</template>

<script setup>
const props = defineProps({
  form: {
    type: Object,
    required: true
  }
})

const provider = computed(() => props.form?.analytics?.provider)
const trackingId = computed(() => props.form?.analytics?.tracking_id)

const shouldInjectScripts = computed(() => {
  return import.meta.client && provider.value && trackingId.value
})

// Provider configurations with script generators and tracking handlers
const providerConfig = {
  meta_pixel: {
    getScripts: (id) => [{
      key: 'meta-pixel',
      innerHTML: `
        !function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
        n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t,s)}(window, document,'script',
        'https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', '${id}');
        fbq('track', 'PageView');
      `
    }],
    trackSubmit: () => window.fbq?.('track', 'Lead')
  },
  google_analytics: {
    getScripts: (id) => [
      { key: 'ga-external', src: `https://www.googletagmanager.com/gtag/js?id=${id}`, async: true },
      { key: 'ga-inline', innerHTML: `
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '${id}');
      `}
    ],
    trackSubmit: (form) => window.gtag?.('event', 'form_submit', { form_id: form?.id, form_slug: form?.slug })
  },
  gtm: {
    getScripts: (id) => [{
      key: 'gtm',
      innerHTML: `
        (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
        new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
        'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','${id}');
      `
    }],
    trackSubmit: (form) => window.dataLayer?.push({ event: 'form_submit', form_id: form?.id, form_slug: form?.slug })
  }
}

const headConfig = computed(() => {
  if (!shouldInjectScripts.value) return {}
  const config = providerConfig[provider.value]
  return config ? { script: config.getScripts(trackingId.value) } : {}
})

useHead(headConfig)

const trackFormSubmit = () => {
  if (!shouldInjectScripts.value) return
  providerConfig[provider.value]?.trackSubmit(props.form)
}

defineExpose({ trackFormSubmit })
</script>

