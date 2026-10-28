import { createServer, request } from 'node:http'
import { createSecureServer } from 'node:http2'
import { gzipSync } from 'node:zlib'
import { spawn, execFileSync } from 'node:child_process'
import { writeFileSync, readFileSync, mkdirSync, existsSync } from 'node:fs'
import { chromium } from '@playwright/test'
import { getLiveDemoScenario } from '../data/live-demo-scenarios.js'
const [label, build] = process.argv.slice(2)
const root = process.cwd()
mkdirSync('.e2e/performance', { recursive: true })
if (!label || !build)
  throw new Error(
    'Run from the repository root: node client/scripts/measure-landing-performance.mjs LABEL BUILD_DIRECTORY',
  )
const listen = (s) =>
  new Promise((r) => s.listen(0, '127.0.0.1', () => r(s.address().port)))
const calls = []
const fixtureForm = {
  ...getLiveDemoScenario({ variant: 'home' }).form,
  slug: 'performance-fixture',
  workspace: { id: 1, settings: {} },
}
const api = createServer((req, res) => {
  calls.push(req.url)
  res.setHeader('content-type', 'application/json')
  res.setHeader('access-control-allow-origin', '*')
  res.setHeader('access-control-allow-headers', 'authorization, content-type')
  if (req.method === 'OPTIONS') {
    res.writeHead(204)
    res.end()
    return
  }
  const body =
    req.url === '/forms/performance-fixture'
      ? fixtureForm
      : req.url.includes('feature-flags')
        ? {
            self_hosted: false,
            setup_required: false,
            billing: { enabled: true },
            services: {},
            custom_domains: { enabled: false },
          }
        : req.url.includes('/content/plans')
          ? {
              tiers: {
                free: { name: 'Free', features: [] },
                pro: { name: 'Pro', features: [] },
              },
            }
          : req.url.includes('/user')
            ? {
                id: 1,
                name: 'Local QA',
                email: 'qa@example.test',
                active_license: null,
                is_subscribed: false,
              }
            : []
  res.end(JSON.stringify(body))
})
const apiPort = await listen(api)
const reservation = createServer()
const port = await listen(reservation)
await new Promise((r) => reservation.close(r))
const upstreamOrigin = `http://127.0.0.1:${port}`
let origin = upstreamOrigin
let proxy
if (process.env.PERF_HTTP2 === '1') {
  const certificate = '.e2e/performance/localhost.crt'
  const privateKey = '.e2e/performance/localhost.key'
  if (!existsSync(certificate) || !existsSync(privateKey)) {
    execFileSync(
      'openssl',
      [
        'req',
        '-x509',
        '-newkey',
        'rsa:2048',
        '-nodes',
        '-days',
        '1',
        '-subj',
        '/CN=localhost',
        '-keyout',
        privateKey,
        '-out',
        certificate,
      ],
      { stdio: 'ignore' },
    )
  }
  const clean = (headers) =>
    Object.fromEntries(
      Object.entries(headers).filter(
        ([key]) =>
          !key.startsWith(':') &&
          ![
            'connection',
            'keep-alive',
            'transfer-encoding',
            'upgrade',
            'http2-settings',
          ].includes(key.toLowerCase()),
      ),
    )
  proxy = createSecureServer(
    {
      key: readFileSync(privateKey),
      cert: readFileSync(certificate),
      allowHTTP1: true,
    },
    (req, res) => {
      const apiRequest = req.url.startsWith('/fixture-api/')
      const upstream = request(
        {
          hostname: '127.0.0.1',
          port: apiRequest ? apiPort : port,
          path: apiRequest ? req.url.slice('/fixture-api'.length) : req.url,
          method: req.method,
          headers: {
            ...clean(req.headers),
            host: new URL(origin).host,
            'x-forwarded-proto': 'https',
          },
        },
        (response) => {
          const chunks = []
          response.on('data', (chunk) => chunks.push(chunk))
          response.on('end', () => {
            let body = Buffer.concat(chunks)
            const headers = clean(response.headers)
            if (
              !headers['content-encoding'] &&
              /text|javascript|json|svg/.test(headers['content-type'] || '') &&
              String(req.headers['accept-encoding']).includes('gzip')
            ) {
              body = gzipSync(body)
              headers['content-encoding'] = 'gzip'
              headers.vary = 'Accept-Encoding'
            }
            headers['content-length'] = body.length
            res.writeHead(response.statusCode, headers)
            res.end(body)
          })
        },
      )
      upstream.on('error', () => {
        if (!res.headersSent) res.writeHead(502)
        res.end()
      })
      req.pipe(upstream)
    },
  )
  origin = `https://127.0.0.1:${await listen(proxy)}`
}
const server = spawn(
  process.execPath,
  ['--expose-gc', '--inspect=127.0.0.1:0', build + '/server/index.mjs'],
  {
    cwd: root,
    env: {
      PATH: process.env.PATH,
      NODE_ENV: 'production',
      NITRO_HOST: '127.0.0.1',
      NITRO_PORT: String(port),
      NUXT_PUBLIC_ENV: 'testing',
      NUXT_PUBLIC_APP_URL: origin,
      NUXT_PRIVATE_API_BASE: `http://127.0.0.1:${apiPort}`,
      NUXT_PUBLIC_API_BASE: proxy
        ? `${origin}/fixture-api`
        : `http://127.0.0.1:${apiPort}`,
      NUXT_API_SECRET: 'fictional-local-qa',
    },
  },
)
let logs = ''
server.stdout.on('data', (d) => (logs += d))
server.stderr.on('data', (d) => (logs += d))
let browser
let inspector
try {
  for (let i = 0; i < 200 && !logs.includes('Listening on'); i++) {
    if (server.exitCode !== null) throw Error(logs.slice(-1200))
    await new Promise((r) => setTimeout(r, 100))
  }
  const chrome =
    process.env.PERF_CHROME_BIN ||
    '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'
  browser = await chromium.launch({
    ...(existsSync(chrome) ? { executablePath: chrome } : {}),
    headless: true,
  })
  // Warm both builds identically before measuring browser navigation.
  for (let i = 0; i < 2; i++) await fetch(upstreamOrigin).then((r) => r.text())
  inspector = new WebSocket(logs.match(/ws:\/\/127\.0\.0\.1:[^\s]+/)[0])
  await new Promise((r) =>
    inspector.addEventListener('open', r, { once: true }),
  )
  let messageId = 0
  const heap = () =>
    new Promise((resolve) => {
      const id = ++messageId
      const handler = (e) => {
        const data = JSON.parse(e.data)
        if (data.id === id) {
          inspector.removeEventListener('message', handler)
          resolve(JSON.parse(data.result.result.value))
        }
      }
      inspector.addEventListener('message', handler)
      inspector.send(
        JSON.stringify({
          id,
          method: 'Runtime.evaluate',
          params: {
            expression: 'global.gc(); JSON.stringify(process.memoryUsage())',
            returnByValue: true,
          },
        }),
      )
    })
  const results = { label, node: process.version, build, scenarios: [] }
  for (const authenticated of [false, true]) {
    const context = await browser.newContext({
      viewport: { width: 412, height: 823 },
      deviceScaleFactor: 1,
      isMobile: true,
      ignoreHTTPSErrors: true,
    })
    await context.route('**/*', (route) =>
      route.request().url().startsWith(origin) ||
      route.request().url().startsWith(`http://127.0.0.1:${apiPort}`)
        ? route.continue()
        : route.abort(),
    )
    if (authenticated)
      await context.addCookies([
        {
          name: 'opnform_token',
          value: 'fictional-local-token',
          url: origin,
        },
      ])
    const page = await context.newPage()
    page.on('console', (m) => {
      if (m.type() === 'error' || m.type() === 'warning')
        process.stderr.write(m.text().slice(0, 1000) + '\n')
    })
    const cdp = await context.newCDPSession(page)
    await cdp.send('Network.enable')
    await cdp.send('Network.setCacheDisabled', { cacheDisabled: true })
    await cdp.send('Emulation.setCPUThrottlingRate', { rate: 4 })
    const responses = []
    const errors = []
    page.on('pageerror', (e) => {
      errors.push(e.stack || e.message)
      process.stderr.write((e.stack || e.message) + '\n')
    })
    page.on('response', (r) => {
      if (r.url().startsWith(origin))
        responses.push({
          url: r.url().replace(origin, ''),
          type: r.request().resourceType(),
          status: r.status(),
        })
    })
    await page.addInitScript(() => {
      window.__perf = { lcp: 0, cls: 0, longTasks: 0 }
      new PerformanceObserver((l) =>
        l.getEntries().forEach((e) => (window.__perf.lcp = e.startTime)),
      ).observe({ type: 'largest-contentful-paint', buffered: true })
      new PerformanceObserver((l) =>
        l.getEntries().forEach((e) => {
          if (!e.hadRecentInput) window.__perf.cls += e.value
        }),
      ).observe({ type: 'layout-shift', buffered: true })
      new PerformanceObserver((l) =>
        l
          .getEntries()
          .forEach(
            (e) => (window.__perf.longTasks += Math.max(0, e.duration - 50)),
          ),
      ).observe({ type: 'longtask', buffered: true })
    })
    const response = await page.goto(origin, {
      waitUntil: 'networkidle',
      timeout: 60000,
    })
    if (response.status() !== 200)
      throw new Error(
        'Landing returned ' +
          response.status() +
          ': ' +
          (await page.locator('body').innerText()).slice(0, 500),
      )
    await page
      .locator('h1')
      .filter({ hasText: 'Beautiful forms' })
      .waitFor({ timeout: 15000 })
    await page.waitForTimeout(3000)
    const metrics = await page.evaluate(() => ({
      ...window.__perf,
      ttfb: performance.getEntriesByType('navigation')[0].responseStart,
      htmlBytes: document.documentElement.outerHTML.length,
      modulepreloads: document.querySelectorAll('link[rel=modulepreload]')
        .length,
      resources: performance.getEntriesByType('resource').map((e) => ({
        name: e.name,
        transfer: e.transferSize,
        decoded: e.decodedBodySize,
      })),
      lang: document.documentElement.lang,
      main: document.querySelectorAll('main').length,
    }))
    const own = metrics.resources.filter((r) => r.name.startsWith(origin))
    results.scenarios.push({
      authenticated,
      ...metrics,
      resources: undefined,
      transferred: own.reduce((s, r) => s + r.transfer, 0),
      jsRequests: responses.filter((r) => r.type === 'script').length,
      imageRequests: responses.filter((r) => r.type === 'image').length,
      responses,
      errors,
      apiCalls: [...calls],
    })
    await page.mouse.wheel(0, 700)
    await page
      .locator('.live-demo-form')
      .waitFor({ timeout: 15000 })
      .catch(async (error) => {
        writeFileSync('.e2e/performance/debug.html', await page.content())
        await page.screenshot({ path: '.e2e/performance/debug.png' })
        throw error
      })
    await page.waitForLoadState('networkidle')
    results.scenarios.at(-1).afterDemoJsRequests = responses.filter(
      (r) => r.type === 'script',
    ).length
    await page.screenshot({
      path: `.e2e/performance/${label}-${authenticated ? 'member' : 'anonymous'}.png`,
      fullPage: true,
    })
    await context.close()
  }
  const formUrl = upstreamOrigin + '/forms/performance-fixture'
  const formWarmup = await fetch(formUrl)
  if (formWarmup.status !== 200)
    throw new Error('SSR form fixture did not render: ' + formWarmup.status)
  await formWarmup.text()
  if (process.env.PERF_LIGHTHOUSE === '1') {
    const args = [
      '--yes',
      '--package=lighthouse@13.5.0',
      '--',
      'lighthouse',
      origin,
      '--quiet',
      '--only-categories=performance',
      '--output=json',
      `--output-path=.e2e/performance/${label}-lighthouse.json`,
      '--chrome-flags=--headless --no-sandbox --ignore-certificate-errors',
      '--max-wait-for-load=90000',
    ]
    await new Promise((resolve, reject) => {
      const child = spawn('npx', args, {
        env: {
          ...process.env,
          CHROME_PATH: existsSync(chrome) ? chrome : chromium.executablePath(),
        },
      })
      let output = ''
      child.stdout.on('data', (d) => {
        output += d
      })
      child.stderr.on('data', (d) => {
        output += d
      })
      child.on('error', reject)
      child.on('exit', (code) =>
        code === 0 ? resolve() : reject(new Error(output.slice(-2000))),
      )
    })
  }
  const heapBefore = await heap()
  const before = Number(
    execFileSync('ps', ['-o', 'rss=', '-p', String(server.pid)], {
      encoding: 'utf8',
    }).trim(),
  )
  for (let b = 0; b < 100; b++)
    await Promise.all(
      Array.from({ length: 10 }, () =>
        fetch(formUrl).then((r) => {
          if (r.status !== 200) throw new Error('SSR load response ' + r.status)
          return r.text()
        }),
      ),
    )
  const heapAfter = await heap()
  inspector.close()
  results.ssr = {
    heapBefore,
    heapAfter,
    requests: 1000,
    rssBeforeKiB: before,
    rssAfterKiB: Number(
      execFileSync('ps', ['-o', 'rss=', '-p', String(server.pid)], {
        encoding: 'utf8',
      }).trim(),
    ),
  }
  writeFileSync(
    `.e2e/performance/${label}.json`,
    JSON.stringify(results, null, 2),
  )
  console.log(
    JSON.stringify(
      {
        ...results,
        scenarios: results.scenarios.map(
          ({ responses: _responses, apiCalls: _apiCalls, ...s }) => s,
        ),
      },
      null,
      2,
    ),
  )
  if (results.scenarios.some((s) => s.errors.length))
    throw new Error('Browser startup failed; see the saved performance report')
} finally {
  inspector?.close()
  await browser?.close()
  server.kill('SIGTERM')
  api.close()
  proxy?.close()
}
