// student-result-detail.js

document.addEventListener('DOMContentLoaded', function() {
    const currentUser = checkAuth();
    if (!currentUser || currentUser.role !== 'student') {
        window.location.href = '../auth/login.html';
        return;
    }
    updateProfileInfo(currentUser);
    setFooterDate();

    const urlParams = new URLSearchParams(window.location.search);
    const attemptId = urlParams.get('attempt');
    if (!attemptId) {
        alert('No attempt specified');
        window.location.href = 'results.html';
        return;
    }

    loadResultDetails(attemptId);
    setupEventListeners();
});

function updateProfileInfo(user) {
    const fullName = user.name || (user.first_name && user.last_name ? `${user.first_name} ${user.last_name}` : 'Student');
    const initials = fullName.split(' ').map(n => n[0]).join('').substring(0,2).toUpperCase();
    document.getElementById('userInitials').textContent = initials;
    document.getElementById('userName').textContent = fullName;
    document.getElementById('userId').textContent = `ID: ${user.user_id || ''}`;
    document.getElementById('userDept').textContent = user.department || '';
}

function setFooterDate() {
    document.getElementById('footerDate').textContent = new Date().toLocaleDateString();
}

function setupEventListeners() {
    document.getElementById('logoutBtn').addEventListener('click', (e) => {
        e.preventDefault();
        if (confirm('Logout?')) logout();
    });
}

function loadResultDetails(attemptId) {
    const container = document.getElementById('resultContainer');
    container.innerHTML = '<p>Loading result details...</p>';

    fetch(`../../api/student/get-result-details.php?attempt_id=${attemptId}`, { credentials: 'include' })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                renderResult(data);
            } else {
                container.innerHTML = `<p style="color:#dc3545;">Error: ${data.message || 'Unknown error'}</p>`;
            }
        })
        .catch(err => {
            console.error('Fetch error:', err);
            container.innerHTML = '<p style="color:#dc3545;">Error loading result details. Please check your connection and try again.</p>';
        });
}

function renderResult(data) {
    const exam = data.exam;
    const attempt = data.attempt;
    const questions = data.questions;

    const passedClass = attempt.passed ? 'passed' : 'failed';
    const passedText = attempt.passed ? 'Passed' : 'Failed';

    let html = `
        <div class="result-header">
            <div>
                <h2>${exam.exam_title}</h2>
                <p>${exam.course_code} · ${exam.course_name}</p>
                <p>Submitted: ${new Date(attempt.end_time).toLocaleString()}</p>
            </div>
            <div class="score-badge ${passedClass}">${attempt.percentage}%</div>
        </div>

        <div class="summary-stats">
            <div class="stat-item">
                <div class="stat-number">${attempt.total_score}</div>
                <div>Score</div>
                <small>out of ${exam.total_marks}</small>
            </div>
            <div class="stat-item">
                <div class="stat-number">${attempt.percentage}%</div>
                <div>Percentage</div>
            </div>
            <div class="stat-item">
                <div class="stat-number ${passedClass}">${passedText}</div>
                <div>Status</div>
            </div>
        </div>

        <h3>Question Review</h3>
    `;

    questions.forEach((q, index) => {
        const userAnswer = q.user_answer ? q.user_answer.letter : '–';
        const correctAnswer = q.correct_answer ? q.correct_answer.letter : '–';
        const isCorrect = q.user_answer && q.user_answer.is_correct;
        const marksObtained = q.user_answer ? q.user_answer.marks_obtained : 0;

        const answerClass = isCorrect ? 'correct' : 'incorrect';
        const icon = isCorrect ? '<i class="fas fa-check-circle" style="color:#28a745;"></i>' : '<i class="fas fa-times-circle" style="color:#dc3545;"></i>';

        html += `
            <div class="question-review">
                <div class="question-text">${index+1}. ${q.question_text}</div>
                <div class="answer-row">
                    ${icon}
                    <span>Your answer: <strong class="${answerClass}">${userAnswer}</strong></span>
                    <span> | Correct answer: <strong>${correctAnswer}</strong></span>
                    <span> | Marks: ${marksObtained}/${q.marks}</span>
                </div>
                <div style="margin-top:10px;">
                    ${q.options.map(opt => `
                        <div style="display:flex; align-items:center; gap:10px; padding:5px; ${opt.is_correct ? 'background:#d4edda; border-radius:8px;' : ''}">
                            <span class="option-letter">${opt.letter}</span>
                            <span>${opt.text}</span>
                            ${opt.is_correct ? '<span class="correct">✓ Correct</span>' : ''}
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    });

    document.getElementById('resultContainer').innerHTML = html;
}