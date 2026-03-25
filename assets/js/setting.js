// settings.js – Admin Settings Page

document.addEventListener('DOMContentLoaded', function() {
    // Check authentication
    const currentUser = checkAuth(); // from auth-check.js
    if (!currentUser || currentUser.role !== 'admin') {
        window.location.href = '../auth/login.html';
        return;
    }

    // Update profile info in sidebar
    updateProfileInfo(currentUser);

    // Set footer date
    setFooterDate();

    // Initialize settings (load from backend)
    loadSettings();

    // Set up event listeners
    setupEventListeners();
});

function updateProfileInfo(user) {
    const initials = (user.name || 'Admin').split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
    document.getElementById('adminInitials').textContent = initials;
    document.getElementById('adminName').textContent = user.name || 'Admin User';
    document.getElementById('adminEmail').textContent = user.email || 'admin@oes.edu.et';
}

function setFooterDate() {
    const now = new Date();
    const formatted = now.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
    document.getElementById('footerDate').textContent = formatted;
}

function setupEventListeners() {
    // Settings navigation
    document.querySelectorAll('.settings-nav a').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('.settings-nav a').forEach(l => l.classList.remove('active'));
            this.classList.add('active');

            const sectionId = this.dataset.section;
            document.querySelectorAll('.settings-section').forEach(s => s.classList.remove('active'));
            document.getElementById(sectionId).classList.add('active');
        });
    });

    // Save buttons
    document.getElementById('saveGeneral').addEventListener('click', saveGeneralSettings);
    document.getElementById('saveSecurity').addEventListener('click', saveSecuritySettings);
    document.getElementById('saveEmail').addEventListener('click', saveEmailSettings);
    document.getElementById('saveExamDefaults').addEventListener('click', saveExamDefaults);
    document.getElementById('saveBackup').addEventListener('click', saveBackupSettings);
    document.getElementById('backupNow').addEventListener('click', backupNow);
    document.getElementById('clearLogsBtn').addEventListener('click', clearLogs);
    document.getElementById('resetSystemBtn').addEventListener('click', resetSystem);

    // Language toggle (optional)
    document.querySelectorAll('.lang-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.lang-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            // You could implement language switching here
        });
    });

    // Logout
    document.getElementById('logoutBtn').addEventListener('click', function(e) {
        e.preventDefault();
        if (confirm('Are you sure you want to logout?')) {
            logout(); // from auth-check.js
        }
    });
}

function loadSettings() {
    // Fetch all settings from the backend
    fetch('../../api/admin/get-settings.php', {
        headers: {
            'Authorization': 'Bearer ' + (localStorage.getItem('apiToken') || '')
        }
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            populateSettings(result.settings);
        } else {
            console.error('Failed to load settings:', result.message);
        }
    })
    .catch(error => console.error('Error loading settings:', error));
}

function populateSettings(settings) {
    // General
    if (settings.general) {
        document.getElementById('systemName').value = settings.general.system_name || 'Online Examination System';
        document.getElementById('institutionName').value = settings.general.institution_name || 'Ethio-Italy Polytechnic College';
        document.getElementById('academicYear').value = settings.general.academic_year || '2018 E.C.';
        document.getElementById('semester').value = settings.general.semester || 'First Semester';
        document.getElementById('defaultLanguage').value = settings.general.default_language || 'English';
    }

    // Security
    if (settings.security) {
        document.getElementById('passwordPolicy').value = settings.security.password_policy || 'Strong (8+ chars, special chars, numbers)';
        document.getElementById('sessionTimeout').value = settings.security.session_timeout || 30;
        document.getElementById('twoFactor').checked = settings.security.two_factor || false;
        document.getElementById('forceHttps').checked = settings.security.force_https || false;
        document.getElementById('loginLimit').checked = settings.security.login_limit || false;
    }

    // Email
    if (settings.email) {
        document.getElementById('smtpServer').value = settings.email.smtp_server || 'smtp.gmail.com';
        document.getElementById('smtpPort').value = settings.email.smtp_port || '587';
        document.getElementById('smtpEncryption').value = settings.email.smtp_encryption || 'TLS';
        document.getElementById('emailAddress').value = settings.email.email_address || 'noreply@oes.edu.et';
        // Password field is left blank for security – user must re-enter
        document.getElementById('emailPassword').value = '';
    }

    // Exam Defaults
    if (settings.exam) {
        document.getElementById('defaultDuration').value = settings.exam.default_duration || 120;
        document.getElementById('defaultPassingScore').value = settings.exam.default_passing_score || 50;
        document.getElementById('autoSubmit').checked = settings.exam.auto_submit || false;
        document.getElementById('showTimer').checked = settings.exam.show_timer || false;
        document.getElementById('reviewAnswers').checked = settings.exam.review_answers || false;
    }

    // Backup
    if (settings.backup) {
        document.getElementById('backupFrequency').value = settings.backup.frequency || 'Weekly';
        document.getElementById('backupLocation').value = settings.backup.location || '/backups/oes/';
        if (settings.backup.last_backup) {
            document.getElementById('lastBackupInfo').innerHTML = `<i class="fas fa-database" style="color: #1e3c72; margin-right: 10px;"></i> Last backup: ${settings.backup.last_backup}`;
        }
    }

    // Maintenance / System Health
    if (settings.maintenance) {
        document.getElementById('dbStatus').textContent = settings.maintenance.db_status || 'Healthy';
        document.getElementById('storageUsed').textContent = settings.maintenance.storage_used || '4.2 GB / 20 GB';
        document.getElementById('activeUsers').textContent = settings.maintenance.active_users || '86';
        if (settings.maintenance.last_check) {
            document.getElementById('systemHealthInfo').innerHTML = `<i class="fas fa-info-circle" style="color: #1e3c72;"></i> System is running normally. Last check: ${settings.maintenance.last_check}`;
        }
    }
}

