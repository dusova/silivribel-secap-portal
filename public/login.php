<?php
require_once __DIR__ . '/../bootstrap.php';

if (Auth::isLoggedIn()) {
    header('Location: ' . BASE_PATH . '/public/dashboard.php');
    exit;
}

$error = '';

if (isset($_GET['timeout'])) {
    $error = 'Oturumunuz süresi dolduğu için kapatıldı. Lütfen tekrar giriş yapın.';
} elseif (isset($_GET['expired'])) {
    $error = 'Güvenlik politikası gereği oturum süresi aşıldı. Lütfen tekrar giriş yapın.';
} elseif (isset($_GET['refresh'])) {
    $error = 'Hesap bilgileriniz değiştiği için yeniden giriş yapmanız gerekiyor.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();

    $username = Validator::text($_POST['username'] ?? '', 80);
    $password = $_POST['password'] ?? '';
    $ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    if ($username === '' || $password === '') {
        $error = 'Kullanıcı adı ve şifre boş olamaz.';
    } else {
        $pdo = Database::getInstance()->getConnection();

        if (!Auth::isLoginAllowed($pdo, $ip, $username)) {
            $remaining = Auth::getLockoutRemainingMinutes($pdo, $ip, $username);
            $error = "Çok fazla başarısız deneme. Lütfen {$remaining} dakika sonra tekrar deneyin.";
        } elseif (Auth::login($pdo, $username, $password)) {
            Auth::logLoginAttempt($pdo, $ip, $username, true);
            header('Location: ' . BASE_PATH . '/public/dashboard.php');
            exit;
        } else {
            Auth::logLoginAttempt($pdo, $ip, $username, false);
            AuditLog::log($pdo, 'login_fail', 'users', 0, null, ['username' => $username, 'ip' => $ip]);
            $error = 'Geçersiz kullanıcı adı veya şifre.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş — SECAP Portalı</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet" />
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/style.css">
</head>

<body class="login-page">
    <div class="login-wrapper">
        <div class="login-card">
            <div class="text-center">
                <img src="<?= BASE_PATH ?>/assets/new-logo.svg" alt="Silivri Belediyesi"
                    class="login-logo">
                <div class="login-municipality">T.C. SİLİVRİ BELEDİYESİ</div>
                <h1 class="login-title">SECAP Portalı</h1>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger py-2 text-md" style="border-radius:10px;">
                    <i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <?= Csrf::field() ?>

                <div class="mb-3">
                    <label class="form-label" for="username">Kullanıcı Adı</label>
                    <div class="input-group">
                        <span class="input-group-text input-icon-left">
                            <i class="bi bi-person text-muted"></i>
                        </span>
                        <input type="text" name="username" id="username" class="form-control input-icon-right"
                            value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            autocomplete="username" autofocus required>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label" for="password">Şifre</label>
                    <div class="input-group">
                        <span class="input-group-text input-icon-left">
                            <i class="bi bi-lock text-muted"></i>
                        </span>
                        <input type="password" name="password" id="password" class="form-control input-icon-right" autocomplete="current-password" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-login w-100">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Giriş Yap
                </button>
            </form>
        </div>

        <div class="login-footer">
            © <?= date('Y') ?> T.C. Silivri Belediyesi Bilgi İşlem Müdürlüğü
        </div>
    </div>
</body>

</html>