<?php
include '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];

    // Validasi
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "Upload failed: " . $file['error'];
    } else {
        // Create upload directory
        $upload_dir = __DIR__ . '/../uploads/user_' . $user_id . '/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Generate safe filename
        $filename = time() . '_' . preg_replace("/[^a-zA-Z0-9.-]/", "", basename($file['name']));
        $upload_path = $upload_dir . $filename;

        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            $success = "✅ File uploaded: " . htmlspecialchars($filename);
        } else {
            $error = "Failed to move uploaded file";
        }
    }
}

// Get user info
$user_query = $conn->prepare("SELECT username, full_name FROM users WHERE id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user = $user_query->get_result()->fetch_assoc();

// List files
$upload_dir = __DIR__ . '/../uploads/user_' . $user_id . '/';
$files = [];
if (is_dir($upload_dir)) {
    $files = array_diff(scandir($upload_dir), ['.', '..']);
    $files = array_reverse(array_values($files));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Manager - Sakuci Hosting</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <style>
        .dashboard { display: grid; grid-template-columns: 250px 1fr; gap: 2rem; padding: 2rem; }
        .sidebar { background: white; padding: 2rem; border-radius: 0.75rem; box-shadow: 0 2px 8px rgba(0,0,0,0.08); height: fit-content; }
        .main-content { background: white; padding: 2rem; border-radius: 0.75rem; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .sidebar a { display: block; padding: 0.75rem 1rem; margin-bottom: 0.5rem; color: #2d3748; text-decoration: none; border-radius: 0.5rem; }
        .sidebar a:hover, .sidebar a.active { background: #edf2f7; color: #667eea; }
        .upload-box { background: #f7fafc; border: 2px dashed #667eea; border-radius: 0.75rem; padding: 2rem; text-align: center; cursor: pointer; transition: all 0.3s; }
        .upload-box:hover { background: #edf2f7; border-color: #5568d3; }
        .files-list { margin-top: 2rem; }
        .file-item { background: #f7fafc; padding: 1rem; border-radius: 0.5rem; margin-bottom: 0.75rem; display: flex; justify-content: space-between; align-items: center; }
        .file-info { flex: 1; }
        .file-name { font-weight: 600; color: #2d3748; }
        .file-size { font-size: 0.9rem; color: #718096; margin-top: 0.25rem; }
        .btn-download, .btn-delete { padding: 0.5rem 1rem; border: none; border-radius: 0.5rem; cursor: pointer; font-size: 0.9rem; }
        .btn-download { background: #667eea; color: white; }
        .btn-delete { background: #f56565; color: white; }
        #fileInput { display: none; }
    </style>
</head>
<body>
    <header>
        <nav class="container">
            <a href="../" class="logo">🚀 Sakuci Hosting</a>
            <ul class="nav-links">
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="file-manager.php">File Manager</a></li>
                <li><a href="#">Database</a></li>
            </ul>
            <div class="nav-buttons">
                <span style="margin-right: 1rem; color: #2d3748;">👤 <?php echo htmlspecialchars($user['username']); ?></span>
                <a href="logout.php" class="btn btn-secondary">Logout</a>
            </div>
        </nav>
    </header>

    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <h3>Menu</h3>
            <ul style="list-style: none;">
                <li><a href="dashboard.php">📊 Dashboard</a></li>
                <li><a href="file-manager.php" class="active">📁 File Manager</a></li>
                <li><a href="#">🗄️ Database</a></li>
                <li><a href="#">💾 Backup</a></li>
                <li><a href="#">📧 Email</a></li>
                <li><a href="#">⚙️ Settings</a></li>
                <li><a href="#">🎫 Support</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <h1>📁 File Manager</h1>
            <p style="color: #718096; margin-bottom: 2rem;">Upload dan kelola file framework Anda di sini</p>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Upload Area -->
            <div class="upload-box" onclick="document.getElementById('fileInput').click();">
                <div style="font-size: 2rem; margin-bottom: 1rem;">📤</div>
                <h3>Drag & Drop atau Klik untuk Upload</h3>
                <p style="color: #718096; margin-top: 0.5rem;">Framework, file, atau aplikasi apapun</p>
            </div>

            <form method="POST" enctype="multipart/form-data" style="display: none;" id="uploadForm">
                <input type="file" id="fileInput" name="file" onchange="document.getElementById('uploadForm').submit();">
            </form>

            <!-- File List -->
            <div class="files-list">
                <h2>📋 File Anda</h2>
                <?php if (!empty($files)): ?>
                    <?php foreach ($files as $file):
                        $file_path = $upload_dir . $file;
                        $file_size = filesize($file_path);
                        $size_text = $file_size > 1048576 ? round($file_size / 1048576, 2) . ' MB' : round($file_size / 1024, 2) . ' KB';
                    ?>
                    <div class="file-item">
                        <div class="file-info">
                            <div class="file-name">📄 <?php echo htmlspecialchars($file); ?></div>
                            <div class="file-size">Size: <?php echo $size_text; ?> • Uploaded: <?php echo date('d M Y H:i', filemtime($file_path)); ?></div>
                        </div>
                        <div style="display: flex; gap: 0.5rem;">
                            <a href="../uploads/user_<?php echo $user_id; ?>/<?php echo urlencode($file); ?>" class="btn-download" download>Download</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #718096; text-align: center; padding: 2rem;">Belum ada file. Upload framework Anda sekarang!</p>
                <?php endif; ?>
            </div>

            <!-- Info Box -->
            <div style="background: #edf2f7; padding: 2rem; border-radius: 0.75rem; margin-top: 2rem;">
                <h3 style="color: #667eea; margin-bottom: 1rem;">💡 Informasi Upload</h3>
                <ul style="color: #2d3748; list-style-position: inside;">
                    <li>✅ Upload file framework Anda di sini</li>
                    <li>✅ Support untuk .zip, .rar, .tar.gz, dll</li>
                    <li>✅ File tersimpan aman di server</li>
                    <li>✅ Anda bisa download kembali kapan saja</li>
                    <li>✅ Tidak ada batasan jumlah file</li>
                </ul>
            </div>
        </main>
    </div>

    <footer style="margin-top: 3rem;">
        <div class="footer-content">
            <div class="footer-section">
                <h4>Dukungan</h4>
                <ul>
                    <li><a href="#">Contact Us</a></li>
                    <li><a href="#">Knowledge Base</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2024 Sakuci Hosting. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // Drag and drop
        const uploadBox = document.querySelector('.upload-box');
        uploadBox.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadBox.style.background = '#e6f2ff';
        });
        uploadBox.addEventListener('dragleave', () => {
            uploadBox.style.background = '#f7fafc';
        });
        uploadBox.addEventListener('drop', (e) => {
            e.preventDefault();
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                document.getElementById('fileInput').files = files;
                document.getElementById('uploadForm').submit();
            }
        });
    </script>
</body>
</html>
