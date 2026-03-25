// reports.js – Admin Reports Page

document.addEventListener('DOMContentLoaded', function() {
    const currentUser = checkAuth();
    if (!currentUser || currentUser.role !== 'admin') {
        window.location.href = '../auth/login.html';
        return;
    }
    updateProfileInfo(currentUser);
    setFooterDate();
    setDefaultDates();
    loadAllReports();
    setupEventListeners();
});

// ========== Helper Functions ==========
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

function setDefaultDates() {
    const today = new Date();
    const thirtyDaysAgo = new Date();
    thirtyDaysAgo.setDate(today.getDate() - 30);
    document.getElementById('startDate').value = thirtyDaysAgo.toISOString().split('T')[0];
    document.getElementById('endDate').value = today.toISOString().split('T')[0];
}

// ========== Event Listeners ==========
function setupEventListeners() {
    // Tab switching
    document.querySelectorAll('.tab').forEach(tab => {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            document.querySelectorAll('.report-section').forEach(s => s.classList.remove('active'));
            document.getElementById(this.dataset.report + '-section').classList.add('active');
        });
    });

    // Apply date filter
    document.getElementById('applyDateBtn').addEventListener('click', function() {
        const start = document.getElementById('startDate').value;
        const end = document.getElementById('endDate').value;
        if (start && end) {
            loadAllReports(start, end);
        } else {
            alert('Please select both start and end dates.');
        }
    });

    // Export report (placeholder)
    document.getElementById('exportReportBtn').addEventListener('click', function() {
        alert('Export functionality will be implemented here.');
    });

    // Language toggle (optional)
    document.querySelectorAll('.lang-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.lang-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
        });
    });

    // Logout
    document.getElementById('logoutBtn').addEventListener('click', (e) => {
        e.preventDefault();
        if (confirm('Are you sure you want to logout?')) {
            logout();
        }
    });
}

// ========== Data Loading ==========
function loadAllReports(startDate, endDate) {
    let url = '../../api/admin/get-system-stats.php';
    if (startDate && endDate) {
        url += `?start=${startDate}&end=${endDate}`;
    }

    // Load system stats (exam stats, distribution)
    fetch(url, { credentials: 'include' })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                populateExamStats(data.stats);
                populateDeptExamChart(data.stats.departments || []);
                populateStatusDistribution(data.stats.exam_status || {});
            } else {
                console.error('Failed to load stats:', data.message);
            }
        })
        .catch(err => console.error('Error loading stats:', err));

    // Load top students
    fetch('../../api/admin/get-top-students.php', { credentials: 'include' })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                populateTopStudents(data.students);
            }
        })
        .catch(err => console.error('Error loading top students:', err));

    // Load department stats
    fetch('../../api/admin/get-department-stats.php', { credentials: 'include' })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                populateDeptStats(data.departments);
                populateInstructorDist(data.instructors);
            }
        })
        .catch(err => console.error('Error loading department stats:', err));

    // Load trends
    fetch('../../api/admin/get-trends.php', { credentials: 'include' })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                populateMonthlyTrends(data.monthly);
                populateKeyTrends(data.keyTrends);
            }
        })
        .catch(err => console.error('Error loading trends:', err));
}

// ========== Populate Functions ==========
function populateExamStats(stats) {
    document.getElementById('totalExams').textContent = stats.total_exams || 0;
    document.getElementById('activeExams').textContent = stats.active_exams || 0;
    document.getElementById('completedExams').textContent = stats.completed_exams || 0;
    document.getElementById('pendingExams').textContent = stats.pending_exams || 0;

    if (stats.exam_trend) {
        document.getElementById('examTrend').textContent = stats.exam_trend;
        document.getElementById('activeTrend').textContent = stats.active_trend || '';
        document.getElementById('completedTrend').textContent = stats.completed_trend || '';
        document.getElementById('pendingTrend').textContent = stats.pending_trend || '';
    }
}

function populateDeptExamChart(departments) {
    const container = document.getElementById('deptExamChart');
    container.innerHTML = '';
    if (!departments || departments.length === 0) {
        container.innerHTML = '<p style="text-align:center;">No data available</p>';
        return;
    }
    const maxExams = Math.max(...departments.map(d => d.exam_count || 0), 1);
    departments.forEach(dept => {
        const barHeight = (dept.exam_count / maxExams) * 150;
        const bar = document.createElement('div');
        bar.className = 'chart-bar-container';
        bar.innerHTML = `
            <div class="chart-bar" style="height: ${barHeight}px;"></div>
            <p><strong>${dept.name}</strong><br>${dept.exam_count}</p>
        `;
        container.appendChild(bar);
    });
}

