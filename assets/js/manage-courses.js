// manage-courses.js – Admin Manage Courses Page

document.addEventListener('DOMContentLoaded', function() {
    const currentUser = checkAuth();
    if (!currentUser || currentUser.role !== 'admin') {
        window.location.href = '../auth/login.html';
        return;
    }
    updateProfileInfo(currentUser);
    setFooterDate();
    loadCourses();
    loadInstructors(); // for the dropdown in modal
    setupEventListeners();
});

let courses = [];
let instructors = [];
let currentPage = 1;
let totalPages = 1;
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
            document.getElementById('departmentFilter').value = filter;
            currentDeptFilter = filter;
            applyFilters();
            updateStatCardsActive();
        });
    });

    document.getElementById('addCourseBtn').addEventListener('click', openAddModal);
    document.getElementById('closeModal').addEventListener('click', closeModal);
    document.getElementById('cancelModal').addEventListener('click', closeModal);
    document.getElementById('saveCourse').addEventListener('click', saveCourse);
    window.addEventListener('click', (e) => {
        if (e.target.classList.contains('modal')) closeModal();
    });

    document.getElementById('logoutBtn').addEventListener('click', (e) => {
        e.preventDefault();
        if (confirm('Logout?')) logout();
    });
}

// ========== Data Loading ==========
function loadCourses() {
    const tbody = document.getElementById('coursesTableBody');
    tbody.innerHTML = '<tr><td colspan="6">Loading courses...</td></tr>';
    fetch('../../api/admin/get-courses.php', { credentials: 'include' })
        .then(res => res.json())
        .then(result => {
            if (result.success) {
                courses = result.courses;
                updateStats();
                applyFilters();
            } else {
                tbody.innerHTML = '<tr><td colspan="6">Failed to load courses.</td></tr>';
            }
        })
        .catch(() => {
            tbody.innerHTML = '<tr><td colspan="6">Error loading courses.</td></tr>';
        });
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

function populateInstructorDropdown() {
    const select = document.getElementById('courseInstructor');
    select.innerHTML = '<option value="">Select Instructor</option>';
    instructors.forEach(inst => {
        select.innerHTML += `<option value="${inst.id}">${inst.name}</option>`;
    });
}

function updateStats() {
    const total = courses.length;
    const it = courses.filter(c => c.department === 'IT').length;
    const cs = courses.filter(c => c.department === 'CS').length;
    document.getElementById('totalCourses').textContent = total;
    document.getElementById('itCourses').textContent = it;
    document.getElementById('csCourses').textContent = cs;
}

// ========== Filtering & Rendering ==========
function applyFilters() {
    currentDeptFilter = document.getElementById('departmentFilter').value;
    searchTerm = document.getElementById('searchFilter').value.toLowerCase();

    const filtered = courses.filter(c => {
        const deptMatch = currentDeptFilter === 'all' || c.department === currentDeptFilter;
        const searchMatch = c.course_code.toLowerCase().includes(searchTerm) ||
                            c.course_name.toLowerCase().includes(searchTerm);
        return deptMatch && searchMatch;
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

function renderTable(coursesList) {
    const tbody = document.getElementById('coursesTableBody');
    if (!coursesList.length) {
        tbody.innerHTML = '<tr><td colspan="6">No courses found.</td></tr>';
        return;
    }

    let html = '';
    coursesList.forEach(c => {
        html += `<tr data-id="${c.id}">
            <td>${c.course_code}</td>
            <td>${c.course_name}</td>
            <td>${c.department || ''}</td>
            <td>${c.credits}</td>
            <td>${c.instructor_name || ''}</td>
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
        btn.addEventListener('click', (e) => deleteCourse(e.target.closest('tr').dataset.id));
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
    if (currentDeptFilter === 'all') {
        document.getElementById('statAll').classList.add('active');
    } else if (currentDeptFilter === 'IT') {
        document.getElementById('statIT').classList.add('active');
    } else if (currentDeptFilter === 'CS') {
        document.getElementById('statCS').classList.add('active');
    }
}

// ========== Modal Actions ==========
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add New Course';
    document.getElementById('courseForm').reset();
    document.getElementById('courseModal').classList.add('active');
    delete document.getElementById('saveCourse').dataset.id;
}

function openEditModal(id) {
    const course = courses.find(c => c.id == id);
    if (!course) return;

    document.getElementById('modalTitle').textContent = 'Edit Course';
    document.getElementById('courseCode').value = course.course_code;
    document.getElementById('courseName').value = course.course_name;
    document.getElementById('courseDept').value = course.department || '';
    document.getElementById('courseCredits').value = course.credits;
    document.getElementById('courseInstructor').value = course.instructor_id || '';
    document.getElementById('saveCourse').dataset.id = id;
    document.getElementById('courseModal').classList.add('active');
}

function closeModal() {
    document.getElementById('courseModal').classList.remove('active');
}

function saveCourse() {
    const data = {
        course_code: document.getElementById('courseCode').value.trim(),
        course_name: document.getElementById('courseName').value.trim(),
        department: document.getElementById('courseDept').value,
        credits: parseInt(document.getElementById('courseCredits').value),
        instructor_id: parseInt(document.getElementById('courseInstructor').value),
        description: '' // optional, you can add a description field if needed
    };

    if (!data.course_code || !data.course_name || !data.department || !data.credits || !data.instructor_id) {
        alert('Please fill all required fields');
        return;
    }

    const id = document.getElementById('saveCourse').dataset.id;
    const url = id ? '../../api/admin/update-course.php' : '../../api/admin/create-course.php';
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
            alert(id ? 'Course updated' : 'Course created');
            closeModal();
            loadCourses(); // refresh list
            loadInstructors(); // refresh dropdown (optional)
        } else {
            alert('Error: ' + result.message);
        }
    })
    .catch(() => alert('Request failed'));
}

function deleteCourse(id) {
    if (!confirm('Are you sure you want to delete this course? This action cannot be undone.')) return;
    fetch('../../api/admin/delete-course.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ id: id })
    })
    .then(res => res.json())
    .then(result => {
        if (result.success) {
            alert('Course deleted');
            loadCourses(); // refresh
        } else {
            alert('Error: ' + result.message);
        }
    })
    .catch(() => alert('Delete failed'));
}