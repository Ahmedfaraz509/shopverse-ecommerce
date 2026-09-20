/* ============================================================
   ShopVerse – checkout.js
   Validation, order summary and simulated order placement.
   ============================================================ */

SV.initCheckout = function () {
  const cart = SV.getCart();
  if (!cart.length) {
    document.getElementById('checkoutMain').innerHTML = `
      <div class="text-center py-5">
        <i class="bi bi-bag-x fs-1 text-muted-2"></i>
        <h4 class="mt-3">Your cart is empty</h4>
        <p class="text-muted-2">Add some products before proceeding to checkout.</p>
        <a href="shop.php" class="btn btn-primary">Browse Products</a>
      </div>`;
    return;
  }

  SV.renderCheckoutSummary();

  // Prefill from saved checkout info / logged-in user
  const form = document.getElementById('checkoutForm');
  const saved = SV.store.get('checkout_info', null);
  const user = SV.getUser();
  if (saved) Object.entries(saved).forEach(([k, v]) => { if (form[k]) form[k].value = v; });
  else if (user) {
    const [first, ...rest] = user.name.split(' ');
    form.firstName.value = first; form.lastName.value = rest.join(' ');
    form.email.value = user.email; form.phone.value = user.phone || '';
    form.address.value = user.address || '';
  }

  // Payment method panels
  document.querySelectorAll('input[name="payment"]').forEach((r) =>
    r.addEventListener('change', () => {
      document.getElementById('cardFields').classList.toggle('d-none', r.value !== 'Credit Card' || !r.checked);
      document.getElementById('bankFields').classList.toggle('d-none', r.value !== 'Bank Transfer' || !r.checked);
    })
  );

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    form.classList.add('was-validated');
    if (!form.checkValidity()) { SV.toast('Please complete all required fields', 'danger'); return; }

    const btn = document.getElementById('placeOrderBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing payment...';

    const data = Object.fromEntries(new FormData(form).entries());
    SV.store.set('checkout_info', data);
    const order = await SV.placeOrder(data);

    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-lock-fill me-1"></i>Place Order';

    document.getElementById('successOrderId').textContent = order.id;
    document.getElementById('successTotal').textContent = SV.money(order.total);
    document.getElementById('successEmail').textContent = order.email;
    document.getElementById('successPayment').textContent = order.payment;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('successModal')).show();

    SV.clearCart();
    SV.store.remove('coupon');
  });
};

/** Simulated AJAX order submission */
SV.placeOrder = async function (data) {
  await SV.api.delay(1200);
  const t = SV.cartTotals();
  const order = {
    id: 'SV-' + Math.floor(100000 + Math.random() * 899999),
    date: new Date().toISOString().slice(0, 10),
    customer: `${data.firstName} ${data.lastName}`,
    email: data.email, phone: data.phone,
    address: `${data.address}, ${data.city}, ${data.state} ${data.postal}, ${data.country}`,
    items: SV.getCart().map((l) => ({ id: l.id, name: l.name, price: l.price, qty: l.qty, image: l.image })),
    subtotal: t.subtotal, shipping: t.shipping, tax: t.tax, discount: t.discount,
    total: t.total, payment: data.payment, status: 'Pending'
  };
  const orders = SV.getOrders();
  orders.unshift(order);
  SV.saveOrders(orders);
  return order;
};

SV.renderCheckoutSummary = function () {
  const cart = SV.getCart();
  const t = SV.cartTotals();
  document.getElementById('checkoutSummary').innerHTML = `
    <h5 class="mb-3">Order Summary</h5>
    <div class="mb-3">
      ${cart.map((l) => `
        <div class="d-flex gap-2 align-items-center py-2 border-bottom">
          <img src="${l.image}" width="52" height="52" class="rounded-2" style="object-fit:cover" alt="">
          <div class="flex-grow-1">
            <div class="small fw-semibold" style="line-height:1.25">${l.name}</div>
            <small class="text-muted-2">Qty: ${l.qty} × ${SV.money(l.price)}</small>
          </div>
          <strong class="small">${SV.money(l.price * l.qty)}</strong>
        </div>`).join('')}
    </div>
    <div class="summary-row"><span>Subtotal</span><strong>${SV.money(t.subtotal)}</strong></div>
    <div class="summary-row"><span>Discount</span><strong class="text-success">-${SV.money(t.discount)}</strong></div>
    <div class="summary-row"><span>Shipping</span><strong>${t.shipping ? SV.money(t.shipping) : 'Free'}</strong></div>
    <div class="summary-row"><span>Tax (8%)</span><strong>${SV.money(t.tax)}</strong></div>
    <div class="summary-row summary-total"><span>Total</span><span>${SV.money(t.total)}</span></div>
    <p class="text-muted-2 small mt-3 mb-0"><i class="bi bi-shield-check me-1"></i>Your payment details are encrypted and never stored.</p>`;
};
