# Production performance qualification

This change prepares an application and image update. It does not deploy it.

## Runtime configuration

The hosted release controller sets the frontend allocation to 2 GiB and the V8 old-space limit to 1536 MiB, preserving other Node options. The remaining allocation covers young-generation heap, native allocations and buffers. Deployment is still an explicit release operation; the plan-only command does not update Compose or restart containers.

Every SSR request owns its QueryClient. Server queries use an infinite garbage-collection interval so that timer closures do not retain completed requests. Rendering dehydrates the response state and clears the request's query cache; error handling clears it too. Browser caches retain their existing ten-minute lifetime. No shared HTML cache is introduced.

Public API reads authenticated by `FRONT_API_SECRET` have a separate bounded quota (`SSR_READS_PER_MINUTE`, default 6000 per renderer IP). Only GET requests to public form details, feature flags and plans qualify. This does not grant permissions or change validation, password protection, private resources, submissions, uploads or ordinary browser quotas. The production `api` middleware group uses `throttle:internal-api`, preserving its shared ordinary 100/minute quota; the separate external API limiter remains 60/minute. Export, signed-download and other dedicated-limiter exemptions are preserved. No forwarded client-IP header is trusted for this exception.

Hosted backend roles compile configuration, routes, events and Blade views after their runtime environment is injected. A failed cache command stops startup. Runtime secrets are stored privately, and no shared Redis flush is performed.

## Browser behavior

Marketing pages defer private user/workspace reads until hydration finishes. Protected app routes retain their authentication bootstrap. Public respondent pages continue to skip it.

The live demo activates when at least 35% of its reserved frame is visible and keeps its minimum height reserved. Only the selected classic or focused renderer is imported. Login and registration dialogs load when opened. Featurebase loads its SDK on the first feedback or changelog interaction, including an intent queued before the vendor component mounts; Clarity is imported only when its existing session and iframe conditions allow it. Images below the fold load lazily, the large footer texture is resized and compressed, and Euclid uses WOFF2 with all original characters retained.

Hashed Nuxt assets have a one-year immutable cache. Mutable images and fonts use one hour; `/_nuxt/builds/latest.json` is not retained. Error documents and authentication pages are not given public caching. Old hashed assets must remain available during release transitions.

## Reproducing measurements

Use Node 24.22.1 and the same lockfile for both revisions. Save the baseline `.output` before rebuilding the candidate. From the repository root run:

```sh
PERF_HTTP2=1 PERF_LIGHTHOUSE=1 node client/scripts/measure-landing-performance.mjs before /absolute/path/to/baseline-output
PERF_HTTP2=1 PERF_LIGHTHOUSE=1 node client/scripts/measure-landing-performance.mjs after client/.output
```

With `PERF_HTTP2=1`, both revisions use the same temporary loopback HTTP/2 TLS proxy with gzip compression, including HTML. Its self-signed certificate stays in the ignored artifact directory. `PERF_LIGHTHOUSE=1` additionally runs Lighthouse 13.5.0 with mobile simulation. Omit these flags to measure direct Nitro without the proxy or Lighthouse.

The script starts a temporary loopback API with fictional data and a production Nitro server, uses clean Chrome contexts with disabled HTTP cache, a 412 × 823 viewport and CPU slowdown ×4, and compares anonymous and cookie-bearing visits. External requests are blocked; vendor network costs and real customer/API latency are outside this comparison. `PERF_CHROME_BIN` selects Chrome; otherwise it uses local Google Chrome on macOS or Playwright Chromium.

Results and screenshots stay in the ignored `.e2e/performance` directory. Memory samples use a loopback-only Node inspector and explicit garbage collection before and after the workload. RSS includes process allocator high-water marks and is not a leak measurement on its own. These local results are not production Lighthouse scores.

## Investigation and transferred patterns

The read-only production investigation found a frontend close to its 1 GiB container limit, seven restarts and a V8 heap-out-of-memory failure. Its 1024 MiB old-space setting left no allowance for the rest of the process. Completed SSR query caches were retained for ten minutes, and public SSR reads shared the ordinary anonymous API quota, producing HTTP 429 responses. These findings explain concrete failure modes; they do not establish that every slow request has the same cause.

