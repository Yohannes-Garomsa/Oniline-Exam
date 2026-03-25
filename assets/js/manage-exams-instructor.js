// manage-exams-instructor.js

document.addEventListener('DOMContentLoaded', function() {
    const currentUser = checkAuth();
    if (!currentUser || currentUser.role !== 'instructor') {
        window.location.href = '../auth/login.html';
        return;
    }
    updateProfileInfo(currentUser);
    setFooterDate();
    loadCourses();
    loadExams();
    setupEventListeners();
});

let exams = [];
let courses = [];
let currentPage = 1;
let totalPages = 1;
let currentStatusFilter = 'all';
let searchTerm = '';

// questions parsed from uploaded file, pending import
let pendingUploadQuestions = [];

// ========== Helper Functions ==========
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
    document.getElementById('statusFilter').addEventListener('change', applyFilters);
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
            document.getElementById('statusFilter').value = this.dataset.filter;
            currentStatusFilter = this.dataset.filter;
            applyFilters();
            updateStatCardsActive();
        });
    });
    document.getElementById('addExamBtn').addEventListener('click', openAddModal);
    document.getElementById('closeModal').addEventListener('click', closeModal);
    document.getElementById('cancelModal').addEventListener('click', closeModal);
    document.getElementById('saveExam').addEventListener('click', saveExam);
    document.getElementById('questionFile').addEventListener('change', handleFileUpload);
    window.addEventListener('click', e => { if(e.target.classList.contains('modal')) closeModal(); });
    document.getElementById('logoutBtn').addEventListener('click', (e) => { e.preventDefault(); if(confirm('Logout?')) logout(); });
}

function loadCourses() {
    fetch('../../api/instructor/get-courses.php', { credentials: 'include' })
        .then(res => res.json())
        .then(result => {
            if(result.success) {
                courses = result.courses;
                populateCourseDropdown();
            } else {
                console.error('Failed to load courses');
            }
        })
        .catch(err => console.error('Error loading courses:', err));
}

function populateCourseDropdown() {
    const select = document.getElementById('examCourse');
    select.innerHTML = '<option value="">Select Course</option>';
    courses.forEach(c => {
        select.innerHTML += `<option value="${c.id}">${c.course_code} - ${c.course_name}</option>`;
    });
}

function loadExams() {
    const tbody = document.getElementById('examsTableBody');
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">Loading exams...</td></tr>';
    fetch('../../api/instructor/get-exams.php', { credentials: 'include' })
        .then(res => res.json())
        .then(result => {
            if(result.success) {
                exams = result.exams;
                updateStats();
                applyFilters();
            } else {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">Failed to load exams.</td></tr>';
            }
        })
        .catch(() => {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">Error loading exams.</td></tr>';
        });
}

function updateStats() {
    document.getElementById('totalExams').textContent = exams.length;
    document.getElementById('activeExams').textContent = exams.filter(e => e.status === 'active').length;
    document.getElementById('draftExams').textContent = exams.filter(e => e.status === 'draft').length;
}

function applyFilters() {
    currentStatusFilter = document.getElementById('statusFilter').value;
    searchTerm = document.getElementById('searchFilter').value.toLowerCase();

    let filtered = exams.filter(e => {
        const statusMatch = currentStatusFilter === 'all' || e.status === currentStatusFilter;
        const searchMatch = e.exam_title.toLowerCase().includes(searchTerm) ||
                            (e.course_code && e.course_code.toLowerCase().includes(searchTerm));
        return statusMatch && searchMatch;
    });

    totalPages = Math.ceil(filtered.length / 10);
    if(currentPage > totalPages) currentPage = totalPages || 1;
    const start = (currentPage-1)*10;
    const paginated = filtered.slice(start, start+10);
    renderTable(paginated);
    renderPagination();
    updateStatCardsActive();
    document.getElementById('emptyMessage').style.display = filtered.length ? 'none' : 'block';
}

