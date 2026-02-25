<!-- Footer -->
<footer class="bg-dark text-light mt-5 pt-5 pb-3">
    <div class="container">
        <div class="row g-4">
            <!-- Brand -->
            <div class="col-lg-4 col-md-6">
                <h5 class="fw-bold mb-3">
                    <i class="bi bi-bag-heart-fill text-primary me-2"></i><?= APP_NAME ?>
                </h5>
                <p class="text-muted small">
                    Your one-stop destination for premium products at unbeatable prices.
                    Shop with confidence — quality guaranteed.
                </p>
                <div class="d-flex gap-3 mt-3">
                    <a href="#" class="text-muted fs-5 social-link"><i class="bi bi-facebook"></i></a>
                    <a href="#" class="text-muted fs-5 social-link"><i class="bi bi-twitter-x"></i></a>
                    <a href="#" class="text-muted fs-5 social-link"><i class="bi bi-instagram"></i></a>
                    <a href="#" class="text-muted fs-5 social-link"><i class="bi bi-youtube"></i></a>
                </div>
            </div>

            <!-- Quick links -->
            <div class="col-lg-2 col-md-6">
                <h6 class="fw-bold mb-3 text-uppercase text-muted" style="letter-spacing:.05em;font-size:.8rem">Quick Links</h6>
                <ul class="list-unstyled small">
                    <li class="mb-1"><a href="/index.php" class="text-muted text-decoration-none footer-link">Home</a></li>
                    <li class="mb-1"><a href="/index.php" class="text-muted text-decoration-none footer-link">Products</a></li>
                    <li class="mb-1"><a href="/pages/cart.php" class="text-muted text-decoration-none footer-link">Cart</a></li>
                    <li class="mb-1"><a href="/pages/wishlist.php" class="text-muted text-decoration-none footer-link">Wishlist</a></li>
                    <li class="mb-1"><a href="/pages/profile.php" class="text-muted text-decoration-none footer-link">My Account</a></li>
                </ul>
            </div>

            <!-- Categories -->
            <div class="col-lg-2 col-md-6">
                <h6 class="fw-bold mb-3 text-uppercase text-muted" style="letter-spacing:.05em;font-size:.8rem">Categories</h6>
                <ul class="list-unstyled small">
                    <?php foreach (array_slice(get_categories(), 0, 5) as $cat): ?>
                        <li class="mb-1">
                            <a href="/index.php?category=<?= h($cat['slug']) ?>"
                               class="text-muted text-decoration-none footer-link">
                                <?= h($cat['name']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Contact -->
            <div class="col-lg-4 col-md-6">
                <h6 class="fw-bold mb-3 text-uppercase text-muted" style="letter-spacing:.05em;font-size:.8rem">Contact Us</h6>
                <ul class="list-unstyled small text-muted">
                    <li class="mb-2"><i class="bi bi-geo-alt-fill me-2 text-primary"></i>123 Commerce Street, NY 10001</li>
                    <li class="mb-2"><i class="bi bi-telephone-fill me-2 text-primary"></i>1-800-SHOP-NOW</li>
                    <li class="mb-2"><i class="bi bi-envelope-fill me-2 text-primary"></i>support@nextgenshop.com</li>
                    <li class="mb-2"><i class="bi bi-clock-fill me-2 text-primary"></i>Mon–Fri, 9 AM – 6 PM EST</li>
                </ul>
            </div>
        </div>

        <hr class="my-4 border-secondary">

        <!-- Trust badges -->
        <div class="row align-items-center mb-3">
            <div class="col-md-6">
                <div class="d-flex flex-wrap gap-3">
                    <span class="badge bg-secondary py-2 px-3"><i class="bi bi-shield-lock-fill me-1"></i>Secure Checkout</span>
                    <span class="badge bg-secondary py-2 px-3"><i class="bi bi-truck me-1"></i>Free Shipping $50+</span>
                    <span class="badge bg-secondary py-2 px-3"><i class="bi bi-arrow-counterclockwise me-1"></i>30-Day Returns</span>
                </div>
            </div>
            <div class="col-md-6 text-md-end mt-3 mt-md-0">
                <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/2/2a/Mastercard-logo.svg/120px-Mastercard-logo.svg.png"
                     alt="Mastercard" height="28" class="me-2 opacity-75">
                <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/b/b7/MasterCard_Logo.svg/1200px-MasterCard_Logo.svg.png"
                     alt="Visa" height="28" class="me-2 opacity-75" style="display:none">
                <i class="bi bi-paypal fs-3 me-2 opacity-75 text-light"></i>
                <i class="bi bi-credit-card fs-3 opacity-75 text-light"></i>
            </div>
        </div>

        <div class="text-center text-muted small">
            &copy; <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved. &nbsp;|&nbsp;
            <a href="#" class="text-muted text-decoration-none footer-link">Privacy Policy</a> &nbsp;|&nbsp;
            <a href="#" class="text-muted text-decoration-none footer-link">Terms of Service</a>
        </div>
    </div>
</footer>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom JS -->
<script src="/assets/js/main.js"></script>
</body>
</html>
