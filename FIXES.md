# Fixes in this version
- product-details.php: product page was blank (#productDetail never un-hidden); errors now logged to console.
- cart_api.php: add/set/remove/clear/coupon now return `data` (cart.php reads it) as well as `cart`.
- includes/auth.php: require_login_json(); used by wishlist_api, checkout_api, orders_api (guests get JSON 401, not an HTML redirect).
- Wishlist: hearts on shop + home cards, filled/empty state everywhere, `ids` action, `in_wishlist` flag; app.js now uses
  wishlist_api / cart_api instead of localStorage demo data.
- Cart_process: coupon minimum order honoured in the cart (matches checkout) + warning shown.
- Checkout_process: stock locked/re-checked inside the transaction.
- Orders_process / Admin_orders_process: cancelling gives the coupon use back.
- checkout.php / register.php: phone pattern regex made valid for modern Chrome.
- coupons_seed.sql: optional demo coupons.
Note: password-reset emails need real SMTP credentials in includes/mail_config.php (links are in logs/mail.log meanwhile).
