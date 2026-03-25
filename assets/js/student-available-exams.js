// student-available-exams.js

document.addEventListener('DOMContentLoaded', function() {
    const currentUser = checkAuth();
    if (!currentUser || currentUser.role !== 'student') {
        window.location.href = '../auth/login.html';
        return;
    }
    updateProfileInfo(currentUser);
    setFooterDate();
    loadCourses();
    loadExams();
    setupEventListeners();
});

let allExams = [];
let courses = [];
let currentPage = 1;
let totalPages = 1;
const perPage = 9;

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
    document.getElementById('logoutBtn').addEventListener('click', (e) => { e.preventDefault(); if(confirm('Logout?')) logout(); });
    document.getElementById('courseFilter').addEventListener('change', applyFilters);
    document.getElementById('typeFilter').addEventListener('change', applyFilters);
    document.getElementById('searchFilter').addEventListener('input', function() {
        document.getElementById('searchInput').value = this.value;
        applyFilters();
    });
    document.getElementById('searchInput').addEventListener('input', function() {
        document.getElementById('searchFilter').value = this.value;
        applyFilters();
    });

    // Modal handlers
    document.getElementById('closeSecurityModal').addEventListener('click', closeModal);
    document.getElementById('cancelSecurityBtn').addEventListener('click', closeModal);
    document.getElementById('verifyPasswordBtn').addEventListener('click', verifyExamPassword);
    window.addEventListener('click', (e) => {
        if (e.target.classList.contains('modal')) closeModal();
    });

    // Password toggle
    document.querySelectorAll('.toggle-password').forEach(btn => {
        btn.addEventListener('click', function() {
            const input = this.previousElementSibling;
            input.type = input.type === 'password' ? 'text' : 'password';
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });
    });
}

function loadCourses() {
    // For filter dropdown – we can fetch distinct courses from the exams later
    // For simplicity, we'll populate from the exams after loading
}

function loadExams() {
    fetch('../../api/student/get-available-exams.php', { credentials: 'include' })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                allExams = data.exams;
                populateCourseFilter();
                applyFilters();
            } else {
                document.getElementById('examsGrid').innerHTML = '<p>No exams available.</p>';
            }
        })
        .catch(err => console.error(err));
}

function populateCourseFilter() {
    const coursesSet = new Set(allExams.map(e => e.course_code));
    const select = document.getElementById('courseFilter');
    select.innerHTML = '<option value="all">All Courses</option>';
    Array.from(coursesSet).sort().forEach(code => {
        select.innerHTML += `<option value="${code}">${code}</option>`;
    });
}

function applyFilters() {
    const course = document.getElementById('courseFilter').value;
    const type = document.getElementById('typeFilter').value;
    const search = document.getElementById('searchFilter').value.toLowerCase();

    let filtered = allExams.filter(e => {
        const courseMatch = course === 'all' || e.course_code === course;
        const typeMatch = type === 'all' || e.exam_type === type;
        const searchMatch = e.title.toLowerCase().includes(search) || e.course_code.toLowerCase().includes(search);
        return courseMatch && typeMatch && searchMatch;
    });

    totalPages = Math.ceil(filtered.length / perPage);
    if (currentPage > totalPages) currentPage = totalPages || 1;
    const start = (currentPage - 1) * perPage;
    const paginated = filtered.slice(start, start + perPage);

    renderExams(paginated);
    renderPagination();
}

function renderExams(exams) {
    const grid = document.getElementById('examsGrid');
    if (!exams.length) {
        grid.innerHTML = '<p>No exams match your filters.</p>';
        return;
    }
    let html = '';
    exams.forEach(exam => {
        const urgencyClass = exam.urgency; // 'urgent', 'today', 'upcoming'
        html += `
            <div class="exam-card ${urgencyClass}">
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
            </div>
        `;
    });
    grid.innerHTML = html;

    // Attach event listeners to start buttons
    document.querySelectorAll('.btn-start-exam').forEach(btn => {
        btn.addEventListener('click', function() {
            const examId = this.dataset.examId;
            const examTitle = this.dataset.examTitle;
            const courseCode = this.dataset.courseCode;
            openExamSecurity(examId, examTitle, courseCode);
        });
    });
}

function renderPagination() {
    const div = document.getElementById('pagination');
    if (totalPages <= 1) { div.innerHTML = ''; return; }
    let html = '<button class="page-btn" id="prevPage"><i class="fas fa-chevron-left"></i></button>';
    for (let i = 1; i <= totalPages; i++) {
        html += `<button class="page-btn ${i === currentPage ? 'active' : ''}" data-page="${i}">${i}</button>`;
    }
    html += '<button class="page-btn" id="nextPage"><i class="fas fa-chevron-right"></i></button>';
    div.innerHTML = html;

    document.querySelectorAll('.page-btn[data-page]').forEach(btn => {
        btn.addEventListener('click', function() {
            currentPage = parseInt(this.dataset.page);
            applyFilters();
        });
    });
    document.getElementById('prevPage')?.addEventListener('click', () => {
        if (currentPage > 1) { currentPage--; applyFilters(); }
    });
    document.getElementById('nextPage')?.addEventListener('click', () => {
        if (currentPage < totalPages) { currentPage++; applyFilters(); }
    });
}

let currentExam = null;

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
            window.location.href = `take-exam.html?attempt=${result.attempt_id}`;
        } else {
            alert(result.message || 'Incorrect password or exam not available.');
        }
    })
    .catch(() => alert('Error verifying password'));
}