/** Keep answers and attribution private unless the creator explicitly enables sharing. */
export function parentSubmissionPayload(payload, shareAnswers) {
  if (shareAnswers === true) return payload
  const notification = { ...payload }
  delete notification.submission_data
  delete notification.meta
  return notification
}
