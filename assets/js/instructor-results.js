// instructor-results.js

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
    document.getElementById('loadResultsBtn').addEventListener('click', loadResults);
    document.getElementById('exportBtn').addEventListener('click', exportResults);
    document.getElementById('logoutBtn').addEventListener('click', (e) => { e.preventDefault(); if(confirm('Logout?')) logout(); });
}

let exams = [];
let results = [];
let sections = [];

function loadExams() {
    fetch('../../api/instructor/get-exams.php', { credentials: 'include' })
        .then(res => res.json())
        .then(result => {
            if (result.success) {
                exams = result.exams;
                populateExamDropdown();
            } else {
                console.error('Failed to load exams');
            }
        })
        .catch(err => console.error(err));
}

function populateExamDropdown() {
    const select = document.getElementById('examSelect');
    select.innerHTML = '<option value="">Select an exam</option>';
    exams.forEach(ex => {
        select.innerHTML += `<option value="${ex.id}">${ex.exam_title}</option>`;
    });
}

function loadResults() {
    const examId = document.getElementById('examSelect').value;
    if (!examId) {
        alert('Please select an exam');
        return;
    }

    fetch(`../../api/instructor/get-exam-results.php?exam_id=${examId}`, { credentials: 'include' })
        .then(res => res.json())
        .then(result => {
            if (result.success) {
                results = result.results;
                extractSections();
                populateSectionFilter();
                displayOverview();
                renderTable();
                document.getElementById('overviewCards').style.display = 'grid';
                document.getElementById('resultsContainer').style.display = 'block';
            } else {
                alert('No results found for this exam');
                document.getElementById('overviewCards').style.display = 'none';
                document.getElementById('resultsContainer').style.display = 'none';
            }
        })
        .catch(err => {
            console.error(err);
            alert('Error loading results');
        });
}

function extractSections() {
    const secSet = new Set();
    results.forEach(r => secSet.add(r.section || 'A'));
    sections = Array.from(secSet).sort();
}

function populateSectionFilter() {
    const select = document.getElementById('sectionFilter');
    select.innerHTML = '<option value="all">All Sections</option>';
    sections.forEach(s => {
        select.innerHTML += `<option value="${s}">Section ${s}</option>`;
    });
    select.addEventListener('change', filterResults);
}

function filterResults() {
    const section = document.getElementById('sectionFilter').value;
    let filtered = results;
    if (section !== 'all') {
        filtered = results.filter(r => (r.section || 'A') == section);
    }
    displayOverview(filtered);
    renderTable(filtered);
}

function displayOverview(filteredResults = results) {
    const total = filteredResults.length;
    const completed = filteredResults.filter(r => r.status === 'graded').length;
    const passed = filteredResults.filter(r => r.passed).length;
    const avg = filteredResults.reduce((sum, r) => sum + (parseFloat(r.percentage) || 0), 0) / total || 0;
    const highest = filteredResults.reduce((max, r) => Math.max(max, parseFloat(r.percentage) || 0), 0);
    const highestStudent = filteredResults.find(r => parseFloat(r.percentage) == highest) || { first_name: '-', last_name: '' };

    document.getElementById('totalStudents').textContent = total;
    document.getElementById('completedCount').textContent = completed + ' completed';
    document.getElementById('avgScore').textContent = Math.round(avg) + '%';
    document.getElementById('passRate').textContent = total ? Math.round((passed / total) * 100) + '%' : '0%';
    document.getElementById('passedCount').textContent = passed + ' students passed';
    document.getElementById('highestScore').textContent = Math.round(highest) + '%';
    document.getElementById('highestStudent').textContent = highestStudent.first_name + ' ' + highestStudent.last_name;
}

function renderTable(filteredResults = results) {
    const tbody = document.getElementById('resultsBody');
    if (filteredResults.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;">No results for this section.</td></tr>';
        return;
    }
    let html = '';
    filteredResults.forEach(r => {
        const scoreClass = r.percentage >= 70 ? 'high' : (r.percentage >= 50 ? 'medium' : 'low');
        const statusText = r.passed ? 'Passed' : 'Failed';
        const statusClass = r.passed ? 'passed' : 'failed';
        const initials = (r.first_name?.[0] || '') + (r.last_name?.[0] || '');
        html += `<tr>
            <td><div class="student-info"><div class="student-avatar">${initials}</div>${r.first_name} ${r.last_name}</div></td>
            <td>${r.user_id || ''}</td>
            <td>${r.section || 'A'}</td>
            <td><span class="score ${scoreClass}">${r.total_score}/${r.max_score || 100}</span></td>
            <td><span class="score ${scoreClass}">${r.percentage}%</span></td>
            <td><span class="status-badge ${statusClass}">${statusText}</span></td>
            <td><button class="btn-view" onclick="viewDetails(${r.attempt_id})">View</button></td>
        </tr>`;
    });
    tbody.innerHTML = html;
}

function exportResults() {
    const examId = document.getElementById('examSelect').value;
    if (!examId) return alert('Select an exam first');
    window.location.href = `../../api/instructor/export-results.php?exam_id=${examId}`;
}

function viewDetails(attemptId) {
    // Could open a modal with detailed answers (optional)
    alert('Detailed view for attempt ' + attemptId);
}