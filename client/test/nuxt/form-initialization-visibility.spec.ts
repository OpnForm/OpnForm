// @vitest-environment node
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { computed, createSSRApp, h, reactive, ref, watch } from 'vue'
import { renderToString } from 'vue/server-renderer'
import { formsApi } from '~/api'
import Form from '~/composables/lib/vForm/Form.js'
import { createFormModeStrategy, FormMode } from '~/lib/forms/FormModeStrategy'
import { useFieldState } from '~/lib/forms/composables/useFieldState'
import { useFormInitialization } from '~/lib/forms/composables/useFormInitialization'
import { useFocusedStructure } from '~/lib/forms/composables/useFocusedStructure'
import { useFormStructure } from '~/lib/forms/composables/useFormStructure'
import { useComputedVariables } from '~/composables/forms/useComputedVariables'

vi.mock('~/api', () => ({
  apiService: {},
  formsApi: { submissions: { get: vi.fn() } },
}))

vi.mock('#app', async () => ({ createError: (await import('h3')).createError }))

function createFixture(configOverrides = {}, pendingData = null) {
  const config = ref({
    slug: 'feedback',
    presentation_style: 'classic',
    properties: [
      { id: 'include_files', type: 'checkbox', hidden: false },
      {
        id: 'attachments', type: 'files', hidden: false,
        logic: {
          conditions: {
            identifier: 'include_files',
            value: {
              operator: 'is_not_checked',
              property_meta: { id: 'include_files', type: 'checkbox' },
            },
          },
          actions: ['hide-block'],
        },
      },
    ],
    ...configOverrides,
  })
  const form = reactive(new Form())
  const initialization = useFormInitialization(config, form, {
    enabled: ref(true),
    get: () => pendingData,
  })
  const fieldState = useFieldState(
    computed(() => form.data()), config, ref(createFormModeStrategy(FormMode.LIVE)),
  )
  return { config, form, initialization, fieldState }
}

