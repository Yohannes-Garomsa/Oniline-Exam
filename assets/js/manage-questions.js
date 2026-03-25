// manage-questions.js

document.addEventListener("DOMContentLoaded", function () {
  const currentUser = checkAuth();
  if (!currentUser || currentUser.role !== "instructor") {
    window.location.href = "../auth/login.html";
    return;
  }
  updateProfileInfo(currentUser);
  setFooterDate();
  loadCourses();
  loadQuestions();
  setupEventListeners();
});

let questions = [];
let courses = [];
let currentPage = 1;
let totalPages = 1;
let currentCourseFilter = "all";
let currentTypeFilter = "all";
let currentDifficultyFilter = "all";
let searchTerm = "";

function updateProfileInfo(user) {
  const fullName =
    user.name ||
    (user.first_name && user.last_name
      ? `${user.first_name} ${user.last_name}`
      : "Instructor");
  const initials = fullName
    .split(" ")
    .map((n) => n[0])
    .join("")
    .substring(0, 2)
    .toUpperCase();
  document.getElementById("userInitials").textContent = initials;
  document.getElementById("userName").textContent = fullName;
  document.getElementById("userEmail").textContent = user.email || "";
}

function setFooterDate() {
  document.getElementById("footerDate").textContent =
    new Date().toLocaleDateString();
}

function setupEventListeners() {
  document
    .getElementById("courseFilter")
    .addEventListener("change", applyFilters);
  document
    .getElementById("typeFilter")
    .addEventListener("change", applyFilters);
  document
    .getElementById("difficultyFilter")
    .addEventListener("change", applyFilters);
  document
    .getElementById("searchFilter")
    .addEventListener("input", function () {
      searchTerm = this.value.toLowerCase();
      document.getElementById("searchInput").value = this.value;
      applyFilters();
    });
  document.getElementById("searchInput").addEventListener("input", function () {
    document.getElementById("searchFilter").value = this.value;
    searchTerm = this.value.toLowerCase();
    applyFilters();
  });
  document.querySelectorAll(".stat-card").forEach((card) => {
    card.addEventListener("click", function () {
      document.getElementById("typeFilter").value = this.dataset.filter;
      currentTypeFilter = this.dataset.filter;
      applyFilters();
      updateStatCardsActive();
    });
  });
  document
    .getElementById("addQuestionBtn")
    .addEventListener("click", openAddModal);
  document.getElementById("closeModal").addEventListener("click", closeModal);
  document.getElementById("cancelModal").addEventListener("click", closeModal);
  document
    .getElementById("saveQuestion")
    .addEventListener("click", saveQuestion);
  document
    .getElementById("importQuestionFile")
    .addEventListener("change", handleImportFile);
  window.addEventListener("click", (e) => {
    if (e.target.classList.contains("modal")) closeModal();
  });
  document.getElementById("logoutBtn").addEventListener("click", (e) => {
    e.preventDefault();
    if (confirm("Logout?")) logout();
  });

  document
    .getElementById("questionType")
    .addEventListener("change", toggleQuestionType);
  document
    .getElementById("addOptionBtn")
    .addEventListener("click", addOptionField);
}

function loadCourses() {
  fetch("../../api/instructor/get-courses.php", { credentials: "include" })
    .then((res) => res.json())
    .then((result) => {
      if (result.success) {
        courses = result.courses;
        populateCourseFilters();
        populateCourseDropdown();
      } else {
        console.error("Failed to load courses");
      }
    })
    .catch((err) => console.error("Error loading courses:", err));
}

function populateCourseFilters() {
  const select = document.getElementById("courseFilter");
  select.innerHTML = '<option value="all">All Courses</option>';
  courses.forEach((c) => {
    select.innerHTML += `<option value="${c.id}">${c.course_code} - ${c.course_name}</option>`;
  });
}

function populateCourseDropdown() {
  const select = document.getElementById("questionCourse");
  select.innerHTML = '<option value="">Select Course</option>';
  courses.forEach((c) => {
    select.innerHTML += `<option value="${c.id}">${c.course_code} - ${c.course_name}</option>`;
  });
}