function renderTable(examsList) {
    const tbody = document.getElementById('examsTableBody');
    if(!examsList.length) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">No exams found.</td></tr>';
        return;
    }
    let html = '';
    examsList.forEach(e => {
        const statusClass = e.status;
        html += `<tr data-id="${e.id}">
            <td>${e.exam_title}</td>
            <td>${e.course_code || ''}</td>
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
        btn.addEventListener('click', e => openEditModal(e.target.closest('tr').dataset.id));
    });
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', e => deleteExam(e.target.closest('tr').dataset.id));
    });
}

function renderPagination() {
    const div = document.getElementById('pagination');
    if(totalPages <= 1) { div.innerHTML = ''; return; }
    let html = '<button class="page-btn" id="prevPage"><i class="fas fa-chevron-left"></i></button>';
    for(let i=1; i<=totalPages; i++) {
        html += `<button class="page-btn ${i===currentPage?'active':''}" data-page="${i}">${i}</button>`;
    }
    html += '<button class="page-btn" id="nextPage"><i class="fas fa-chevron-right"></i></button>';
    div.innerHTML = html;
    document.querySelectorAll('.page-btn[data-page]').forEach(btn => {
        btn.addEventListener('click', function() { currentPage = parseInt(this.dataset.page); applyFilters(); });
    });
    document.getElementById('prevPage')?.addEventListener('click', () => { if(currentPage>1) { currentPage--; applyFilters(); } });
    document.getElementById('nextPage')?.addEventListener('click', () => { if(currentPage<totalPages) { currentPage++; applyFilters(); } });
}

function updateStatCardsActive() {
    document.querySelectorAll('.stat-card').forEach(c => c.classList.remove('active'));
    if(currentStatusFilter === 'all') document.getElementById('statAll').classList.add('active');
    else if(currentStatusFilter === 'active') document.getElementById('statActive').classList.add('active');
    else if(currentStatusFilter === 'draft') document.getElementById('statDraft').classList.add('active');
}

function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Create New Exam';
    document.getElementById('examForm').reset();
    // clear upload state
    pendingUploadQuestions = [];
    renderUploadPreview();
    document.getElementById('questionFile').value = '';
    document.getElementById('examModal').classList.add('active');
    delete document.getElementById('saveExam').dataset.id;
}

function openEditModal(id) {
    const exam = exams.find(e => e.id == id);
    if(!exam) return;
    document.getElementById('modalTitle').textContent = 'Edit Exam';
    document.getElementById('examTitle').value = exam.exam_title;
    document.getElementById('examCourse').value = exam.course_id;
    document.getElementById('durationMinutes').value = exam.duration_minutes;
    document.getElementById('totalMarks').value = exam.total_marks;
    document.getElementById('passingScore').value = exam.passing_score;
    document.getElementById('attemptsAllowed').value = exam.attempts_allowed;
    if(exam.available_from) {
        const d = new Date(exam.available_from);
        document.getElementById('availableFrom').value = d.toISOString().slice(0,16);
    }
    if(exam.available_until) {
        const d = new Date(exam.available_until);
        document.getElementById('availableUntil').value = d.toISOString().slice(0,16);
    }
    document.getElementById('examType').value = exam.exam_type || 'Quiz';
    document.getElementById('examStatus').value = exam.status || 'draft';
    document.getElementById('examDescription').value = exam.description || '';
    document.getElementById('randomizeQuestions').checked = exam.randomize_questions == 1;
    // clear upload state when editing; we don't automatically import previous file
    pendingUploadQuestions = [];
    renderUploadPreview();
    document.getElementById('questionFile').value = '';
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
        duration_minutes: parseInt(document.getElementById('durationMinutes').value),
        total_marks: parseInt(document.getElementById('totalMarks').value),
        passing_score: parseInt(document.getElementById('passingScore').value),
        attempts_allowed: parseInt(document.getElementById('attemptsAllowed').value),
        available_from: document.getElementById('availableFrom').value || null,
        available_until: document.getElementById('availableUntil').value || null,
        exam_type: document.getElementById('examType').value,
        status: document.getElementById('examStatus').value,
        description: document.getElementById('examDescription').value,
        randomize_questions: document.getElementById('randomizeQuestions').checked ? 1 : 0
    };
    if(!data.exam_title || !data.course_id || !data.duration_minutes || !data.total_marks) {
        alert('Please fill required fields'); return;
    }
    const id = document.getElementById('saveExam').dataset.id;
    const url = id ? '../../api/instructor/update-exam.php' : '../../api/instructor/create-exam.php';
    if(id) data.id = id;
    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(result => {
        if(result.success) {
            const examId = id ? id : result.exam_id;
            // before importing, make sure pending questions have correct course_id
            if(data.course_id) {
                pendingUploadQuestions = pendingUploadQuestions.map(q => {
                    if(!q.course_id) q.course_id = data.course_id;
                    return q;
                });
            }
            // if we have pending uploads, import them now
            if(pendingUploadQuestions.length && examId) {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({ questions: pendingUploadQuestions, exam_id: examId })
                })
                .then(r => r.json())
                .then(res => {
                    if(res.success) {
                        console.log('Imported', res.imported, 'questions');
                        // if question bank function exists, reload it
                        if(typeof loadQuestions === 'function') {
                            loadQuestions();
                        }
                    } else {
                        alert('Upload import failed: ' + (res.message||''));
                    }
                    // clear pending after attempt regardless
                    pendingUploadQuestions = [];
                    renderUploadPreview();
                    alert(id ? 'Exam updated' : 'Exam created');
                    closeModal();
                    loadExams();
                })
                .catch(err => {
                    console.error(err);
                    alert('Import request failed');
                    // keep pending so teacher can retry
                });
            } else {
                alert(id ? 'Exam updated' : 'Exam created');
                closeModal();
                loadExams();
            }
        } else {
            alert('Error: ' + (result.message || 'Unknown error'));
        }
    })
    .catch(() => alert('Request failed'));
}

// render pending upload preview
function renderUploadPreview() {
    const container = document.getElementById('uploadPreview');
    if(!pendingUploadQuestions.length) {
        container.style.display = 'none';
        container.innerHTML = '';
        return;
    }
    let html = '<strong>Parsed questions:</strong> <button id="clearUpload" style="float:right;">Clear</button><ul>';
    pendingUploadQuestions.forEach((q,idx) => {
        html += `<li>${q.question} <small>(${q.type})</small></li>`;
    });
    html += '</ul>';
    container.innerHTML = html;
    container.style.display = 'block';
    document.getElementById('clearUpload').addEventListener('click', function() {
        pendingUploadQuestions = [];
        renderUploadPreview();
        document.getElementById('questionFile').value = '';
    });
}

function handleFileUpload(e) {
    const file = e.target.files[0];
    if(!file) return;
    const form = new FormData();
    form.append('file', file);
    fetch('../../api/instructor/parse-questions-file.php', {
        method: 'POST',
        credentials: 'include',
        body: form
    })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            pendingUploadQuestions = data.questions || [];
            // assign the currently selected course to each question if missing
            const courseId = parseInt(document.getElementById('examCourse').value) || null;
            if(courseId) {
                pendingUploadQuestions = pendingUploadQuestions.map(q => {
                    if(!q.course_id) q.course_id = courseId;
                    return q;
                });
            }
            renderUploadPreview();
        } else {
            alert('Parse error: ' + (data.message || 'unknown'));
        }
    })
    .catch(err => {
        console.error(err);
        alert('File parse request failed');
    });
}
}

function deleteExam(id) {
    if(!confirm('Delete this exam? This cannot be undone.')) return;
    fetch(`../../api/instructor/delete-exam.php?id=${id}`, {
        method: 'DELETE',
        credentials: 'include'
    })
    .then(res => res.json())
    .then(result => {
        if(result.success) {
            alert('Exam deleted');
            loadExams();
        } else {
            alert('Error: ' + result.message);
        }
    })
    .catch(() => alert('Delete failed'));
}