describe('form visibility before inputs mount', () => {
  beforeEach(() => vi.clearAllMocks())

  it('hides the file field on the first render when its controlling checkbox is empty', async () => {
    const { config, form, initialization, fieldState } = createFixture()

    await initialization.initialize()

    expect(form.include_files).toBe(false)
    expect(fieldState.getState(config.value.properties[1]).hidden).toBe(true)

    form.include_files = true
    expect(fieldState.getState(config.value.properties[1]).hidden).toBe(false)
    form.include_files = false
    expect(fieldState.getState(config.value.properties[1]).hidden).toBe(true)
  })

  it.each([undefined, null, ''])('treats a %s checkbox value as unchecked', async (value) => {
    const { config, form, initialization, fieldState } = createFixture()

    await initialization.initialize({ defaultData: { include_files: value } })

    expect(form.include_files).toBe(false)
    expect(fieldState.getState(config.value.properties[1]).hidden).toBe(true)
  })

  it.each([
    ['true', true], ['false', false], ['1', true], ['0', false], ['', false],
  ])('resolves URL prefill %s before visibility is evaluated', async (value, expected) => {
    const { config, form, initialization, fieldState } = createFixture()

    await initialization.initialize({ urlParams: new URLSearchParams({ include_files: value }) })

    expect(form.include_files).toBe(expected)
    expect(fieldState.getState(config.value.properties[1]).hidden).toBe(!expected)
  })

  it.each([true, false])('preserves the configured checkbox prefill %s', async (prefill) => {
    const fixture = createFixture()
    fixture.config.value.properties[0].prefill = prefill

    await fixture.initialization.initialize()

    expect(fixture.form.include_files).toBe(prefill)
    expect(fixture.fieldState.getState(fixture.config.value.properties[1]).hidden).toBe(!prefill)
  })

  it.each([true, false])('preserves the explicit default answer %s', async (value) => {
    const { config, form, initialization, fieldState } = createFixture()

    await initialization.initialize({ defaultData: { include_files: value } })

    expect(form.include_files).toBe(value)
    expect(fieldState.getState(config.value.properties[1]).hidden).toBe(!value)
  })

  it('initializes a hidden controlling checkbox even though it will never mount', async () => {
    const fixture = createFixture()
    fixture.config.value.properties[0].hidden = true

    await fixture.initialization.initialize()

    expect(fixture.form.include_files).toBe(false)
    expect(fixture.fieldState.getState(fixture.config.value.properties[1]).hidden).toBe(true)
  })

  it('applies a new configured prefill when reinitializing an initially unanswered checkbox', async () => {
    const { config, form, initialization } = createFixture()
    await initialization.initialize()
    expect(form.include_files).toBe(false)

    config.value = {
      ...config.value,
      properties: config.value.properties.map(field => field.id === 'include_files' ? { ...field, prefill: true } : field),
    }
    await initialization.initialize()

    expect(form.include_files).toBe(true)
  })

  it('retains an explicit unchecked answer when reinitializing with a checked prefill', async () => {
    const { config, form, initialization } = createFixture()
    await initialization.initialize({ defaultData: { include_files: false } })
    config.value.properties[0].prefill = true

    await initialization.initialize()

    expect(form.include_files).toBe(false)
  })

  it('allows a new prefill after an initially empty checkbox string', async () => {
    const { config, form, initialization } = createFixture()
    await initialization.initialize({ defaultData: { include_files: '' } })
    config.value.properties[0].prefill = true

    await initialization.initialize()

    expect(form.include_files).toBe(true)
  })

  it.each([undefined, null])('applies a changed prefill after initializing an unanswered %s checkbox value', async (value) => {
    const { config, form, initialization } = createFixture()
    await initialization.initialize({ defaultData: { include_files: value } })
    expect(form.include_files).toBe(false)
    config.value.properties[0].prefill = true

    await initialization.initialize()

    expect(form.include_files).toBe(true)
  })

  it('adds an unchecked default when restoring a draft that omits the checkbox', async () => {
    const { config, form, initialization, fieldState } = createFixture({}, { feedback: 'Saved feedback' })

    await initialization.initialize()

    expect(form.feedback).toBe('Saved feedback')
    expect(form.include_files).toBe(false)
    expect(fieldState.getState(config.value.properties[1]).hidden).toBe(true)
  })

  it.each(['true', 'false'])('preserves a restored draft checkbox value of %s', async (value) => {
    const { config, form, initialization, fieldState } = createFixture({}, { include_files: value })

    await initialization.initialize()

    expect(form.include_files).toBe(value === 'true')
    expect(fieldState.getState(config.value.properties[1]).hidden).toBe(value !== 'true')
  })

  it('applies configured prefill to an omitted draft checkbox before resolving visibility', async () => {
    const fixture = createFixture({}, { feedback: 'Saved feedback' })
    fixture.config.value.properties[0].prefill = true

    await fixture.initialization.initialize()

    expect(fixture.form.include_files).toBe(true)
    expect(fixture.fieldState.getState(fixture.config.value.properties[1]).hidden).toBe(false)
  })

  it.each([{}, { include_files: true }])('normalizes existing submission answers %j', async (data) => {
    vi.mocked(formsApi.submissions.get).mockResolvedValue({ data })
    const { config, form, initialization, fieldState } = createFixture()

    await initialization.initialize({ submissionId: 'submission-id' })

    expect(form.include_files).toBe(data.include_files ?? false)
    expect(fieldState.getState(config.value.properties[1]).hidden).toBe(!data.include_files)
  })

  it('keeps an unanswered focused Yes/No field unanswered', async () => {
    const { form, initialization } = createFixture({ presentation_style: 'focused' })

    await initialization.initialize()

    expect(form.include_files).toBeUndefined()
  })

  it('initializes a standard checkbox in a focused form when the Yes/No selector is disabled', async () => {
    const fixture = createFixture({ presentation_style: 'focused' })
    fixture.config.value.properties[0].use_focused_toggle = false

    await fixture.initialization.initialize()

    expect(fixture.form.include_files).toBe(false)
    const structure = useFocusedStructure(fixture.config, { currentPage: 0 }, fixture.form, fixture.fieldState)
    expect(structure.pageCount.value).toBe(1)
  })

  it('evaluates conditional page breaks before building the first page', async () => {
    const fixture = createFixture()
    fixture.config.value.properties[1].type = 'nf-page-break'
    fixture.config.value.properties.push({ id: 'email', type: 'email', hidden: false })

    await fixture.initialization.initialize()

    const structure = useFormStructure(fixture.config, { currentPage: 0 }, fixture.form, fixture.fieldState)
    expect(structure.pageCount.value).toBe(1)
    expect(structure.getPageFields(0).map(field => field.id)).toContain('email')
  })

  for (const style of ['classic', 'focused']) {
    it.each(['rating', 'slider'])(`preserves an explicit zero ${style} %s answer on reinitialization`, async (type) => {
      const fixture = createFixture({ presentation_style: style })
      fixture.config.value.properties[0] = { id: 'control', type, prefill: 4 }
      await fixture.initialization.initialize({ defaultData: { control: 0 } })

      await fixture.initialization.initialize()

      expect(fixture.form.control).toBe(0)
    })
    it.each([undefined, null, ''])(`keeps an empty ${style} scale unanswered (%s)`, async (value) => {
      const fixture = createFixture({ presentation_style: style })
      fixture.config.value.properties[0] = { id: 'control', type: 'scale' }

      await fixture.initialization.initialize({ defaultData: { control: value } })

      expect(fixture.form.control).toBe(value)
    })
    for (const type of ['rating', 'slider']) {
      it.each([undefined, null, ''])(`initializes an empty ${style} ${type} before resolving visibility (%s)`, async (value) => {
        const fixture = createFixture({ presentation_style: style })
        fixture.config.value.properties[0] = { id: 'control', type, hidden: true }
        fixture.config.value.properties[1].logic.conditions = {
          identifier: 'control', value: {
            operator: 'equals', property_meta: { id: 'control', type }, value: 0,
          },
        }

        await fixture.initialization.initialize({ defaultData: { control: value } })

        expect(fixture.form.control).toBe(0)
        expect(fixture.fieldState.getState(fixture.config.value.properties[1]).hidden).toBe(true)
        fixture.config.value.properties[0].prefill = 4
        await fixture.initialization.initialize()
        expect(fixture.form.control).toBe(4)
      })
    }
    it.each(['configured prefill', 'URL', 'default data', 'draft', 'submission'])(`normalizes a ${style} decimal scale from %s without changing the source answer`, async (source) => {
      const data = { control: '2.5' }
      const fixture = createFixture({ presentation_style: style }, source === 'draft' ? data : null)
      fixture.config.value.properties[0] = { id: 'control', type: 'scale', prefill: '2.5' }
      fixture.config.value.properties[1].logic.conditions = {
        identifier: 'control', value: {
          operator: 'equals', property_meta: { id: 'control', type: 'scale' }, value: 2.5,
        },
      }
      if (source === 'submission') vi.mocked(formsApi.submissions.get).mockResolvedValue({ data })

      await fixture.initialization.initialize(source === 'URL'
        ? { urlParams: new URLSearchParams(data) }
        : source === 'default data' ? { defaultData: data }
        : source === 'submission' ? { submissionId: 'stored-id' } : {})

      expect(fixture.form.control).toBe(2.5)
      expect(fixture.fieldState.getState(fixture.config.value.properties[1]).hidden).toBe(true)
      expect(data.control).toBe('2.5')
    })

    for (const source of ['default data', 'draft', 'submission']) {
      it(`preserves other ${style} answers loaded from ${source} without mutating the source`, async () => {
        const data = {
          include_files: true, text: 'Original answer', number: 7, date: '2026-10-01',
          matrix: { Service: 'Good' }, select: 'first', multi: ['first'], submission_hash: 'stored-hash',
        }
        const original = structuredClone(data)
        const fixture = createFixture({ presentation_style: style }, source === 'draft' ? data : null)
        fixture.config.value.properties.push(
          { id: 'text', type: 'text' }, { id: 'number', type: 'number' }, { id: 'date', type: 'date' },
          { id: 'matrix', type: 'matrix' },
          { id: 'select', type: 'select', select: { options: [{ id: 'first', name: 'First' }] } },
          { id: 'multi', type: 'multi_select', multi_select: { options: [{ id: 'first', name: 'First' }] } },
        )
        if (source === 'submission') vi.mocked(formsApi.submissions.get).mockResolvedValue({ data })

        await fixture.initialization.initialize(source === 'default data'
          ? { defaultData: data }
          : source === 'submission' ? { submissionId: 'stored-id' } : {})

        expect(fixture.form.data()).toMatchObject({ ...data, select: 'First', multi: ['First'] })
        expect(data).toEqual(original)
      })
    }
  }

  it.each(['configured prefill', 'URL', 'default data', 'submission'])('renders chained computed visibility from %s during SSR', async (source) => {
    vi.stubGlobal('ref', ref)
    vi.stubGlobal('computed', computed)
    vi.stubGlobal('watch', watch)
    const data = { control: 5 }
    if (source === 'submission') vi.mocked(formsApi.submissions.get).mockResolvedValue({ data })
    const app = createSSRApp({
      async setup() {
        const fixture = createFixture({
          properties: [
            { id: 'control', type: 'number', ...(source === 'configured prefill' ? { prefill: 5 } : {}) },
            { id: 'dependent', type: 'text', logic: {
              conditions: { identifier: 'cv_total', value: {
                operator: 'greater_than', property_meta: { id: 'cv_total', type: 'computed', result_type: 'number' }, value: 10,
              } }, actions: ['hide-block'],
            } },
          ],
          computed_variables: [
            { id: 'cv_total', name: 'Total', formula: '{cv_double} + 1' },
            { id: 'cv_double', name: 'Double', formula: '{control} * 2' },
          ],
        })
        const variables = useComputedVariables(fixture.config, computed(() => fixture.form.data()))
        // Resolve once before initialization, as consumers can do during setup.
        expect(variables.values.value.cv_total).toBeNull()
        const states = useFieldState(computed(() => fixture.form.data()), fixture.config,
          ref(createFormModeStrategy(FormMode.LIVE)), variables.values)
        await fixture.initialization.initialize(source === 'URL'
          ? { urlParams: new URLSearchParams({ control: '5' }) }
          : source === 'default data' ? { defaultData: data }
          : source === 'submission' ? { submissionId: 'stored-id' } : {})
        return () => h('div', [
          h('span', String(variables.values.value.cv_total)),
          ...(states.getState(fixture.config.value.properties[1]).hidden ? [] : [h('input', { name: 'dependent' })]),
        ])
      },
    })
    try {
      const html = await renderToString(app)
      expect(html).toContain('<span>11</span>')
      expect(html).not.toContain('name="dependent"')
    } finally {
      vi.unstubAllGlobals()
    }
  })
})
