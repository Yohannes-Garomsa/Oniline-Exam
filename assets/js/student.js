// student-dashboard.js

document.addEventListener('DOMContentLoaded', function() {
    const currentUser = checkAuth();
    if (!currentUser || currentUser.role !== 'student') {
        window.location.href = '../auth/login.html';
        return;
    }
    updateProfileInfo(currentUser);
    setFooterDate();
    loadDashboardData();
    setupEventListeners();
});

function updateProfileInfo(user) {
    const fullName = user.name || 
                    (user.first_name && user.last_name ? `${user.first_name} ${user.last_name}` : 'Student');
    const initials = fullName.split(' ').map(n => n[0]).join('').substring(0,2).toUpperCase();
    document.getElementById('userInitials').textContent = initials;
    document.getElementById('userName').textContent = fullName;
    document.getElementById('userId').textContent = `ID: ${user.user_id || ''}`;
    document.getElementById('userDept').textContent = user.department || '';
    document.getElementById('welcomeName').textContent = fullName.split(' ')[0];
}

function setFooterDate() {
    const now = new Date();
    document.getElementById('footerDate').textContent = now.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
}

function setupEventListeners() {
    document.getElementById('logoutBtn').addEventListener('click', (e) => {
        e.preventDefault();
        if (confirm('Logout?')) logout();
    });

    // Password toggle in modal
    document.querySelectorAll('.toggle-password').forEach(btn => {
        btn.addEventListener('click', function() {
            const input = this.previousElementSibling;
            const type = input.type === 'password' ? 'text' : 'password';
            input.type = type;
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });
    });

    // Modal close handlers
    document.getElementById('closeSecurityModal').addEventListener('click', closeModal);
    document.getElementById('cancelSecurityBtn').addEventListener('click', closeModal);
    document.getElementById('verifyPasswordBtn').addEventListener('click', verifyExamPassword);
    window.addEventListener('click', (e) => {
        if (e.target.classList.contains('modal')) closeModal();
    });
}

let currentExam = null;

function loadDashboardData() {
    fetch('../../api/student/get-dashboard-stats.php', { credentials: 'include' })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('gpa').textContent = data.gpa || '0.0';
                document.getElementById('examsTaken').textContent = data.examsTaken || 0;
                document.getElementById('avgScore').textContent = (data.avgScore || 0) + '%';
                document.getElementById('availableExams').textContent = data.availableExams || 0;
                document.getElementById('completedExams').textContent = data.completedExams || 0;
                document.getElementById('upcomingExams').textContent = data.upcomingExams || 0;
                document.getElementById('avgTime').textContent = (data.avgTime || 0) + 'm';
                document.getElementById('dueToday').textContent = (data.dueToday || 0) + ' due today';
                document.getElementById('nextDeadline').textContent = data.nextDeadline || 'No upcoming';
            }
        })
        .catch(err => console.error('Error loading dashboard stats:', err));

    fetch('../../api/student/get-available-exams.php', { credentials: 'include' })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                renderAvailableExams(data.exams);
            }
        })
        .catch(err => console.error('Error loading exams:', err));

    fetch('../../api/student/get-recent-results.php', { credentials: 'include' })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                renderRecentResults(data.results);
            }
        })
        .catch(err => console.error('Error loading results:', err));
}

function renderAvailableExams(exams) {
    const grid = document.getElementById('examsGrid');
    if (!exams || exams.length === 0) {
        grid.innerHTML = '<p>No available exams at the moment.</p>';
        return;
    }
    grid.innerHTML = '';
    exams.forEach(exam => {
        const card = document.createElement('div');
        card.className = `exam-card ${exam.urgency}`;
        card.innerHTML = `
            <div class="exam-header">
                <span class="exam-type">${exam.exam_type}</span>
                <span class="exam-status" style="background:${exam.status_color}">${exam.status_text}</span>
            </div>
            <h4>${exam.title}</h4>
            <div class="exam-info">
                <div class="exam-info-item"><i class="fas fa-book"></i> ${exam.course_code}</div>
                <div class="exam-info-item"><i class="fas fa-clock"></i> ${exam.duration} min</div>
                <div class="exam-info-item"><i class="fas fa-question-circle"></i> ${exam.question_count} Questions</div>
                <div class="exam-info-item"><i class="fas fa-calendar"></i> Due: ${exam.due_date}</div>
            </div>
            <button class="btn-start-exam" data-exam-id="${exam.id}" data-exam-title="${exam.title}" data-course-code="${exam.course_code}">
                <i class="fas fa-shield-alt"></i> Start Secure Exam
            </button>
        `;
        grid.appendChild(card);
    });

    // Add event listeners to all start buttons
    document.querySelectorAll('.btn-start-exam').forEach(btn => {
        btn.addEventListener('click', function() {
            const examId = this.dataset.examId;
            const examTitle = this.dataset.examTitle;
            const courseCode = this.dataset.courseCode;
            openExamSecurity(examId, examTitle, courseCode);
        });
    });
}

function renderRecentResults(results) {
    const tbody = document.getElementById('resultsBody');
    if (!results || results.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7">No results yet.</td></tr>';
        return;
    }
    let html = '';
    results.forEach(r => {
        const scoreClass = r.percentage >= 70 ? 'score-high' : (r.percentage >= 50 ? 'score-medium' : 'score-low');
        html += `<tr>
            <td>${r.exam_title}</td>
            <td>${r.course_code}</td>
            <td>${r.date_taken}</td>
            <td class="${scoreClass}">${r.score}/${r.total_marks}</td>
            <td class="${scoreClass}">${r.percentage}%</td>
            <td><span style="color:${r.passed ? '#28a745' : '#dc3545'}">${r.passed ? 'Passed' : 'Failed'}</span></td>
            <td><button class="btn-view" onclick="viewResult(${r.attempt_id})">View</button></td>
        </tr>`;
    });
    tbody.innerHTML = html;
}

function viewResult(attemptId) {
    window.location.href = `results.html?attempt=${attemptId}`;
}

function openExamSecurity(examId, examTitle, courseCode) {
    currentExam = { id: examId, title: examTitle, code: courseCode };
    document.getElementById('securityExamName').textContent = examTitle;
    document.getElementById('securityCourseCode').textContent = courseCode;
    document.getElementById('examSecurityModal').classList.add('active');
}

function closeModal() {
    document.getElementById('examSecurityModal').classList.remove('active');
    document.getElementById('examPassword').value = '';
}

function verifyExamPassword() {
    const password = document.getElementById('examPassword').value;
    if (!currentExam) return;

    fetch('../../api/student/verify-exam-password.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ exam_id: currentExam.id, password: password })
    })
    .then(res => res.json())
    .then(result => {
        if (result.success) {
            window.location.href = `take-exam.html?exam_id=${currentExam.id}`;
        } else {
            alert('Incorrect password or exam not available.');
        }
    })
    .catch(() => alert('Error verifying password'));
}