function loadQuestions() {
  const tbody = document.getElementById("questionsTableBody");
  tbody.innerHTML =
    '<tr><td colspan="5" style="text-align:center;">Loading questions...</td></tr>';
  fetch("../../api/instructor/get-questions.php", { credentials: "include" })
    .then((res) => res.json())
    .then((result) => {
      if (result.success) {
        questions = result.questions;
        updateStats();
        applyFilters();
      } else {
        tbody.innerHTML =
          '<tr><td colspan="5" style="text-align:center;">Failed to load questions.</td></tr>';
      }
    })
    .catch(() => {
      tbody.innerHTML =
        '<tr><td colspan="5" style="text-align:center;">Error loading questions.</td></tr>';
    });
}

function updateStats() {
  document.getElementById("totalQuestions").textContent = questions.length;
  document.getElementById("mcqCount").textContent = questions.filter(
    (q) => q.question_type === "MCQ",
  ).length;
  document.getElementById("tfCount").textContent = questions.filter(
    (q) => q.question_type === "True/False",
  ).length;
}

// handle import file input in question bank page
function handleImportFile(e) {
  const file = e.target.files[0];
  if (!file) return;
  const form = new FormData();
  form.append("file", file);
  fetch("../../api/instructor/parse-questions-file.php", {
    method: "POST",
    credentials: "include",
    body: form,
  })
    .then((r) => r.json())
    .then((data) => {
      if (data.success) {
        let questionsToImport = data.questions || [];
        // if filtering by a specific course, assign that course to any question missing one
        const courseFilter = document.getElementById("courseFilter").value;
        if (courseFilter && courseFilter !== "all") {
          questionsToImport = questionsToImport.map((q) => {
            if (!q.course_id) q.course_id = parseInt(courseFilter);
            return q;
          });
        }
        renderImportPreview(questionsToImport, file.name);
        // if any question still missing course_id, abort and ask user to set filter or use CSV
        const missingCourse = questionsToImport.some((q) => !q.course_id);
        if (missingCourse) {
          alert(
            "Some questions lack a course assignment; please select a specific course filter or provide course_id in the file.",
          );
          return;
        }
        // immediately import
        fetch("../../api/instructor/import-questions.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          credentials: "include",
          body: JSON.stringify({ questions: questionsToImport }),
        })
          .then((r2) => r2.json())
          .then((res) => {
            if (res.success) {
              alert("Imported " + res.imported + " questions");
              if (typeof loadQuestions === "function") loadQuestions();
            } else {
              alert("Import failed: " + (res.message || ""));
            }
            // clear file input and preview
            document.getElementById("importQuestionFile").value = "";
            renderImportPreview([], "");
          });
      } else {
        alert("Parse error: " + (data.message || "unknown"));
      }
    })
    .catch((err) => {
      console.error(err);
      alert("File parse request failed");
    });
}

function renderImportPreview(questionsArray, filename) {
  const container = document.getElementById("importPreview");
  if (!questionsArray.length) {
    container.style.display = "none";
    container.innerHTML = "";
    return;
  }
  let html = `<strong>Parsed ${questionsArray.length} questions from ${filename}.</strong><button id="clearImport" style="float:right;">Clear</button><ul>`;
  questionsArray.forEach((q) => {
    html += `<li>${q.question} <small>(${q.type})</small></li>`;
  });
  html += "</ul>";
  container.innerHTML = html;
  container.style.display = "block";
  document.getElementById("clearImport").addEventListener("click", function () {
    document.getElementById("importQuestionFile").value = "";
    renderImportPreview([], "");
  });
}

function applyFilters() {
  currentCourseFilter = document.getElementById("courseFilter").value;
  currentTypeFilter = document.getElementById("typeFilter").value;
  currentDifficultyFilter = document.getElementById("difficultyFilter").value;
  searchTerm = document.getElementById("searchFilter").value.toLowerCase();

  let filtered = questions.filter((q) => {
    const courseMatch =
      currentCourseFilter === "all" || q.course_id == currentCourseFilter;
    const typeMatch =
      currentTypeFilter === "all" || q.question_type === currentTypeFilter;
    const difficultyMatch =
      currentDifficultyFilter === "all" ||
      q.difficulty === currentDifficultyFilter;
    const searchMatch =
      q.question_text.toLowerCase().includes(searchTerm) ||
      (q.course_name && q.course_name.toLowerCase().includes(searchTerm));
    return courseMatch && typeMatch && difficultyMatch && searchMatch;
  });

  totalPages = Math.ceil(filtered.length / 10);
  if (currentPage > totalPages) currentPage = totalPages || 1;
  const start = (currentPage - 1) * 10;
  const paginated = filtered.slice(start, start + 10);
  renderTable(paginated);
  renderPagination();
  updateStatCardsActive();
  document.getElementById("emptyMessage").style.display = filtered.length
    ? "none"
    : "block";
}

