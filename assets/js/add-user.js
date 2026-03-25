// add-user.js – Admin Add User Page Logic

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

    // Role selection logic
    initRoleSelection();

    // Form submission
    document.getElementById('addUserForm').addEventListener('submit', handleFormSubmit);

    // Logout handler
    document.getElementById('logoutBtn').addEventListener('click', function(e) {
        e.preventDefault();
        if (confirm('Are you sure you want to logout?')) {
            logout(); // from auth-check.js
        }
    });

    // Language toggle (optional)
    initLanguageToggle();
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

function initRoleSelection() {
    const roleOptions = document.querySelectorAll('.role-option');
    roleOptions.forEach(option => {
        option.addEventListener('click', function() {
            roleOptions.forEach(opt => opt.classList.remove('selected'));
            this.classList.add('selected');
        });
    });
}

function handleFormSubmit(e) {
    e.preventDefault();

    // Get selected role
    const selectedRoleElem = document.querySelector('.role-option.selected');
    const role = selectedRoleElem ? selectedRoleElem.dataset.role : 'student';

    // Collect form data
    const formData = {
        first_name: document.getElementById('firstName').value.trim(),
        last_name: document.getElementById('lastName').value.trim(),
        email: document.getElementById('email').value.trim(),
        phone: document.getElementById('phone').value.trim(),
        role: role,
        department: document.getElementById('department').value,
        password: document.getElementById('password').value,
        status: document.getElementById('status').value,
        user_id: document.getElementById('userId').value.trim()
    };

    // Basic validation
    if (!formData.first_name || !formData.last_name || !formData.email) {
        alert('Please fill in all required fields (First Name, Last Name, Email).');
        return;
    }

    // Show loading state
    const submitBtn = e.target.querySelector('.btn-save');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = 'Creating...';
    submitBtn.disabled = true;

    // Send to API
    fetch('../../api/admin/create-user.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert(`✅ ${role.charAt(0).toUpperCase() + role.slice(1)} created successfully!`);
            window.location.href = 'manage-users.html';
        } else {
            alert('❌ Error: ' + result.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Failed to create user. Please try again.');
    })
    .finally(() => {
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    });
}

function initLanguageToggle() {
    const langBtns = document.querySelectorAll('.lang-btn');
    langBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            langBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
        });
    });
}