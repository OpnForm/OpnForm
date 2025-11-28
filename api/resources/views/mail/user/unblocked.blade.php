@component('mail::message')
# Welcome Back! Your Account Has Been Restored

Hello {{ $user->name }},

Great news! Your account has been reviewed and unblocked by our team. You can now log in and use all features normally.

**Reason for unblocking:**
{{ $reason }}

## To Avoid Future Issues

To help prevent automatic blocks in the future, please ensure your forms:

- Have **clear, descriptive titles** that explain their purpose
- Include **detailed descriptions** of what data you're collecting and why
- Avoid requesting sensitive information like passwords for existing accounts
- Don't impersonate other companies or services

Our security system is designed to protect all users, and we appreciate your understanding.

@component('mail::button', ['url' => config('app.url')])
Access Your Account
@endcomponent

If you have any questions about form best practices, feel free to reach out via live chat.

Thanks,<br>
{{ config('app.name') }} Security Team
@endcomponent