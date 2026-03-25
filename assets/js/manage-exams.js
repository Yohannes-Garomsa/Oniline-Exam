// manage-exams.js – Admin Manage Exams Page

document.addEventListener('DOMContentLoaded', function() {
    const currentUser = checkAuth();
    if (!currentUser || currentUser.role !== 'admin') {
        window.location.href = '../auth/login.html';
        return;
    }
    updateProfileInfo(currentUser);
    setFooterDate();
    loadExams();
    loadCourses();
    loadInstructors();
    setupEventListeners();
});

let exams = [];
let courses = [];
let instructors = [];
let currentPage = 1;
let totalPages = 1;
let currentStatusFilter = 'all';
let currentDeptFilter = 'all';
let searchTerm = '';

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

// ========== Event Listeners ==========
function setupEventListeners() {
    document.getElementById('statusFilter').addEventListener('change', applyFilters);
    document.getElementById('departmentFilter').addEventListener('change', applyFilters);
    document.getElementById('searchFilter').addEventListener('input', function() {
        searchTerm = this.value.toLowerCase();
        document.getElementById('searchInput').value = this.value;
        applyFilters();
    });
    document.getElementById('searchInput').addEventListener('input', function() {
        document.getElementById('searchFilter').value = this.value;
        searchTerm = this.value.toLowerCase();
        applyFilters();
    });
    document.querySelectorAll('.stat-card').forEach(card => {
        card.addEventListener('click', function() {
            const filter = this.dataset.filter;
            document.getElementById('statusFilter').value = filter;
            currentStatusFilter = filter;
            applyFilters();
            updateStatCardsActive();
        });
    });
    document.getElementById('addExamBtn').addEventListener('click', openAddModal);
    document.getElementById('closeModal').addEventListener('click', closeModal);
    document.getElementById('cancelModal').addEventListener('click', closeModal);
    document.getElementById('saveExam').addEventListener('click', saveExam);
    window.addEventListener('click', (e) => {
        if (e.target.classList.contains('modal')) closeModal();
    });
    document.getElementById('logoutBtn').addEventListener('click', (e) => {
        e.preventDefault();
        if (confirm('Logout?')) logout();
    });
}

// ========== Data Loading ==========
function loadExams() {
    const tbody = document.getElementById('examsTableBody');
    tbody.innerHTML = '<tr><td colspan="7">Loading exams...</td></tr>';
    fetch('../../api/admin/get-exams.php', { credentials: 'include' })
        .then(res => res.json())
        .then(result => {
            if (result.success) {
                exams = result.exams;
                updateStats();
                applyFilters();
            } else {
                tbody.innerHTML = '<tr><td colspan="7">Failed to load exams.</td></tr>';
            }
        })
        .catch(() => {
            tbody.innerHTML = '<tr><td colspan="7">Error loading exams.</td></tr>';
        });
}

function loadCourses() {
    fetch('../../api/admin/get-courses.php', { credentials: 'include' })
        .then(res => res.json())
        .then(result => {
            if (result.success) {
                courses = result.courses;
                populateCourseDropdown();
            }
        })
        .catch(console.error);
}

function loadInstructors() {
    fetch('../../api/admin/get-instructors.php', { credentials: 'include' })
        .then(res => res.json())
        .then(result => {
            if (result.success) {
                instructors = result.instructors;
                populateInstructorDropdown();
            }
        })
        .catch(console.error);
}

function populateCourseDropdown() {
    const select = document.getElementById('examCourse');
    select.innerHTML = '<option value="">Select Course</option>';
    courses.forEach(c => {
        select.innerHTML += `<option value="${c.id}">${c.course_code} - ${c.course_name}</option>`;
    });
}

function populateInstructorDropdown() {
    const select = document.getElementById('examInstructor');
    select.innerHTML = '<option value="">Select Instructor</option>';
    instructors.forEach(i => {
        select.innerHTML += `<option value="${i.id}">${i.name}</option>`;
    });
}

