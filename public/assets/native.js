'use strict';
// Progressive enhancement only: routing, pricing, authorization and saving stay in PHP.
const rupiah = value => new Intl.NumberFormat('id-ID', {style:'currency', currency:'IDR', maximumFractionDigits:0}).format(value);
for (const button of document.querySelectorAll('[data-demo]')) {
  button.addEventListener('click', () => {
    const form = document.querySelector('[data-login]');
    const admin = button.dataset.demo === 'admin';
    form.elements.email.value = admin ? 'admin@rentique.test' : 'customer@rentique.test';
    form.elements.password.value = admin ? 'Admin123!' : 'Customer123!';
    form.elements.email.focus();
  });
}
for (const form of document.querySelectorAll('[data-payment]')) {
  function update() {
    const balance = Number(form.dataset.balance);
    if (!form.elements.payment_mode) return;
    const partial = form.elements.payment_mode.value === 'partial';
    const amount = partial ? Number(form.elements.amount.value) : balance;
    form.querySelector('[data-payment-partial]').hidden = !partial;
    form.elements.amount.required = partial;
    form.elements.amount.disabled = !partial;
    const method = form.elements.payment_method.value;
    for (const field of form.querySelectorAll('[data-method]')) {
      const active = field.dataset.method === method;
      field.hidden = !active;
      for (const input of field.querySelectorAll('input,select')) input.disabled = !active;
    }
    form.querySelector('[data-payment-total]').textContent = rupiah(amount);
    const received = form.elements.cash_received.value === '' ? amount : Number(form.elements.cash_received.value);
    form.querySelector('[data-change]').textContent = rupiah(Math.max(0,received-amount));
  }
  form.addEventListener('change', update);
  form.addEventListener('input', update);
  update();
}
for (const button of document.querySelectorAll('[data-print]')) button.addEventListener('click', () => window.print());
for (const image of document.querySelectorAll('img')) {
  image.addEventListener('error', () => {
    if (image.dataset.fallbackAttempted) return;
    image.dataset.fallbackAttempted = 'true';
    image.src = '/images/dress.webp';
  }, {once:true});
}
for (const dialog of document.querySelectorAll('dialog[open][data-dialog]')) {
  if (typeof dialog.showModal !== 'function') continue;
  dialog.close();
  dialog.showModal();
}
