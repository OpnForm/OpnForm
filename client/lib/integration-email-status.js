export function isStale(event, now = Date.now()) {
  if (!event.email_tracking) return false
  return (event.status === "Processing" && now - Date.parse(event.updated_at) > 10 * 60 * 1000) ||
    Object.values(event.data?.email?.recipients || {}).some((recipient) =>
      ["pending", "sending"].includes(recipient.status) && now - Date.parse(recipient.updated_at) > 10 * 60 * 1000)

}

export function eventLabel(event) {
  if (event.status === "Error") return "Action needed"
  if (event.legacy_email && event.status === "Success") return "Completed · delivery untracked"
  if (isStale(event)) return "Outcome unknown"
  return ({ Accepted: "Accepted · delivery unconfirmed", Delivered: "Delivered to mail server", Unknown: "Outcome unknown", Error: "Action needed" })[event.status] || event.status
}

export function eventColor(event) {
  if (event.status === "Error") return "error"
  if (isStale(event) || ["Unknown", "Accepted", "Processing"].includes(event.status)) return "warning"
  if (["Success", "Delivered"].includes(event.status)) return event.legacy_email ? "neutral" : "success"
  return event.status === "Error" ? "error" : "neutral"
}

export function recipientLabel(status) {
  return ({ pending: "Not yet attempted", sending: "Sending · outcome unconfirmed", accepted: "Accepted by transport · delivery unconfirmed", delivered: "Delivered to mail server", invalid: "Invalid address · not sent", blocked: "Cancelled by application · not sent", not_sent: "Non-delivering transport", unknown: "Outcome unknown", rejected: "Rejected by email provider", bounced: "Bounced", complained: "Spam complaint" })[status] || status
}