[Raydocs #308](https://github.com/JhumanJ/raydocs/pull/308) supplied applicable patterns: mount optional tools on visibility or intent, defer private identity reads on public routes, and ignore stale authentication failures after a new login. Those patterns are adapted to OpnForm's existing query and authentication contracts.

## Before and after

Baseline: `3a0178856c3bb12d214de86a452b84f979ca7a98`. Measured application source: `cef9a64cd9aa3e3950f7d4849390695145084820` (later report-only commits do not change the build). Both builds use the same installed dependencies, production Nitro, HTTP/2 and compression. Browser runs and Lighthouse runs are sequential, without concurrent builds or test suites. Values below are the final local runs, with decimal MB. Lighthouse is a single simulated mobile run per revision; its timings and score vary between runs.

| Metric | Before | After |
| --- | ---: | ---: |
| Anonymous initial JS responses | 209 | 56 |
| Anonymous JS responses after viewing demo | 209 | 64 |
| Anonymous initial first-party resource transfer | 8.27 MB | 0.51 MB |
| Synthetic signed-in initial JS responses | 215 | 68 |
| Synthetic signed-in JS responses after viewing demo | 215 | 83 |
| Synthetic signed-in initial first-party resource transfer | 7.92 MB | 0.54 MB |
| Lighthouse performance score | 66 | 88 |
| Lighthouse LCP | 6.27 s | 3.25 s |
| Lighthouse TBT | 353.00 ms | 72.50 ms |
| Lighthouse CLS | 0.040 | 0.040 |
| V8 live heap before workload | 72.9 MB | 63.7 MB |
| V8 live heap after 1,000 SSR form renders | 707.3 MB | 69.9 MB |

Initial transfer includes scripts, styles, fonts and images loaded before scrolling, excluding the HTML document and blocked external vendors. It does not count every image eventually loaded down the page. Both browser scenarios rendered successfully without JavaScript exceptions; the demo and its cover were checked after scrolling. The synthetic signed-in scenario exercises deferred loading with fictional API data; real authentication is covered separately by browser tests against the isolated Laravel stack.

The static landing import graph is 357.1 KiB gzip across 47 files. It differs from the browser's 56 script responses because runtime imports also load. CI now rejects a landing graph above 375 KiB gzip or 50 static files. Existing public-form budgets remain unchanged: classic 418.4 KiB and focused 409.0 KiB, below 425 KiB each.

## Validation and review

- Frontend: 111 files / 1,214 tests passed; full ESLint and production build passed. Built-server checks verify Brotli, immutable hashed assets, mutable-asset revalidation, uncached errors and private login responses.
- Backend: 2,567 tests / 20,343 assertions passed, with two skips and two existing deprecation notices. Full Pint passed. The changed provider passes scoped PHPStan.
- Full PHPStan reports three existing relation diagnostics in `WorkspaceController`, `FormListStatsLoader` and `McpFormManagementService`. The unmodified baseline was analyzed separately and reports the same three diagnostics; this PR introduces none.
- Deployment: 15 controller contract tests and 21 disposable runtime scenarios passed against the actual PHP 8.3 application image. These cover runtime cache generation and permissions, PostgreSQL, Redis, real synthetic HTTP login/submission/upload, workers, expiry and shutdown. Disposable containers and networks were removed.
- Production-build browser tests against the isolated seeded stack passed login, logout/protected redirects, classic/focused creation, public field rendering and a mixed-field submission. All 25 SSR initialization/draft/conditional-visibility browser tests also passed (31 browser cases total), including classic/focused hydration, numeric controls, computed values, restored drafts, delayed synchronization, URL prefills and auto-submission.
- Review covered query dehydration and cleanup, private-route bootstrap, stale authentication responses, first-use/remount/error handling for feedback, lazy dialogs and demo, image dimensions/font character coverage, cache headers and runtime allocation. Concrete findings were corrected and the diff was reviewed again.

No production deployment, DNS change, production data mutation or merge was performed. Runtime headroom and higher SSR quota take effect only through a future authorized release. Real production traffic, external vendors and API latency still require verification after that release; the local score must not be represented as a production score.

## Independent review loop

The JhumanJ Code Review skill was run in a read-only subagent. Round one found three issues: the renderer quota was registered on the external rather than internal middleware path, a new login could still be expired during an authentication-module import, and a first changelog click did not load the vendor SDK. All three were corrected with regression tests. Round two found no remaining concrete findings.

The quota regressions instantiate the production HTTP Kernel and call the real public routes, checking bounded renderer reads, forged credentials, the shared ordinary 100/minute bucket and the independent external 60/minute limiter. Existing signed-download and export regressions also exercise the new middleware name. Authentication tests suspend the module import while the session changes. Mounted navigation/vendor tests exercise changelog-first intent, delayed vendor mount/user data and download retries.

These review fixes follow the benchmark source snapshot identified above. Full frontend/backend suites, lint and bundle guards were rerun for the updated application; the earlier measured timings remain a snapshot of their explicitly identified revision.
