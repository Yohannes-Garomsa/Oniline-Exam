document.addEventListener('DOMContentLoaded', function() {
    const currentUser = checkAuth();
    if (!currentUser || currentUser.role !== 'instructor') {
        window.location.href = '../auth/login.html';
        return;
    }
    updateProfileInfo(currentUser);
    setFooterDate();
    loadDashboardData();
    setupEventListeners();
});

function updateProfileInfo(user) {
    // Construct full name if not directly provided
    const fullName = user.name || 
                    (user.first_name && user.last_name ? `${user.first_name} ${user.last_name}` : 'Instructor');
    const initials = fullName.split(' ').map(n => n[0]).join('').substring(0,2).toUpperCase();
    
    document.getElementById('userInitials').textContent = initials;
    document.getElementById('userName').textContent = fullName;
    document.getElementById('userEmail').textContent = user.email || 'instructor@oes.edu.et';
    document.getElementById('welcomeName').textContent = fullName.split(' ')[0];
}
function setFooterDate() {
    const now = new Date();
    const formatted = now.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
    document.getElementById('footerDate').textContent = formatted;
}

function setupEventListeners() {
    document.getElementById('logoutBtn').addEventListener('click', (e) => {
        e.preventDefault();
        if (confirm('Are you sure you want to logout?')) {
            logout(); // from auth-check.js
        }
    });
}

function loadDashboardData() {
    fetch('../../api/instructor/get-dashboard-stat.php', { credentials: 'include' })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('totalExams').textContent = data.totalExams || 0;
                document.getElementById('activeExams').textContent = data.activeExams || 0;
                document.getElementById('totalQuestions').textContent = data.totalQuestions || 0;
                document.getElementById('totalStudents').textContent = data.totalStudents || 0;
                renderRecentExams(data.recentExams);
            } else {
                console.error('Failed to load dashboard stats');
            }
        })
        .catch(err => console.error('Error loading dashboard stats:', err));
}

function renderRecentExams(exams) {
    const tbody = document.getElementById('recentExamsTable');
    if (!exams || exams.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:20px;">No recent exams.</td></tr>';
        return;
    }
    let html = '';
    exams.forEach(ex => {
        const statusClass = ex.status;
        const formattedDate = new Date(ex.created_at).toLocaleDateString();
        html += `<tr>
            <td>${ex.exam_title}</td>
            <td>${ex.course_code || ''}</td>
            <td><span class="status-badge ${statusClass}">${ex.status}</span></td>
            <td>${formattedDate}</td>
        </tr>`;
    });
    tbody.innerHTML = html;
}