function updateStats() {
    const total = exams.length;
    const active = exams.filter(e => e.status === 'active').length;
    const draft = exams.filter(e => e.status === 'draft').length;
    const completed = exams.filter(e => e.status === 'completed').length;
    document.getElementById('totalExams').textContent = total;
    document.getElementById('activeExams').textContent = active;
    document.getElementById('draftExams').textContent = draft;
    document.getElementById('completedExams').textContent = completed;
}

// ========== Filtering & Rendering ==========
function applyFilters() {
    currentStatusFilter = document.getElementById('statusFilter').value;
    currentDeptFilter = document.getElementById('departmentFilter').value;
    searchTerm = document.getElementById('searchFilter').value.toLowerCase();
    const filtered = exams.filter(e => {
        const statusMatch = currentStatusFilter === 'all' || e.status === currentStatusFilter;
        const deptMatch = currentDeptFilter === 'all' || (e.course_code && e.course_code.startsWith(currentDeptFilter));
        const searchMatch = e.exam_title.toLowerCase().includes(searchTerm) ||
                            (e.course_code && e.course_code.toLowerCase().includes(searchTerm));
        return statusMatch && deptMatch && searchMatch;
    });
    totalPages = Math.ceil(filtered.length / 10);
    if (currentPage > totalPages) currentPage = totalPages || 1;
    const start = (currentPage - 1) * 10;
    const paginated = filtered.slice(start, start + 10);
    renderTable(paginated);
    renderPagination();
    updateStatCardsActive();
    document.getElementById('emptyMessage').style.display = filtered.length ? 'none' : 'block';
}

function renderTable(examsList) {
    const tbody = document.getElementById('examsTableBody');
    if (!examsList.length) {
        tbody.innerHTML = '<tr><td colspan="7">No exams found.</td></tr>';
        return;
    }
    let html = '';
    examsList.forEach(e => {
        const statusClass = e.status;
        html += `<tr data-id="${e.id}" data-status="${e.status}">
            <td>${e.exam_title}</td>
            <td>${e.course_code || ''}</td>
            <td>${e.instructor_name || ''}</td>
            <td>${e.duration_minutes} min</td>
            <td>${e.total_marks}</td>
            <td><span class="status-badge ${statusClass}">${e.status}</span></td>
            <td class="action-cell">
                <button class="btn-icon edit-btn" title="Edit"><i class="fas fa-edit"></i></button>
                <button class="btn-icon delete-btn" title="Delete"><i class="fas fa-trash"></i></button>
            </td>
        </tr>`;
    });
    tbody.innerHTML = html;
    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', (e) => openEditModal(e.target.closest('tr').dataset.id));
    });
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', (e) => deleteExam(e.target.closest('tr').dataset.id));
    });
}

function renderPagination() {
    const paginationDiv = document.getElementById('pagination');
    if (totalPages <= 1) {
        paginationDiv.innerHTML = '';
        return;
    }
    let html = '<button class="page-btn" id="prevPage"><i class="fas fa-chevron-left"></i></button>';
    for (let i = 1; i <= totalPages; i++) {
        html += `<button class="page-btn ${i === currentPage ? 'active' : ''}" data-page="${i}">${i}</button>`;
    }
    html += '<button class="page-btn" id="nextPage"><i class="fas fa-chevron-right"></i></button>';
    paginationDiv.innerHTML = html;

    document.querySelectorAll('.page-btn[data-page]').forEach(btn => {
        btn.addEventListener('click', function() {
            currentPage = parseInt(this.dataset.page);
            applyFilters();
        });
    });
    document.getElementById('prevPage')?.addEventListener('click', function() {
        if (currentPage > 1) {
            currentPage--;
            applyFilters();
        }
    });
    document.getElementById('nextPage')?.addEventListener('click', function() {
        if (currentPage < totalPages) {
            currentPage++;
            applyFilters();
        }
    });
}

