<template>
  <div class="flex-1">
    <!-- START HERO -->
    <section
      class="bg-gradient-to-b relative from-white to-neutral-100 py-12 sm:py-16 lg:py-20 xl:py-24"
    >
      <div class="absolute inset-0">
        <img
          class="w-full h-full object-cover object-top"
          src="/img/pages/ai_form_builder/background-pattern.svg"
          alt=""
        >
      </div>

      <div class="px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto relative">
        <div class="max-w-4xl mx-auto text-center">
          <h1
            class="text-4xl sm:text-5xl lg:text-6xl font-semibold text-neutral-900 tracking-tight"
          >
            Say goodbye to tedious form building with OpnForm's new
            <span
              class="bg-clip-text text-transparent bg-gradient-to-r lg:block from-blue-600 to-blue-400"
            >AI-powered feature!</span>
          </h1>
          <p
            class="mt-4 sm:mt-5 text-base leading-7 sm:text-xl sm:leading-9 font-medium text-neutral-500"
          >
            Easily generate a fully working form in seconds with just a simple
            description.
          </p>

          <div class="mt-8 flex justify-center">
            <UButton
              class="mr-1"
              :to="{ name: authenticated ? 'forms-create' : 'forms-create-guest' }"
              trailing-icon="i-heroicons-arrow-right"
              label="Get started for free"
            />
          </div>
        </div>

        <div class="max-w-6xl mx-auto mt-12 sm:mt-16">
          <div
                          class="-m-2 rounded-xl bg-blue-900/5 p-2 backdrop-blur-xs ring ring-inset ring-blue-900/10 lg:-m-4 lg:rounded-2xl lg:p-4"
          >
            <video
                              class="rounded-sm ring ring-neutral-200 shadow-xl shadow-blue-600/10 ring-blue-900/10"
              controls
              autoplay
              loop
              muted
            >
              <source
                src="/video/opnform-ai.mp4"
                type="video/mp4"
              >

              This browser does not display the video tag.
            </video>
          </div>
        </div>

        <div
          class="grid lg:grid-cols-3 grid-cols-1 sm:max-w-lg lg:max-w-5xl sm:mx-auto gap-8 sm:gap-10 mt-12 sm:mt-16"
        >
          <div class="flex items-start gap-4">
            <img
              class="w-12 h-12 shrink-0"
              src="/img/pages/ai_form_builder/icon-fast.svg"
              alt=""
            >

            <div>
              <p class="text-md font-semibold text-neutral-900">
                Faster than Ever
              </p>
              <p class="text-base font-medium text-neutral-500 mt-2">
                Save time and effort by generating a form in seconds
              </p>
            </div>
          </div>

          <div class="flex items-start gap-4">
            <img
              class="w-12 h-12 shrink-0"
              src="/img/pages/ai_form_builder/icon-customization.svg"
              alt=""
            >

            <div>
              <p class="text-md font-semibold text-neutral-900">
                Customizations
              </p>
              <p class="text-base font-medium text-neutral-500 mt-2">
                Customize your form to your exact specifications
              </p>
            </div>
          </div>

          <div class="flex items-start gap-4">
            <img
              class="w-12 h-12 shrink-0"
              src="/img/pages/ai_form_builder/icon-browser.svg"
              alt=""
            >

            <div>
              <p class="text-md font-semibold text-neutral-900">
                No Coding Knowledge Required
              </p>
              <p class="text-base font-medium text-neutral-500 mt-2">
                No coding knowledge required and it's completely free to use
              </p>
            </div>
          </div>
        </div>
      </div>
    </section>
    <!-- END HERO -->

    <!-- START HOW IT WORKS -->
    <section class="bg-white py-12 sm:py-16 lg:py-20 xl:py-24">
      <div class="px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
        <div class="text-center max-w-3xl mx-auto">
          <h2 class="text-sm font-semibold text-blue-600">
            How Does It Work?
          </h2>
          <p
            class="text-3xl mt-4 sm:text-4xl lg:text-5xl font-semibold text-neutral-900 tracking-tight lg:leading-tight"
          >
            Save hours in just a few clicks
          </p>
          <p
            class="text-neutral-500 text-base leading-7 sm:text-lg sm:leading-8 font-medium mt-4"
          >
            Building forms has never been easier
          </p>
        </div>

        <div
          class="space-y-16 text-center lg:text-left sm:max-w-md sm:mx-auto lg:max-w-none lg:space-y-20 xl:space-y-24 mt-8 sm:mt-12 lg:mt-16"
        >
          <div
            class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-8 lg:gap-12"
          >
            <img
              class="w-full lg:flex-1 bg-neutral-300 lg:shrink-0 rounded-2xl ring ring-neutral-200 shadow-xs"
              src="/img/pages/ai_form_builder/step-1.svg"
              alt=""
            >

            <div
              class="w-16 h-16 rounded-full bg-blue-50 border-2 border-blue-200 hidden xl:inline-flex items-center justify-center text-blue-600 text-2xl font-semibold leading-none"
            >
              1
            </div>

            <div class="lg:flex-1 lg:shrink-0">
              <img
                class="w-auto h-16 hidden lg:block"
                src="/img/pages/ai_form_builder/icon-create.svg"
                alt=""
              >

              <h3
                class="text-2xl sm:text-3xl lg:text-4xl font-semibold text-neutral-900 lg:mt-8"
              >
                Building forms made easy
              </h3>
              <p
                class="text-base font-medium leading-7 sm:text-lg sm:leading-8 text-neutral-500 mt-4"
              >
                OpnForm's easy-to-use online form creator lets you create a
                beautiful web form in no time. Whether you need to create
                contact forms and registration forms for a landing page or an
                online order form for your business, you will no longer need to
                spend hours working on forms.
              </p>
            </div>
          </div>

          <div
            class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-8 lg:gap-12"
          >
            <img
                              class="w-full lg:flex-1 bg-neutral-300 lg:shrink-0 rounded-2xl ring ring-neutral-200 shadow-xs lg:order-3"
              src="/img/pages/ai_form_builder/step-2.svg"
              alt=""
            >
            <div
              class="w-16 h-16 lg:order-2 rounded-full bg-blue-50 border-2 border-blue-200 hidden xl:inline-flex items-center justify-center text-blue-600 text-2xl font-semibold leading-none"
            >
              2
            </div>

            <div class="lg:flex-1 lg:shrink-0 lg:order-1">
              <img
                class="w-auto h-16 hidden lg:block"
                src="/img/pages/ai_form_builder/icon-customization.svg"
                alt=""
              >

              <h3
                class="text-2xl sm:text-3xl lg:text-4xl font-semibold text-neutral-900 lg:mt-8"
              >
                Customized forms work wonders
              </h3>
              <p
                class="text-base font-medium leading-7 sm:text-lg sm:leading-8 text-neutral-500 mt-4"
              >
                Did you know that good-looking forms are more likely to convert
                more responses than ordinary ones? On OpnForm, you can customize
                your form design to the tiniest detail and match your brand
                image with your forms.
              </p>
            </div>
          </div>

          <div
            class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-8 lg:gap-12"
          >
            <img
              class="w-full lg:flex-1 bg-neutral-300 lg:shrink-0 rounded-2xl ring ring-neutral-200 shadow-xs"
              src="/img/pages/ai_form_builder/step-3.svg"
              alt=""
            >
            <div
              class="w-16 h-16 rounded-full bg-blue-50 border-2 border-blue-200 hidden xl:inline-flex items-center justify-center text-blue-600 text-2xl font-semibold leading-none"
            >
              3
            </div>

            <div class="lg:flex-1 lg:shrink-0">
              <img
                class="w-auto h-16 hidden lg:block"
                src="/img/pages/ai_form_builder/icon-share.svg"
                alt=""
              >

              <h3
                class="text-2xl sm:text-3xl lg:text-4xl font-semibold text-neutral-900 lg:mt-8"
              >
                Share your forms anywhere
              </h3>
              <p
                class="text-base font-medium leading-7 sm:text-lg sm:leading-8 text-neutral-500 mt-4"
              >
                You can share your forms anywhere using their unique weblink or
                embed them on your landing pages seamlessly. Furthermore, you
                can adjust your form’s privacy settings in order to reach the
                targeted audience and prevent others from viewing your form.
              </p>
            </div>
          </div>
        </div>
      </div>
    </section>
    <!-- END HOW IT WORKS -->

    <!-- START EXAMPLES -->
    <!--    <section class="bg-neutral-50 py-12 sm:py-16 lg:py-20 xl:py-24">-->
    <!--        <div class="px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">-->
    <!--          <div class="text-center max-w-3xl mx-auto">-->
    <!--            <h2 class="text-sm font-semibold text-blue-600">-->
    <!--              Examples-->
    <!--            </h2>-->
    <!--            <p-->
    <!--              class="text-3xl mt-4 sm:text-4xl lg:text-5xl font-semibold text-neutral-900 tracking-tight lg:leading-tight">-->
    <!--              What our user has created-->
    <!--            </p>-->
    <!--            <p class="text-neutral-500 text-base leading-7 sm:text-lg sm:leading-8 font-medium mt-4">-->
    <!--              Amet minim mollit non deserunt ullamco est sit aliqua dolor do amet sint. Velit officia consequat duis-->
    <!--              enim velit-->
    <!--              mollit.-->
    <!--            </p>-->
    <!--          </div>-->

    <!--          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mt-8 sm:mt-12 lg:mt-16">-->
    <!--            <div-->
    <!--              class="bg-white overflow-hidden rounded-2xl ring-1 ring-neutral-200 shadow-sm hover:shadow-lg hover:-translate-y-2 transition-all duration-150">-->
    <!--              <img class="w-full" src="/img/pages/ai_form_builder/examples-placeholder.png" alt=""/>
