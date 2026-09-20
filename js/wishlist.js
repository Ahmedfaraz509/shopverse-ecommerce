/* ============================================================
   ShopVerse – wishlist.js
   ============================================================ */

SV.initWishlist = async function () {
  await SV.loadProducts();
  SV.renderWishlist();
  document.addEventListener('sv:wishlist-changed', SV.renderWishlist);

  document.getElementById('wishGrid').addEventListener('click', (e) => {
    const rm = e.target.closest('[data-wish-remove]');
    if (rm) { SV.removeFromWishlist(rm.dataset.wishRemove); SV.toast('Removed from wishlist', 'warning'); SV.renderWishlist(); }
  });
  document.getElementById('wishAddAll').onclick = () => {
    const list = SV.getWishlist();
    if (!list.length) return SV.toast('Your wishlist is empty', 'warning');
    list.forEach((id) => SV.addToCart(id, 1, true));
    SV.toast(`${list.length} item(s) added to cart`);
  };
  document.getElementById('wishClear').onclick = () => { SV.saveWishlist([]); SV.renderWishlist(); SV.toast('Wishlist cleared', 'warning'); };
};

SV.renderWishlist = function () {
  const ids = SV.getWishlist();
  const items = SV.PRODUCTS.filter((p) => ids.includes(p.id));
  const grid = document.getElementById('wishGrid');
  document.getElementById('wishCountLabel').textContent = `${items.length} item(s) saved`;

  grid.innerHTML = items.length ? items.map((p) => `
    <div class="col-6 col-md-4 col-lg-3">
      <article class="product-card">
        <div class="product-thumb">
          <a href="product-details.php?id=${p.id}"><img src="${p.images[0]}" alt="${p.name}" loading="lazy"></a>
          <div class="product-badges">${p.discount ? `<span class="badge text-bg-danger">-${p.discount}%</span>` : ''}</div>
          <div class="product-actions" style="opacity:1;transform:none">
            <button class="btn text-danger" data-wish-remove="${p.id}" title="Remove"><i class="bi bi-x-lg"></i></button>
          </div>
        </div>
        <div class="product-body">
          <span class="product-cat">${p.category}</span>
          <a class="product-name" href="product-details.php?id=${p.id}">${p.name}</a>
          <div class="mb-2">${SV.stars(p.rating)}</div>
          <div class="mb-2"><span class="price">${SV.money(p.price)}</span><span class="price-old">${SV.money(p.oldPrice)}</span></div>
          <p class="mb-2 small">${p.stock > 0
            ? `<span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>In stock (${p.stock})</span>`
            : '<span class="text-danger"><i class="bi bi-x-circle-fill me-1"></i>Out of stock</span>'}</p>
          <div class="d-grid gap-2">
            <button class="btn btn-sm btn-primary" data-add-cart="${p.id}" ${p.stock ? '' : 'disabled'}><i class="bi bi-cart-plus me-1"></i>Add to Cart</button>
            <button class="btn btn-sm btn-outline-danger" data-wish-remove="${p.id}">Remove</button>
          </div>
        </div>
      </article>
    </div>`).join('') : `
    <div class="col-12 text-center py-5">
      <i class="bi bi-heart fs-1 text-muted-2"></i>
      <h5 class="mt-3">Your wishlist is empty</h5>
      <p class="text-muted-2">Save the products you love and find them here later.</p>
      <a href="shop.php" class="btn btn-primary">Start Shopping</a>
    </div>`;
  SV.updateBadges();
};
