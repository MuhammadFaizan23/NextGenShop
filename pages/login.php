<?php
/**
 * NextGenShop – User Login
 */
define('NEXTGENSHOP', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in()) {
    redirect('/index.php');
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            $error = 'Please enter your email and password.';
        } else {
            // Fetch user by email (uses index on email column)
            $stmt = db()->prepare(
                'SELECT id, name, email, password, role FROM users WHERE email = ? LIMIT 1'
            );
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                login_user($user);
                flash('success', 'Welcome back, ' . $user['name'] . '!');
                $redirect = $_SESSION['redirect_after_login'] ?? '/index.php';
                unset($_SESSION['redirect_after_login']);
                redirect($redirect);
            } else {
                // Consistent timing to prevent user enumeration
                usleep(100000);
                $error = 'Invalid email or password. Please try again.';
            }
        }
    }
}

$page_title = 'Sign In';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5" style="max-width:440px">
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <i class="bi bi-box-arrow-in-right display-4 text-primary"></i>
                <h1 class="h4 fw-bold mt-2">Sign In</h1>
                <p class="text-muted small">Welcome back! Please sign in to continue.</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-circle-fill"></i><?= h($error) ?>
                </div>
            <?php endif; ?>

            <form method="post" novalidate>
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label for="email" class="form-label fw-semibold">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" id="email" name="email" class="form-control"
                               value="<?= h($email) ?>" placeholder="you@example.com"
                               required autocomplete="email" autofocus>
                    </div>
                </div>

                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label for="password" class="form-label fw-semibold mb-0">Password</label>
                        <a href="#" class="text-primary small text-decoration-none">Forgot password?</a>
                    </div>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" id="password" name="password" class="form-control"
                               placeholder="Your password" required autocomplete="current-password">
                        <button type="button" class="btn btn-outline-secondary toggle-password" data-target="password">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="mb-4 form-check">
                    <input type="checkbox" class="form-check-input" id="remember" name="remember">
                    <label class="form-check-label small" for="remember">Remember me</label>
                </div>

                <button type="submit" class="btn btn-primary w-100 btn-lg">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                </button>
            </form>

            <!-- Demo credentials hint -->
            <div class="alert alert-info mt-3 small py-2">
                <strong>Demo admin:</strong> admin@nextgenshop.com / admin123
            </div>

            <hr class="my-4">
            <p class="text-center text-muted small mb-0">
                Don't have an account?
                <a href="/pages/register.php" class="text-primary fw-semibold text-decoration-none">Create one free</a>
            </p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