-->
    <!--              <div class="px-4 py-5 sm:p-6">-->
    <!--                <h3 class="text-lg font-semibold text-neutral-900">-->
    <!--                  Example 1-->
    <!--                </h3>-->
    <!--                <p class="text-base font-medium text-neutral-500 mt-2 line-clamp-2">-->
    <!--                  Nulla Lorem mollit cupidatat irure. Laborum magna nulla duis ullamco cillum dolor-->
    <!--                </p>-->
    <!--              </div>-->
    <!--            </div>-->

    <!--            <div-->
    <!--              class="bg-white overflow-hidden rounded-2xl ring-1 ring-neutral-200 shadow-sm hover:shadow-lg hover:-translate-y-2 transition-all duration-150">-->
    <!--              <img class="w-full" src="/img/pages/ai_form_builder/examples-placeholder.png" alt=""/>
-->
    <!--              <div class="px-4 py-5 sm:p-6">-->
    <!--                <h3 class="text-lg font-semibold text-neutral-900">-->
    <!--                  Example 2-->
    <!--                </h3>-->
    <!--                <p class="text-base font-medium text-neutral-500 mt-2 line-clamp-2">-->
    <!--                  Nulla Lorem mollit cupidatat irure. Laborum magna nulla duis ullamco cillum dolor-->
    <!--                </p>-->
    <!--              </div>-->
    <!--            </div>-->

    <!--            <div-->
    <!--              class="bg-white overflow-hidden rounded-2xl ring-1 ring-neutral-200 shadow-sm hover:shadow-lg hover:-translate-y-2 transition-all duration-150">-->
    <!--              <img class="w-full" src="/img/pages/ai_form_builder/examples-placeholder.png" alt=""/>
