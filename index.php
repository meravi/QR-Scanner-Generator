<?php
session_start();

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");

require_once __DIR__ . '/meRaviQr/qrlib.php';
require_once __DIR__ . '/config.php';

function app_escape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function app_text_length($value)
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }

    return strlen($value);
}

function app_text_slice($value, $start, $length)
{
    if (function_exists('mb_substr')) {
        return mb_substr($value, $start, $length, 'UTF-8');
    }

    return substr($value, $start, $length);
}

function app_normalize_input($value)
{
    $value = str_replace(array("\r\n", "\r"), "\n", (string) $value);

    return trim($value);
}

function app_excerpt($value, $maxLength)
{
    $value = trim((string) preg_replace('/\s+/u', ' ', (string) $value));

    if (app_text_length($value) <= $maxLength) {
        return $value;
    }

    return rtrim(app_text_slice($value, 0, $maxLength - 3)) . '...';
}

function app_public_path($relativePath)
{
    return __DIR__ . '/' . ltrim($relativePath, '/');
}

function app_base_url()
{
    $scheme = 'http';

    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $forwarded = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_PROTO']);
        $scheme = strtolower(trim($forwarded[0])) === 'https' ? 'https' : 'http';
    } elseif (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        $scheme = 'https';
    }

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $host = preg_replace('/[^a-zA-Z0-9.\-:\[\]]/', '', $host);
    if ($host === null || $host === '') {
        $host = 'localhost';
    }

    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $basePath = str_replace('\\', '/', dirname($scriptName));
    if ($basePath === '/' || $basePath === '.') {
        $basePath = '';
    }

    return $scheme . '://' . $host . $basePath;
}

function app_url($relativePath)
{
    return rtrim(app_base_url(), '/') . '/' . ltrim($relativePath, '/');
}

