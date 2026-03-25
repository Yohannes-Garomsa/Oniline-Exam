// student-profile.js

document.addEventListener('DOMContentLoaded', function() {
    const currentUser = checkAuth();
    if (!currentUser || currentUser.role !== 'student') {
        window.location.href = '../auth/login.html';
        return;
    }
    updateProfileInfo(currentUser);
    setFooterDate();
    loadProfileData();
    setupEventListeners();
});

function updateProfileInfo(user) {
    const fullName = user.name || (user.first_name && user.last_name ? `${user.first_name} ${user.last_name}` : 'Student');
    const initials = fullName.split(' ').map(n => n[0]).join('').substring(0,2).toUpperCase();
    document.getElementById('userInitials').textContent = initials;
    document.getElementById('userName').textContent = fullName;
    document.getElementById('userId').textContent = `ID: ${user.user_id || ''}`;
    document.getElementById('userDept').textContent = user.department || '';
    document.getElementById('profileInitials').textContent = initials;
    document.getElementById('profileName').textContent = fullName;
    document.getElementById('profileEmail').textContent = user.email || '';
}

function setFooterDate() {
    document.getElementById('footerDate').textContent = new Date().toLocaleDateString();
}

function setupEventListeners() {
    document.getElementById('logoutBtn').addEventListener('click', (e) => {
        e.preventDefault();
        if (confirm('Logout?')) logout();
    });
    document.getElementById('profileForm').addEventListener('submit', updateProfile);
}

function loadProfileData() {
    fetch('../../api/student/get-profile.php', { credentials: 'include' })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('firstName').value = data.user.first_name || '';
                document.getElementById('lastName').value = data.user.last_name || '';
                document.getElementById('email').value = data.user.email || '';
                document.getElementById('phone').value = data.user.phone || '';
                document.getElementById('department').value = data.user.department || '';
                document.getElementById('year').value = data.student.year_of_study || '';
                document.getElementById('section').value = data.student.section || '';
                document.getElementById('statGPA').textContent = data.student.gpa || '0.0';
                document.getElementById('statExams').textContent = data.stats.exams_taken || 0;
                document.getElementById('statAvg').textContent = (data.stats.avg_score || 0) + '%';
            }
        })
        .catch(err => console.error(err));
}

function updateProfile(e) {
    e.preventDefault();
    const data = {
        first_name: document.getElementById('firstName').value,
        last_name: document.getElementById('lastName').value,
        phone: document.getElementById('phone').value
    };
    fetch('../../api/student/update-profile.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(result => {
        if (result.success) {
            alert('Profile updated successfully');
            // Update displayed name in sidebar
            const newName = data.first_name + ' ' + data.last_name;
            document.getElementById('userName').textContent = newName;
            document.getElementById('profileName').textContent = newName;
            const initials = (data.first_name[0] + data.last_name[0]).toUpperCase();
            document.getElementById('userInitials').textContent = initials;
            document.getElementById('profileInitials').textContent = initials;
        } else {
            alert('Error: ' + result.message);
        }
    })
    .catch(() => alert('Request failed'));
}