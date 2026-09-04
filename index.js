// ============================================================
//  SCRIPT.JS – COMPLETE FRONTEND LOGIC
// ============================================================

// ============================================================
//  PAGE LOADER
// ============================================================
function loadPage(page, data = null) {
    const content = document.getElementById('page-content');
    content.innerHTML = '<div class="loader">Loading...</div>';

    let url = 'index.php?page=' + page;
    if (data) {
        url += '&' + new URLSearchParams(data).toString();
    }

    fetch(url)
        .then(response => response.text())
        .then(html => {
            content.innerHTML = html;
            const scripts = content.querySelectorAll('script');
            scripts.forEach(oldScript => {
                const newScript = document.createElement('script');
                newScript.textContent = oldScript.textContent;
                oldScript.parentNode.replaceChild(newScript, oldScript);
            });
            initMenuButtons();
            initLongPress();
            initFileInputs();
            initUploadForms();
            initAuthForms();
        })
        .catch(error => {
            console.error('Error:', error);
            content.innerHTML = `
                <div class="error-page">
                    <h2>❌ Error</h2>
                    <p>${error.message}</p>
                    <button onclick="loadPage('login')">Go to Login</button>
                </div>
            `;
        });
}

// ============================================================
//  TOAST NOTIFICATION
// ============================================================
function showToast(msg, duration = 3000) {
    let toast = document.getElementById('toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'toast';
        toast.className = 'toast';
        document.body.appendChild(toast);
    }
    toast.textContent = msg;
    toast.classList.add('show');
    clearTimeout(toast._timeout);
    toast._timeout = setTimeout(() => toast.classList.remove('show'), duration);
}

// ============================================================
//  THREE-DOT MENU (⋮)
// ============================================================
function initMenuButtons() {
    document.querySelectorAll('.menu-btn').forEach(btn => {
        btn.removeEventListener('click', handleMenuClick);
        btn.addEventListener('click', handleMenuClick);
    });
}

function handleMenuClick(e) {
    e.stopPropagation();
    const menu = this.parentElement.querySelector('.dropdown-menu');
    document.querySelectorAll('.dropdown-menu.show').forEach(m => {
        if (m !== menu) m.classList.remove('show');
    });
    if (menu) menu.classList.toggle('show');
}

document.addEventListener('click', function(e) {
    document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
        if (!menu.parentElement.contains(e.target)) {
            menu.classList.remove('show');
        }
    });
});

// ============================================================
//  LONG PRESS (Mobile)
// ============================================================
function initLongPress() {
    document.querySelectorAll('.cell').forEach(cell => {
        let timer = null;
        cell.removeEventListener('touchstart', handleTouchStart);
        cell.removeEventListener('touchmove', handleTouchMove);
        cell.removeEventListener('touchend', handleTouchEnd);
        cell.addEventListener('touchstart', handleTouchStart);
        cell.addEventListener('touchmove', handleTouchMove);
        cell.addEventListener('touchend', handleTouchEnd);
    });
}

function handleTouchStart(e) {
    const cell = this;
    this._timer = setTimeout(() => {
        const menu = cell.querySelector('.dropdown-menu');
        if (menu) {
            menu.classList.toggle('show');
            if (navigator.vibrate) navigator.vibrate(20);
        }
    }, 500);
}

function handleTouchMove(e) {
    clearTimeout(this._timer);
}

function handleTouchEnd(e) {
    clearTimeout(this._timer);
}

// ============================================================
//  FILE INPUT AUTO-SUBMIT
// ============================================================
function initFileInputs() {
    document.querySelectorAll('input[type="file"]').forEach(inp => {
        inp.removeEventListener('change', handleFileChange);
        inp.addEventListener('change', handleFileChange);
    });
}

function handleFileChange(e) {
    const form = this.closest('form');
    if (form) {
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.click();
        } else {
            form.submit();
        }
    }
}

// ============================================================
//  UPLOAD FORMS – AJAX
// ============================================================
function initUploadForms() {
    document.querySelectorAll('.cell form').forEach(form => {
        form.removeEventListener('submit', handleUploadSubmit);
        form.addEventListener('submit', handleUploadSubmit);
    });
}

function handleUploadSubmit(e) {
    e.preventDefault();
    const form = this;
    const fileInput = form.querySelector('input[type="file"]');
    const file = fileInput.files[0];
    if (!file) return;

    // Check file size (1GB max)
    if (file.size > 1073741824) {
        showToast('❌ File too large (max 1GB)');
        return;
    }

    const btn = form.querySelector('button[type="submit"]');
    const originalText = btn.textContent;
    btn.textContent = '⏳ Uploading...';
    btn.disabled = true;

    const formData = new FormData(form);
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(() => {
        showToast('✅ Image uploaded!');
        loadPage('dashboard');
    })
    .catch(error => {
        showToast('❌ Error: ' + error.message);
        btn.textContent = originalText;
        btn.disabled = false;
    });
}

