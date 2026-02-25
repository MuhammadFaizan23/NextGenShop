<?php
/**
 * NextGenShop – User Registration
 */
define('NEXTGENSHOP', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Already logged in
if (is_logged_in()) {
    redirect('/index.php');
}

$errors = [];
$name = $email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please try again.';
    } else {
        $name     = trim($_POST['name'] ?? '');
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['password_confirm'] ?? '';

        // Validation
        if (mb_strlen($name) < 2) {
            $errors[] = 'Name must be at least 2 characters.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if (mb_strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($password !== $confirm) {
            $errors[] = 'Passwords do not match.';
        }

        if (empty($errors)) {
            // Check duplicate email
            $stmt = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            if ($stmt->fetchColumn()) {
                $errors[] = 'An account with this email already exists.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $stmt = db()->prepare(
                    'INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, "customer")'
                );
                $stmt->execute([$name, $email, $hash]);
                $user_id = (int)db()->lastInsertId();

                login_user(['id' => $user_id, 'name' => $name, 'email' => $email, 'role' => 'customer']);
                flash('success', "Welcome to " . APP_NAME . ", {$name}! Your account has been created.");

                $redirect = $_SESSION['redirect_after_login'] ?? '/index.php';
                unset($_SESSION['redirect_after_login']);
                redirect($redirect);
            }
        }
    }
}

$page_title = 'Create Account';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5" style="max-width:480px">
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <i class="bi bi-person-plus-fill display-4 text-primary"></i>
                <h1 class="h4 fw-bold mt-2">Create Your Account</h1>
                <p class="text-muted small">Join thousands of happy shoppers</p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $err): ?>
                            <li><?= h($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" novalidate>
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label for="name" class="form-label fw-semibold">Full Name</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" id="name" name="name" class="form-control"
                               value="<?= h($name) ?>" placeholder="John Doe"
                               required autocomplete="name" autofocus>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label fw-semibold">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" id="email" name="email" class="form-control"
                               value="<?= h($email) ?>" placeholder="you@example.com"
                               required autocomplete="email">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label fw-semibold">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" id="password" name="password" class="form-control"
                               placeholder="Min. 8 characters" required autocomplete="new-password">
                        <button type="button" class="btn btn-outline-secondary toggle-password" data-target="password">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="password_confirm" class="form-label fw-semibold">Confirm Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                        <input type="password" id="password_confirm" name="password_confirm" class="form-control"
                               placeholder="Repeat your password" required autocomplete="new-password">
                    </div>
                </div>

                <div class="mb-4 form-check">
                    <input type="checkbox" class="form-check-input" id="terms" required>
                    <label class="form-check-label small" for="terms">
                        I agree to the <a href="#" class="text-primary">Terms of Service</a> and
                        <a href="#" class="text-primary">Privacy Policy</a>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary w-100 btn-lg">
                    <i class="bi bi-person-plus me-2"></i>Create Account
                </button>
            </form>

            <hr class="my-4">
            <p class="text-center text-muted small mb-0">
                Already have an account?
                <a href="/pages/login.php" class="text-primary fw-semibold text-decoration-none">Sign in</a>
            </p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