// Save functions – each sends a POST to the appropriate endpoint
function saveGeneralSettings() {
    const data = {
        system_name: document.getElementById('systemName').value,
        institution_name: document.getElementById('institutionName').value,
        academic_year: document.getElementById('academicYear').value,
        semester: document.getElementById('semester').value,
        default_language: document.getElementById('defaultLanguage').value
    };
    saveSettings('general', data);
}

function saveSecuritySettings() {
    const data = {
        password_policy: document.getElementById('passwordPolicy').value,
        session_timeout: parseInt(document.getElementById('sessionTimeout').value),
        two_factor: document.getElementById('twoFactor').checked,
        force_https: document.getElementById('forceHttps').checked,
        login_limit: document.getElementById('loginLimit').checked
    };
    saveSettings('security', data);
}

function saveEmailSettings() {
    const data = {
        smtp_server: document.getElementById('smtpServer').value,
        smtp_port: document.getElementById('smtpPort').value,
        smtp_encryption: document.getElementById('smtpEncryption').value,
        email_address: document.getElementById('emailAddress').value,
        email_password: document.getElementById('emailPassword').value // empty if unchanged
    };
    saveSettings('email', data);
}

function saveExamDefaults() {
    const data = {
        default_duration: parseInt(document.getElementById('defaultDuration').value),
        default_passing_score: parseInt(document.getElementById('defaultPassingScore').value),
        auto_submit: document.getElementById('autoSubmit').checked,
        show_timer: document.getElementById('showTimer').checked,
        review_answers: document.getElementById('reviewAnswers').checked
    };
    saveSettings('exam', data);
}

function saveBackupSettings() {
    const data = {
        frequency: document.getElementById('backupFrequency').value,
        location: document.getElementById('backupLocation').value
    };
    saveSettings('backup', data);
}

function saveSettings(section, data) {
    const btn = event.target;
    const originalText = btn.textContent;
    btn.textContent = 'Saving...';
    btn.disabled = true;

    fetch('../../api/admin/update-settings.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + (localStorage.getItem('apiToken') || '')
        },
        body: JSON.stringify({ section: section, settings: data })
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('✅ Settings saved successfully!');
        } else {
            alert('❌ Error: ' + result.message);
        }
    })
    .catch(error => {
        console.error('Error saving settings:', error);
        alert('❌ Failed to save settings.');
    })
    .finally(() => {
        btn.textContent = originalText;
        btn.disabled = false;
    });
}

function backupNow() {
    if (!confirm('Create a backup now?')) return;
    fetch('../../api/admin/backup-now.php', {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + (localStorage.getItem('apiToken') || '')
        }
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('✅ Backup created successfully!');
            // Update last backup info
            document.getElementById('lastBackupInfo').innerHTML = `<i class="fas fa-database" style="color: #1e3c72; margin-right: 10px;"></i> Last backup: ${result.backup_time}`;
        } else {
            alert('❌ Error: ' + result.message);
        }
    })
    .catch(error => {
        console.error('Error creating backup:', error);
        alert('❌ Failed to create backup.');
    });
}

function clearLogs() {
    if (!confirm('Are you sure you want to clear all logs? This action cannot be undone.')) return;
    fetch('../../api/admin/clear-logs.php', {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + (localStorage.getItem('apiToken') || '')
        }
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('✅ Logs cleared successfully!');
        } else {
            alert('❌ Error: ' + result.message);
        }
    })
    .catch(error => {
        console.error('Error clearing logs:', error);
        alert('❌ Failed to clear logs.');
    });
}

function resetSystem() {
    if (!confirm('⚠️ WARNING: This will reset the entire system and delete all data! Are you absolutely sure?')) return;
    if (!confirm('Type "RESET" to confirm:')) return;
    fetch('../../api/admin/reset-system.php', {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + (localStorage.getItem('apiToken') || '')
        }
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('✅ System has been reset. You will be logged out.');
            logout();
        } else {
            alert('❌ Error: ' + result.message);
        }
    })
    .catch(error => {
        console.error('Error resetting system:', error);
        alert('❌ Failed to reset system.');
    });
}