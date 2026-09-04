<?php
// ============================================================
//  SINGLE PHP FILE – index.php (Backend + All Pages)
//  Database, Routing, HTML Pages, API Endpoints
// ============================================================

session_start();

// ---------- CONFIG ----------
define('DB_HOST', 'sql123.infinityfree.com');  // Your MySQL Host
define('DB_USER', 'if0_12345678');            // Your DB Username
define('DB_PASS', 'YourPassword123');         // Your DB Password
define('DB_NAME', 'if0_12345678_myapp');      // Your DB Name

define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 1073741824); // 1GB
define('MIN_SLOTS', 4); // Minimum 4 empty slots always visible

if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0777, true);

// ---------- DATABASE ----------
function getDB() {
    static $pdo = null;
    if ($pdo) return $pdo;
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            secret_answer VARCHAR(255) NOT NULL,
            hint VARCHAR(255) DEFAULT '',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL,
            slot VARCHAR(20) NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_slot (username, slot)
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS login_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL,
            success BOOLEAN DEFAULT FALSE,
            login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            ip_address VARCHAR(45),
            user_agent TEXT
        )");
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
        $stmt->execute();
        if ($stmt->fetchColumn() == 0) {
            $pdo->prepare("INSERT INTO users (username, password, secret_answer, hint) VALUES ('admin', '123', 'mysecret', 'Hint: My secret')")->execute();
        }
        return $pdo;
    } catch (PDOException $e) {
        die("❌ Database Error: " . $e->getMessage());
    }
}

// ---------- FUNCTIONS ----------
function getUser($username) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function createUser($username, $password, $secretAnswer, $hint = '') {
    $pdo = getDB();
    $stmt = $pdo->prepare("INSERT INTO users (username, password, secret_answer, hint) VALUES (?, ?, ?, ?)");
    return $stmt->execute([$username, $password, $secretAnswer, $hint]);
}

function updatePassword($username, $newPassword) {
    $pdo = getDB();
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = ?");
    return $stmt->execute([$newPassword, $username]);
}