function populateStatusDistribution(statusData) {
    const container = document.getElementById('statusDistribution');
    container.innerHTML = '';
    const total = statusData.total || 1;
    const statuses = [
        { label: 'Active', count: statusData.active || 0, color: '#10b981' },
        { label: 'Completed', count: statusData.completed || 0, color: '#1e3c72' },
        { label: 'Draft', count: statusData.draft || 0, color: '#f97316' },
        { label: 'Pending Grading', count: statusData.pending || 0, color: '#ef4444' }
    ];
    statuses.forEach(s => {
        const percent = total ? Math.round((s.count / total) * 100) : 0;
        const div = document.createElement('div');
        div.className = 'status-item';
        div.innerHTML = `
            <div class="label">
                <span>${s.label}</span>
                <span><strong>${s.count} exams</strong> (${percent}%)</span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: ${percent}%; background: ${s.color};"></div>
            </div>
        `;
        container.appendChild(div);
    });
}

function populateTopStudents(students) {
    const tbody = document.getElementById('topStudentsTable');
    tbody.innerHTML = '';
    if (!students || students.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6">No data available</td></tr>';
        return;
    }
    students.forEach((student, index) => {
        const rank = index + 1;
        let rankClass = '';
        if (rank === 1) rankClass = 'rank-1';
        else if (rank === 2) rankClass = 'rank-2';
        else if (rank === 3) rankClass = 'rank-3';

        const row = document.createElement('tr');
        row.innerHTML = `
            <td><span class="${rankClass}">${rank}</span></td>
            <td><strong>${student.name}</strong></td>
            <td>${student.id}</td>
            <td>${student.department}</td>
            <td style="color:#1e3c72; font-weight:700;">${student.avg_score}%</td>
            <td>${student.exams_taken}</td>
        `;
        tbody.appendChild(row);
    });
}

function populateDeptStats(departments) {
    const container = document.getElementById('deptStatsList');
    container.innerHTML = '';
    if (!departments || departments.length === 0) {
        container.innerHTML = '<p>No data available</p>';
        return;
    }
    departments.forEach(dept => {
        const div = document.createElement('div');
        div.innerHTML = `
            <div class="dept-stat">
                <span><strong>${dept.name}</strong></span>
                <span>${dept.students} students</span>
            </div>
            <div class="dept-progress">
                <div class="dept-progress-fill" style="width: ${dept.pass_rate}%;"></div>
            </div>
        `;
        container.appendChild(div);
    });
}

function populateInstructorDist(instructors) {
    const container = document.getElementById('instructorDistList');
    container.innerHTML = '';
    if (!instructors || instructors.length === 0) {
        container.innerHTML = '<p>No data available</p>';
        return;
    }
    instructors.forEach(dept => {
        const div = document.createElement('div');
        div.className = 'dept-stat';
        div.innerHTML = `
            <span>${dept.name}</span>
            <span>${dept.count} instructors</span>
        `;
        container.appendChild(div);
    });
    const total = instructors.reduce((sum, d) => sum + d.count, 0);
    const totalDiv = document.createElement('div');
    totalDiv.style.marginTop = '30px';
    totalDiv.innerHTML = `<h4>Total: ${total} Instructors</h4>`;
    container.appendChild(totalDiv);
}

function populateMonthlyTrends(months) {
    const container = document.getElementById('monthlyTrends');
    container.innerHTML = '';
    if (!months || months.length === 0) {
        container.innerHTML = '<p>No data available</p>';
        return;
    }
    const maxExams = Math.max(...months.map(m => m.count), 1);
    months.forEach(month => {
        const barWidth = (month.count / maxExams) * 100;
        const div = document.createElement('div');
        div.className = 'month-bar';
        div.innerHTML = `
            <span class="month-name">${month.name}</span>
            <div class="bar-container">
                <div class="bar-fill" style="width: ${barWidth}%;">${month.count} exams</div>
            </div>
        `;
        container.appendChild(div);
    });
}

function populateKeyTrends(trends) {
    const container = document.getElementById('keyTrends');
    container.innerHTML = '';
    if (!trends || trends.length === 0) {
        container.innerHTML = '<p>No data available</p>';
        return;
    }
    trends.forEach(trend => {
        const div = document.createElement('div');
        div.className = 'trend-item';
        const trendClass = trend.change > 0 ? 'trend-up' : 'trend-down';
        const arrow = trend.change > 0 ? '↑' : '↓';
        div.innerHTML = `
            <span>${trend.label}</span>
            <span class="${trendClass}"><i class="fas fa-arrow-${trend.change > 0 ? 'up' : 'down'}"></i> ${Math.abs(trend.change)}${trend.suffix || '%'}</span>
        `;
        container.appendChild(div);
    });
}