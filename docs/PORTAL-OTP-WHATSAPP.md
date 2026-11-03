# Portal OTP and WhatsApp Direction

Status: User-provided direction — not client approval (2026-10-02).

The Parent/Student portal uses `/student-login` with an explicit Student or Parent choice, normalized verified mobile number, and a short-lived WhatsApp OTP. Unknown and ineligible numbers receive the same generic response.

## Security controls

- OTPs use cryptographically secure numeric generation and password hashing.
- Challenges expire after five minutes, allow five verification attempts, and enforce a sixty-second resend cooldown.
- A new request invalidates prior pending challenges for the same session and type.
- OTP values, mobile values, provider tokens, and credentials are never logged or rendered.
- Student access resolves to an active Player; Parent access resolves only through active guardian relationships.
- Portal sessions remain separate from staff sessions.

## Provider boundary

`WhatsAppOtpProviderInterface` is the application boundary. `ConfiguredWhatsAppOtpProvider` reads API URL, bearer token, sender, and timeout from environment configuration. Missing configuration fails closed and keeps the generic eligibility response. Tests must use a fake provider and must not send real WhatsApp messages.

## Schema and deployment

`20261002000200_AddPortalOtpAuthentication` adds mobile verification metadata and `portal_otp_challenges`. It is forward-only and must be reviewed and applied by the deployment owner before OTP login can operate. Mobile verification population, provider selection, consent, activation, document visibility, and retention remain NEEDS_DECISION.

## Development-only OTP display

This is user-provided local testing direction and not client approval. After a successful eligible OTP request, the login response may show a warning panel containing the generated OTP only when `CI_ENVIRONMENT` is `development` or `testing`, the database name is exactly `mcc_cricket_club_test`, and `PORTAL_OTP_DEBUG=true`. The server enforces all three checks. The OTP is not persisted in plaintext, logged, placed in a URL, attribute, cookie, audit record, API response, or staff page. The panel is omitted for unknown/ineligible mobiles and failed delivery, and the display is cleared on a new request, expiry, successful verification, or logout. The flag defaults to false; deployment-owner evidence is still required before any local test use.

## Local development test flow (2026-10-02)

User-provided local testing direction — not client approval. Use only the `mcc_cricket_club_test` database with `CI_ENVIRONMENT=development`, `PORTAL_OTP_DEBUG=true`, and a configured test or mock WhatsApp provider. The portal migrations `20261002000100_CreatePortalAccounts` and `20261002000200_AddPortalOtpAuthentication` must be applied to the `tests` group.

Open `/student-login`, choose Student with `+919900000101` or Parent with `+919900000102`, request an OTP, and complete verification using the development-only OTP panel. Successful verification redirects Student users to `/student-dashboard` and Parent users to `/parent-dashboard`. Logout clears the portal session; portal users cannot use the staff dashboard.

The local `.env` uses `PORTAL_OTP_PROVIDER=mock`; the mock performs no HTTP request and returns success only under the same three server-side gates. No real WhatsApp credentials are stored or added. The OTP debug panel remains gated by environment, exact database name, and flag; it never appears in production, on another database, or after failed delivery. If the mock gates fail, the existing provider boundary fails closed.
