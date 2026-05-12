<?php header_remove('X-Powered-By'); ?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sayfa Bulunamadı — SECAP Portalı</title>
    <link href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/varliklar/fontlar/fonts.css" rel="stylesheet">
    <link href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/varliklar/kutuphaneler/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Manrope', system-ui, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: #F4F5F7;
            color: #1F2329;
            -webkit-font-smoothing: antialiased;
            overflow: hidden;
            position: relative;
        }

        body::before {
            content: '';
            position: absolute;
            top: -120px;
            right: -120px;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(0,208,132,.12) 0%, transparent 70%);
            pointer-events: none;
        }

        body::after {
            content: '';
            position: absolute;
            bottom: -80px;
            left: -80px;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(0,208,132,.08) 0%, transparent 70%);
            pointer-events: none;
        }

        .error-wrapper {
            position: relative;
            z-index: 1;
            text-align: center;
            padding: 2rem;
            max-width: 560px;
        }

        .marka {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .75rem;
            margin-bottom: 3rem;
            animation: fadeIn .8s ease forwards;
        }

        .marka img {
            height: 36px;
        }

        .brand-text {
            text-align: left;
            border-left: 2px solid #E6E8EB;
            padding-left: .75rem;
        }

        .marka-baslik {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: .85rem;
            color: #090A0C;
            letter-spacing: -0.02em;
        }

        .marka-alt {
            font-size: .6rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .12em;
            color: #8B94A3;
        }

        .error-visual {
            position: relative;
            margin-bottom: 2rem;
            animation: fadeUp .7s cubic-bezier(.2,.8,.2,1) forwards;
        }

        .error-code {
            font-family: 'Outfit', sans-serif;
            font-size: 10rem;
            font-weight: 900;
            letter-spacing: -0.08em;
            line-height: 1;
            color: #090A0C;
            position: relative;
            display: inline-block;
        }

        .error-code span {
            background: linear-gradient(135deg, #00D084 0%, #00A669 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .error-code::after {
            content: '';
            position: absolute;
            bottom: 8px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 5px;
            border-radius: 99px;
            background: linear-gradient(90deg, #00D084, #00A669);
        }

        .error-icon-float {
            position: absolute;
            top: 15px;
            right: -10px;
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: linear-gradient(135deg, #00D084, #00A669);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.25rem;
            box-shadow: 0 8px 24px rgba(0,208,132,.3);
            animation: float 3s ease-in-out infinite;
        }

        .error-content {
            animation: fadeUp .7s cubic-bezier(.2,.8,.2,1) .15s both;
        }

        .error-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1.6rem;
            font-weight: 800;
            color: #090A0C;
            margin-bottom: .6rem;
            letter-spacing: -0.03em;
        }

        .error-desc {
            color: #5C6573;
            font-size: .95rem;
            font-weight: 500;
            line-height: 1.7;
            margin-bottom: 2.5rem;
        }

        .error-actions {
            display: flex;
            gap: .75rem;
            justify-content: center;
            flex-wrap: wrap;
            animation: fadeUp .7s cubic-bezier(.2,.8,.2,1) .3s both;
        }

        .btn-err {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            padding: .9rem 2rem;
            border-radius: 12px;
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: .9rem;
            text-decoration: none;
            transition: all .25s cubic-bezier(.2,.8,.2,1);
            cursor: pointer;
        }

        .btn-err i { transition: transform .25s ease; }

        .btn-primary-err {
            background: #090A0C;
            color: #fff;
            border: 2px solid #090A0C;
        }
        .btn-primary-err:hover {
            background: #00D084;
            border-color: #00D084;
            color: #090A0C;
            transform: translateY(-3px);
            box-shadow: 0 12px 24px rgba(0,208,132,.25);
        }
        .btn-primary-err:hover i { transform: translateX(-3px); }

        .btn-ghost-err {
            background: #fff;
            color: #5C6573;
            border: 2px solid #E6E8EB;
        }
        .btn-ghost-err:hover {
            background: #090A0C;
            border-color: #090A0C;
            color: #fff;
            transform: translateY(-3px);
            box-shadow: 0 8px 16px rgba(9,10,12,.1);
        }

        .footer-text {
            position: fixed;
            bottom: 1.5rem;
            left: 50%;
            transform: translateX(-50%);
            font-size: .72rem;
            color: #8B94A3;
            font-weight: 600;
            z-index: 1;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(24px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-8px) rotate(5deg); }
        }
    </style>
</head>
<body>

<div class="error-wrapper">
    <div class="marka">
        <img src="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/varliklar/belediye-logo.svg" alt="Silivri Belediyesi">
        <div class="brand-text">
            <div class="marka-baslik">T.C. Silivri Belediyesi</div>
            <div class="marka-alt">SECAP Portalı</div>
        </div>
    </div>

    <div class="error-visual">
        <div class="error-code">4<span>0</span>4</div>
        <div class="error-icon-float">
            <i class="bi bi-compass"></i>
        </div>
    </div>

    <div class="error-content">
        <h1 class="error-title">Sayfa Bulunamadı</h1>
        <p class="error-desc">Aradığınız sayfa taşınmış, silinmiş veya hiç var olmamış olabilir.<br>Kontrol merkezine dönerek devam edebilirsiniz.</p>
    </div>

    <div class="error-actions">
        <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/kontrol-merkezi" class="btn-err btn-primary-err">
            <i class="bi bi-arrow-left"></i> Kontrol Merkezi
        </a>
        <a href="javascript:history.back()" class="btn-err btn-ghost-err">
            <i class="bi bi-arrow-counterclockwise"></i> Geri Dön
        </a>
    </div>
</div>

<div class="footer-text">&copy; <?= date('Y') ?> T.C. Silivri Belediyesi Bilgi İşlem Müdürlüğü</div>

</body>
</html>