function renderTable(questionsList) {
  const tbody = document.getElementById("questionsTableBody");
  if (!questionsList.length) {
    tbody.innerHTML =
      '<tr><td colspan="5" style="text-align:center;">No questions found.</td></tr>';
    return;
  }
  let html = "";
  questionsList.forEach((q) => {
    html += `<tr data-id="${q.id}">
            <td>${q.question_text.substring(0, 60)}${q.question_text.length > 60 ? "..." : ""}</td>
            <td>${q.course_code || ""}</td>
            <td><span class="type-badge">${q.question_type}</span></td>
            <td><span class="difficulty-badge ${q.difficulty}">${q.difficulty}</span></td>
            <td class="action-cell">
                <button class="btn-icon edit-btn" title="Edit"><i class="fas fa-edit"></i></button>
                <button class="btn-icon delete-btn" title="Delete"><i class="fas fa-trash"></i></button>
            </td>
        </tr>`;
  });
  tbody.innerHTML = html;
  document.querySelectorAll(".edit-btn").forEach((btn) => {
    btn.addEventListener("click", (e) =>
      openEditModal(e.target.closest("tr").dataset.id),
    );
  });
  document.querySelectorAll(".delete-btn").forEach((btn) => {
    btn.addEventListener("click", (e) =>
      deleteQuestion(e.target.closest("tr").dataset.id),
    );
  });
}

function renderPagination() {
  const div = document.getElementById("pagination");
  if (totalPages <= 1) {
    div.innerHTML = "";
    return;
  }
  let html =
    '<button class="page-btn" id="prevPage"><i class="fas fa-chevron-left"></i></button>';
  for (let i = 1; i <= totalPages; i++) {
    html += `<button class="page-btn ${i === currentPage ? "active" : ""}" data-page="${i}">${i}</button>`;
  }
  html +=
    '<button class="page-btn" id="nextPage"><i class="fas fa-chevron-right"></i></button>';
  div.innerHTML = html;
  document.querySelectorAll(".page-btn[data-page]").forEach((btn) => {
    btn.addEventListener("click", function () {
      currentPage = parseInt(this.dataset.page);
      applyFilters();
    });
  });
  document.getElementById("prevPage")?.addEventListener("click", () => {
    if (currentPage > 1) {
      currentPage--;
      applyFilters();
    }
  });
  document.getElementById("nextPage")?.addEventListener("click", () => {
    if (currentPage < totalPages) {
      currentPage++;
      applyFilters();
    }
  });
}

function updateStatCardsActive() {
  document
    .querySelectorAll(".stat-card")
    .forEach((c) => c.classList.remove("active"));
  if (currentTypeFilter === "all")
    document.getElementById("statAll").classList.add("active");
  else if (currentTypeFilter === "MCQ")
    document.getElementById("statMCQ").classList.add("active");
  else if (currentTypeFilter === "True/False")
    document.getElementById("statTF").classList.add("active");
}

function toggleQuestionType() {
  const type = document.getElementById("questionType").value;
  if (type === "MCQ") {
    document.getElementById("optionsArea").style.display = "block";
    document.getElementById("trueFalseArea").style.display = "none";
  } else {
    document.getElementById("optionsArea").style.display = "none";
    document.getElementById("trueFalseArea").style.display = "block";
  }
}

function addOptionField() {
  const container = document.getElementById("optionsContainer");
  const optionCount = container.children.length;
  const div = document.createElement("div");
  div.className = "option-row";
  div.innerHTML = `
        <input type="text" class="form-control option-text" placeholder="Option text" required>
        <input type="radio" name="correctOption" class="option-correct" value="${optionCount}">
        <button type="button" class="btn-remove-option"><i class="fas fa-times"></i></button>
    `;
  div
    .querySelector(".btn-remove-option")
    .addEventListener("click", function () {
      div.remove();
      Array.from(container.children).forEach((child, idx) => {
        child.querySelector(".option-correct").value = idx;
      });
    });
  container.appendChild(div);
}

function openAddModal() {
  document.getElementById("modalTitle").textContent = "Create New Question";
  document.getElementById("questionForm").reset();
  document.getElementById("optionsContainer").innerHTML = "";
  for (let i = 0; i < 4; i++) addOptionField();
  toggleQuestionType();
  document.getElementById("questionModal").classList.add("active");
  delete document.getElementById("saveQuestion").dataset.id;
}

