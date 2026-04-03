<template>
  <div class="mx-auto max-w-266">
    <div class="max-w-lg mx-auto text-center md:px-1">
      <!-- <p class="text-lg font-semibold leading-8 tracking-tight text-blue-500">
        Single or multi-page forms
      </p> -->
      <h2
        class="text-3xl sm:text-5xl sm:leading-14 font-semibold text-gray-950 tracking-[-1%]"
      >
        Templates for financial services
      </h2>
      <p
        class="mt-4 text-base font-normal text-gray-600 leading-7 tracking-[-1.1%]"
      >
        All templates are fully customizable — adapt them to your compliance and
        brand requirements in minutes.
      </p>
    </div>
    <!-- <div class="my-3 flex justify-center">
      <NuxtLink :to="{ name: 'templates' }">
        See all templates
        <svg
          class="h-4 w-4 inline"
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 20 20"
          fill="currentColor"
          aria-hidden="true"
        >
          <path
            fill-rule="evenodd"
            d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z"
            clip-rule="evenodd"
          />
        </svg>
      </NuxtLink>
    </div> -->

    <div
      v-if="sliderTemplates && sliderTemplates.length"
      class="mt-12 grid gap-6 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3"
    >
      <!-- <ul
        ref="templates-slider"
        class="flex justify-center md:justify-start animate-infinite-scroll"
      >
        <li
          v-for="template in sliderTemplates"
          :key="template.name"
          class="mx-4 w-72 h-auto"
        >
          <single-template :template="template" />
        </li>
      </ul> -->

      <single-template
        v-for="template in sliderTemplates"
        :key="template.slug"
        :template="template"
      />
    </div>
  </div>
</template>

<script>
import SingleTemplate from "../templates/SingleTemplate.vue"

export default {
  components: { SingleTemplate },
  setup() {
    const { list } = useTemplates()
    const { data: templates } = list({ limit: 10 })

    return {
      sliderTemplates: computed(() => {
        if (templates.value && templates.value.length) {
          return templates.value
        }

        return [
          {
            name: "KYC / Client Onboarding Form",
            slug: "kyc-client-onboarding-1",
            short_description: "Some text goes here...",
          },
          {
            name: "Loan Application Form",
            slug: "loan-application-form",
            short_description: "Some text goes here...",
          },
          {
            name: "Internal Audit Checklist",
            slug: "internal-audit-checklist",
            short_description: "Some text goes here...",
          },
          {
            name: "Expense Reimbursement Form",
            slug: "expense-reimbursement-form",
            short_description: "Some text goes here...",
          },
          {
            name: "Client Feedback Form",
            slug: "client-feedback-form",
            short_description: "Some text goes here...",
          },
          {
            name: "Some title",
            slug: "some-title",
            short_description: "Some text goes here...",
          },
        ]
      }),
    }
  },

  // watch: {
  //   sliderTemplates: {
  //     deep: true,
  //     handler() {
  //       this.$nextTick(() => {
  //         this.setInfinite();
  //       });
  //     },
  //   },
  // },

  mounted() {
    this.$nextTick(() => {
      this.setInfinite()
    })
  },

  // methods: {
  //   setInfinite() {
  //     const ul = this.$refs["templates-slider"];
  //     if (!ul || ul.nextSibling) return;

  //     ul.insertAdjacentHTML("afterend", ul.outerHTML);
  //     ul.nextSibling.setAttribute("aria-hidden", "true");
  //   },
  // },
}
</script>
