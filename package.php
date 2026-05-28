<?php
// package.php
// Local deployment packaging script for Laravel -> InfinityFree

echo "Laravel InfinityFree Packager\n";
echo "=============================\n\n";

$zipName = 'project.zip';
$unzipScriptName = 'unzip.php';

if (file_exists($zipName)) {
    echo "Removing existing $zipName...\n";
    unlink($zipName);
}

$zip = new ZipArchive();
if ($zip->open($zipName, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    die("Error: Cannot create $zipName\n");
}

$rootPath = realpath(__DIR__);

// Create files list using recursive directory iterator
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($rootPath, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

$excludedCount = 0;
$includedCount = 0;

foreach ($files as $name => $file) {
    // Skip directories (they will be added automatically by files)
    if ($file->isDir()) {
        continue;
    }

    $filePath = $file->getRealPath();
    // Get path relative to the root
    $relativePath = substr($filePath, strlen($rootPath) + 1);
    
    // Normalize path separators to forward slash
    $relativePath = str_replace('\\', '/', $relativePath);

    // Exclusion rules
    if (
        strpos($relativePath, '.git/') === 0 || 
        $relativePath === '.git' ||
        strpos($relativePath, 'node_modules/') === 0 ||
        $relativePath === 'node_modules' ||
        strpos($relativePath, '.docker/') === 0 ||
        $relativePath === '.docker' ||
        $relativePath === 'Dockerfile' ||
        $relativePath === 'fly.toml' ||
        $relativePath === 'package.php' ||
        $relativePath === 'unzip.php' ||
        $relativePath === 'project.zip' ||
        $relativePath === '.env' ||
        $relativePath === '.phpunit.result.cache' ||
        // Exclude storage contents but KEEP the directories by adding .gitignore
        (strpos($relativePath, 'storage/logs/') === 0 && $relativePath !== 'storage/logs/.gitignore') ||
        (strpos($relativePath, 'storage/framework/cache/') === 0 && $relativePath !== 'storage/framework/cache/.gitignore') ||
        (strpos($relativePath, 'storage/framework/sessions/') === 0 && $relativePath !== 'storage/framework/sessions/.gitignore') ||
        (strpos($relativePath, 'storage/framework/views/') === 0 && $relativePath !== 'storage/framework/views/.gitignore')
    ) {
        $excludedCount++;
        continue;
    }

    // Add file to zip
    $zip->addFile($filePath, $relativePath);
    $includedCount++;
}

$zip->close();

echo "Archive created successfully!\n";
echo "Total files included: $includedCount\n";
echo "Total files excluded: $excludedCount\n\n";

// Generate unzip.php
echo "Generating unzip.php helper...\n";
$unzipContent = <<<'PHP'
<?php
// unzip.php
// Helper script to extract project.zip on the server
set_time_limit(600);
ini_set('memory_limit', '512M');

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laravel Deployment Unzipper</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f3f4f6;
            color: #1f2937;
            padding: 40px 20px;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 80vh;
        }
        .card {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            padding: 32px;
            max-width: 600px;
            width: 100%;
        }
        h1 {
            color: #4f46e5;
            font-size: 24px;
            margin-top: 0;
            margin-bottom: 24px;
            border-bottom: 2px solid #f3f4f6;
            padding-bottom: 12px;
        }
        p {
            line-height: 1.6;
            font-size: 16px;
        }
        .btn {
            display: inline-block;
            background-color: #4f46e5;
            color: white;
            padding: 12px 24px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            margin-top: 16px;
            transition: background-color 0.2s;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        .btn:hover {
            background-color: #4338ca;
        }
        .alert {
            padding: 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .alert-danger {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        .alert-info {
            background-color: #e0f2fe;
            color: #0369a1;
            border: 1px solid #bae6fd;
        }
        ol {
            padding-left: 20px;
            line-height: 1.8;
        }
        code {
            background-color: #f3f4f6;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: Consolas, Monaco, monospace;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>Laravel Deployment Unzipper</h1>
        
        <?php
        $zipFile = __DIR__ . '/project.zip';

        if (isset($_POST['action']) && $_POST['action'] === 'extract') {
            if (!file_exists($zipFile)) {
                echo '<div class="alert alert-danger"><strong>Error:</strong> project.zip tidak ditemukan di direktori saat ini. Silakan unggah file project.zip ke samping script ini.</div>';
            } else {
                $zip = new ZipArchive();
                $res = $zip->open($zipFile);
                if ($res === TRUE) {
                    $zip->extractTo(__DIR__);
                    $zip->close();
                    
                    echo '<div class="alert alert-success"><strong>Sukses!</strong> project.zip telah berhasil diekstrak di direktori saat ini.</div>';
                    echo '<p><strong>Langkah selanjutnya untuk menyelesaikan deployment:</strong></p>';
                    echo '<ol>';
                    echo '<li><strong>Buat file .env</strong> di file manager Anda pada folder root <code>htdocs/</code>. Isi dengan konfigurasi database InfinityFree Anda dan konfigurasi MikroTik. Anda bisa menyalin isi dari file <code>.env.example</code> sebagai template.</li>';
                    echo '<li><strong>Jalankan migrasi database:</strong> Kunjungi URL berikut di browser Anda:<br>';
                    echo '<a href="deploy-migrate?key=monitoring123" target="_blank" class="btn" style="background-color: #059669; margin-top: 8px;">Jalankan Migrasi & Seed Database</a></li>';
                    echo '<li>Setelah berhasil, login ke dashboard menggunakan akun admin (username: <code>kepsek</code>, password: <code>kepsek</code>).</li>';
                    echo '<li><strong style="color: #dc2626;">PENTING:</strong> Hapus file <code>project.zip</code> dan <code>unzip.php</code> dari server Anda segera demi keamanan!</li>';
                    echo '</ol>';
                } else {
                    echo '<div class="alert alert-danger"><strong>Gagal mengekstrak:</strong> Gagal membuka file zip. Kode error: ' . $res . '</div>';
                }
            }
        } else {
            if (file_exists($zipFile)) {
                $size = round(filesize($zipFile) / (1024 * 1024), 2);
                echo '<div class="alert alert-info">File <strong>project.zip</strong> terdeteksi (' . $size . ' MB). Siap untuk diekstrak.</div>';
                echo '<form method="post">';
                echo '<input type="hidden" name="action" value="extract">';
                echo '<button type="submit" class="btn">Ekstrak File Project Sekarang</button>';
                echo '</form>';
            } else {
                echo '<div class="alert alert-danger"><strong>project.zip tidak ditemukan!</strong> Silakan unggah file <code>project.zip</code> menggunakan FTP Client atau File Manager ke folder yang sama dengan script ini terlebih dahulu.</div>';
            }
        }
        ?>
    </div>
</body>
</html>
PHP;

file_put_contents($unzipScriptName, $unzipContent);
echo "unzip.php generated successfully!\n\n";
echo "Ready to deploy! Upload project.zip and unzip.php to your htdocs/ directory on InfinityFree.\n";