// ============================================================
//  AUTH FORMS
// ============================================================
function initAuthForms() {
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.removeEventListener('submit', handleLogin);
        loginForm.addEventListener('submit', handleLogin);
    }
    const signupForm = document.getElementById('signup-form');
    if (signupForm) {
        signupForm.removeEventListener('submit', handleSignup);
        signupForm.addEventListener('submit', handleSignup);
    }
    const forgotForm = document.getElementById('forgot-form');
    if (forgotForm) {
        forgotForm.removeEventListener('submit', handleForgot);
        forgotForm.addEventListener('submit', handleForgot);
    }
    const resetForm = document.getElementById('reset-form');
    if (resetForm) {
        resetForm.removeEventListener('submit', handleReset);
        resetForm.addEventListener('submit', handleReset);
    }
}

function handleLogin(e) {
    e.preventDefault();
    const form = this;
    const formData = new FormData(form);
    showToast('⏳ Logging in...');
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(html => {
        if (html.includes('dashboard')) {
            loadPage('dashboard');
            showToast('✅ Welcome back!');
        } else {
            document.getElementById('page-content').innerHTML = html;
            initAuthForms();
        }
    });
}

function handleSignup(e) {
    e.preventDefault();
    const form = this;
    const formData = new FormData(form);
    showToast('⏳ Creating account...');
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(html => {
        document.getElementById('page-content').innerHTML = html;
        initAuthForms();
    });
}

function handleForgot(e) {
    e.preventDefault();
    const form = this;
    const formData = new FormData(form);
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(html => {
        document.getElementById('page-content').innerHTML = html;
        initAuthForms();
    });
}

function handleReset(e) {
    e.preventDefault();
    const form = this;
    const formData = new FormData(form);
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(html => {
        document.getElementById('page-content').innerHTML = html;
        initAuthForms();
    });
}

// ============================================================
//  LOGOUT
// ============================================================
function logout() {
    fetch('index.php?logout=1')
        .then(() => {
            showToast('👋 Logged out');
            loadPage('login');
        });
}

// ============================================================
//  BACKUP / RESTORE / CLEAR
// ============================================================
function backup() {
    window.location.href = 'index.php?backup=1';
    showToast('📦 Creating backup...');
}

function restore(event) {
    const file = event.target.files[0];
    if (!file) return;
    const formData = new FormData();
    formData.append('restore', '1');
    formData.append('backup', file);
    showToast('⏳ Restoring...');
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(() => {
        showToast('✅ Restore successful!');
        loadPage('dashboard');
    })
    .catch(err => {
        showToast('❌ Error: ' + err.message);
    });
    event.target.value = '';
}

function clearAllData() {
    if (!confirm('⚠️ Delete ALL data?')) return;
    fetch('index.php?clear=1')
        .then(() => {
            showToast('🗑️ Data cleared');
            loadPage('login');
        });
}

// ============================================================
//  DOWNLOAD / DELETE
// ============================================================
function downloadImage(slot) {
    window.location.href = 'index.php?download=1&slot=' + slot;
    showToast('⬇️ Downloading...');
}

function deleteImage(slot) {
    if (!confirm('Delete this image?')) return;
    fetch('index.php?delete=1&slot=' + slot)
        .then(() => {
            showToast('🗑️ Deleted');
            loadPage('dashboard');
        });
}

// ============================================================
//  ADMIN PANEL
// ============================================================
function loadAdminPanel() {
    loadPage('admin');
}

// ============================================================
//  INIT – Check Session
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    fetch('index.php?check=1')
        .then(response => response.json())
        .then(data => {
            if (data.loggedIn) {
                loadPage('dashboard');
            } else {
                loadPage('login');
            }
        })
        .catch(() => {
            loadPage('login');
        });
});

// ============================================================
//  EXPOSE GLOBAL FUNCTIONS
// ============================================================
window.loadPage = loadPage;
window.showToast = showToast;
window.logout = logout;
window.backup = backup;
window.restore = restore;
window.clearAllData = clearAllData;
window.loadAdminPanel = loadAdminPanel;
window.downloadImage = downloadImage;
window.deleteImage = deleteImage;

console.log('✅ Script loaded');