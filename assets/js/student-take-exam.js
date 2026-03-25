// student-take-exam.js

document.addEventListener('DOMContentLoaded', function() {
    const currentUser = checkAuth();
    if (!currentUser || currentUser.role !== 'student') {
        window.location.href = '../auth/login.html';
        return;
    }

    const urlParams = new URLSearchParams(window.location.search);
    const attemptId = urlParams.get('attempt');
    if (!attemptId) {
        alert('No attempt specified');
        window.location.href = 'available-exams.html';
        return;
    }

    loadExam(attemptId);
    setupEventListeners();
});

let examData = null;
let questions = [];
let currentIndex = 0;
let answers = {}; // question_id -> selected_option_id
let timerInterval = null;
let endTime = null;

function loadExam(attemptId) {
    fetch(`../../api/student/get-exam-questions.php?attempt_id=${attemptId}`, { credentials: 'include' })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (data.questions.length === 0) {
                    alert('This exam has no questions yet. Please contact your instructor.');
                    window.location.href = 'available-exams.html';
                    return;
                }
                examData = data.exam;
                questions = data.questions;
                document.getElementById('examTitle').textContent = examData.title;
                document.getElementById('courseCode').textContent = examData.course_code;

                // Calculate end time based on start time + duration
                const start = new Date(examData.start_time).getTime();
                const duration = examData.duration * 60 * 1000; // minutes to ms
                endTime = start + duration;
                startTimer();

                renderQuestionNav();
                showQuestion(0);
            } else {
                alert('Error loading exam: ' + (data.message || 'Unknown error'));
                window.location.href = 'available-exams.html';
            }
        })
        .catch(err => {
            console.error('Fetch error:', err);
            alert('Error loading exam. Please try again.');
            window.location.href = 'available-exams.html';
        });
}

function startTimer() {
    timerInterval = setInterval(() => {
        const now = Date.now();
        const remaining = Math.max(0, endTime - now);
        const minutes = Math.floor(remaining / 60000);
        const seconds = Math.floor((remaining % 60000) / 1000);
        document.getElementById('timer').textContent = `${minutes.toString().padStart(2,'0')}:${seconds.toString().padStart(2,'0')}`;
        if (remaining <= 300000) { // 5 minutes warning
            document.getElementById('timer').classList.add('warning');
        }
        if (remaining <= 0) {
            clearInterval(timerInterval);
            autoSubmit();
        }
    }, 1000);
}

function renderQuestionNav() {
    const nav = document.getElementById('questionNav');
    if (!nav) return;
    let html = '';
    questions.forEach((q, idx) => {
        const answered = answers[q.id] ? 'answered' : '';
        const active = idx === currentIndex ? 'active' : '';
        html += `<button class="${answered} ${active}" data-index="${idx}">${idx+1}</button>`;
    });
    nav.innerHTML = html;
    document.querySelectorAll('#questionNav button').forEach(btn => {
        btn.addEventListener('click', () => {
            const idx = parseInt(btn.dataset.index);
            showQuestion(idx);
        });
    });
}

function showQuestion(index) {
    if (!questions || index < 0 || index >= questions.length) return;
    currentIndex = index;
    const q = questions[index];

    const currentQuestionDiv = document.getElementById('currentQuestion');
    const optionsContainer = document.getElementById('optionsContainer');
    if (!currentQuestionDiv || !optionsContainer) return;

    currentQuestionDiv.innerHTML = `<h3>${index+1}. ${q.question_text}</h3>`;

    let optionsHtml = '';
    q.options.forEach(opt => {
        const selected = answers[q.id] == opt.id ? 'selected' : '';
        optionsHtml += `<div class="option-item ${selected}" data-option-id="${opt.id}">${opt.letter}. ${opt.text}</div>`;
    });
    optionsContainer.innerHTML = optionsHtml;

    document.querySelectorAll('.option-item').forEach(optDiv => {
        optDiv.addEventListener('click', function() {
            const optionId = this.dataset.optionId;
            answers[q.id] = optionId;
            // Remove selected class from siblings
            this.parentElement.querySelectorAll('.option-item').forEach(o => o.classList.remove('selected'));
            this.classList.add('selected');
            renderQuestionNav(); // update nav to show answered
        });
    });

    // Update navigation buttons state
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    if (prevBtn) prevBtn.disabled = index === 0;
    if (nextBtn) nextBtn.disabled = index === questions.length - 1;

    updateNavActive();
}

function updateNavActive() {
    document.querySelectorAll('#questionNav button').forEach(btn => {
        btn.classList.remove('active');
        if (parseInt(btn.dataset.index) === currentIndex) {
            btn.classList.add('active');
        }
    });
}

function setupEventListeners() {
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const submitBtn = document.getElementById('submitExamBtn');

    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            if (currentIndex > 0) showQuestion(currentIndex - 1);
        });
    }
    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            if (currentIndex < questions.length - 1) showQuestion(currentIndex + 1);
        });
    }
    if (submitBtn) {
        submitBtn.addEventListener('click', submitExam);
    }
}

function submitExam() {
    if (!confirm('Are you sure you want to submit the exam? This action cannot be undone.')) return;

    clearInterval(timerInterval);

    const attemptId = new URLSearchParams(window.location.search).get('attempt');
    const answerArray = Object.entries(answers).map(([qid, optId]) => ({
        question_id: parseInt(qid),
        selected_option_id: parseInt(optId)
    }));

    fetch('../../api/student/submit-exam.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ attempt_id: attemptId, answers: answerArray })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert(`Exam submitted! Your score: ${data.score} (${data.percentage}%)`);
            window.location.href = 'results.html';
        } else {
            alert('Submission failed: ' + data.message);
        }
    })
    .catch(() => alert('Error submitting exam'));
}

function autoSubmit() {
    alert('Time is up! Your exam will be submitted automatically.');
    submitExam();
}