<?php
require_once __DIR__ . '/uygulama/baslat.php';

if (Auth::isLoggedIn()) {
    header('Location: ' . BASE_PATH . '/kontrol-merkezi');
    exit;
}

$error = '';

if (isset($_GET['timeout'])) {
    $error = 'Oturumunuz süresi dolduğu için kapatıldı. Lütfen tekrar giriş yapın.';
} elseif (isset($_GET['expired'])) {
    $error = 'Güvenlik politikası gereği oturum süresi aşıldı. Lütfen tekrar giriş yapın.';
} elseif (isset($_GET['refresh'])) {
    $error = 'Hesap bilgileriniz değiştiği için yeniden giriş yapmanız gerekiyor.';
} elseif (isset($_GET['session_replaced'])) {
    $error = 'Hesabınızla başka bir oturum açıldığı için bu oturum kapatıldı.';
}

$loggedOut = isset($_GET['logged_out']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();

    $username = Validator::text($_POST['username'] ?? '', 80);
    $password = $_POST['password'] ?? '';
    $ip = ClientIp::get();

    if ($username === '' || $password === '') {
        $error = 'Kullanıcı adı ve şifre boş olamaz.';
    } else {
        $pdo = Database::getInstance()->getConnection();

        if (!Auth::isLoginAllowed($pdo, $ip, $username)) {
            $remaining = Auth::getLockoutRemainingMinutes($pdo, $ip, $username);
            $error = "Çok fazla başarısız deneme. Lütfen {$remaining} dakika sonra tekrar deneyin.";
        } elseif (Auth::login($pdo, $username, $password)) {
            Auth::logLoginAttempt($pdo, $ip, $username, true);
            header('Location: ' . BASE_PATH . '/kontrol-merkezi');
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
    <link rel="stylesheet" href="<?= BASE_PATH ?>/varliklar/fontlar/fonts.css">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/varliklar/kutuphaneler/bootstrap-icons/bootstrap-icons.min.css">
    <style>
        :root {

            --renk-vurgu: #00D084;
            --renk-vurgu-gecis: #00B371;
            --renk-vurgu-soluk: rgba(0, 208, 132, 0.1);

            --arkaplan-govde: #F3F6F5;
            --arkaplan-yuzey: #FFFFFF;

            --yazi-baslik: #090A0C;
            --yazi-birincil: #1F2329;
            --yazi-ikincil: #5C6573;

            --kenar-acik: #E6E8EB;

            --golge-mikro: 0 4px 12px rgba(0, 0, 0, 0.03);
            --golge-gecis: 0 12px 32px rgba(0, 0, 0, 0.06);

            --yuvarlik-orta: 12px;
            --yuvarlik-buyuk: 24px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Manrope', system-ui, sans-serif;
            min-height: 100vh;
            display: flex;
            background: var(--arkaplan-yuzey);
            color: var(--yazi-birincil);
            overflow: hidden;
            -webkit-font-smoothing: antialiased;
        }

        .giris-gorsel {
            flex: 0 0 55%;
            position: relative;
            background-color: #042A1D;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 4rem 5rem;
            color: #FFFFFF;
            overflow: hidden;
        }

        .giris-gorsel-arka {
            position: absolute;
            inset: -5%;
            background-image: url('<?= BASE_PATH ?>/varliklar/gorseller/giris-arkaplan.jpg');
            background-size: cover;
            background-position: center;
            animation: yavasKaydirma 30s linear infinite alternate;
            z-index: 1;
        }

        .giris-gorsel-katman {
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(0, 208, 132, 0.85) 0%, rgba(4, 42, 29, 0.95) 100%);
            z-index: 2;
        }

        .giris-gorsel::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)'/%3E%3C/svg%3E");
            opacity: 0.15;
            mix-blend-mode: overlay;
            pointer-events: none;
            z-index: 3;
        }

        .giris-marka,
        .giris-kahraman {
            position: relative;
            z-index: 10;
        }

        .giris-marka {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            animation: gorunmeAcilma 1s ease forwards;
        }

        .giris-marka img {
            height: 52px;
            filter: brightness(0) invert(1);
        }

        .giris-marka-yazi {
            border-left: 2px solid rgba(255, 255, 255, 0.25);
            padding-left: 1.25rem;
            display: flex;
            flex-direction: column;
            gap: 0.1rem;
        }

        .fs-marka-baslik {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: 1.25rem;
            letter-spacing: -0.02em;
            line-height: 1.2;
        }

        .fs-marka-alt {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            opacity: 0.85;
        }

        .giris-kahraman {
            padding-bottom: 2rem;
            animation: yukariAcilma 1s ease forwards;
            animation-delay: 0.15s;
        }

        .giris-kahraman-rozet {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            padding: 0.6rem 1.25rem;
            border-radius: 99px;
            font-size: 0.85rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 1.75rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
        }

        .giris-kahraman-baslik {
            font-family: 'Outfit', sans-serif;
            font-size: clamp(3rem, 4.5vw, 4.5rem);
            font-weight: 800;
            line-height: 1.05;
            letter-spacing: -0.04em;
            margin-bottom: 1.5rem;
        }

        .giris-kahraman-aciklama {
            font-size: 1.15rem;
            font-weight: 500;
            line-height: 1.6;
            opacity: 0.9;
            max-width: 520px;
        }

        .giris-form {
            flex: 0 0 45%;
            background: var(--arkaplan-yuzey);
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 4rem 6rem;
            position: relative;
            z-index: 10;
            box-shadow: -24px 0 48px rgba(0, 0, 0, 0.04);
        }

        .form-ic {
            max-width: 420px;
            width: 100%;
            margin: 0 auto;
            animation: gorunmeAcilma 0.8s ease forwards;
        }

        .form-ust {
            margin-bottom: 2.5rem;
        }

        .form-baslik {
            font-family: 'Outfit', sans-serif;
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--yazi-baslik);
            letter-spacing: -0.03em;
            margin-bottom: 0.5rem;
        }

        .form-alt-baslik {
            font-size: 1rem;
            color: var(--yazi-ikincil);
            font-weight: 500;
        }

        .uyari-hata {
            background: #FEF2F2;
            color: #DC2626;
            padding: 1.25rem;
            border-radius: var(--yuvarlik-orta);
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border: 1px solid rgba(220, 38, 38, 0.2);
        }

        .girdi-grubu {
            margin-bottom: 1.5rem;
        }

        .girdi-etiket {
            display: block;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--yazi-birincil);
            margin-bottom: 0.5rem;
        }

        .girdi-alan {
            width: 100%;
            background: var(--arkaplan-govde);
            border: 1px solid transparent;
            padding: 1.1rem 1.25rem;
            border-radius: var(--yuvarlik-orta);
            font-family: 'Manrope', sans-serif;
            font-size: 1rem;
            font-weight: 600;
            color: var(--yazi-baslik);
            transition: all 0.3s ease;
        }

        .girdi-alan:focus {
            outline: none;
            background: var(--arkaplan-yuzey);
            border-color: var(--renk-vurgu);
            box-shadow: 0 0 0 4px var(--renk-vurgu-soluk);
        }

        .girdi-alan::placeholder {
            color: #A0AAB5;
            font-weight: 500;
        }

        .dugme-gonder {
            width: 100%;
            background: var(--yazi-baslik);
            color: #FFFFFF;
            border: none;
            padding: 1.25rem;
            border-radius: var(--yuvarlik-orta);
            font-family: 'Outfit', sans-serif;
            font-size: 1.05rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            margin-top: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .dugme-gonder:hover {
            transform: translateY(-2px);
            background: var(--renk-vurgu);
            color: var(--yazi-baslik);
            box-shadow: 0 8px 16px var(--renk-vurgu-soluk);
        }

        .dugme-gonder i {
            transition: transform 0.3s ease;
        }

        .dugme-gonder:hover i {
            transform: translateX(4px);
        }

        .dugme-gonder:active {
            transform: translateY(0);
        }

        .giris-altbilgi {
            margin-top: 3rem;
            color: var(--yazi-ikincil);
            font-size: 0.85rem;
            font-weight: 600;
            text-align: center;
        }

        .mobil-marka {
            display: none;
            margin-bottom: 2.5rem;
        }

        @keyframes yukariAcilma {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes gorunmeAcilma {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes yavasKaydirma {
            0% {
                transform: scale(1) translate(0, 0);
            }

            100% {
                transform: scale(1.05) translate(-1%, -1%);
            }
        }

        @media (max-width: 1024px) {
            body {
                flex-direction: column;
                overflow: auto;
            }

            .giris-gorsel {
                flex: none;
                padding: 4rem 2.5rem;
                min-height: 55vh;
            }

            .giris-form {
                flex: none;
                padding: 4rem 2.5rem;
                box-shadow: none;
            }

            .giris-kahraman-baslik {
                font-size: 3rem;
            }

            .mobil-marka {
                display: flex;
                align-items: center;
                gap: 1rem;
            }

            .mobil-marka img {
                height: 48px;
            }

            .mobil-marka span {
                font-family: 'Outfit', sans-serif;
                font-weight: 800;
                font-size: 1.35rem;
                letter-spacing: -0.02em;
                color: var(--yazi-baslik);
            }

            .giris-marka {
                display: none;
            }
        }
    </style>
</head>

<body>

    <div class="giris-gorsel">
        <div class="giris-gorsel-arka"></div>
        <div class="giris-gorsel-katman"></div>

        <div class="giris-marka">
            <img src="<?= BASE_PATH ?>/varliklar/belediye-logo.svg" alt="Silivri Belediyesi">
            <div class="giris-marka-yazi">
                <span class="fs-marka-baslik">T.C. Silivri Belediyesi</span>
                <span class="fs-marka-alt">Sürdürülebilir Enerji ve İklim Eylem Planı</span>
            </div>
        </div>

        <div class="giris-kahraman">
            <div class="giris-kahraman-rozet">
                <i class="bi bi-globe-americas"></i> Sürdürülebilir Bir Şehir İçin
            </div>
            <h1 class="giris-kahraman-baslik">SECAP Portalı</h1>
            <p class="giris-kahraman-aciklama">
                İklim değişikliğiyle mücadele, enerji verimliliği ve yeşil dönüşüm hedeflerimizi birlikte inşa ediyoruz.
                Tüm sürdürülebilirlik verileri tek merkezde.
            </p>
        </div>
    </div>

    <div class="giris-form">
        <div class="form-ic">

            <div class="mobil-marka">
                <img src="<?= BASE_PATH ?>/varliklar/belediye-logo.svg" alt="Logo">
                <span>SECAP Portalı</span>
            </div>

            <div class="form-ust">
                <h2 class="form-baslik">Giriş Yap</h2>
                <div class="form-alt-baslik">Sisteme erişmek için bilgilerinizi giriniz.</div>
            </div>

            <?php if ($loggedOut): ?>
                <div class="uyari-hata"
                    style="background:rgba(0,208,132,.08); border-color:rgba(0,208,132,.25); color:#00A669;">
                    <i class="bi bi-check-circle-fill"></i>
                    <span>Oturumunuz güvenli şekilde kapatıldı.</span>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="uyari-hata">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <?= Csrf::field() ?>

                <div class="girdi-grubu">
                    <label class="girdi-etiket" for="username">Kullanıcı Adı</label>
                    <input type="text" name="username" id="username" class="girdi-alan"
                        placeholder="Kullanıcı adınızı giriniz."
                        value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        autocomplete="username" autofocus required>
                </div>

                <div class="girdi-grubu">
                    <label class="girdi-etiket" for="password">Şifre</label>
                    <input type="password" name="password" id="password" class="girdi-alan" placeholder="••••••••"
                        autocomplete="current-password" required>
                </div>

                <button type="submit" class="dugme-gonder">
                    SİSTEME BAĞLAN <i class="bi bi-arrow-right-short" style="font-size: 1.35rem; line-height: 0;"></i>
                </button>
            </form>

            <div class="giris-altbilgi">
                &copy; <?= date('Y') ?> T.C. Silivri Belediyesi Bilgi İşlem Müdürlüğü
            </div>
        </div>
    </div>

</body>

</html>