-->
    <!--              <div class="px-4 py-5 sm:p-6">-->
    <!--                <h3 class="text-lg font-semibold text-neutral-900">-->
    <!--                  Example 3-->
    <!--                </h3>-->
    <!--                <p class="text-base font-medium text-neutral-500 mt-2 line-clamp-2">-->
    <!--                  Nulla Lorem mollit cupidatat irure. Laborum magna nulla duis ullamco cillum dolor-->
    <!--                </p>-->
    <!--              </div>-->
    <!--            </div>-->

    <!--            <div-->
    <!--              class="bg-white overflow-hidden rounded-2xl ring-1 ring-neutral-200 shadow-sm hover:shadow-lg hover:-translate-y-2 transition-all duration-150">-->
    <!--              <img class="w-full" src="/img/pages/ai_form_builder/examples-placeholder.png" alt=""/>
-->
    <!--              <div class="px-4 py-5 sm:p-6">-->
    <!--                <h3 class="text-lg font-semibold text-neutral-900">-->
    <!--                  Example 4-->
    <!--                </h3>-->
    <!--                <p class="text-base font-medium text-neutral-500 mt-2 line-clamp-2">-->
    <!--                  Nulla Lorem mollit cupidatat irure. Laborum magna nulla duis ullamco cillum dolor-->
    <!--                </p>-->
    <!--              </div>-->
    <!--            </div>-->
    <!--          </div>-->

    <!--&lt;!&ndash;          <hr class="mt-12 border-neutral-200 sm:mt-16">&ndash;&gt;-->

    <!--&lt;!&ndash;          <div class="max-w-2xl mx-auto mt-12 text-center sm:mt-16">&ndash;&gt;-->
    <!--&lt;!&ndash;            <h4 class="text-2xl font-semibold tracking-tight text-neutral-900 sm:text-3xl lg:text-4xl">&ndash;&gt;-->
    <!--&lt;!&ndash;              Ready to level-up?&ndash;&gt;-->
    <!--&lt;!&ndash;            </h4>&ndash;&gt;-->
    <!--&lt;!&ndash;            <p class="mt-4 text-base leading-7 sm:text-xl sm:leading-9 font-medium text-neutral-500">&ndash;&gt;-->
    <!--&lt;!&ndash;              Save time and effortlessly create forms with OpnForm&ndash;&gt;-->
    <!--&lt;!&ndash;            </p>&ndash;&gt;-->

    <!--&lt;!&ndash;            <div class="mt-8 flex justify-center">&ndash;&gt;-->
    <!--&lt;!&ndash;              <UButton v-if="!authenticated" class="mr-1" :to="{ name: 'forms-create-guest' }" :arrow="true">&ndash;&gt;-->
    <!--&lt;!&ndash;                Get started for free&ndash;&gt;-->
    <!--&lt;!&ndash;              </UButton>&ndash;&gt;-->
    <!--&lt;!&ndash;              <UButton v-else class="mr-1" :to="{ name: 'forms-create' }" :arrow="true">&ndash;&gt;-->
    <!--&lt;!&ndash;                Get started for free&ndash;&gt;-->
    <!--&lt;!&ndash;              </UButton>&ndash;&gt;-->
    <!--&lt;!&ndash;            </div>&ndash;&gt;-->

    <!--&lt;!&ndash;            <ul&ndash;&gt;-->
    <!--&lt;!&ndash;              class="flex mt-8 sm:mt-12 text-sm font-medium text-neutral-900 items-center justify-center flex-wrap gap-x-6 gap-y-4">&ndash;&gt;-->
    <!--&lt;!&ndash;              <li class="flex items-center gap-2">&ndash;&gt;-->
    <!--&lt;!&ndash;                <svg aria-hidden="true" class="h-5 w-5 text-neutral-400 shrink-0" xmlns="http://www.w3.org/2000/svg"&ndash;&gt;-->
    <!--&lt;!&ndash;                  viewBox="0 0 20 20" fill="currentColor">&ndash;&gt;-->
    <!--&lt;!&ndash;                  <path fill-rule="evenodd"&ndash;&gt;-->
    <!--&lt;!&ndash;                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"&ndash;&gt;-->
    <!--&lt;!&ndash;                    clip-rule="evenodd" />&ndash;&gt;-->
    <!--&lt;!&ndash;                </svg>&ndash;&gt;-->
    <!--&lt;!&ndash;                No design skills required&ndash;&gt;-->
    <!--&lt;!&ndash;              </li>&ndash;&gt;-->

    <!--&lt;!&ndash;              <li class="flex items-center gap-2">&ndash;&gt;-->
    <!--&lt;!&ndash;                <svg aria-hidden="true" class="h-5 w-5 text-neutral-400 shrink-0" xmlns="http://www.w3.org/2000/svg"&ndash;&gt;-->
    <!--&lt;!&ndash;                  viewBox="0 0 20 20" fill="currentColor">&ndash;&gt;-->
    <!--&lt;!&ndash;                  <path fill-rule="evenodd"&ndash;&gt;-->
    <!--&lt;!&ndash;                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"&ndash;&gt;-->
    <!--&lt;!&ndash;                    clip-rule="evenodd" />&ndash;&gt;-->
    <!--&lt;!&ndash;                </svg>&ndash;&gt;-->
    <!--&lt;!&ndash;                Setup in minutes&ndash;&gt;-->
    <!--&lt;!&ndash;              </li>&ndash;&gt;-->

    <!--&lt;!&ndash;              <li class="flex items-center gap-2">&ndash;&gt;-->
    <!--&lt;!&ndash;                <svg aria-hidden="true" class="h-5 w-5 text-neutral-400 shrink-0" xmlns="http://www.w3.org/2000/svg"&ndash;&gt;-->
    <!--&lt;!&ndash;                  viewBox="0 0 20 20" fill="currentColor">&ndash;&gt;-->
    <!--&lt;!&ndash;                  <path fill-rule="evenodd"&ndash;&gt;-->
    <!--&lt;!&ndash;                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"&ndash;&gt;-->
    <!--&lt;!&ndash;                    clip-rule="evenodd" />&ndash;&gt;-->
    <!--&lt;!&ndash;                </svg>&ndash;&gt;-->
    <!--&lt;!&ndash;                It's free&ndash;&gt;-->
    <!--&lt;!&ndash;              </li>&ndash;&gt;-->
    <!--&lt;!&ndash;            </ul>&ndash;&gt;-->
    <!--&lt;!&ndash;          </div>&ndash;&gt;-->
    <!--        </div>-->
    <!--    </section>-->
    <!-- END EXAMPLES -->

    <!-- START TESTIMONIALS -->
    <!--    <section class="bg-white py-12 sm:py-16 lg:py-20 xl:py-24">-->
    <!--      <div class="px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">-->
    <!--        <div class="text-center max-w-3xl mx-auto">-->
    <!--          <h2 class="text-sm font-semibold text-blue-600">-->
    <!--            Customer Testimonials-->
    <!--          </h2>-->
    <!--          <p-->
    <!--            class="text-3xl mt-4 sm:text-4xl lg:text-5xl font-semibold text-neutral-900 tracking-tight lg:leading-tight">-->
    <!--            See what people are saying-->
    <!--          </p>-->
    <!--          <p class="text-neutral-500 text-base leading-7 sm:text-lg sm:leading-8 font-medium mt-4">-->
    <!--            These are the stories of our customers who have joined us with great pleasure when using this crazy-->
    <!--            feature.-->
    <!--          </p>-->
    <!--        </div>-->
    <!--      </div>-->
    <!--    </section>-->
    <!-- END TESTIMONIALS -->

    <!-- START FAQS -->
    <!--    <section class="bg-white py-12 sm:py-16 lg:py-20 xl:py-24 border-t border-neutral-200">-->
    <!--      <div class="px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">-->
    <!--        <div class="text-center max-w-3xl mx-auto">-->
    <!--          <h2 class="text-3xl sm:text-4xl lg:text-5xl font-semibold text-neutral-900 tracking-tight lg:leading-tight">-->
    <!--            Frequently Asked Questions-->
    <!--          </h2>-->
    <!--          <p class="text-neutral-500 text-base leading-7 sm:text-lg sm:leading-8 font-medium mt-4">-->
    <!--            We've compiled a list of the most common questions we get asked.-->
    <!--          </p>-->
    <!--        </div>-->

    <!--        <div class="mt-12 sm:mt-16 lg:mt-20">-->
    <!--          <dl class="gap-y-12 grid grid-cols-1 sm:grid-cols-2 sm:gap-x-8 sm:gap-y-16 lg:gap-x-10">-->
    <!--            <div>-->
    <!--              <dt class="sm:text-lg text-base leading-7 font-medium sm:leading-8 text-neutral-900">-->
    <!--                What's the best thing about Switzerland?-->
    <!--              </dt>-->
    <!--              <dd class="mt-2 text-base font-medium leading-7 text-neutral-500">-->
    <!--                I don't know, but the flag is a big plus. Lorem ipsum dolor sit amet consectetur adipisicing elit.-->
    <!--                Quas cupiditate-->
    <!--                laboriosam fugiat.-->
    <!--              </dd>-->
    <!--            </div>-->

    <!--            <div>-->
    <!--              <dt class="sm:text-lg text-base leading-7 font-medium sm:leading-8 text-neutral-900">-->
    <!--                How do you make holy water?-->
    <!--              </dt>-->
    <!--              <dd class="mt-2 text-base font-medium leading-7 text-neutral-500">-->
    <!--                You boil the hell out of it. Lorem ipsum dolor sit amet consectetur adipisicing elit. Magnam aut-->
    <!--                tempora vitae odio-->
    <!--                inventore fuga aliquam nostrum quod porro. Delectus quia facere id sequi expedita natus.-->
    <!--              </dd>-->
    <!--            </div>-->

    <!--            <div>-->
    <!--              <dt class="sm:text-lg text-base leading-7 font-medium sm:leading-8 text-neutral-900">-->
    <!--                What do you call someone with no body and no nose?-->
    <!--              </dt>-->
    <!--              <dd class="mt-2 text-base font-medium leading-7 text-neutral-500">-->
    <!--                Nobody knows. Lorem ipsum dolor sit amet consectetur adipisicing elit. Culpa, voluptas ipsa quia-->
    <!--                excepturi, quibusdam-->
    <!--                natus exercitationem sapiente tempore labore voluptatem.-->
    <!--              </dd>-->
    <!--            </div>-->

    <!--            <div>-->
    <!--              <dt class="sm:text-lg text-base leading-7 font-medium sm:leading-8 text-neutral-900">-->
    <!--                Why do you never see elephants hiding in trees?-->
    <!--              </dt>-->
    <!--              <dd class="mt-2 text-base font-medium leading-7 text-neutral-500">-->
    <!--                Because they're so good at it. Lorem ipsum dolor sit amet consectetur adipisicing elit. Quas-->
    <!--                cupiditate laboriosam-->
    <!--                fugiat.-->
    <!--              </dd>-->
    <!--            </div>-->

    <!--            <div>-->
    <!--              <dt class="sm:text-lg text-base leading-7 font-medium sm:leading-8 text-neutral-900">-->
    <!--                Why can't you hear a pterodactyl go to the bathroom?-->
    <!--              </dt>-->
    <!--              <dd class="mt-2 text-base font-medium leading-7 text-neutral-500">-->
    <!--                Because the pee is silent. Lorem ipsum dolor sit amet, consectetur adipisicing elit. Ipsam, quas-->
    <!--                voluptatibus ex culpa-->
    <!--                ipsum, aspernatur blanditiis fugiat ullam magnam suscipit deserunt illum natus facilis atque vero-->
    <!--                consequatur! Quisquam,-->
    <!--                debitis error.-->
    <!--              </dd>-->
    <!--            </div>-->

    <!--            <div>-->
    <!--              <dt class="sm:text-lg text-base leading-7 font-medium sm:leading-8 text-neutral-900">-->
    <!--                Why did the invisible man turn down the job offer?-->
    <!--              </dt>-->
    <!--              <dd class="mt-2 text-base font-medium leading-7 text-neutral-500">-->
    <!--                He couldn't see himself doing it. Lorem ipsum dolor sit, amet consectetur adipisicing elit. Eveniet-->
    <!--                perspiciatis-->
    <!--                officiis corrupti tenetur. Temporibus ut voluptatibus, perferendis sed unde rerum deserunt eius.-->
    <!--              </dd>-->
    <!--            </div>-->
    <!--          </dl>-->
    <!--        </div>-->
    <!--      </div>-->
    <!--    </section>-->
    <!-- END FAQS -->

    <!-- START CTA -->
    <section
      class="bg-gradient-to-b from-neutral-100 to-white py-12 sm:py-16 lg:pt-20 xl:pt-24"
    >
      <div class="px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
        <div class="flex items-center justify-center gap-8">
          <img
            class="w-auto h-12"
            src="/img/pages/ai_form_builder/icon-email-input.svg"
            alt=""
          >

          <img
            class="w-auto h-12"
            src="/img/pages/ai_form_builder/icon-radio-buttons.svg"
            alt=""
          >

          <img
            class="w-auto h-12"
            src="/img/pages/ai_form_builder/icon-textarea.svg"
            alt=""
          >
        </div>

        <div class="text-center max-w-3xl mx-auto mt-8 sm:mt-12">
          <h2
            class="text-3xl sm:text-4xl lg:text-5xl font-semibold text-neutral-900 tracking-tight lg:leading-tight"
          >
            Create beautiful forms and share them anywhere
          </h2>
          <p
            class="mt-4 sm:mt-5 text-base leading-7 sm:text-xl sm:leading-9 font-medium text-neutral-500"
          >
            It takes seconds, you don't need to know how to code and
            <span class="text-blue-600">it's free</span>.
          </p>

          <div class="mt-8 flex justify-center">
            <UButton
              class="mr-1"
              :to="{ name: authenticated ? 'forms-create' : 'forms-create-guest' }"
              trailing-icon="i-heroicons-arrow-right"
              label="Get started for free"
            />
          </div>

          <ul
            class="flex mt-8 sm:mt-12 text-sm font-medium text-neutral-900 items-center justify-center flex-wrap gap-x-6 gap-y-4"
          >
            <li class="flex items-center gap-2">
              <svg
                aria-hidden="true"
                class="h-5 w-5 text-neutral-400 shrink-0"
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 20 20"
                fill="currentColor"
              >
                <path
                  fill-rule="evenodd"
                  d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                  clip-rule="evenodd"
                />
              </svg>
              No design skills required
            </li>

            <li class="flex items-center gap-2">
              <svg
                aria-hidden="true"
                class="h-5 w-5 text-neutral-400 shrink-0"
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 20 20"
                fill="currentColor"
              >
                <path
                  fill-rule="evenodd"
                  d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                  clip-rule="evenodd"
                />
              </svg>
              Setup in minutes
            </li>

            <li class="flex items-center gap-2">
              <svg
                aria-hidden="true"
                class="h-5 w-5 text-neutral-400 shrink-0"
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 20 20"
                fill="currentColor"
              >
                <path
                  fill-rule="evenodd"
                  d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                  clip-rule="evenodd"
                />
              </svg>
              Free plan available
            </li>
          </ul>
        </div>
      </div>
    </section>
    <!-- END CTA -->

    <open-form-footer class="dark:border-t border-t" />
  </div>
</template>

<script setup>
definePageMeta({
  middleware: ['root-redirect','self-hosted']
})
useOpnSeoMeta({
  title: "Free AI form builder",
  description:
    "Transform your ideas into fully functional forms with OpnForm AI Builder – quick, accurate, and tailored to fit any requirement.",
})
defineRouteRules({
  swr: 3600,
})

const { isAuthenticated: authenticated } = useIsAuthenticated()
</script>

<style lang="scss" scoped>
.customer-logo-container {
  max-width: 130px;
  width: 100%;
}

.ticks {
  color: #2563eb;
}

@screen md {
  #macbook-video {
    position: absolute;
    max-width: 84.8% !important;
    right: 0px;
    top: 6.8%;
  }
}
</style>
