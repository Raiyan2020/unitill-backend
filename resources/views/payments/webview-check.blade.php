<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>UniTill wallet / WebView check</title>
<style>
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;margin:0;padding:16px;background:#f6f7f9;color:#111}
  h1{font-size:18px;margin:0 0 12px}
  .row{background:#fff;border-radius:10px;padding:12px 14px;margin-bottom:10px;box-shadow:0 1px 2px rgba(0,0,0,.06)}
  .k{font-size:12px;color:#666;text-transform:uppercase;letter-spacing:.04em}
  .v{font-size:15px;margin-top:4px;word-break:break-all}
  .ok{color:#0a7d34;font-weight:600}.bad{color:#c0261c;font-weight:600}.warn{color:#9a6b00;font-weight:600}
  .verdict{font-size:16px;line-height:1.4}
  pre{font-size:11px;white-space:pre-wrap;background:#fff;padding:10px;border-radius:8px}
  button{font-size:15px;padding:10px 14px;border-radius:8px;border:0;background:#111;color:#fff}
</style>
</head>
<body>
<h1>Wallet availability inside this browser / WebView</h1>

<div class="row"><div class="k">Verdict</div><div class="v verdict" id="verdict">Running checks…</div></div>
<div class="row"><div class="k">Browser engine (User-Agent)</div><div class="v" id="ua"></div></div>
<div class="row"><div class="k">Running inside a WebView?</div><div class="v" id="webview"></div></div>
<div class="row"><div class="k">Secure context (HTTPS)</div><div class="v" id="secure"></div></div>
<div class="row"><div class="k">window.PaymentRequest (needed by Stripe Checkout for Google Pay)</div><div class="v" id="pr"></div></div>
<div class="row"><div class="k">Google Pay via PaymentRequest → canMakePayment()</div><div class="v" id="prgpay"></div></div>
<div class="row"><div class="k">Google Pay JS (pay.js) → isReadyToPay()</div><div class="v" id="gpay"></div></div>
<div class="row"><div class="k">Apple Pay (window.ApplePaySession)</div><div class="v" id="apple"></div></div>
<div class="row"><div class="k">Raw details</div><pre id="raw"></pre></div>
<div class="row"><button onclick="copyReport()">Copy report</button></div>

<script src="https://pay.google.com/gp/p/js/pay.js" async onload="gpayJs()" onerror="gpayJsFailed()"></script>
<script>
// Diagnostic only: tells us whether the surface this page is rendered in
// (Chrome, Safari, an in-app WebView...) is even capable of showing Google Pay /
// Apple Pay. Stripe Checkout decides wallet visibility from exactly these
// browser signals, so if they fail here, no Stripe-side setting will help.
const R = {};
const set = (id, text, cls) => { const el = document.getElementById(id); el.textContent = text; el.className = 'v ' + (cls || ''); R[id] = text; };
let gpayResult = null;

const ua = navigator.userAgent;
set('ua', ua);

// Android WebView adds "; wv" to the UA; iOS WKWebView lacks "Safari/" while still saying "AppleWebKit".
const isAndroid = /Android/i.test(ua);
const isIOS = /iPhone|iPad|iPod/i.test(ua);
const androidWebView = isAndroid && (/; wv\)/.test(ua) || /Version\/\d+\.\d+.*Chrome/.test(ua));
const iosWebView = isIOS && !/Safari\//.test(ua);
const inWebView = androidWebView || iosWebView;
set('webview', inWebView ? 'YES — in-app WebView detected' : 'No — looks like a normal browser', inWebView ? 'warn' : 'ok');

set('secure', window.isSecureContext ? 'Yes' : 'NO — wallets require HTTPS', window.isSecureContext ? 'ok' : 'bad');

const hasPR = typeof window.PaymentRequest === 'function';
set('pr', hasPR ? 'Available' : 'NOT available — Google Pay cannot render here', hasPR ? 'ok' : 'bad');

const hasApple = typeof window.ApplePaySession !== 'undefined';
set('apple', hasApple ? ('Available' + (ApplePaySession.canMakePayments() ? ', device can make payments' : ', but canMakePayments() = false')) : 'Not available (expected outside Safari / SFSafariViewController)', hasApple ? 'ok' : '');

async function prGooglePay() {
  if (!hasPR) { set('prgpay', 'Skipped — no PaymentRequest'); return false; }
  try {
    const req = new PaymentRequest(
      [{ supportedMethods: 'https://google.com/pay', data: { apiVersion: 2, apiVersionMinor: 0, environment: 'PRODUCTION',
          allowedPaymentMethods: [{ type: 'CARD', parameters: { allowedAuthMethods: ['PAN_ONLY', 'CRYPTOGRAM_3DS'], allowedCardNetworks: ['VISA', 'MASTERCARD', 'AMEX'] } }] } }],
      { total: { label: 'Test', amount: { currency: 'GBP', value: '0.30' } } }
    );
    const can = await req.canMakePayment();
    let enrolled = null;
    if (can && typeof req.hasEnrolledInstrument === 'function') { try { enrolled = await req.hasEnrolledInstrument(); } catch (e) { enrolled = 'n/a (' + e.message + ')'; } }
    const ready = can && enrolled !== false;
    set('prgpay', !can ? 'No — Google Pay not available in this surface' : (enrolled === false ? 'Supported, but hasEnrolledInstrument() = false: no saved card / not signed in' : 'YES — Google Pay available' + (enrolled === true ? ' with a saved card' : ' (card presence: ' + enrolled + ')')), !can ? 'bad' : (enrolled === false ? 'warn' : 'ok'));
    return ready;
  } catch (e) {
    set('prgpay', 'Error: ' + (e && e.message ? e.message : e), 'bad');
    return false;
  }
}

async function gpayJs() {
  try {
    const client = new google.payments.api.PaymentsClient({ environment: 'PRODUCTION' });
    const res = await client.isReadyToPay({ apiVersion: 2, apiVersionMinor: 0, existingPaymentMethodRequired: true,
      allowedPaymentMethods: [{ type: 'CARD', parameters: { allowedAuthMethods: ['PAN_ONLY', 'CRYPTOGRAM_3DS'], allowedCardNetworks: ['VISA', 'MASTERCARD', 'AMEX'] } }] });
    gpayResult = !!(res.result && res.paymentMethodPresent);
    set('gpay', res.result ? (res.paymentMethodPresent ? 'YES — supported AND a card is saved in this Google account' : 'Supported, but NO card saved in this Google account — Stripe hides Google Pay in that case') : 'No — isReadyToPay = false', res.result ? (res.paymentMethodPresent ? 'ok' : 'warn') : 'bad');
  } catch (e) {
    set('gpay', 'Error: ' + (e && (e.statusMessage || e.message) ? (e.statusMessage || e.message) : JSON.stringify(e)), 'bad');
  }
  finish();
}
function gpayJsFailed() { set('gpay', 'pay.js failed to load (blocked network / no Google services)', 'bad'); finish(); }

let prResult = null;
prGooglePay().then(r => { prResult = r; finish(); });

// Safety net: if pay.js never fires onload/onerror (some WebViews swallow it),
// still produce a verdict after a few seconds.
setTimeout(() => { if (finished < 2) { set('gpay', 'pay.js did not respond within 8s', 'bad'); finished = 1; finish(); } }, 8000);

let finished = 0;
function finish() {
  finished++; if (finished < 2) return;
  let v, cls;
  if (!hasPR) { v = 'Google Pay cannot appear here: this surface has no PaymentRequest API. Stripe Checkout / Payment Links will never show Google Pay in it. Open the URL in Chrome (Custom Tabs) instead.'; cls = 'bad'; }
  else if (prResult || gpayResult) { v = 'Google Pay CAN appear here. If Stripe Checkout still hides it, the issue is on the Stripe/session side.'; cls = 'ok'; }
  else { v = 'PaymentRequest exists but Google Pay is not ready on this device/account: sign into Google with a saved card, disable incognito, allow "check for saved payment methods".'; cls = 'warn'; }
  if (isIOS) v += ' (iOS: Google Pay is never offered by Stripe Checkout; only Apple Pay in Safari.)';
  set('verdict', v, 'v verdict ' + cls);
  document.getElementById('raw').textContent = JSON.stringify({ ...R, isAndroid, isIOS, androidWebView, iosWebView, time: new Date().toISOString() }, null, 2);
}
function copyReport() { navigator.clipboard && navigator.clipboard.writeText(document.getElementById('raw').textContent); }
</script>
</body>
</html>
