// Check if user is logged in
function checkAuth() {
    // First, try to get from sessionStorage (fast)
    const storedUser = sessionStorage.getItem('user');
    if (storedUser) {
        try {
            return JSON.parse(storedUser);
        } catch (e) {
            // invalid JSON, ignore
        }
    }
    return null;
}

// Get current user
function getCurrentUser() {
    const user = sessionStorage.getItem('user');
    return user ? JSON.parse(user) : null;
}
function verifyAuth() {
    return fetch('/api/auth/validate.php', {
        credentials: 'include'
    })
    .then(response => response.json())
    .then(data => {
        if (data.authenticated) {
            sessionStorage.setItem('user', JSON.stringify(data.user));
            return data.user;
        } else {
            sessionStorage.removeItem('user');
            return null;
        }
    })
    .catch(() => {
        sessionStorage.removeItem('user');
        return null;
    });
}

// Logout function
function logout() {
    return fetch('/api/auth/logout.php', {
        method: 'POST',
        credentials: 'include'
    })
    .then(() => {
        sessionStorage.removeItem('user');
        window.location.href = '../auth/login.html';
    })
    .catch(() => {
        sessionStorage.removeItem('user');
        window.location.href = '../auth/login.html';
    });
}

// Add to all protected pages
document.addEventListener('DOMContentLoaded', function() {
    // Check if current page is not login or register
    const currentPage = window.location.pathname;
    if(!currentPage.includes('login.html') && !currentPage.includes('register.html')) {
        checkAuth();
    }
});