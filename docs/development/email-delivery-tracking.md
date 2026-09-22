# Integration email events

## Resolved design review (grill-me)

- **Do we need another tracking system?** No. Use the existing integration event, its JSON data, and an indexed nullable UUID. Create the event before sending and register each resolved recipient before dispatch. Non-email integrations keep their existing behavior.
- **What does success prove?** A transport response proves acceptance only. A signed SES Delivery event proves acceptance by the receiving server, never inbox placement. Old Success events are explicitly untracked. Log/array transports are marked not sent.
- **What if only one recipient fails?** Keep each recipient's result, including invalid addresses, application cancellation, uncertain exceptions, bounces and complaints. Continue other recipients. Trim and deduplicate addresses. Never silently discard invalid recipients.
- **Can feedback arrive first or twice?** Yes. Correlate two server-generated MIME headers (event UUID and recipient UUID), require matching destination and SES message ID, and lock the event row when merging. Retain one fact per feedback type. A late Delivery cannot erase Bounce/Complaint. Keep attempt/acceptance timestamps and feedback timestamps.
- **Are existing SNS webhooks sufficient?** They provide the feedback mechanism; they do not automatically associate feedback with an integration execution. Subscribe this new signed endpoint alongside the existing bounce/complaint handlers. It never writes the legacy suppression table or removes its consumers.
- **Could another AWS customer forge feedback?** A valid signature is insufficient: the topic must also appear in this app's exact allowlist. Confirmation goes only to the AWS endpoint constructed from that allowlisted ARN. Invalid/unsigned requests fail closed.
- **Can we retry an uncertain attempt?** No automatic resend is introduced. NoteForms disables grouped retry for tracked email events, server-side as well as in the UI. Queue redelivery/exactly-once delivery is NOT solved by this change. Worker death leaves a visible uncertain event after ten minutes; this threshold is a display heuristic, not proof of delivery failure.
- **What about custom SMTP?** Acceptance is visible but SES delivery feedback does not cover external SMTP. Cached custom SMTP transport must be purged when changing workspace configuration.
- **Does this change anti-abuse or support access?** Existing sending restrictions remain. NoteForms baseline counts include tracked attempts. Events remain scoped to the form's integration, including the previously insufficiently scoped NoteForms list endpoint.
- **What remains outside coverage?** This tracks form email integrations after their worker starts, not every transactional email or a job that never starts. No retroactive provider evidence is invented. Existing history retention (14 days) applies; unmatched/expired feedback is acknowledged and logged. No new SQS/configuration-set infrastructure is needed.

## Deployment and activation (not performed by this change)

1. Apply the additive migration before starting the new application/workers. Deploy backend and frontend together; refresh cached routes/config/events and restart long-lived workers. NoteForms includes the prior signed SNS/topic-allowlist repair as its base.
2. Set `EMAIL_TRACKING_SNS_TOPICS` to the exact comma-separated ARNs for this application's Bounce, Complaint and Delivery topics in the sending SES region/account. Empty means the endpoint accepts nothing. Do not include other applications' topics.
3. Subscribe the HTTPS endpoint `/aws/sns/ses/integration-events` to the bounce and complaint topics without replacing existing subscriptions. Create/use a separate Delivery topic and configure SES identity Delivery notifications to it. Existing bounce endpoints may not support Delivery payloads.
4. Enable inclusion of original email headers on **all three** SES identity notification types. Check every sending identity and region. Configure SNS topic policy to permit SES publishing. This implementation uses `notificationType` or `eventType` and requires intact tracking headers; absent/truncated headers leave delivery unconfirmed and produce an unmatched-feedback log.
5. Verify confirmed subscriptions, then run an explicitly authorized controlled submission to a test mailbox and SES bounce/complaint simulator addresses. Confirm the original integration event gains the correct recipient outcome and SES message ID, and duplicate feedback produces no duplicate failure alert. Do not send test messages to customers.
6. Monitor unmatched-feedback logs and SNS failed-notification metrics. An accepted event without feedback stays unconfirmed; it is not reported as delivered or inferred as bounced. Refresh the event modal to load asynchronous updates.

The webhook stores only normalized feedback type/subtype, identifiers and timestamps, not complete message content or raw SMTP responses. Transient database errors return server errors so SNS can retry. Permanent non-matches are acknowledged to avoid a retry storm after history deletion.

## Rollback

Roll back code/workers first and leave the nullable column in place until no new workers use it. Disable the new subscriptions independently; preserve legacy bounce/complaint subscriptions. Removing the column later drops correlation only, not the event JSON. Do not replay uncertain events while rolling back.

## Failure isolation and callback retries

A later listener failure cannot erase already-persisted transport acceptance. Failure alerts are best-effort: queue failures are logged but cannot interrupt recipient sends or SNS acknowledgement. If tracking storage fails after a possible send, the handler stops and logs the tracking ID instead of automatically replaying the sending job; the retained incomplete event becomes uncertain in the UI. This does not provide exactly-once sending after worker termination.

SNS certificate downloads have a five-second timeout, redirects disabled and a one-hour cache. Temporary HTTP failures return 503 so SNS can retry, while invalid signatures return 403. Malformed or unmatched SES feedback is acknowledged without changing the event; duplicate feedback does not refresh timestamps. Known errors remain highlighted even if another recipient has an uncertain result.

Run `npm run test:email-events` from client/ for the event status and Vue rendering regressions; CI also runs this command.
