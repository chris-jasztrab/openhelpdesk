<?php
$layout    = 'auth';
$pageTitle = 'Setup — Create Admin Account';
?>
<div style="max-width:480px;width:100%;">

    <!-- Brand -->
    <div class="text-center mb-4">
        <div class="d-inline-flex align-items-center justify-content-center rounded-3 mb-3"
             style="width:56px;height:56px;background:#4f46e5;">
            <i class="bi bi-stars text-white" style="font-size:1.6rem;"></i>
        </div>
        <h1 class="text-white fw-bold fs-4 mb-1">Welcome to OpenHelpDesk</h1>
        <p class="text-white-50 mb-0">Let's get your helpdesk set up. Create your admin account to get started.</p>
    </div>

    <div class="card shadow-lg border-0" style="border-radius:1rem;">
        <div class="card-body p-4">

            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <form method="POST" action="/setup">
                <?= csrfField() ?>

                <!-- App Name -->
                <div class="mb-3">
                    <label for="app_name" class="form-label fw-semibold">Helpdesk Name</label>
                    <input type="text" class="form-control" id="app_name" name="app_name"
                           value="<?= e($formData['appName'] ?? 'OpenHelpDesk') ?>"
                           placeholder="e.g. My IT Helpdesk" required>
                    <div class="form-text">This is shown in emails and the browser title.</div>
                </div>

                <hr class="my-3">
                <p class="fw-semibold mb-3 text-muted small text-uppercase letter-spacing-1">Admin Account</p>

                <!-- Name -->
                <div class="row g-2 mb-3">
                    <div class="col">
                        <label for="first_name" class="form-label fw-semibold">First Name</label>
                        <input type="text" class="form-control" id="first_name" name="first_name"
                               value="<?= e($formData['firstName'] ?? '') ?>"
                               placeholder="First name" required autofocus>
                    </div>
                    <div class="col">
                        <label for="last_name" class="form-label fw-semibold">Last Name</label>
                        <input type="text" class="form-control" id="last_name" name="last_name"
                               value="<?= e($formData['lastName'] ?? '') ?>"
                               placeholder="Last name" required>
                    </div>
                </div>

                <!-- Email -->
                <div class="mb-3">
                    <label for="email" class="form-label fw-semibold">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control" id="email" name="email"
                               value="<?= e($formData['email'] ?? '') ?>"
                               placeholder="admin@example.com" required>
                    </div>
                </div>

                <!-- Password -->
                <div class="mb-3">
                    <label for="password" class="form-label fw-semibold">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password"
                               placeholder="At least 8 characters" required minlength="8">
                    </div>
                </div>

                <!-- Confirm Password -->
                <div class="mb-4">
                    <label for="password_confirm" class="form-label fw-semibold">Confirm Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                        <input type="password" class="form-control" id="password_confirm" name="password_confirm"
                               placeholder="Repeat password" required minlength="8">
                    </div>
                </div>

                <button type="submit" class="btn w-100 py-2 fw-semibold text-white"
                        style="background-color:#4f46e5;border-color:#4f46e5;">
                    <i class="bi bi-check-circle me-2"></i>Create Account &amp; Continue
                </button>
            </form>
        </div>
    </div>

    <p class="text-center text-white-50 mt-3 small">
        After setup, head to <strong>Admin → Settings</strong> to configure email delivery.
    </p>
</div>
