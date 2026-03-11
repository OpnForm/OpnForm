<template>
  <div class="mx-auto max-w-266">
    <div class="text-center">
      <h2
        class="text-4xl sm:text-5xl sm:leading-14 tracking-[-1%] font-semibold text-gray-950"
      >
        Feature Comparison
      </h2>
      <p
        class="mt-4 text-base font-normal tracking-[-1.1%] leading-7 text-gray-600"
      >
        Compare the features of the different plans and choose the one that best
        suits your needs.
      </p>
    </div>

    <div class="mt-12 sm:mt-16 overflow-x-auto">
      <table class="w-full min-w-108.75border-collapse">
        <thead>
          <tr class="border-b border-neutral-200">
            <th class="py-4 pr-6 text-left text-sm font-semibold text-gray-600">
              &nbsp;
            </th>
            <th
              v-for="(plan, planIndex) in plans"
              :key="planIndex"
              class="p-6 text-center"
            >
              <div class="text-xl leading-7 font-medium text-gray-950">
                {{ plan.label }}
              </div>
              <div
                class="mt-0.5 text-base leading-7 tracking-[-1.1%] font-medium text-gray-600"
              >
                ({{ plan.priceLabel }})
              </div>
            </th>
          </tr>
        </thead>

        <tbody>
          <template v-for="section in sections" :key="section.title">
            <tr class="bg-white border-b border-neutral-200">
              <th
                colspan="5"
                class="pt-8 pb-4 pr-6 text-left text-xl leading-7 font-medium text-gray-950"
              >
                {{ section.title }}
              </th>
            </tr>

            <tr
              v-for="(row, rowIndex) in section.rows"
              :key="rowIndex"
              class="bg-white"
            >
              <th
                class="py-5 pr-6 text-left text-sm leading-5 tracking-[-0.6%] font-medium text-gray-700"
              >
                {{ row.label }}
              </th>

              <td
                v-for="(plan, planIndex) in plans"
                :key="planIndex"
                class="py-5 px-6 text-center"
              >
                <div class="flex items-center justify-center gap-2">
                  <template v-if="row.values?.[planIndex] === true">
                    <Icon
                      class="w-5 h-5 text-emerald-600"
                      name="heroicons:check-20-solid"
                    />
                  </template>

                  <template
                    v-else-if="
                      row.values?.[planIndex] === false ||
                      row.values?.[planIndex] == null
                    "
                  >
                    <span class="text-sm font-medium text-gray-300">—</span>
                  </template>

                  <template v-else-if="row.values?.[planIndex] === 'soon'">
                    <Icon
                      title="Coming soon..."
                      class="w-5 h-5 text-amber-500"
                      name="heroicons:clock-20-solid"
                    />
                  </template>

                  <template v-else>
                    <span class="text-sm font-medium text-gray-700">
                      {{ row.values?.[planIndex] }}
                    </span>
                  </template>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
const plans = [
  { key: "free", label: "Free", priceLabel: "$0" },
  { key: "pro", label: "Pro", priceLabel: "$29" },
  { key: "business", label: "Business", priceLabel: "$79" },
  { key: "enterprise", label: "Enterprise", priceLabel: "$250+" },
];

const sections = [
  {
    title: "Core Form Capabilities",
    rows: [
      {
        label: "Unlimited forms & submissions",
        values: [true, true, true, true],
      },
      {
        label: "File uploads",
        values: ["(10MB)", true, "(1GB)", "(configurable)"],
      },
      {
        label: "Form logic & validation",
        values: [true, true, true, true],
      },
      {
        label: "Computed fields (calculations)",
        values: [true, true, true, true],
      },
      {
        label: "Pre-fills, URL params",
        values: [true, true, true, true],
      },
    ],
  },
  {
    title: "Collaboration",
    rows: [
      {
        label: "Multi-user access",
        values: [
          "(all admins)",
          "(all admins)",
          "(roles & permissions)",
          "(roles + SSO)",
        ],
      },
      {
        label: "Workspaces",
        values: ["1", "1", "Multiple", "Multiple"],
      },
    ],
  },
  {
    title: "Branding",
    rows: [
      {
        label: "Branding removal",
        values: [false, true, true, true],
      },
      {
        label: "Custom domain",
        values: [false, true, true, true],
      },
      {
        label: "Advanced branding (CSS/fonts)",
        values: [false, false, true, true],
      },
      {
        label: "White-label hosting",
        values: [false, false, false, true],
      },
    ],
  },
  {
    title: "Delivery",
    rows: [
      {
        label: "Custom SMTP",
        values: [false, true, true, true],
      },
    ],
  },
  {
    title: "Security & Access Control",
    rows: [
      {
        label: "Security (password/IP/expiry)",
        values: [false, true, true, true],
      },
      {
        label: "SSO (SAML, OIDC, LDAP)",
        values: [false, false, false, true],
      },
    ],
  },
  {
    title: "Integrations",
    rows: [
      {
        label: "Basic integrations (Zapier, etc.)",
        values: [false, true, true, true],
      },
      {
        label: "Advanced integrations (HubSpot, Salesforce, Airtable)",
        values: [false, false, "soon", true],
      },
    ],
  },
  {
    title: "Data & Insights",
    rows: [
      {
        label: "Analytics dashboard",
        values: [false, false, "soon", true],
      },
      {
        label: "Partial submissions / draft saving",
        values: [false, false, "soon", true],
      },
    ],
  },
  {
    title: "Compliance",
    rows: [
      {
        label: "Audit logs & compliance",
        values: [false, false, false, true],
      },
      {
        label: "External storage (S3, GCS)",
        values: [false, false, false, true],
      },
    ],
  },
  {
    title: "Support & Services",
    rows: [
      {
        label: "Priority support",
        values: [false, false, true, "(SLA)"],
      },
      {
        label: "SLA & onboarding",
        values: [false, false, false, true],
      },
    ],
  },
];
</script>
