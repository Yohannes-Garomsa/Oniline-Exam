document.addEventListener('DOMContentLoaded', function() {
    const currentUser = checkAuth();
    if (!currentUser || currentUser.role !== 'instructor') {
        window.location.href = '../auth/login.html';
        return;
    }
    updateProfileInfo(currentUser);
    const urlParams = new URLSearchParams(window.location.search);
    const attemptId = urlParams.get('attempt_id');
    if (!attemptId) {
        alert('No attempt specified');
        window.location.href = 'reports.html';
        return;
    }
    loadAttempt(attemptId);
    setupEventListeners();
});

function updateProfileInfo(user) { /* same as before */ }

function setupEventListeners() {
    document.getElementById('logoutBtn').addEventListener('click', (e) => { e.preventDefault(); logout(); });
}

function loadAttempt(attemptId) {
    fetch(`../../api/instructor/get-attempt-details.php?attempt_id=${attemptId}`, { credentials: 'include' })
        .then(res => res.json())
        .then(result => {
            if (result.success) {
                renderAttempt(result.attempt, result.answers);
            } else {
                alert('Error loading attempt');
            }
        })
        .catch(err => console.error(err));
}

function renderAttempt(attempt, answers) {
    const attemptInfo = document.getElementById('attemptInfo');
    attemptInfo.innerHTML = `
        <h3>${attempt.exam_title}</h3>
        <p><strong>Student:</strong> ${attempt.first_name} ${attempt.last_name} (${attempt.user_id})</p>
        <p><strong>Email:</strong> ${attempt.email}</p>
        <p><strong>Started:</strong> ${new Date(attempt.start_time).toLocaleString()}</p>
        <p><strong>Status:</strong> ${attempt.status}</p>
        <p><strong>Current Score:</strong> ${attempt.total_score} / ${attempt.exam_total}</p>
    `;

    const container = document.getElementById('questionsList');
    container.innerHTML = '';
    let allAutoGraded = true;
    answers.forEach(ans => {
        const card = document.createElement('div');
        card.className = 'question-card';
        card.dataset.answerId = ans.id;

        let answerHtml = '';
        if (ans.question_type === 'MCQ' || ans.question_type === 'True/False') {
            // Auto-graded
            answerHtml = `
                <div class="student-answer">
                    <strong>Selected:</strong> ${ans.selected_option} - ${ans.selected_option_text || ''}
                </div>
                <div class="auto-grade">Auto-graded: ${ans.marks_obtained} marks</div>
            `;
        } else {
            allAutoGraded = false;
            // Manual grading needed
            answerHtml = `
                <div class="student-answer">
                    <strong>Answer:</strong> ${ans.answer_text || '(No text answer)'}
                </div>
                <div class="grade-input">
                    <input type="number" class="grade-marks" value="${ans.marks_obtained || ''}" placeholder="Marks" step="0.5" min="0">
                    <textarea class="grade-feedback" placeholder="Feedback">${ans.feedback || ''}</textarea>
                    <button class="btn-save-grade save-one">Save</button>
                </div>
            `;
        }

        card.innerHTML = `
            <div class="question-text">${ans.question_text} (${ans.question_type})</div>
            ${answerHtml}
        `;
        container.appendChild(card);

        // Attach save event for manual grading if not auto
        if (ans.question_type !== 'MCQ' && ans.question_type !== 'True/False') {
            const saveBtn = card.querySelector('.save-one');
            saveBtn.addEventListener('click', function() {
                const marks = card.querySelector('.grade-marks').value;
                const feedback = card.querySelector('.grade-feedback').value;
                saveGrade(ans.id, marks, feedback);
            });
        }
    });

    if (allAutoGraded) {
        document.getElementById('saveAllGrades').style.display = 'none';
        const msg = document.createElement('p');
        msg.textContent = 'All questions are auto-graded.';
        container.appendChild(msg);
    } else {
        document.getElementById('saveAllGrades').style.display = 'block';
        document.getElementById('saveAllGrades').addEventListener('click', function() {
            // Save all manually graded questions
            document.querySelectorAll('.question-card').forEach(card => {
                const answerId = card.dataset.answerId;
                const marks = card.querySelector('.grade-marks')?.value;
                const feedback = card.querySelector('.grade-feedback')?.value;
                if (marks !== undefined) {
                    saveGrade(answerId, marks, feedback);
                }
            });
        });
    }
}

function saveGrade(answerId, marks, feedback) {
    fetch('../../api/instructor/save-grade.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ answer_id: answerId, marks_obtained: marks, feedback: feedback })
    })
    .then(res => res.json())
    .then(result => {
        if (result.success) {
            alert('Grade saved');
        } else {
            alert('Error: ' + result.message);
        }
    })
    .catch(() => alert('Request failed'));
}