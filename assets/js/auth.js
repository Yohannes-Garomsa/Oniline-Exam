// auth.js - Shared functionality for login and registration

document.addEventListener('DOMContentLoaded', function() {
    // --- Toggle password visibility (common) ---
    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function(e) {
            const passwordField = this.previousElementSibling;
            const icon = this.querySelector('i');
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });

    // --- Role toggle for registration page ---
    const roleStudent = document.getElementById('roleStudent');
    const roleInstructor = document.getElementById('roleInstructor');
    const studentFields = document.getElementById('studentFields');
    const instructorFields = document.getElementById('instructorFields');

    if (roleStudent && roleInstructor) {
        function toggleRoleFields() {
            const isStudent = roleStudent.checked;
            studentFields.style.display = isStudent ? 'block' : 'none';
            instructorFields.style.display = isStudent ? 'none' : 'block';

            // Set required attributes accordingly
            document.getElementById('studentId').required = isStudent;
            document.getElementById('department').required = isStudent;
            document.getElementById('year').required = isStudent;
            document.getElementById('section').required = isStudent;

            document.getElementById('employeeId').required = !isStudent;
            document.getElementById('instructorDept').required = !isStudent;
            document.getElementById('qualification').required = !isStudent;
            document.getElementById('experience').required = !isStudent;
        }

        roleStudent.addEventListener('change', toggleRoleFields);
        roleInstructor.addEventListener('change', toggleRoleFields);
        toggleRoleFields(); // initial call
    }

    // --- Login form submission ---
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging in...';
            submitBtn.disabled = true;

            const data = {
                email: document.getElementById('email').value,
                password: document.getElementById('password').value,
                role: document.querySelector('input[name="role"]:checked').value
            };

            fetch('/api/auth/login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    sessionStorage.setItem('user', JSON.stringify(result.user));
                    if (result.role === 'student') window.location.href = '../student/dashboard.html';
                    else if (result.role === 'instructor') window.location.href = '../instructor/dashboard.html';
                    else if (result.role === 'admin') window.location.href = '../admin/dashboard.html';
                } else {
                    alert('❌ ' + result.message);
                }
            })
            .catch(error => {
                alert('Error connecting to server. Please try again.');
                console.error(error);
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    }

    // --- Registration form submission ---
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating account...';
            submitBtn.disabled = true;

            const role = document.querySelector('input[name="role"]:checked').value;

            // Base data
            const data = {
                first_name: document.getElementById('firstName').value.trim(),
                last_name: document.getElementById('lastName').value.trim(),
                email: document.getElementById('email').value.trim(),
                password: document.getElementById('password').value,
                phone: document.getElementById('phone').value.trim(),
                role: role
            };

            // Role-specific fields
            if (role === 'student') {
                data.student_id = document.getElementById('studentId').value.trim();
                data.department = document.getElementById('department').value;
                data.year = parseInt(document.getElementById('year').value);
                data.section = document.getElementById('section').value;
                const gpa = document.getElementById('gpa').value;
                if (gpa) data.gpa = parseFloat(gpa);
            } else {
                data.employee_id = document.getElementById('employeeId').value.trim();
                data.department = document.getElementById('instructorDept').value;
                data.qualification = document.getElementById('qualification').value;
                data.experience = parseInt(document.getElementById('experience').value);
            }

            // Password match
            const confirmPass = document.getElementById('confirmPassword').value;
            if (data.password !== confirmPass) {
                alert('❌ Passwords do not match!');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                return;
            }

             fetch('/api/auth/login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',          // <-- add this
                    body: JSON.stringify(data)
             })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert('✅ Registration successful! Please login.');
                    window.location.href = 'login.html';
                } else {
                    alert('❌ ' + result.message);
                }
            })
            .catch(error => {
                alert('Error connecting to server. Please try again.');
                console.error(error);
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    }
});