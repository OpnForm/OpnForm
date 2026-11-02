import { beforeEach, describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { useState } from '#imports'
import { useGithubStarPrompt } from '~/composables/useGithubStarPrompt'
import GithubStarPrompt from '~/components/pages/forms/show/GithubStarPrompt.vue'

function mountPrompt(slug = 'first-form') {
  return mount(GithubStarPrompt, {
    props: { formSlug: slug },
    global: {
      stubs: {
        UModal: {
          props: ['open'],
          template: '<div v-if="open"><slot name="body" /><slot name="footer" /></div>',
        },
        UButton: {
          props: ['to', 'target', 'rel'],
          template: '<a v-if="to" :href="to" :target="target" :rel="rel"><slot /></a><button v-else><slot /></button>',
        },
        Icon: true,
      },
    },
  })
}

describe('first form GitHub invitation', () => {
  beforeEach(() => {
    useState('featureFlags').value = { self_hosted: true }
    useGithubStarPrompt().dismissPrompt()
  })

  it('opens for the first self-hosted form and stays dismissed after remounting', async () => {
    useGithubStarPrompt().queuePrompt({ form: { slug: 'first-form' }, users_first_form: true })
    const wrapper = mountPrompt()
    expect(wrapper.get('a').attributes('href')).toBe('https://github.com/OpnForm/OpnForm')
    expect(wrapper.get('a').attributes('target')).toBe('_blank')
    await wrapper.get('button').trigger('click')
    expect(wrapper.text()).toBe('')
    wrapper.unmount()
    expect(mountPrompt().text()).toBe('')
  })

  it('dismisses when the GitHub link is clicked', async () => {
    useGithubStarPrompt().queuePrompt({ form: { slug: 'first-form' }, users_first_form: true })
    const wrapper = mountPrompt()
    await wrapper.get('a').trigger('click')
    expect(wrapper.text()).toBe('')
  })

  it('does not open for subsequent forms or other form pages', () => {
    useGithubStarPrompt().queuePrompt({ form: { slug: 'later-form' }, users_first_form: false })
    expect(mountPrompt('later-form').text()).toBe('')
    useGithubStarPrompt().queuePrompt({ form: { slug: 'first-form' }, users_first_form: true })
    expect(mountPrompt('other-form').text()).toBe('')
  })

  it('does not open on the hosted service', () => {
    useState('featureFlags').value = { self_hosted: false }
    useGithubStarPrompt().queuePrompt({ form: { slug: 'first-form' }, users_first_form: true })
    expect(mountPrompt().text()).toBe('')
    expect(useGithubStarPrompt().pendingFormSlug.value).toBeNull()
  })
})
