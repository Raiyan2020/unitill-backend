# Web/share domain change — mobile action required (2026-09-10)

## What changed

The backend's public web domain is moving off the old subdomain and onto the apex domain:

- **Old:** subdomain (e.g. `api.unitill.uk` / whatever subdomain the app currently has hardcoded)
- **New:** `https://unitill.uk/`

All public web pages — shared ad links (`/ads/{id}`), the account-deletion page, the app-download
page — are served from `https://unitill.uk/` going forward. The API itself is unaffected by this
change; this is specifically about the **web URL** the app uses for links, share sheets, and
Universal Links / App Links.

## What you need to do

**Update any hardcoded web/share base URL in the app from the old subdomain to
`https://unitill.uk/`.** This includes (check for all of these — not all may apply):

- The base URL used to build shareable ad links (the URL put in the share sheet when a user shares
  a listing)
- The `associated domains` entitlement on iOS (`applinks:unitill.uk`) — must be the apex domain,
  not a subdomain, since that's where `apple-app-site-association` is hosted
- The Android App Links `intent-filter` host (`android:host="unitill.uk"`) in the manifest
- Any WebView base URL or config pointing at the old subdomain

## Why this matters for Universal Links / App Links

Universal Links (iOS) and App Links (Android) only work if the domain in the app's entitlement/
manifest **exactly matches** the domain hosting the verification files
(`https://unitill.uk/.well-known/apple-app-site-association` and
`https://unitill.uk/.well-known/assetlinks.json`). If the app still points at the old subdomain,
tapping a shared `/ads/{id}` link will never open the app — it'll always fall through to the
browser (which now auto-redirects to the store instead, but won't deep-link into the specific ad).

`assetlinks.json` has already been updated on our side with the current package name
(`uk.unitill.app`) and signing certificate fingerprint.

## Still blocked on your side

We also still need the **Apple Team ID** for the `uk.unitill.app` build to finish the iOS half of
this (`apple-app-site-association` needs `TEAMID.uk.unitill.app`, not the old app's ID). Find it in
Xcode → target → Signing & Capabilities → Team, or App Store Connect → Membership — it's a
10-character alphanumeric string (e.g. `ABCDE12345`).

## Observed problem on Android

Tested `https://unitill.uk/ads/P7KV3ANKED` on a real Android device with the app already
installed. The backend's redirect response was confirmed correct via `curl` with an Android
user-agent:

```
HTTP/1.1 302 Found
Location: intent://unitill.uk/ads/P7KV3ANKED#Intent;scheme=https;package=uk.unitill.app;S.browser_fallback_url=https%3A%2F%2Fplay.google.com%2Fstore%2Fapps%2Fdetails%3Fid%3Duk.unitill.app;end
```

Despite this, tapping the link on the device sent the user to the Play Store instead of opening
the installed app.
