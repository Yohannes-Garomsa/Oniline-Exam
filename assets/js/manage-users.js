// manage-users.js – Admin Manage Users Page

document.addEventListener("DOMContentLoaded", function () {
  // Check authentication
  const currentUser = checkAuth(); // from auth-check.js
  if (!currentUser || currentUser.role !== "admin") {
    window.location.href = "../auth/login.html";
    return;
  }

  // Update profile info in sidebar
  updateProfileInfo(currentUser);

  // Set footer date
  setFooterDate();

  // Load users from API
  loadUsers();

  // Set up event listeners
  setupEventListeners();
});

// Store current filter values
let currentRoleFilter = "all";
let currentStatusFilter = "all";
let searchTerm = "";
let currentPage = 1;
let totalPages = 1;
let usersData = []; // store all users for client-side filtering/pagination

function updateProfileInfo(user) {
  const initials = (user.name || "Admin")
    .split(" ")
    .map((n) => n[0])
    .join("")
    .substring(0, 2)
    .toUpperCase();
  document.getElementById("adminInitials").textContent = initials;
  document.getElementById("adminName").textContent = user.name || "Admin User";
  document.getElementById("adminEmail").textContent =
    user.email || "admin@oes.edu.et";
}

function setFooterDate() {
  const now = new Date();
  const formatted = now.toLocaleDateString("en-US", {
    month: "long",
    day: "numeric",
    year: "numeric",
  });
  document.getElementById("footerDate").textContent = formatted;
}

function setupEventListeners() {
  // Filter change events
  document
    .getElementById("roleFilter")
    .addEventListener("change", applyFilters);
  document
    .getElementById("statusFilter")
    .addEventListener("change", applyFilters);
  document.getElementById("nameFilter").addEventListener("input", function () {
    searchTerm = this.value.toLowerCase();
    document.getElementById("searchInput").value = this.value; // sync top search
    applyFilters();
  });
  document.getElementById("searchInput").addEventListener("input", function () {
    document.getElementById("nameFilter").value = this.value;
    searchTerm = this.value.toLowerCase();
    applyFilters();
  });

  // Stat card clicks
  document.querySelectorAll(".stat-card").forEach((card) => {
    card.addEventListener("click", function () {
      const filter = this.dataset.filter;
      document.getElementById("roleFilter").value = filter;
      currentRoleFilter = filter;
      applyFilters();
      updateStatCardsActive();
    });
  });

  // Logout
  document.getElementById("logoutBtn").addEventListener("click", function (e) {
    e.preventDefault();
    if (confirm("Are you sure you want to logout?")) {
      logout(); // from auth-check.js
    }
  });

  // Language toggle (optional)
  document.querySelectorAll(".lang-btn").forEach((btn) => {
    btn.addEventListener("click", function () {
      document
        .querySelectorAll(".lang-btn")
        .forEach((b) => b.classList.remove("active"));
      this.classList.add("active");
      // You could implement language switching here
    });
  });

  // Modal close events
  document
    .getElementById("closeEditModal")
    .addEventListener("click", closeEditModal);
  document
    .getElementById("cancelEdit")
    .addEventListener("click", closeEditModal);
  window.addEventListener("click", function (e) {
    const modal = document.getElementById("editModal");
    if (e.target === modal) closeEditModal();
  });

  // Save edit
  document.getElementById("saveEdit").addEventListener("click", saveEdit);
}

function loadUsers() {
  // Show loading state
  const tbody = document.getElementById("usersTableBody");
  tbody.innerHTML =
    '<tr><td colspan="6" style="text-align: center; padding: 30px;">Loading users...</td></tr>';

  fetch("../../api/admin/get-users.php", {
    headers: {
      Authorization: "Bearer " + (localStorage.getItem("apiToken") || ""),
    },
  })
    .then((response) => response.json())
    .then((result) => {
      if (result.success && result.users) {
        usersData = result.users;
        updateStats();
        applyFilters(); // this will render the table and pagination
      } else {
        tbody.innerHTML =
          '<tr><td colspan="6" style="text-align: center; padding: 30px; color: #ef4444;">Failed to load users.</td></tr>';
      }
    })
    .catch((error) => {
      console.error("Error loading users:", error);
      tbody.innerHTML =
        '<tr><td colspan="6" style="text-align: center; padding: 30px; color: #ef4444;">Error loading users.</td></tr>';
    });
}