function openEditModal(id) {
  fetch(`../../api/instructor/get-questions.php?id=${id}`, {
    credentials: "include",
  })
    .then((res) => res.json())
    .then((result) => {
      if (result.success) {
        const q = result.question;
        document.getElementById("modalTitle").textContent = "Edit Question";
        document.getElementById("questionText").value = q.question_text;
        document.getElementById("questionCourse").value = q.course_id;
        document.getElementById("questionType").value = q.question_type;
        document.getElementById("questionDifficulty").value = q.difficulty;
        document.getElementById("questionExplanation").value =
          q.explanation || "";

        if (q.question_type === "MCQ") {
          document.getElementById("optionsContainer").innerHTML = "";
          if (q.options && q.options.length) {
            q.options.forEach((opt, index) => {
              const div = document.createElement("div");
              div.className = "option-row";
              div.innerHTML = `
                                <input type="text" class="form-control option-text" value="${opt.option_text}" required>
                                <input type="radio" name="correctOption" class="option-correct" value="${index}" ${opt.is_correct ? "checked" : ""}>
                                <button type="button" class="btn-remove-option"><i class="fas fa-times"></i></button>
                            `;
              div
                .querySelector(".btn-remove-option")
                .addEventListener("click", function () {
                  div.remove();
                });
              document.getElementById("optionsContainer").appendChild(div);
            });
          } else {
            for (let i = 0; i < 4; i++) addOptionField();
          }
        } else {
          // True/False
          const correctValue =
            q.options && q.options[0] && q.options[0].is_correct
              ? "true"
              : "false";
          document.querySelector(
            'input[name="tfCorrect"][value="true"]',
          ).checked = correctValue === "true";
          document.querySelector(
            'input[name="tfCorrect"][value="false"]',
          ).checked = correctValue === "false";
        }
        toggleQuestionType();
        document.getElementById("saveQuestion").dataset.id = id;
        document.getElementById("questionModal").classList.add("active");
      } else {
        alert("Error loading question");
      }
    })
    .catch((err) => console.error(err));
}

function closeModal() {
  document.getElementById("questionModal").classList.remove("active");
}

function saveQuestion() {
  const type = document.getElementById("questionType").value;
  const data = {
    question_text: document.getElementById("questionText").value,
    course_id: parseInt(document.getElementById("questionCourse").value),
    question_type: type,
    difficulty: document.getElementById("questionDifficulty").value,
    explanation: document.getElementById("questionExplanation").value,
  };

  if (type === "MCQ") {
    const options = [];
    const optionRows = document.querySelectorAll(".option-row");
    optionRows.forEach((row, index) => {
      const text = row.querySelector(".option-text").value;
      const isCorrect = row.querySelector(".option-correct").checked;
      options.push({ text, is_correct: isCorrect });
    });
    data.options = options;
  } else {
    const correct = document.querySelector(
      'input[name="tfCorrect"]:checked',
    )?.value;
    if (!correct) {
      alert("Please select the correct answer (True/False).");
      return;
    }
    data.correct_answer = correct;
  }

  if (!data.question_text || !data.course_id) {
    alert("Please fill required fields.");
    return;
  }

  const id = document.getElementById("saveQuestion").dataset.id;
  const url = id
    ? "../../api/instructor/update-question.php"
    : "../../api/instructor/create-question.php";
  if (id) data.id = id;

  fetch(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    credentials: "include",
    body: JSON.stringify(data),
  })
    .then((res) => res.json())
    .then((result) => {
      if (result.success) {
        alert(id ? "Question updated" : "Question created");
        closeModal();
        loadQuestions();
      } else {
        alert("Error: " + (result.message || "Unknown error"));
      }
    })
    .catch(() => alert("Request failed"));
}

function deleteQuestion(id) {
  if (!confirm("Delete this question? This cannot be undone.")) return;
  fetch(`../../api/instructor/delete-question.php?id=${id}`, {
    method: "DELETE",
    credentials: "include",
  })
    .then((res) => res.json())
    .then((result) => {
      if (result.success) {
        alert("Question deleted");
        loadQuestions();
      } else {
        alert("Error: " + result.message);
      }
    })
    .catch(() => alert("Delete failed"));
}
