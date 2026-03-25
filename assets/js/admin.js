// admin.js – Admin Dashboard Logic

document.addEventListener('DOMContentLoaded', function() {
    // Get authenticated user
    const currentUser = checkAuth(); // from auth-check.js
    if (!currentUser || currentUser.role !== 'admin') {
        window.location.href = '../auth/login.html';
        return;
    }

    // Update profile info
    updateProfileInfo(currentUser);

    // Set current date in Ethiopian format (simulated)
    setCurrentDate();

    // Load stats from API
    loadSystemStats();

    // Load recent users
    loadRecentUsers();

    // Logout handler
    document.getElementById('logoutBtn').addEventListener('click', function(e) {
        e.preventDefault();
        logout(); // from auth-check.js
    });
});
function updateProfileInfo(user) {
    const initials = (user.name || 'Admin').split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
    document.getElementById('adminInitials').textContent = initials;
    document.getElementById('adminName').textContent = user.name || 'Admin User';
    document.getElementById('adminEmail').textContent = user.email || 'admin@oes.edu.et';
}

function setCurrentDate() {
    // In a real app, fetch from server or compute Ethiopian date.
    // For now, just show a placeholder.
    const now = new Date();
    const formatted = now.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
    document.getElementById('currentDate').innerHTML = `<i class="fas fa-calendar"></i> ${formatted} (approx.)`;
    document.getElementById('footerDate').textContent = formatted;
}

function loadSystemStats() {
    fetch('../../api/admin/get-system-stats.php', {
        headers: {
            'Authorization': 'Bearer ' + (localStorage.getItem('apiToken') || '')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const stats = data.stats;
            document.getElementById('totalUsers').textContent = stats.total_users || 0;
            document.getElementById('totalStudents').textContent = stats.total_students || 0;
            document.getElementById('totalInstructors').textContent = stats.total_instructors || 0;
            document.getElementById('totalExams').textContent = stats.total_exams || 0;

            // Trends (example – you may get these from backend)
            document.getElementById('totalUsersTrend').textContent = `↑ ${stats.user_growth || 0}% this month`;
            document.getElementById('studentsTrend').textContent = `↑ ${stats.student_growth || 0}% this month`;
            document.getElementById('instructorsTrend').textContent = `↑ ${stats.instructor_new || 0} new this month`;
            document.getElementById('activeExams').textContent = `${stats.active_exams || 0} active now`;

            // Build charts
            buildUserDistributionChart(stats);
            buildExamActivityChart(stats.exam_activity || []);
        } else {
            console.error('Failed to load stats:', data.message);
        }
    })
    .catch(error => {
        console.error('Error loading stats:', error);
    });
}

function buildUserDistributionChart(stats) {
    const container = document.getElementById('userDistributionChart');
    container.innerHTML = ''; // clear placeholders

    const data = [
        { label: 'Students', count: stats.total_students || 0, color: '#1e3c72' },
        { label: 'Instructors', count: stats.total_instructors || 0, color: '#f9a826' },
        { label: 'Admins', count: stats.total_admins || 0, color: '#10b981' }
    ];

    const maxCount = Math.max(...data.map(d => d.count), 1);
    data.forEach(item => {
        const barHeight = (item.count / maxCount) * 150; // max height 150px
        const barContainer = document.createElement('div');
        barContainer.className = 'chart-bar-container';
        barContainer.innerHTML = `
            <div class="chart-bar" style="height: ${barHeight}px; background: ${item.color};"></div>
            <div class="chart-label">${item.label}</div>
            <div class="chart-value">${item.count}</div>
        `;
        container.appendChild(barContainer);
    });
}
 verifyAuth().then(user => {
        if (!user || user.role !== 'admin') {
            // Not authenticated or wrong role → redirect to login
            window.location.href = '../auth/login.html';
        } else {
            // User is authenticated – now load dashboard data
            loadDashboardData(); // your existing function
        }
    });

    // Also attach logout handler
    document.getElementById('logoutBtn').addEventListener('click', function(e) {
        e.preventDefault();
        logout();
    });

function buildExamActivityChart(activityData) {
    const container = document.getElementById('examActivityChart');
    container.innerHTML = ''; // clear placeholders

    // If no data, create dummy data for last 4 weeks
    const weeks = activityData.length ? activityData : [100, 150, 180, 120];
    const max = Math.max(...weeks, 1);
    weeks.forEach((value, index) => {
        const barHeight = (value / max) * 150;
        const barContainer = document.createElement('div');
        barContainer.className = 'chart-bar-container';
        barContainer.innerHTML = `
            <div class="chart-bar" style="height: ${barHeight}px; background: #1e3c72;"></div>
            <div class="chart-label">Week ${index + 1}</div>
            <div class="chart-value">${value}</div>
        `;
        container.appendChild(barContainer);
    });
}

function loadRecentUsers() {
    fetch('../../api/admin/get-recent-users.php', {
        headers: {
            'Authorization': 'Bearer ' + (localStorage.getItem('apiToken') || '')
        }
    })
    .then(response => response.json())
    .then(data => {
        const container = document.getElementById('recentUsersContainer');
        if (data.success && data.users.length > 0) {
            // Keep header, remove previous items
            const header = container.querySelector('.chart-header');
            container.innerHTML = '';
            container.appendChild(header);

            data.users.forEach(user => {
                const item = createUserItem(user);
                container.appendChild(item);
            });
        } else {
            // Show placeholder
            container.innerHTML += '<p class="text-muted">No recent users</p>';
        }
    })
    .catch(error => console.error('Error loading recent users:', error));
}

function createUserItem(user) {
    const div = document.createElement('div');
    div.className = 'recent-item';

    const initials = (user.first_name?.[0] || '') + (user.last_name?.[0] || '') || 'U';
    const name = `${user.first_name || ''} ${user.last_name || ''}`.trim() || 'Unknown';
    const role = user.role || 'User';
    const dept = user.department || 'N/A';
    const timeAgo = user.time_ago || 'recently';

    div.innerHTML = `
        <div class="user-info">
            <div class="user-avatar">${initials}</div>
            <div>
                <strong>${name}</strong>
                <p style="color: #64748b; font-size: 0.85rem;">${role} · ${dept}</p>
            </div>
        </div>
        <span style="color: #64748b;">${timeAgo}</span>
    `;
    return div;
}