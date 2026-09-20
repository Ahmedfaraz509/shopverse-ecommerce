/* ============================================================
   ShopVerse – cart.js  (cart page rendering & interactions)
   ============================================================ */

SV.initCart = async function () {
  await SV.loadProducts();
  SV.renderCart();

  // Event delegation for qty / remove / wishlist moves
  document.getElementById('cartItems').addEventListener('click', (e) => {
    const inc = e.target.closest('[data-inc]');
    const dec = e.target.closest('[data-dec]');
    const rm = e.target.closest('[data-remove]');
    const mv = e.target.closest('[data-move-wish]');
    if (inc) { const l = SV.getCart().find((x) => x.id === +inc.dataset.inc); SV.updateCart(l.id, l.qty + 1); SV.renderCart(); }
    if (dec) { const l = SV.getCart().find((x) => x.id === +dec.dataset.dec); SV.updateCart(l.id, l.qty - 1); SV.renderCart(); }
    if (rm) { SV.removeFromCart(rm.dataset.remove); SV.renderCart(); }
    if (mv) { SV.addToWishlist(mv.dataset.moveWish); SV.removeFromCart(mv.dataset.moveWish); SV.toast('Moved to wishlist', 'info'); SV.renderCart(); }
  });
  document.getElementById('cartItems').addEventListener('change', (e) => {
    const inp = e.target.closest('[data-qty]');
    if (inp) { SV.updateCart(inp.dataset.qty, inp.value); SV.renderCart(); }
  });

  document.getElementById('clearCart').onclick = () => { SV.clearCart(); SV.renderCart(); SV.toast('Cart cleared', 'warning'); };
  document.getElementById('couponForm').onsubmit = (e) => {
    e.preventDefault();
    const code = e.target.code.value.trim().toUpperCase();
    const coupons = { SAVE10: .10, SHOPVERSE20: .20, WELCOME5: .05 };
    if (coupons[code]) { SV.store.set('coupon', { code, rate: coupons[code] }); SV.toast(`Coupon <strong>${code}</strong> applied`); }
    else { SV.store.remove('coupon'); SV.toast('Invalid coupon code', 'danger'); }
    SV.renderCart();
  };
};

SV.renderCart = function () {
  const cart = SV.getCart();
  const box = document.getElementById('cartItems');
  const empty = document.getElementById('cartEmpty');
  const panel = document.getElementById('cartPanel');

  if (!cart.length) {
    empty.classList.remove('d-none');
    panel.classList.add('d-none');
    SV.updateBadges();
    return;
  }
  empty.classList.add('d-none');
  panel.classList.remove('d-none');

  box.innerHTML = `
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead><tr><th>Product</th><th>Price</th><th>Quantity</th><th>Subtotal</th><th></th></tr></thead>
      <tbody>
        ${cart.map((l) => `
        <tr>
          <td style="min-width:260px">
            <div class="d-flex gap-3 align-items-center">
              <img src="${l.image}" width="72" height="72" class="rounded-3" style="object-fit:cover" alt="${l.name}">
              <div>
                <a href="product-details.php?id=${l.id}" class="fw-semibold text-dark d-block">${l.name}</a>
                <small class="text-muted-2">${l.category}</small>
              </div>
            </div>
          </td>
          <td class="fw-semibold">${SV.money(l.price)}</td>
          <td>
            <div class="qty-box">
              <button type="button" data-dec="${l.id}">−</button>
              <input type="text" value="${l.qty}" data-qty="${l.id}" inputmode="numeric">
              <button type="button" data-inc="${l.id}">+</button>
            </div>
          </td>
          <td class="fw-bold">${SV.money(l.price * l.qty)}</td>
          <td class="text-end text-nowrap">
            <button class="btn btn-sm btn-light border" data-move-wish="${l.id}" title="Move to wishlist"><i class="bi bi-heart"></i></button>
            <button class="btn btn-sm btn-light border text-danger" data-remove="${l.id}" title="Remove"><i class="bi bi-trash"></i></button>
          </td>
        </tr>`).join('')}
      </tbody>
    </table>
  </div>`;

  const t = SV.cartTotals();
  document.getElementById('cartSummary').innerHTML = `
    <h5 class="mb-3">Order Summary</h5>
    <div class="summary-row"><span>Subtotal (${t.count} items)</span><strong>${SV.money(t.subtotal)}</strong></div>
    <div class="summary-row"><span>Discount ${t.coupon ? `<span class="badge text-bg-success ms-1">${t.coupon.code}</span>` : ''}</span><strong class="text-success">-${SV.money(t.discount)}</strong></div>
    <div class="summary-row"><span>Shipping</span><strong>${t.shipping ? SV.money(t.shipping) : 'Free'}</strong></div>
    <div class="summary-row"><span>Tax (8%)</span><strong>${SV.money(t.tax)}</strong></div>
    <div class="summary-row summary-total"><span>Grand Total</span><span>${SV.money(t.total)}</span></div>
    <a href="checkout.php" class="btn btn-primary w-100 mt-3"><i class="bi bi-credit-card me-1"></i>Proceed to Checkout</a>
    <a href="shop.php" class="btn btn-outline-primary w-100 mt-2">Continue Shopping</a>
    <p class="text-muted-2 small mt-3 mb-0"><i class="bi bi-shield-lock me-1"></i>Secure checkout · SSL encrypted</p>`;

  SV.updateBadges();
};