function getAllUsers() {
    $pdo = getDB();
    return $pdo->query("SELECT * FROM users ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);
}

function getImages($username) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM images WHERE username = ? ORDER BY slot");
    $stmt->execute([$username]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function saveImage($username, $slot, $fileName, $filePath) {
    $pdo = getDB();
    $pdo->prepare("DELETE FROM images WHERE username = ? AND slot = ?")->execute([$username, $slot]);
    $stmt = $pdo->prepare("INSERT INTO images (username, slot, file_name, file_path) VALUES (?, ?, ?, ?)");
    return $stmt->execute([$username, $slot, $fileName, $filePath]);
}

function deleteImage($username, $slot) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT file_path FROM images WHERE username = ? AND slot = ?");
    $stmt->execute([$username, $slot]);
    $img = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($img && file_exists($img['file_path'])) unlink($img['file_path']);
    $pdo->prepare("DELETE FROM images WHERE username = ? AND slot = ?")->execute([$username, $slot]);
}

function getAllImages() {
    $pdo = getDB();
    return $pdo->query("SELECT * FROM images ORDER BY username, slot")->fetchAll(PDO::FETCH_ASSOC);
}

function addLog($username, $success, $ip = null, $userAgent = null) {
    $pdo = getDB();
    $ip = $ip ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $userAgent ?? $_SERVER['HTTP_USER_AGENT'] ?? '';
    $stmt = $pdo->prepare("INSERT INTO login_logs (username, success, ip_address, user_agent) VALUES (?, ?, ?, ?)");
    return $stmt->execute([$username, $success ? 1 : 0, $ip, $userAgent]);
}

function getAllLogs() {
    $pdo = getDB();
    return $pdo->query("SELECT * FROM login_logs ORDER BY login_time DESC LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
}

function clearAllLogs() {
    getDB()->query("TRUNCATE TABLE login_logs");
}

function clearAllData() {
    $pdo = getDB();
    $images = getAllImages();
    foreach ($images as $img) if (file_exists($img['file_path'])) unlink($img['file_path']);
    $pdo->query("TRUNCATE TABLE images");
    $pdo->query("TRUNCATE TABLE login_logs");
    $pdo->query("DELETE FROM users WHERE username != 'admin'");
}

function isLoggedIn() {
    return isset($_SESSION['username']);
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function formatBytes($bytes) {
    if ($bytes < 1024) return $bytes . ' B';
    else if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    else return round($bytes / 1048576, 1) . ' MB';
}

// ---------- ROUTING ----------
$page = isset($_GET['page']) ? $_GET['page'] : '';
$check = isset($_GET['check']);
$action = isset($_POST['action']) ? $_POST['action'] : '';

// ----- AJAX CHECK SESSION -----
if ($check) {
    header('Content-Type: application/json');
    echo json_encode(['loggedIn' => isLoggedIn()]);
    exit;
}

// ----- LOGIN -----
if (isset($_POST['action']) && $_POST['action'] == 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $remember = isset($_POST['remember']);

    $user = getUser($username);
    $success = ($user && $user['password'] === $password);
    addLog($username, $success);

    if ($success) {
        $_SESSION['username'] = $username;
        if ($remember) {
            setcookie('remember_user', $username, time() + 86400 * 30, '/');
            setcookie('remember_pass', $password, time() + 86400 * 30, '/');
        }
        echo '<script>window.location.href="?page=dashboard";</script>';
        exit;
    } else {
        $error = '❌ Invalid credentials';
    }
}

// ----- SIGNUP -----
if (isset($_POST['action']) && $_POST['action'] == 'signup') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $secret = trim($_POST['secretAnswer'] ?? '');
    $hint = trim($_POST['hint'] ?? '');

    if (!$username || !$password || !$secret) {
        $error = '❌ All fields except hint are required';
    } elseif (getUser($username)) {
        $error = '❌ Username already exists';
    } else {
        createUser($username, $password, $secret, $hint);
        echo '<script>alert("✅ Account created! Please login."); window.location.href="?page=login";</script>';
        exit;
    }
}

// ----- FORGOT -----
if (isset($_POST['action']) && $_POST['action'] == 'forgot') {
    $username = trim($_POST['username'] ?? '');
    $secret = trim($_POST['secretAnswer'] ?? '');
    $user = getUser($username);
    if ($user && $user['secret_answer'] === $secret) {
        $_SESSION['reset_user'] = $username;
        echo '<script>window.location.href="?page=reset";</script>';
        exit;
    } else {
        $error = '❌ Invalid username or secret answer.';
    }
}

// ----- RESET -----
if (isset($_POST['action']) && $_POST['action'] == 'reset') {
    if (!isset($_SESSION['reset_user'])) {
        echo '<script>window.location.href="?page=forgot";</script>';
        exit;
    }
    $newPass = trim($_POST['newPassword'] ?? '');
    if (strlen($newPass) >= 1) {
        updatePassword($_SESSION['reset_user'], $newPass);
        unset($_SESSION['reset_user']);
        echo '<script>alert("✅ Password updated! Please login."); window.location.href="?page=login";</script>';
        exit;
    }
}

// ----- UPLOAD -----
if (isset($_POST['slot']) && isset($_FILES['image'])) {
    if (!isLoggedIn()) {
        echo '<script>alert("❌ Not logged in"); window.history.back();</script>';
        exit;
    }
    $username = $_SESSION['username'];
    $slot = $_POST['slot'];
    $file = $_FILES['image'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        if ($file['size'] > MAX_FILE_SIZE) {
            echo '<script>alert("❌ File too large (max 1GB)"); window.history.back();</script>';
            exit;
        }
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $ext = $ext ? ".$ext" : ".png";
        $savedName = $username . '_' . $slot . '_' . time() . $ext;
        $dest = UPLOAD_DIR . $savedName;
        move_uploaded_file($file['tmp_name'], $dest);
        saveImage($username, $slot, $file['name'], $dest);
    }
    echo '<script>window.location.href="?page=dashboard";</script>';
    exit;
}

// ----- DOWNLOAD -----
if (isset($_GET['download']) && isset($_GET['slot'])) {
    if (!isLoggedIn()) {
        echo '<script>window.location.href="?page=login";</script>';
        exit;
    }
    $username = $_SESSION['username'];
    $slot = $_GET['slot'];
    $images = getImages($username);
    $img = null;
    foreach ($images as $im) {
        if ($im['slot'] === $slot) { $img = $im; break; }
    }
    if (!$img || !file_exists($img['file_path'])) {
        echo '<script>alert("❌ Image not found"); window.history.back();</script>';
        exit;
    }
    header('Content-Type: image/png');
    header('Content-Disposition: attachment; filename="' . $img['file_name'] . '"');
    header('Content-Length: ' . filesize($img['file_path']));
    readfile($img['file_path']);
    exit;
}

// ----- DELETE -----
if (isset($_GET['delete']) && isset($_GET['slot'])) {
    if (!isLoggedIn()) {
        echo '<script>window.location.href="?page=login";</script>';
        exit;
    }
    $username = $_SESSION['username'];
    $slot = $_GET['slot'];
    deleteImage($username, $slot);
    echo '<script>window.location.href="?page=dashboard";</script>';
    exit;
}

// ----- BACKUP -----
if (isset($_GET['backup'])) {
    if (!isLoggedIn()) {
        echo '<script>window.location.href="?page=login";</script>';
        exit;
    }
    $users = getAllUsers();
    $images = getAllImages();
    $logs = getAllLogs();
    $payload = [
        'users' => $users,
        'images' => $images,
        'logs' => $logs,
        'exportedAt' => date('Y-m-d H:i:s')
    ];
    $json = json_encode($payload, JSON_PRETTY_PRINT);
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="backup_' . date('Y-m-d_H-i-s') . '.json"');
    echo $json;
    exit;
}

// ----- RESTORE -----
if (isset($_POST['restore']) && isset($_FILES['backup'])) {
    if (!isLoggedIn()) {
        echo '<script>alert("❌ Not logged in"); window.history.back();</script>';
        exit;
    }
    $file = $_FILES['backup'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $json = file_get_contents($file['tmp_name']);
        $data = json_decode($json, true);
        if ($data && isset($data['users'])) {
            clearAllData();
            foreach ($data['users'] as $u) {
                createUser($u['username'], $u['password'], $u['secret_answer'], $u['hint'] ?? '');
            }
            foreach ($data['images'] as $img) {
                saveImage($img['username'], $img['slot'], $img['file_name'], $img['file_path']);
            }
            foreach ($data['logs'] as $log) {
                addLog($log['username'], $log['success']);
            }
            echo '<script>alert("✅ Restore successful!"); window.location.href="?page=dashboard";</script>';
            exit;
        } else {
            echo '<script>alert("❌ Invalid backup file"); window.history.back();</script>';
            exit;
        }
    }
    echo '<script>window.location.href="?page=dashboard";</script>';
    exit;
}

// ----- CLEAR -----
if (isset($_GET['clear'])) {
    if (!isLoggedIn() || $_SESSION['username'] !== 'admin') {
        echo '<script>window.location.href="?page=login";</script>';
        exit;
    }
    clearAllData();
    echo '<script>window.location.href="?page=dashboard";</script>';
    exit;
}

// ----- LOGOUT -----
if (isset($_GET['logout'])) {
    session_destroy();
    setcookie('remember_user', '', time() - 3600, '/');
    setcookie('remember_pass', '', time() - 3600, '/');
    echo '<script>window.location.href="?page=login";</script>';
    exit;
}

// ---------- PAGE RENDER ----------
$page = isset($_GET['page']) ? $_GET['page'] : 'login';

// ============================================================
//  LOGIN PAGE
// ============================================================
if ($page == 'login') {
    if (isLoggedIn()) {
        echo '<script>window.location.href="?page=dashboard";</script>';
        exit;
    }
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login</title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
    <div class="card">
        <h2>✨ Welcome Back</h2>
        <p class="sub">Sign in to your secure dashboard</p>
        <?php if (isset($error)) echo '<div class="error">' . $error . '</div>'; ?>
        <form id="login-form" method="POST">
            <input type="hidden" name="action" value="login">
            <input type="text" name="username" placeholder="Username" required value="<?= $_COOKIE['remember_user'] ?? '' ?>">
            <input type="password" name="password" placeholder="Password" required>
            <div class="checkbox-group">
                <input type="checkbox" name="remember" id="remember" checked>
                <label for="remember">Remember me</label>
            </div>
            <button type="submit" style="width:100%">🔐 Sign In</button>
        </form>
        <p style="margin-top:20px; text-align:center;">
            <a href="#" onclick="loadPage('forgot')">Forgot password?</a> · 
            <a href="#" onclick="loadPage('signup')">Create account</a>
        </p>
    </div>
    </body>
    </html>
    <?php
    exit;
}

// ============================================================
//  SIGNUP PAGE
// ============================================================
if ($page == 'signup') {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Signup</title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
    <div class="card">
        <h2>📝 Create Account</h2>
        <p class="sub">Join the secure grid</p>
        <?php if (isset($error)) echo '<div class="error">' . $error . '</div>'; ?>
        <form id="signup-form" method="POST">
            <input type="hidden" name="action" value="signup">
            <input type="text" name="username" placeholder="Choose username" required>
            <input type="password" name="password" placeholder="Password" required>
            <input type="text" name="secretAnswer" placeholder="Secret answer (for recovery)" required>
            <input type="text" name="hint" placeholder="Hint (optional)">
            <button type="submit" style="width:100%">🚀 Create Account</button>
        </form>
        <p style="margin-top:16px; text-align:center;"><a href="#" onclick="loadPage('login')">← Back to Login</a></p>
    </div>
    </body>
    </html>
    <?php
    exit;
}

// ============================================================
//  FORGOT PAGE
// ============================================================
if ($page == 'forgot') {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Forgot</title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
    <div class="card">
        <h2>🔑 Recover Account</h2>
        <p class="sub">Enter your username and secret answer</p>
        <?php if (isset($error)) echo '<div class="error">' . $error . '</div>'; ?>
        <form id="forgot-form" method="POST">
            <input type="hidden" name="action" value="forgot">
            <input type="text" name="username" placeholder="Username" required>
            <input type="text" name="secretAnswer" placeholder="Secret Answer" required>
            <button type="submit" style="width:100%">Verify</button>
        </form>
        <p style="margin-top:16px; text-align:center;"><a href="#" onclick="loadPage('login')">← Back to Login</a></p>
    </div>
    </body>
    </html>
    <?php
    exit;
}

// ============================================================
//  RESET PAGE
// ============================================================
if ($page == 'reset') {
    if (!isset($_SESSION['reset_user'])) {
        echo '<script>window.location.href="?page=forgot";</script>';
        exit;
    }
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Reset</title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
    <div class="card">
        <h2>🔄 Reset Password</h2>
        <p class="sub">Set new password for <span style="color:#a78bfa;"><?= $_SESSION['reset_user'] ?></span></p>
        <form id="reset-form" method="POST">
            <input type="hidden" name="action" value="reset">
            <input type="password" name="newPassword" placeholder="New Password" required>
            <button type="submit" style="width:100%">Update Password</button>
        </form>
    </div>
    </body>
    </html>
    <?php
    exit;
}

// ============================================================
//  DASHBOARD PAGE (Unlimited Grid)
// ============================================================
if ($page == 'dashboard') {
    if (!isLoggedIn()) {
        echo '<script>window.location.href="?page=login";</script>';
        exit;
    }

    $username = $_SESSION['username'];
    $user = getUser($username);
    $images = getImages($username);
    $hint = $user['hint'] ?? '';

    // Calculate total size
    $totalSize = 0;
    foreach ($images as $img) {
        if (file_exists($img['file_path'])) {
            $totalSize += filesize($img['file_path']);
        }
    }
    $formattedSize = formatBytes($totalSize);

    // Determine how many slots to show (at least MIN_SLOTS, plus more for each image)
    $totalSlots = max(count($images) + 2, MIN_SLOTS);
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Dashboard</title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
    <div class="card">

        <!-- Header -->
        <div class="flex flex-between">
            <div>
                <h2>👋 Hello, <?= htmlspecialchars($username) ?></h2>
                <p class="sub" style="margin-bottom:4px;">Your secure image vault</p>
            </div>
            <div class="flex">
                <span class="badge"><?= $formattedSize ?> used</span>
                <?php if ($username === 'admin'): ?>
                    <a href="#" class="btn-outline" style="padding:10px 18px; border-radius:30px;" onclick="loadAdminPanel()">🛠️ Admin</a>
                <?php endif; ?>
                <a href="#" class="btn-outline" onclick="logout()" style="padding:10px 18px; border-radius:30px;">🚪 Logout</a>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex" style="margin:16px 0 12px;">
            <button class="tag tag-green" onclick="backup()">⬇️ Backup</button>
            <form id="restore-form" style="display:inline;">
                <span class="tag tag-yellow" onclick="document.getElementById('restore-input').click()" style="cursor:pointer;">⬆️ Restore</span>
                <input type="file" id="restore-input" accept=".json" style="display:none;" onchange="restore(event)">
            </form>
            <span class="tag tag-red" onclick="clearAllData()" style="cursor:pointer;">🗑️ Clear All</span>
        </div>

        <p style="color:#64748b; font-size:13px; margin:0 0 16px;">
            💡 Hint: <?= htmlspecialchars($hint) ?>
        </p>

        <!-- UNLIMITED GRID -->
        <div class="grid">
            <?php for ($i = 1; $i <= $totalSlots; $i++): 
                $slot = 'slot-' . $i;
                $img = null;
                foreach ($images as $im) {
                    if ($im['slot'] === $slot) { $img = $im; break; }
                }
            ?>
            <div class="cell" data-slot="<?= $slot ?>">
                <div class="img-wrapper">
                    <?php if ($img && file_exists($img['file_path'])): ?>
                        <img src="<?= $img['file_path'] ?>" alt="<?= htmlspecialchars($img['file_name']) ?>" loading="lazy">
                    <?php else: ?>
                        <div class="empty-slot">📷</div>
                    <?php endif; ?>
                </div>

                <!-- Three-Dot Menu -->
                <?php if ($img && file_exists($img['file_path'])): ?>
                <button class="menu-btn" type="button">⋮</button>
                <div class="dropdown-menu">
                    <button onclick="downloadImage('<?= $slot ?>')">⬇️ Download</button>
                    <button onclick="deleteImage('<?= $slot ?>')" style="color:#f87171;">🗑️ Delete</button>
                    <div class="divider"></div>
                    <form method="POST" action="index.php" enctype="multipart/form-data">
                        <input type="hidden" name="slot" value="<?= $slot ?>">
                        <input type="file" name="image" accept="image/*" style="display:none;" id="file-<?= $slot ?>">
                        <button type="button" onclick="document.getElementById('file-<?= $slot ?>').click()">🔄 Replace</button>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Upload Form -->
                <form method="POST" action="index.php" enctype="multipart/form-data">
                    <input type="hidden" name="slot" value="<?= $slot ?>">
                    <div class="custom-file" onclick="this.nextElementSibling.click()">
                        <?= ($img) ? '🔄 Replace' : '📸 Upload' ?>
                    </div>
                    <input type="file" name="image" accept="image/*" required>
                    <button type="submit"><?= ($img) ? 'Replace' : 'Upload' ?></button>
                </form>

                <small><?= $slot ?></small>
            </div>
            <?php endfor; ?>
        </div>

    </div>
    <script src="script.js"></script>
    </body>
    </html>
    <?php
    exit;
}

// ============================================================
//  ADMIN PAGE
// ============================================================
if ($page == 'admin') {
    if (!isLoggedIn() || $_SESSION['username'] !== 'admin') {
        echo '<script>window.location.href="?page=login";</script>';
        exit;
    }

    $users = getAllUsers();
    $logs = getAllLogs();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin</title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
    <div class="card">
        <div class="flex flex-between">
            <h2>🛠️ Admin Panel</h2>
            <a href="#" class="btn-outline" onclick="loadPage('dashboard')">← Back</a>
        </div>
        <p class="sub">View all users and login logs</p>

        <h3 style="margin-top:24px; color:#c4b5fd;">👥 Users</h3>
        <table class="admin-table">
            <thead>
                <tr><th>Username</th><th>Password</th><th>Secret Answer</th><th>Hint</th><th>Images</th></tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): 
                $imgs = getImages($u['username']);
            ?>
                <tr>
                    <td><?= htmlspecialchars($u['username']) ?></td>
                    <td><?= htmlspecialchars($u['password']) ?></td>
                    <td><?= htmlspecialchars($u['secret_answer']) ?></td>
                    <td><?= htmlspecialchars($u['hint'] ?? '-') ?></td>
                    <td><?= count($imgs) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <h3 style="margin-top:30px; color:#c4b5fd;">📋 Login Logs</h3>
        <table class="admin-table">
            <thead>
                <tr><th>Time</th><th>Username</th><th>Status</th><th>IP</th></tr>
            </thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= htmlspecialchars($log['login_time']) ?></td>
                    <td><?= htmlspecialchars($log['username']) ?></td>
                    <td class="<?= $log['success'] ? 'log-success' : 'log-fail' ?>">
                        <?= $log['success'] ? '✅ Success' : '❌ Failed' ?>
                    </td>
                    <td><?= htmlspecialchars($log['ip_address']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <script src="script.js"></script>
    </body>
    </html>
    <?php
    exit;
}

// ---------- 404 ----------
header("HTTP/1.0 404 Not Found");
echo "404 - Page not found";
exit;
?>