function updateStatCardsActive() {
    document.querySelectorAll('.stat-card').forEach(card => card.classList.remove('active'));
    if (currentStatusFilter === 'all') {
        document.getElementById('statAll').classList.add('active');
    } else if (currentStatusFilter === 'active') {
        document.getElementById('statActive').classList.add('active');
    } else if (currentStatusFilter === 'draft') {
        document.getElementById('statDraft').classList.add('active');
    } else if (currentStatusFilter === 'completed') {
        document.getElementById('statCompleted').classList.add('active');
    }
}

// ========== Modal Actions ==========
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add New Exam';
    document.getElementById('examForm').reset();
    document.getElementById('examModal').classList.add('active');
    delete document.getElementById('saveExam').dataset.id;
}

function openEditModal(id) {
    const exam = exams.find(e => e.id == id);
    if (!exam) return;
    document.getElementById('modalTitle').textContent = 'Edit Exam';
    document.getElementById('examTitle').value = exam.exam_title;
    document.getElementById('examCourse').value = exam.course_id;
    document.getElementById('examInstructor').value = exam.instructor_id;
    document.getElementById('examDescription').value = exam.description || '';
    document.getElementById('examType').value = exam.exam_type || 'Quiz';
    document.getElementById('totalMarks').value = exam.total_marks;
    document.getElementById('passingScore').value = exam.passing_score;
    document.getElementById('durationMinutes').value = exam.duration_minutes;
    if (exam.available_from) {
        const d = new Date(exam.available_from);
        document.getElementById('availableFrom').value = d.toISOString().slice(0,16);
    }
    if (exam.available_until) {
        const d = new Date(exam.available_until);
        document.getElementById('availableUntil').value = d.toISOString().slice(0,16);
    }
    document.getElementById('randomizeQuestions').checked = exam.randomize_questions == 1;
    document.getElementById('showResults').value = exam.show_results || 'immediate';
    document.getElementById('attemptsAllowed').value = exam.attempts_allowed;
    document.getElementById('examStatus').value = exam.status || 'draft';
    document.getElementById('examPassword').value = exam.exam_password || '';
    document.getElementById('saveExam').dataset.id = id;
    document.getElementById('examModal').classList.add('active');
}

function closeModal() {
    document.getElementById('examModal').classList.remove('active');
}

function saveExam() {
    const data = {
        exam_title: document.getElementById('examTitle').value,
        course_id: parseInt(document.getElementById('examCourse').value),
        instructor_id: parseInt(document.getElementById('examInstructor').value),
        description: document.getElementById('examDescription').value,
        exam_type: document.getElementById('examType').value,
        total_marks: parseInt(document.getElementById('totalMarks').value),
        passing_score: parseInt(document.getElementById('passingScore').value),
        duration_minutes: parseInt(document.getElementById('durationMinutes').value),
        available_from: document.getElementById('availableFrom').value || null,
        available_until: document.getElementById('availableUntil').value || null,
        randomize_questions: document.getElementById('randomizeQuestions').checked ? 1 : 0,
        show_results: document.getElementById('showResults').value,
        attempts_allowed: parseInt(document.getElementById('attemptsAllowed').value),
        status: document.getElementById('examStatus').value,
        exam_password: document.getElementById('examPassword').value
    };
    if (!data.exam_title || !data.course_id || !data.instructor_id || !data.duration_minutes || !data.total_marks || !data.passing_score) {
        alert('Please fill all required fields');
        return;
    }
    const id = document.getElementById('saveExam').dataset.id;
    const url = id ? '../../api/admin/update-exam.php' : '../../api/admin/create-exam.php';
    if (id) data.id = id;
    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(result => {
        if (result.success) {
            alert(id ? 'Exam updated' : 'Exam created');
            closeModal();
            loadExams();
        } else {
            alert('Error: ' + result.message);
        }
    })
    .catch(() => alert('Request failed'));
}

function deleteExam(id) {
    if (!confirm('Delete this exam?')) return;
    fetch('../../api/admin/delete-exam.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ id: id })
    })
    .then(res => res.json())
    .then(result => {
        if (result.success) {
            alert('Exam deleted');
            loadExams();
        } else {
            alert('Error: ' + result.message);
        }
    })
    .catch(() => alert('Delete failed'));
}