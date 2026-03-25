// instructor-exam-questions.js

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

let currentExamId = null;
let examQuestions = [];
let availableQuestions = [];

function updateProfileInfo(user) {
    const fullName = user.name || (user.first_name && user.last_name ? `${user.first_name} ${user.last_name}` : 'Instructor');
    const initials = fullName.split(' ').map(n => n[0]).join('').substring(0,2).toUpperCase();
    document.getElementById('userInitials').textContent = initials;
    document.getElementById('userName').textContent = fullName;
    document.getElementById('userEmail').textContent = user.email || '';
}

function setFooterDate() {
    document.getElementById('footerDate').textContent = new Date().toLocaleDateString();
}

function setupEventListeners() {
    document.getElementById('loadExamBtn').addEventListener('click', loadExam);
    document.getElementById('saveOrderBtn').addEventListener('click', saveOrder);
    document.getElementById('logoutBtn').addEventListener('click', (e) => { e.preventDefault(); if(confirm('Logout?')) logout(); });
}

function loadExams() {
    fetch('../../api/instructor/get-exams.php', { credentials: 'include' })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('examSelect');
                select.innerHTML = '<option value="">-- Select an exam --</option>';
                data.exams.forEach(ex => {
                    select.innerHTML += `<option value="${ex.id}">${ex.exam_title} (${ex.course_code})</option>`;
                });
            } else {
                console.error('Failed to load exams');
            }
        })
        .catch(err => console.error(err));
}

function loadExam() {
    const examId = document.getElementById('examSelect').value;
    if (!examId) {
        alert('Please select an exam');
        return;
    }
    currentExamId = examId;
    document.getElementById('questionsPanel').style.display = 'block';

    // Load questions already in exam
    fetch(`../../api/instructor/get-exam-questions.php?exam_id=${examId}`, { credentials: 'include' })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                examQuestions = data.questions;
                renderExamQuestions();
            } else {
                alert('Failed to load exam questions');
            }
        })
        .catch(err => console.error(err));

    // Load available questions (not in exam)
    fetch(`../../api/instructor/get-question-bank.php?exam_id=${examId}`, { credentials: 'include' })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                availableQuestions = data.questions;
                renderAvailableQuestions();
            } else {
                alert('Failed to load available questions');
            }
        })
        .catch(err => console.error(err));
}

function renderExamQuestions() {
    const container = document.getElementById('examQuestionsList');
    if (!examQuestions.length) {
        container.innerHTML = '<div class="empty-message">No questions added yet.</div>';
        document.getElementById('saveOrderBtn').style.display = 'none';
        return;
    }
    let html = '';
    examQuestions.sort((a,b) => a.question_order - b.question_order).forEach(q => {
        html += `
            <div class="question-item" data-id="${q.id}">
                <span class="question-text">${q.question_text.substring(0,60)}${q.question_text.length>60?'...':''}</span>
                <input type="number" class="order-input" value="${q.question_order}" data-id="${q.id}" placeholder="Order" min="1">
                <input type="number" class="marks-input" value="${q.marks}" data-id="${q.id}" placeholder="Marks" min="0" step="0.5">
                <button class="btn-icon btn-remove" onclick="removeQuestion(${q.id})"><i class="fas fa-trash"></i></button>
            </div>
        `;
    });
    container.innerHTML = html;
    document.getElementById('saveOrderBtn').style.display = 'block';
}

function renderAvailableQuestions() {
    const container = document.getElementById('availableQuestionsList');
    if (!availableQuestions.length) {
        container.innerHTML = '<div class="empty-message">All questions are already in this exam.</div>';
        return;
    }
    let html = '';
    availableQuestions.forEach(q => {
        html += `
            <div class="question-item" data-id="${q.id}">
                <span class="question-text">${q.question_text.substring(0,60)}${q.question_text.length>60?'...':''}</span>
                <button class="btn-icon btn-add" onclick="addQuestion(${q.id})"><i class="fas fa-plus"></i></button>
            </div>
        `;
    });
    container.innerHTML = html;
}

// Global functions called from onclick
window.addQuestion = function(questionId) {
    const order = examQuestions.length + 1;
    const marks = 2; // default
    fetch('../../api/instructor/add-question-to-exam.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({
            exam_id: currentExamId,
            question_id: questionId,
            order: order,
            marks: marks
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // Reload both lists
            loadExam();
        } else {
            alert('Error adding question: ' + data.message);
        }
    })
    .catch(() => alert('Request failed'));
};

window.removeQuestion = function(questionId) {
    if (!confirm('Remove this question from the exam?')) return;
    fetch('../../api/instructor/remove-question-from-exam.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({
            exam_id: currentExamId,
            question_id: questionId
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            loadExam();
        } else {
            alert('Error removing question: ' + data.message);
        }
    })
    .catch(() => alert('Request failed'));
};

function saveOrder() {
    const updates = [];
    document.querySelectorAll('#examQuestionsList .question-item').forEach(item => {
        const qid = item.dataset.id;
        const order = item.querySelector('.order-input').value;
        const marks = item.querySelector('.marks-input').value;
        updates.push({ question_id: qid, order: order, marks: marks });
    });

    fetch('../../api/instructor/update-exam-questions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({
            exam_id: currentExamId,
            questions: updates
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Order and marks updated');
            loadExam(); // refresh
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(() => alert('Request failed'));
}