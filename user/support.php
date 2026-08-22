<?php
include '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Create ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_ticket') {
    $subject = htmlspecialchars($_POST['subject'] ?? '');
    $description = htmlspecialchars($_POST['description'] ?? '');
    $category = htmlspecialchars($_POST['category'] ?? '');
    $priority = htmlspecialchars($_POST['priority'] ?? 'medium');

    if (empty($subject) || empty($description)) {
        $error = "Subject dan description harus diisi";
    } else if ($DB_CONNECTED && $conn) {
        $stmt = $conn->prepare("INSERT INTO support_tickets (user_id, subject, description, category, priority, ticket_status) VALUES (?, ?, ?, ?, ?, 'open')");
        $stmt->bind_param("issss", $user_id, $subject, $description, $category, $priority);

        if ($stmt->execute()) {
            $success = "✅ Ticket berhasil dibuat. Tim support kami akan merespon dalam 24 jam.";
        } else {
            $error = "Gagal membuat ticket";
        }
    }
}

// Get tickets
$tickets = [];
if ($DB_CONNECTED && $conn) {
    $result = $conn->query("SELECT * FROM support_tickets WHERE user_id = $user_id ORDER BY created_at DESC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $tickets[] = $row;
        }
    }
}

$user = $conn->prepare("SELECT username FROM users WHERE id = ?");
$user->bind_param("i", $user_id);
$user->execute();
$user_data = $user->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support - Sakuci Hosting</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <style>
        .dashboard { display: grid; grid-template-columns: 250px 1fr; gap: 2rem; padding: 2rem; }
        .sidebar { background: white; padding: 2rem; border-radius: 0.75rem; height: fit-content; }
        .main-content { background: white; padding: 2rem; border-radius: 0.75rem; }
        .sidebar a { display: block; padding: 0.75rem 1rem; margin-bottom: 0.5rem; color: #2d3748; text-decoration: none; border-radius: 0.5rem; }
        .sidebar a:hover, .sidebar a.active { background: #edf2f7; color: #667eea; }
        .ticket-item { background: #f7fafc; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1.5rem; margin-bottom: 1rem; }
        .ticket-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem; }
        .ticket-title { font-weight: 600; color: #2d3748; }
        .ticket-meta { color: #718096; font-size: 0.9rem; margin-top: 0.5rem; }
    </style>
</head>
<body>
    <header>
        <nav class="container">
            <a href="../" class="logo">🚀 Sakuci Hosting</a>
            <div class="nav-buttons">
                <span style="margin-right: 1rem; color: #2d3748;">👤 <?php echo htmlspecialchars($user_data['username']); ?></span>
                <a href="logout.php" class="btn btn-secondary">Logout</a>
            </div>
        </nav>
    </header>

    <div class="dashboard">
        <aside class="sidebar">
            <h3>Menu</h3>
            <ul style="list-style: none;">
                <li><a href="dashboard.php">📊 Dashboard</a></li>
                <li><a href="file-manager.php">📁 File Manager</a></li>
                <li><a href="database.php">🗄️ Database</a></li>
                <li><a href="backup.php">💾 Backup</a></li>
                <li><a href="email.php">📧 Email</a></li>
                <li><a href="settings.php">⚙️ Settings</a></li>
                <li><a href="support.php" class="active">🎫 Support</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <h1>🎫 Support Tickets</h1>
            <p style="color: #718096; margin-bottom: 2rem;">Hubungi tim support kami untuk bantuan</p>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Create Ticket Form -->
            <div style="background: #f7fafc; padding: 1.5rem; border-radius: 0.75rem; margin-bottom: 2rem;">
                <h2 style="margin-top: 0;">Buat Ticket Baru</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="create_ticket">
                    <div class="form-group">
                        <label>Subject</label>
                        <input type="text" name="subject" placeholder="Deskripsi singkat masalah" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" placeholder="Jelaskan masalah Anda secara detail" required></textarea>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category" required>
                                <option value="">Select Category</option>
                                <option value="technical">Technical Support</option>
                                <option value="billing">Billing</option>
                                <option value="account">Account</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Priority</label>
                            <select name="priority" required>
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Buat Ticket</button>
                </form>
            </div>

            <!-- Tickets List -->
            <h2>📋 Your Tickets</h2>
            <?php if (!empty($tickets)): ?>
                <?php foreach ($tickets as $ticket): ?>
                <div class="ticket-item">
                    <div class="ticket-header">
                        <div>
                            <div class="ticket-title">TICK-<?php echo $ticket['id']; ?>: <?php echo htmlspecialchars($ticket['subject']); ?></div>
                            <div class="ticket-meta">
                                Category: <strong><?php echo ucfirst($ticket['category']); ?></strong> |
                                Priority: <strong><?php echo ucfirst($ticket['priority']); ?></strong> |
                                Created: <?php echo date('d M Y', strtotime($ticket['created_at'])); ?>
                            </div>
                        </div>
                        <span class="status-badge status-<?php echo $ticket['ticket_status']; ?>">
                            <?php echo ucfirst($ticket['ticket_status']); ?>
                        </span>
                    </div>
                    <p style="color: #718096; margin: 0;"><?php echo htmlspecialchars(substr($ticket['description'], 0, 150)); ?>...</p>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color: #718096; text-align: center; padding: 2rem;">Anda belum membuat ticket apapun.</p>
            <?php endif; ?>

            <div style="background: #edf2f7; padding: 2rem; border-radius: 0.75rem; margin-top: 2rem;">
                <h3 style="color: #667eea;">💡 Support Information</h3>
                <ul style="color: #2d3748; list-style-position: inside;">
                    <li>✅ Response time: 24 jam</li>
                    <li>✅ Available: 24/7</li>
                    <li>✅ Prioritize urgent issues</li>
                    <li>✅ Free technical support included</li>
                    <li>✅ Email notification untuk updates</li>
                </ul>
            </div>
        </main>
    </div>

    <footer style="margin-top: 3rem;">
        <div class="footer-bottom">
            <p>&copy; 2024 Sakuci Hosting. All rights reserved.</p>
        </div>
    </footer>

    <script src="../public/js/script.js"></script>
</body>
</html>
