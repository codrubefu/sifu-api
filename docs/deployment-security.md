# Production deployment security baseline

These controls are mandatory for every production deployment:

* **HTTPS only:** terminate TLS with a currently supported protocol at the ingress, redirect HTTP to HTTPS, set `APP_URL=https://…`, and never expose the PHP container directly. HSTS is emitted for secure requests; enable it only after every subdomain supports HTTPS.
* **Trusted proxies:** set `TRUSTED_PROXIES` to the explicit comma-separated ingress/load-balancer addresses. Never use an unrestricted wildcard. This is required so client IP rate-limit keys and HTTPS detection cannot be forged.
* **Cookies and headers:** set `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax` (or `strict` where possible), and an appropriate `SESSION_DOMAIN`. The application emits HSTS, `nosniff`, frame denial, referrer and permissions headers; the edge must add a beneficiary-approved Content Security Policy.
* **Secrets:** inject `APP_KEY`, database credentials, callback keys, mail/SMS credentials and other secrets from the platform secret manager. Do not commit them, bake them into images, print them in logs, or reuse them between environments. Define an owner and rotation/revocation runbook.
* **Branch access:** branches/locations may reach the application and administrative infrastructure only through the corporate VPN, with individual identities, MFA, least-privilege network rules and centrally retained access logs. Do not allow public-IP bypasses.
* **Authentication:** configure bearer lifetime and the distinct login/callback/expensive endpoint limits using the documented environment variables. Configure operator and administrator password policy values to the beneficiary's approved baseline; non-zero `*_UNCOMPROMISED_THRESHOLD` values enable compromised-password rejection.

After changing a password or suspending a user, all bearer sessions are revoked automatically. During a security incident, the response workflow must call `BearerTokenService::revokeAll($user)`, rotate affected secrets and preserve sanitized authentication logs.
