# Security Report - Connectix v4.0.43
Date: 2026-10-09
Scope: panel PHP + client Flutter + build

## Auth
- Panel: Bearer token api_token (reseller), auth_token app_<hmac> (client). HMAC key in Setting? Checked ApiControllerV2 login generates auth_token via hash_hmac with client secret.
- Sublink: sub_token random 32 chars, accessed via /sub/{token} without auth but token unguessable. Rate limit? Not seen - need check.
- Server nodes: api_url + api_username/password or pg_key_ token. Stored encrypted? In server_nodes table plaintext? Need verify. If DB leak, all nodes compromised.
- APK download: download_apk.php serves without auth but with ?v param - OK, public asset.

## Secrets
- GitHub token ghp_V0V1g... stored at /home/user/.github_token_secure outside repo - GOOD, not in code.
- No secrets in git diff per audit (git diff reviewed).
- config.php DB credentials - should not be in repo, is .gitignored? Need verify .gitignore includes config.php.

## Injection / XSS / CSRF
- Panel PHP uses PDO prepared statements? Database.php uses PDO with prepare - need verify all queries use prepare, not string concat. Quick grep shows some use prepare, but need full review.
- Download APK filename param ?file=arm64 sanitized? download_apk.php uses whitelist ['arm64','arm32','universal'] - GOOD.
- Flutter: config_uri from server, parsed via V2Ray - if malicious config, could execute? V2Ray config JSON, not code exec, but could cause DoS via infinite loop or large file. Need size limit on config (currently not seen).
- XSS: Panel dashboard views output user-controlled data (username, etc.) - need htmlspecialchars.

## VPN Security
- TUN mode: If fake VPN (Hypothesis 1), traffic leaks direct, not encrypted - CRITICAL privacy failure. User thinks VPN on but ISP sees traffic.
- DNS leak: If DoH direct blocked and fallback to system DNS, DNS queries leak to ISP even if TUN on. Need ensure DNS via proxy or block direct DNS.
- Split tunneling: defaultDomesticBypassApps 130 domestic apps bypass VPN via addDisallowedApplication - OK, but if bypass list includes filtered app due to bug, leak.
- WakeLock/WiFiLock: Acquired but released on disconnect? MainActivity has acquireWakeLock but need ensure release on stopVless to avoid battery drain, not security.

## Dependencies
- flutter_vless ^1.1.6 - Xray core version? Need check for known CVEs. Xray often has CVEs for VMess.
- PHP 8.4.26 - latest, good.
- GitHub Actions uses actions/checkout, subosito/flutter-action - pinned? Not pinned to SHA, risk supply chain.

## Recommendations
- CRITICAL: Fix TUN mode to ensure real VPN, else privacy leak.
- HIGH: Add rate limit to /sub/{token} and /api/v1/app/login to prevent brute force.
- MEDIUM: Encrypt server_nodes api credentials at rest.
- MEDIUM: Ensure config.php not in repo, add to .gitignore.
- MEDIUM: Pin GitHub Actions to SHA.
- LOW: Add CSP headers to panel.

## Evidence
- AndroidManifest no VPNService declaration - relies on flutter_vless plugin - verified via cat.
- download_apk.php whitelist - verified via read.
- Token storage outside repo - verified via ls ~/.github_token_secure.
- No secrets in git diff - verified via git diff.

## Status
- FIXED: Old version serving (now 4.0.43)
- OPEN CRITICAL: Fake VPN potential leak
- OPEN MEDIUM: Rate limit, encryption at rest, CSP
