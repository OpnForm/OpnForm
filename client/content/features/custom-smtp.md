---
title: Custom SMTP
slug: custom-smtp
summary: Send form notifications from your own SMTP server so emails come from your domain and follow your delivery policies.
category: Notifications & Delivery
plan: Pro
color: violet
icon: i-heroicons-envelope
featured: true
order: 2
seoTitle: Custom SMTP for OpnForm notifications
seoDescription: Configure custom SMTP in OpnForm to send form notifications from your own mail server and sender domain.
published: true
---

## Deliver notifications from your own mail server

Custom SMTP lets OpnForm send form notifications through your SMTP provider instead of a shared default sender. That means confirmation emails, team alerts, and respondent messages can come from an address your audience already recognizes.

::feature-callout
---
title: What custom SMTP unlocks
icon: i-heroicons-envelope
tone: emerald
items:
  - Emails sent from your own domain and sender address
  - Better control over deliverability and sender reputation
  - Workspace-specific SMTP credentials on supported plans
  - Separation between marketing, product, and operations mail streams
---
Custom SMTP is available on paid cloud plans. Self-hosted Enterprise customers can also configure dedicated SMTP settings per workspace.
::

## When to use it

Use custom SMTP when you need:

- Branded sender addresses such as `forms@yourcompany.com`
- Compliance with internal email policies or approved providers
- Separate SMTP credentials for different teams or workspaces
- More control over bounce handling and delivery monitoring

::feature-metric-grid
---
metrics:
  - value: Branded
    label: sender addresses on your own domain
  - value: Controlled
    label: delivery through your SMTP provider
  - value: Isolated
    label: workspace-level settings on supported plans
---
::

## Configure custom SMTP

::feature-workflow
---
title: Set up workspace email delivery
steps:
  - title: Open workspace email settings
    description: Go to your workspace settings and open the email configuration section for SMTP credentials.
  - title: Add SMTP host and credentials
    description: Enter the SMTP host, port, encryption method, username, password, and sender address from your provider.
  - title: Save and test delivery
    description: Save the configuration and trigger a test notification to confirm messages are sent successfully.
  - title: Use it for form notifications
    description: Form email notifications from that workspace will use your SMTP settings instead of the default sender.
---
::

## How it compares to default email

OpnForm can still send notifications through the default platform mailer when custom SMTP is not configured. Custom SMTP overrides that behavior for the workspace where it is enabled, giving you full control over sender identity and delivery provider.

## Best practices

- Use a dedicated sender address for form notifications.
- Enable TLS/SSL according to your SMTP provider requirements.
- Monitor bounce rates after switching providers or domains.
- Pair custom SMTP with custom domains for a fully branded respondent journey.
