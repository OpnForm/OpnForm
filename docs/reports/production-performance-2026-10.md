# Production performance qualification

This change prepares an application and image update. It does not deploy it.

## Runtime configuration

The hosted release controller sets the frontend allocation to 2 GiB and the V8 old-space limit to 1536 MiB, preserving other Node options. The remaining allocation covers young-generation heap, native allocations and buffers. Deployment is still an explicit release operation; the plan-only command does not update Compose or restart containers.

Every SSR request owns its QueryClient. Server queries use an infinite garbage-collection interval so that timer closures do not retain completed requests. Rendering dehydrates the response state and clears the request's query cache; error handling clears it too. Browser caches retain their existing ten-minute lifetime. No shared HTML cache is introduced.

Public API reads authenticated by `FRONT_API_SECRET` have a separate bounded quota (`SSR_READS_PER_MINUTE`, default 6000 per renderer IP). Only GET requests to public form details, feature flags and plans qualify. This does not grant permissions or change validation, password protection, private resources, submissions, uploads or ordinary browser quotas. No forwarded client-IP header is trusted for this exception.

Hosted backend roles compile configuration, routes, events and Blade views after their runtime environment is injected. A failed cache command stops startup. Runtime secrets are stored privately, and no shared Redis flush is performed.

## Browser behavior

Marketing pages defer private user/workspace reads until hydration finishes. Protected app routes retain their authentication bootstrap. Public respondent pages continue to skip it.

The live demo activates when at least 35% of its reserved frame is visible and keeps its minimum height reserved. Only the selected classic or focused renderer is imported. Login and registration dialogs load when opened. Feedback loads its SDK on first feedback interaction; Clarity is imported only when its existing session and iframe conditions allow it. Images below the fold load lazily, the large footer texture is resized and compressed, and Euclid uses WOFF2 with all original characters retained.

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