function updateStats() {
  const total = usersData.length;
  const students = usersData.filter((u) => u.role === "student").length;
  const instructors = usersData.filter((u) => u.role === "instructor").length;

  document.getElementById("totalUsers").textContent = total;
  document.getElementById("totalStudents").textContent = students;
  document.getElementById("totalInstructors").textContent = instructors;
}

function applyFilters() {
  currentRoleFilter = document.getElementById("roleFilter").value;
  currentStatusFilter = document.getElementById("statusFilter").value;
  searchTerm = document.getElementById("nameFilter").value.toLowerCase();

  // Filter users
  const filtered = usersData.filter((user) => {
    const roleMatch =
      currentRoleFilter === "all" || user.role === currentRoleFilter;
    const statusMatch =
      currentStatusFilter === "all" || user.status === currentStatusFilter;
    const nameMatch =
      user.first_name?.toLowerCase().includes(searchTerm) ||
      user.last_name?.toLowerCase().includes(searchTerm) ||
      user.user_id?.toLowerCase().includes(searchTerm) ||
      user.email?.toLowerCase().includes(searchTerm);
    return roleMatch && statusMatch && nameMatch;
  });

  // Pagination
  totalPages = Math.ceil(filtered.length / 10);
  if (currentPage > totalPages) currentPage = totalPages || 1;
  const start = (currentPage - 1) * 10;
  const paginated = filtered.slice(start, start + 10);

  renderTable(paginated);
  renderPagination();
  updateStatCardsActive();

  // Show empty message if no results
  document.getElementById("emptyMessage").style.display =
    filtered.length === 0 ? "block" : "none";
}

function renderTable(users) {
  const tbody = document.getElementById("usersTableBody");
  if (users.length === 0) {
    tbody.innerHTML =
      '<tr><td colspan="7" style="text-align: center; padding: 30px;">No users found.</td></tr>';
    return;
  }

  let html = "";
  users.forEach((user) => {
    const fullName =
      `${user.first_name || ""} ${user.last_name || ""}`.trim() || "Unknown";
    const roleClass =
      user.role === "student"
        ? "student"
        : user.role === "instructor"
          ? "instructor"
          : "admin";
    const roleDisplay = user.role
      ? user.role.charAt(0).toUpperCase() + user.role.slice(1)
      : "N/A";
    const statusClass = user.status === "active" ? "active" : "inactive";
    const statusDisplay = user.status === "active" ? "Active" : "Inactive";

    html += `<tr data-user-id="${user.id}" data-role="${user.role}" data-status="${user.status}">
            <td>${fullName}</td>
            <td>${user.email || "N/A"}</td>
            <td>${user.user_id || "N/A"}</td>
            <td><span class="role-badge ${roleClass}">${roleDisplay}</span></td>
            <td>${user.department || "N/A"}</td>
            <td><span class="status-badge ${statusClass}">${statusDisplay}</span></td>
            <td>
                <div class="action-cell">
                    <button class="btn-icon edit-btn" title="Edit" data-user-id="${user.id}"><i class="fas fa-edit"></i></button>
                    <button class="btn-icon block-btn" title="Block/Unblock" data-user-id="${user.id}"><i class="fas fa-ban"></i></button>
                    <button class="btn-icon delete-btn" title="Delete" data-user-id="${user.id}"><i class="fas fa-trash"></i></button>
                </div>
            </td>
        </tr>`;
  });

  tbody.innerHTML = html;

  // Attach event listeners to action buttons
  document.querySelectorAll(".edit-btn").forEach((btn) => {
    btn.addEventListener("click", () => editUser(btn.dataset.userId));
  });
  document.querySelectorAll(".block-btn").forEach((btn) => {
    btn.addEventListener("click", () => toggleUserStatus(btn.dataset.userId));
  });
  document.querySelectorAll(".delete-btn").forEach((btn) => {
    btn.addEventListener("click", () => deleteUser(btn.dataset.userId));
  });
}

function renderPagination() {
  const paginationDiv = document.getElementById("pagination");
  if (totalPages <= 1) {
    paginationDiv.innerHTML = "";
    return;
  }

  let html =
    '<button class="page-btn" id="prevPage"><i class="fas fa-chevron-left"></i></button>';
  for (let i = 1; i <= totalPages; i++) {
    html += `<button class="page-btn ${i === currentPage ? "active" : ""}" data-page="${i}">${i}</button>`;
  }
  html +=
    '<button class="page-btn" id="nextPage"><i class="fas fa-chevron-right"></i></button>';
  paginationDiv.innerHTML = html;

  // Pagination event listeners
  document.querySelectorAll(".page-btn[data-page]").forEach((btn) => {
    btn.addEventListener("click", function () {
      currentPage = parseInt(this.dataset.page);
      applyFilters();
    });
  });
  document.getElementById("prevPage")?.addEventListener("click", function () {
    if (currentPage > 1) {
      currentPage--;
      applyFilters();
    }
  });
  document.getElementById("nextPage")?.addEventListener("click", function () {
    if (currentPage < totalPages) {
      currentPage++;
      applyFilters();
    }
  });
}

