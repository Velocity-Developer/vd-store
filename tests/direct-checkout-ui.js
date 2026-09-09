// node tests/direct-checkout-ui.js
const fs = require('fs');
const vm = require('vm');
const assert = require('assert/strict');
const path = require('path');
const template = fs.readFileSync(path.join(__dirname, '../templates/frontend/components/add-to-cart.php'), 'utf8');
const scripts = [...template.matchAll(/<script>([\s\S]*?)<\/script>/g)].map(match => match[1].replace(/<\?php[\s\S]*?\?>/g, 'null'));

async function run(buyNow, success = true) {
  const calls = [];
  const events = [];
  const settings = { restUrl: '/wp-json/wp-store/v1/', nonce: 'test' };
  const window = {
    wpStoreSettings: settings,
    location: { origin: 'https://shop.example', href: '' },
    dispatchEvent: event => events.push(event),
  };
  const context = vm.createContext({
    window, wpStoreSettings: settings, URL,
    document: { addEventListener() {}, dispatchEvent: event => events.push(event) },
    CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } },
    fetch: async (url, options) => {
      calls.push({ url, data: JSON.parse(options.body) });
      return { ok: success, json: async () => success ? { token: 'test-token', items: [] } : { message: 'Stok kurang' } };
    },
  });
  scripts.forEach(script => vm.runInContext(script, context));
  const button = window.wpStoreAddToCart({ id: 77, qtyEnabled: true, minQty: 1, qty: 3, buyNow, checkoutUrl: '/checkout/?ref=single', basicName: 'Warna' });
  button.selectedBasic = 'Merah';
  await button.confirmAdd();
  assert.equal(calls.length, 1);
  assert.equal(calls[0].data.qty, 3);
  assert.equal(calls[0].data.options.Warna, 'Merah');
  assert.equal(calls[0].url, settings.restUrl + (buyNow ? 'checkout/direct' : 'cart'));
  if (buyNow) {
    assert(!events.some(event => event.type === 'wp-store:cart-updated'));
    assert.equal(window.location.href, success ? 'https://shop.example/checkout/?ref=single&direct_checkout=test-token' : '');
  } else {
    assert(events.some(event => event.type === 'wp-store:cart-updated'));
    assert.equal(window.location.href, '');
  }
  assert.equal(button.loading, false);
}

(async () => {
  await run(true);
  await run(true, false);
  await run(false);
  const checkout = fs.readFileSync(path.join(__dirname, '../templates/frontend/pages/checkout.php'), 'utf8');
  for (const script of checkout.matchAll(/<script>([\s\S]*?)<\/script>/g)) {
    new vm.Script(script[1].replace(/<\?php[\s\S]*?\?>/g, 'null'));
  }
  console.log('PASS: Buy Now routes separately, preserves cart events, handles errors, and retains options/quantity. Checkout scripts parse.');
})().catch(error => { console.error(error); process.exitCode = 1; });
