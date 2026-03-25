// student-results.js

document.addEventListener('DOMContentLoaded', function() {
    const currentUser = checkAuth();
    if (!currentUser || currentUser.role !== 'student') {
        window.location.href = '../auth/login.html';
        return;
    }
    updateProfileInfo(currentUser);
    setFooterDate();
    loadResults();
    setupEventListeners();
});

let allResults = [];
let currentPage = 1;
let totalPages = 1;
const perPage = 10;

function updateProfileInfo(user) { /* same as before */ }
function setFooterDate() { document.getElementById('footerDate').textContent = new Date().toLocaleDateString(); }

function setupEventListeners() {
    document.getElementById('logoutBtn').addEventListener('click', (e) => { e.preventDefault(); if(confirm('Logout?')) logout(); });
    document.getElementById('yearFilter').addEventListener('change', applyFilters);
    document.getElementById('searchFilter').addEventListener('input', applyFilters);
    document.getElementById('searchInput').addEventListener('input', function() {
        document.getElementById('searchFilter').value = this.value;
        applyFilters();
    });
}

function loadResults() {
    fetch('../../api/student/get-all-results.php', { credentials: 'include' })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                allResults = data.results;
                applyFilters();
            } else {
                document.getElementById('resultsBody').innerHTML = '<tr><td colspan="7">No results found.</td></tr>';
            }
        })
        .catch(err => console.error(err));
}

function applyFilters() {
    const year = document.getElementById('yearFilter').value;
    const search = document.getElementById('searchFilter').value.toLowerCase();

    let filtered = allResults.filter(r => {
        const yearMatch = year === 'all' || r.year == year;
        const searchMatch = r.exam_title.toLowerCase().includes(search) || r.course_code.toLowerCase().includes(search);
        return yearMatch && searchMatch;
    });

    totalPages = Math.ceil(filtered.length / perPage);
    if (currentPage > totalPages) currentPage = totalPages || 1;
    const start = (currentPage - 1) * perPage;
    const paginated = filtered.slice(start, start + perPage);

    renderTable(paginated);
    renderPagination();
    document.getElementById('emptyMessage').style.display = filtered.length ? 'none' : 'block';
}

function renderTable(results) {
    const tbody = document.getElementById('resultsBody');
    if (!results.length) {
        tbody.innerHTML = '<tr><td colspan="7">No results found.</td></tr>';
        return;
    }
    let html = '';
    results.forEach(r => {
        const scoreClass = r.percentage >= 70 ? 'score-high' : (r.percentage >= 50 ? 'score-medium' : 'score-low');
        html += `<tr>
            <td>${r.exam_title}</td>
            <td>${r.course_code}</td>
            <td>${r.date_taken}</td>
            <td class="${scoreClass}">${r.score}/${r.total_marks}</td>
            <td class="${scoreClass}">${r.percentage}%</td>
            <td><span style="color:${r.passed ? '#28a745' : '#dc3545'}">${r.passed ? 'Passed' : 'Failed'}</span></td>
            <td><button class="btn-view" onclick="viewDetails(${r.attempt_id})">View</button></td>
        </tr>`;
    });
    tbody.innerHTML = html;
}

function renderPagination() { /* same as available exams */ }

function viewDetails(attemptId) {
    // Could open a modal or navigate to a detailed result page
    window.location.href = `result-detail.html?attempt=${attemptId}`;
}