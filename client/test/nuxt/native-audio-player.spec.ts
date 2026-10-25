import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import NativeAudioPlayer from '~/components/forms/heavy/components/NativeAudioPlayer.vue'

describe('NativeAudioPlayer', () => {
  it('unmutes the native audio element when the source changes', async () => {
    const wrapper = mount(NativeAudioPlayer, {
      props: { src: 'https://example.test/first.mp3' },
      global: {
        stubs: { Icon: true },
      },
    })
    const audio = wrapper.get('audio').element

    audio.muted = true
    await wrapper.setProps({ src: 'https://example.test/second.mp3' })

    expect(audio.muted).toBe(false)
    expect(wrapper.get('button[aria-label="Mute"]').exists()).toBe(true)
  })
})
