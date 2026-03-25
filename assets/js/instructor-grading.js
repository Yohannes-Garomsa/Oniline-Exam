// instructor-grading.js

document.addEventListener('DOMContentLoaded', function() {
    const currentUser = checkAuth();
    if (!currentUser || currentUser.role !== 'instructor') {
        window.location.href = '../auth/login.html';
        return;
    }
    updateProfileInfo(currentUser);
    loadPendingExams();
    setupEventListeners();
});

function updateProfileInfo(user) { /* same as before */ }
function setFooterDate() { /* same */ }

let currentExamId = null;
let attempts = [];

function loadPendingExams() {
    fetch('../../api/instructor/get-pending-grading.php', { credentials: 'include' })
        .then(res => res.json())
        .then(result => {
            const container = document.getElementById('pendingExams');
            if (result.success && result.exams.length) {
                let html = '<h3>Exams awaiting grading</h3>';
                result.exams.forEach(ex => {
                    html += `<div class="exam-item">
                        <div><strong>${ex.exam_title}</strong> (${ex.pending_count} pending)</div>
                        <button class="btn-view" onclick="startGrading(${ex.exam_id})">Grade</button>
                    </div>`;
                });
                container.innerHTML = html;
            } else {
                container.innerHTML = '<p>No exams pending grading.</p>';
            }
        });
}

function startGrading(examId) {
    currentExamId = examId;
    document.getElementById('gradingArea').style.display = 'block';
    fetch(`../../api/instructor/get-attempts-for-grading.php?exam_id=${examId}`, { credentials: 'include' })
        .then(res => res.json())
        .then(result => {
            if (result.success) {
                attempts = result.attempts;
                renderGradingTable();
            }
        });
}

function renderGradingTable() {
    let html = '';
    attempts.forEach(att => {
        html += `<tr>
            <td>${att.student_name}</td>
            <td><button onclick="viewAnswers(${att.id})">View Answers</button></td>
            <td><input type="number" class="grade-input" data-attempt="${att.id}" value="${att.total_score || 0}" min="0" max="${att.max_score}"></td>
            <td><span class="status-badge">Pending</span></td>
        </tr>`;
    });
    document.getElementById('gradeBody').innerHTML = html;
}

function submitGrades() {
    const grades = [];
    document.querySelectorAll('.grade-input').forEach(input => {
        grades.push({
            attempt_id: input.dataset.attempt,
            score: input.value
        });
    });
    fetch('../../api/instructor/submit-grades.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ exam_id: currentExamId, grades })
    })
    .then(res => res.json())
    .then(result => {
        if (result.success) {
            alert('Grades submitted');
            location.reload();
        }
    });
}