function app_recent_local_files($directoryPath, $limit)
{
    $files = glob(rtrim($directoryPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.png');
    if (!is_array($files)) {
        return array();
    }

    usort(
        $files,
        function ($left, $right) {
            return filemtime($right) <=> filemtime($left);
        }
    );

    $items = array();
    foreach (array_slice($files, 0, max(1, $limit)) as $filePath) {
        $fileName = basename($filePath);
        $items[$fileName] = array(
            'qrImg' => $fileName,
            'qrUsername' => 'Local file',
            'qrContent' => 'Generated image available in the local userQr folder.',
            'qrlink' => app_url('userQr/' . $fileName),
            'timestamp' => (int) filemtime($filePath),
        );
    }

    return $items;
}

function app_recent_history($databaseRows, $directoryPath, $limit)
{
    $items = array();

    foreach ($databaseRows as $row) {
        $fileName = basename((string) ($row['qrImg'] ?? ''));
        if ($fileName === '') {
            continue;
        }

        $filePath = rtrim($directoryPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;
        $items[$fileName] = array(
            'qrImg' => $fileName,
            'qrUsername' => trim((string) ($row['qrUsername'] ?? '')),
            'qrContent' => trim((string) ($row['qrContent'] ?? '')),
            'qrlink' => app_url('userQr/' . $fileName),
            'timestamp' => is_file($filePath) ? (int) filemtime($filePath) : 0,
        );
    }

    foreach (app_recent_local_files($directoryPath, $limit * 2) as $fileName => $item) {
        if (!isset($items[$fileName])) {
            $items[$fileName] = $item;
        }
    }

    $items = array_values($items);
    usort(
        $items,
        function ($left, $right) {
            return ($right['timestamp'] ?? 0) <=> ($left['timestamp'] ?? 0);
        }
    );

    return array_slice($items, 0, max(1, $limit));
}

$qrDirectory = app_public_path('userQr');
$errors = array();
$notices = array();
$generatedQr = null;
$formValues = array(
    'qrUname' => '',
    'qrContent' => '',
);

if (empty($_SESSION['qr_csrf_token'])) {
    $_SESSION['qr_csrf_token'] = bin2hex(random_bytes(32));
}

if (!empty($_SESSION['qr_flash']) && is_array($_SESSION['qr_flash'])) {
    $generatedQr = $_SESSION['qr_flash'];
    unset($_SESSION['qr_flash']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formValues['qrUname'] = app_normalize_input($_POST['qrUname'] ?? '');
    $formValues['qrContent'] = app_normalize_input($_POST['qrContent'] ?? '');
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');

    if (!hash_equals($_SESSION['qr_csrf_token'], $submittedToken)) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    }

    if ($formValues['qrUname'] === '') {
        $errors[] = 'Enter your name before generating the QR code.';
    } elseif (app_text_length($formValues['qrUname']) > 80) {
        $errors[] = 'Keep the name under 80 characters.';
    }

    if ($formValues['qrContent'] === '') {
        $errors[] = 'Enter the URL or text that should be encoded.';
    } elseif (app_text_length($formValues['qrContent']) > 1500) {
        $errors[] = 'Keep the QR content under 1500 characters.';
    }

    if (!is_dir($qrDirectory) && !mkdir($qrDirectory, 0775, true) && !is_dir($qrDirectory)) {
        $errors[] = 'The userQr folder is not writable. Fix folder permissions and try again.';
    }

    if (empty($errors)) {
        $fileName = 'qr-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.png';
        $targetPath = $qrDirectory . DIRECTORY_SEPARATOR . $fileName;

        QRcode::png($formValues['qrContent'], $targetPath, 'H', 6, 2);

        if (!is_file($targetPath)) {
            $errors[] = 'QR image generation failed. Make sure the GD extension is enabled.';
        } else {
            $publicUrl = app_url('userQr/' . $fileName);
            $storageNotice = null;

            if (!$meravi->insertQr($formValues['qrUname'], $formValues['qrContent'], $fileName, $publicUrl)) {
                $storageNotice = $meravi->getLastError();
            }

            $_SESSION['qr_flash'] = array(
                'file' => $fileName,
                'url' => $publicUrl,
                'name' => $formValues['qrUname'],
                'content' => $formValues['qrContent'],
                'storageNotice' => $storageNotice,
            );

            header('Location: index.php');
            exit;
        }
    }
}

$recentDatabaseRows = $meravi->getRecentQrs(12);
$recentHistory = app_recent_history($recentDatabaseRows, $qrDirectory, 8);

if (!$meravi->isConnected()) {
    $notices[] = 'Database storage is currently unavailable. QR generation still works, and recent local files are shown below.';
} elseif (empty($recentDatabaseRows) && $meravi->getLastError()) {
    $notices[] = $meravi->getLastError();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>QR Scanner Generator</title>
    <meta
        name="description"
        content="Generate QR codes from URLs or plain text, download PNG files instantly, and keep a lightweight history in core PHP."
    >
    <link rel="stylesheet" href="assets/site.css">
</head>
<body>
<main class="page-shell">
    <section class="hero panel">
        <p class="eyebrow">Core PHP Refresh</p>
        <h1>QR Scanner Generator</h1>
        <p class="hero-copy">
            The generator has been rebuilt for a cleaner PHP 8 flow: safer inputs, local downloads,
            responsive layout, and a lightweight recent-history archive.
        </p>
        <div class="hero-points">
            <span>No login required</span>
            <span>PNG export</span>
            <span>Local file history</span>
            <span>Prepared statements</span>
        </div>
    </section>

    <?php if (!empty($errors)) : ?>
        <section class="message-stack">
            <?php foreach ($errors as $error) : ?>
                <div class="message message-error"><?php echo app_escape($error); ?></div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <?php if (!empty($notices)) : ?>
        <section class="message-stack">
            <?php foreach ($notices as $notice) : ?>
                <div class="message message-note"><?php echo app_escape($notice); ?></div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <?php if (!empty($generatedQr)) : ?>
        <section class="message-stack">
            <div class="message message-success">
                QR code generated for <?php echo app_escape($generatedQr['name']); ?>.
                <?php if (!empty($generatedQr['storageNotice'])) : ?>
                    Saved locally only: <?php echo app_escape($generatedQr['storageNotice']); ?>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="workspace">
        <section class="panel form-panel">
            <div class="section-heading">
                <p class="section-label">Generator</p>
                <h2>Create a fresh QR code</h2>
            </div>

            <form method="post" class="generator-form" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo app_escape($_SESSION['qr_csrf_token']); ?>">

                <label class="field">
                    <span>Your name</span>
                    <input
                        type="text"
                        name="qrUname"
                        maxlength="80"
                        value="<?php echo app_escape($formValues['qrUname']); ?>"
                        placeholder="Ravi Khadka"
                        autocomplete="name"
                    >
                </label>

                <label class="field">
                    <span>Website URL or text</span>
                    <textarea
                        name="qrContent"
                        rows="8"
                        maxlength="1500"
                        placeholder="https://example.com or any plain text you want to encode"
                    ><?php echo app_escape($formValues['qrContent']); ?></textarea>
                </label>

                <div class="form-meta">
                    <p>Only the text you enter is encoded. The old developer-signature suffix has been removed.</p>
                </div>

                <button class="button button-primary" type="submit">Generate QR Code</button>
            </form>
        </section>

        <section class="panel preview-panel">
            <div class="section-heading">
                <p class="section-label">Preview</p>
                <h2>Latest result</h2>
            </div>

            <?php if (!empty($generatedQr)) : ?>
                <div class="qr-preview">
                    <img
                        class="qr-image"
                        src="userQr/<?php echo app_escape($generatedQr['file']); ?>"
                        alt="Generated QR code"
                    >

                    <div class="preview-details">
                        <p class="preview-title"><?php echo app_escape($generatedQr['name']); ?></p>
                        <p class="preview-copy"><?php echo app_escape(app_excerpt($generatedQr['content'], 150)); ?></p>
                    </div>

                    <div class="link-row">
                        <input
                            id="generated-link"
                            type="text"
                            readonly
                            value="<?php echo app_escape($generatedQr['url']); ?>"
                        >
                        <button type="button" class="button button-secondary" data-copy-target="generated-link">Copy Link</button>
                    </div>

                    <div class="action-row">
                        <a class="button button-primary" href="download.php?download=<?php echo rawurlencode($generatedQr['file']); ?>">Download PNG</a>
                        <a class="button button-ghost" href="index.php">Create Another</a>
                    </div>
                </div>
            <?php else : ?>
                <div class="empty-state">
                    <p class="empty-kicker">Nothing generated yet</p>
                    <p>Use the form to create a QR code. The PNG preview and download link will appear here immediately.</p>
                </div>
            <?php endif; ?>
        </section>
    </section>

    <section class="panel history-panel">
        <div class="section-heading">
            <p class="section-label">Archive</p>
            <h2>Recent QR files</h2>
        </div>

        <?php if (!empty($recentHistory)) : ?>
            <div class="history-grid">
                <?php foreach ($recentHistory as $item) : ?>
                    <article class="history-card">
                        <img
                            class="history-image"
                            src="userQr/<?php echo app_escape($item['qrImg']); ?>"
                            alt="QR code file <?php echo app_escape($item['qrImg']); ?>"
                        >

                        <div class="history-content">
                            <p class="history-name">
                                <?php echo app_escape($item['qrUsername'] !== '' ? $item['qrUsername'] : 'QR file'); ?>
                            </p>
                            <p class="history-text"><?php echo app_escape(app_excerpt($item['qrContent'], 110)); ?></p>
                        </div>

                        <div class="history-actions">
                            <a class="button button-secondary" href="<?php echo app_escape($item['qrlink']); ?>" target="_blank" rel="noopener noreferrer">Open</a>
                            <a class="button button-ghost" href="download.php?download=<?php echo rawurlencode($item['qrImg']); ?>">Download</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <div class="empty-state empty-state-inline">
                <p>No stored QR files were found yet.</p>
            </div>
        <?php endif; ?>
    </section>
</main>

<script src="assets/app.js" defer></script>
</body>
</html>