function updateStatCardsActive() {
  document
    .querySelectorAll(".stat-card")
    .forEach((card) => card.classList.remove("active"));
  if (currentRoleFilter === "all") {
    document.getElementById("statAll").classList.add("active");
  } else if (currentRoleFilter === "student") {
    document.getElementById("statStudents").classList.add("active");
  } else if (currentRoleFilter === "instructor") {
    document.getElementById("statInstructors").classList.add("active");
  }
}

// ========== User Actions ==========

function editUser(userId) {
  const user = usersData.find((u) => u.id == userId);
  if (!user) return;

  document.getElementById("editName").value =
    `${user.first_name || ""} ${user.last_name || ""}`.trim();
  document.getElementById("editEmail").value = user.email || "";
  document.getElementById("editId").value = user.user_id || "";
  document.getElementById("editPassword").value = ""; // clear previous value
  document.getElementById("editRole").value = user.role || "student";
  document.getElementById("editDept").value = user.department || "";
  document.getElementById("editStatus").value = user.status || "active";

  // Store userId for save
  document.getElementById("saveEdit").dataset.userId = userId;

  document.getElementById("editModal").classList.add("active");
}

function closeEditModal() {
  document.getElementById("editModal").classList.remove("active");
}

function saveEdit() {
  const userId = document.getElementById("saveEdit").dataset.userId;
  const nameParts = document.getElementById("editName").value.trim().split(" ");
  const firstName = nameParts[0] || "";
  const lastName = nameParts.slice(1).join(" ") || "";

  const updatedData = {
    id: userId,
    first_name: firstName,
    last_name: lastName,
    email: document.getElementById("editEmail").value,
    role: document.getElementById("editRole").value,
    department: document.getElementById("editDept").value,
    status: document.getElementById("editStatus").value,
  };
  const newPassword = document.getElementById("editPassword").value;
  if (newPassword.trim() !== "") {
    updatedData.password = newPassword;
  }

  fetch("../../api/admin/update-user.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Authorization: "Bearer " + (localStorage.getItem("apiToken") || ""),
    },
    body: JSON.stringify(updatedData),
  })
    .then((response) => response.json())
    .then((result) => {
      if (result.success) {
        alert("✅ User updated successfully!");
        closeEditModal();
        loadUsers(); // refresh list
      } else {
        alert("❌ Error: " + result.message);
      }
    })
    .catch((error) => {
      console.error("Error updating user:", error);
      alert("❌ Failed to update user.");
    });
}

function toggleUserStatus(userId) {
  const user = usersData.find((u) => u.id == userId);
  if (!user) return;

  const newStatus = user.status === "active" ? "inactive" : "active";
  const action = newStatus === "active" ? "unblock" : "block";
  if (!confirm(`Are you sure you want to ${action} this user?`)) return;

  fetch("../../api/admin/toggle-user-status.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Authorization: "Bearer " + (localStorage.getItem("apiToken") || ""),
    },
    body: JSON.stringify({ user_id: userId, status: newStatus }),
  })
    .then((response) => response.json())
    .then((result) => {
      if (result.success) {
        alert(`✅ User ${action}ed successfully!`);
        loadUsers(); // refresh
      } else {
        alert("❌ Error: " + result.message);
      }
    })
    .catch((error) => {
      console.error("Error toggling status:", error);
      alert("❌ Failed to update status.");
    });
}

function deleteUser(userId) {
  if (
    !confirm(
      "Are you sure you want to delete this user? This action cannot be undone.",
    )
  )
    return;

  fetch("../../api/admin/delete-user.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Authorization: "Bearer " + (localStorage.getItem("apiToken") || ""),
    },
    body: JSON.stringify({ user_id: userId }),
  })
    .then((response) => response.json())
    .then((result) => {
      if (result.success) {
        alert("✅ User deleted successfully!");
        loadUsers(); // refresh
      } else {
        alert("❌ Error: " + result.message);
      }
    })
    .catch((error) => {
      console.error("Error deleting user:", error);
      alert("❌ Failed to delete user.");
    });
}
