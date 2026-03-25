document.addEventListener('DOMContentLoaded', function() {
    const currentUser = checkAuth();
    if (!currentUser || currentUser.role !== 'instructor') {
        window.location.href = '../auth/login.html';
        return;
    }
    updateProfileInfo(currentUser);
    setFooterDate();
    loadExams();
    setupEventListeners();
});

function updateProfileInfo(user) {
    const fullName = user.name || 
                    (user.first_name && user.last_name ? `${user.first_name} ${user.last_name}` : 'Instructor');
    const initials = fullName.split(' ').map(n => n[0]).join('').substring(0,2).toUpperCase();
    document.getElementById('userInitials').textContent = initials;
    document.getElementById('userName').textContent = fullName;
    document.getElementById('userEmail').textContent = user.email || '';
}

function setFooterDate() {
    document.getElementById('footerDate').textContent = new Date().toLocaleDateString();
}

function setupEventListeners() {
    document.getElementById('logoutBtn').addEventListener('click', (e) => { e.preventDefault(); if(confirm('Logout?')) logout(); });
    document.getElementById('loadReportBtn').addEventListener('click', loadReport);
}

let exams = [];

function loadExams() {
    fetch('../../api/instructor/get-exams.php', { credentials: 'include' })
        .then(res => res.json())
        .then(result => {
            if(result.success) {
                exams = result.exams;
                const select = document.getElementById('examSelect');
                select.innerHTML = '<option value="">-- Choose an exam --</option>';
                exams.forEach(e => {
                    select.innerHTML += `<option value="${e.id}">${e.exam_title}</option>`;
                });
            }
        })
        .catch(console.error);
}

function loadReport() {
    const examId = document.getElementById('examSelect').value;
    if(!examId) { alert('Please select an exam'); return; }

    // Load results
    fetch(`../../api/instructor/get-exam-results.php?exam_id=${examId}`, { credentials: 'include' })
        .then(res => res.json())
        .then(result => {
            if(result.success) {
                renderResults(result.results);
            } else {
                alert('Failed to load results');
            }
        })
        .catch(err => console.error(err));

    // Load question performance
    fetch(`../../api/instructor/get-question-performance.php?exam_id=${examId}`, { credentials: 'include' })
        .then(res => res.json())
        .then(result => {
            if(result.success) {
                renderPerformance(result.performance);
            } else {
                document.getElementById('questionPerformance').innerHTML = '<p>No performance data.</p>';
            }
        })
        .catch(err => console.error(err));

    document.getElementById('reportContent').style.display = 'block';
}

function renderResults(results) {
    const tbody = document.getElementById('resultsBody');
    if(!results || results.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5">No attempts yet.</td></tr>';
        return;
    }
    let html = '';
    results.forEach(r => {
        const passed = r.passed ? 'Yes' : 'No';
        html += `<tr>
            <td>${r.first_name} ${r.last_name}</td>
            <td>${r.user_id || ''}</td>
            <td>${r.total_score}</td>
            <td>${r.percentage}%</td>
            <td>${passed}</td>
        </tr>`;
    });
    tbody.innerHTML = html;
}

function renderPerformance(perf) {
    const container = document.getElementById('questionPerformance');
    if(!perf || perf.length === 0) {
        container.innerHTML = '<p>No performance data.</p>';
        return;
    }
    let html = '';
    perf.forEach(p => {
        const correctPercent = p.times_answered > 0 ? Math.round((p.correct_count / p.times_answered) * 100) : 0;
        html += `<div class="performance-card">
            <strong>${p.question_text}</strong><br>
            Times answered: ${p.times_answered} | Correct: ${p.correct_count} (${correctPercent}%) | Avg marks: ${p.avg_marks}
        </div>`;
    });
    container.innerHTML = html;
}