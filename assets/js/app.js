// assets/js/app.js - LX Frontend Logic

// Global state
let currentUser = null;
let activeScreen = 'dashboard';
let friendsCache = [];
let currentFriendId = null; // For friend profile view

// Format helper functions
function formatCurrency(amount) {
    return 'Rs. ' + parseFloat(amount).toLocaleString('en-US', { 
        minimumFractionDigits: 2, 
        maximumFractionDigits: 2 
    });
}

function formatDate(dateStr) {
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    const options = { day: 'numeric', month: 'short', year: 'numeric' };
    return d.toLocaleDateString('en-US', options);
}

// Service worker registration and update notification
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('./sw.js')
            .then(reg => {
                console.log('Service Worker registered.', reg.scope);
                
                // Monitor for updates to prompt reload
                reg.addEventListener('updatefound', () => {
                    const newWorker = reg.installing;
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            console.log('New update installed. Reloading...');
                            showAlert('success', 'App updated! Reloading...');
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                        }
                    });
                });
            })
            .catch(err => console.warn('Service Worker registration failed.', err));
    });
}

// Document ready entry point
document.addEventListener('DOMContentLoaded', () => {
    checkAuthStatus();
    setupEventListeners();
});

// Check login status and configure views
function checkAuthStatus() {
    fetch('api.php?action=status')
        .then(res => {
            if (!res.ok) {
                // Read JSON error message if possible
                return res.json().then(errData => {
                    throw new Error(errData.error || `Server error (${res.status})`);
                }).catch(() => {
                    throw new Error(`Server returned status ${res.status}. Check database connection details.`);
                });
            }
            return res.json();
        })
        .then(data => {
            if (data.logged_in) {
                currentUser = data.username;
                showAppShell();
                // Handle initial routing based on URL hash
                const hash = window.location.hash.substring(1) || 'dashboard';
                navigateTo(hash);
            } else if (data.setup_required) {
                showScreen('view-setup');
            } else {
                showScreen('view-login');
            }
        })
        .catch(err => {
            console.error('Error checking auth status:', err);
            // Show persistent message instead of transient alert so they can read DB config issue
            const viewLogin = document.getElementById('view-login');
            const viewSetup = document.getElementById('view-setup');
            
            // Render friendly overlay in login/setup screen
            const errorHtml = `
                <div class="alert alert-danger glass-card m-3 p-4 shadow-lg text-center" style="max-width: 450px; border-radius: 20px;">
                    <i class="bi bi-database-fill-exclamation fs-1 d-block mb-3 text-danger"></i>
                    <h4 class="fw-bold">Database Connection Error</h4>
                    <p class="small text-white-50">${escapeHTML(err.message)}</p>
                    <hr class="border-secondary opacity-25">
                    <p class="small mb-0">Please edit <code>config.php</code> on your server to input the correct Plesk database credentials.</p>
                </div>
            `;
            
            viewLogin.innerHTML = errorHtml;
            viewLogin.style.display = 'flex';
            viewSetup.style.display = 'none';
        });
}

// Show/hide screen views
function showScreen(screenId) {
    document.querySelectorAll('.auth-wrapper, #view-app').forEach(el => {
        el.style.display = 'none';
    });
    
    if (screenId === 'view-app') {
        document.getElementById('view-app').style.display = 'block';
    } else {
        const target = document.getElementById(screenId);
        if (target) target.style.display = 'flex';
    }
}

function showAppShell() {
    showScreen('view-app');
    document.getElementById('display-username').textContent = currentUser;
}

// Router navigation logic
function navigateTo(screenName) {
    // Check if we are viewing a friend profile which needs parameters
    if (screenName.startsWith('friend-profile/')) {
        const id = parseInt(screenName.split('/')[1]);
        if (id) {
            currentFriendId = id;
            loadFriendProfile(id);
            window.location.hash = screenName;
            return;
        }
    }
    
    activeScreen = screenName;
    window.location.hash = screenName;
    
    // Update bottom nav highlights
    document.querySelectorAll('.bottom-nav-item').forEach(item => {
        const href = item.getAttribute('href');
        if (href === '#' + screenName) {
            item.classList.add('active');
        } else {
            item.classList.remove('active');
        }
    });

    // Hide all screens
    document.querySelectorAll('.page-view').forEach(screen => {
        screen.classList.remove('active');
    });

    // Display active screen
    const activeEl = document.getElementById(`screen-${screenName}`);
    if (activeEl) {
        activeEl.classList.add('active');
        
        // Trigger data loads per screen
        if (screenName === 'dashboard') loadDashboardData();
        else if (screenName === 'friends') loadFriendsList();
        else if (screenName === 'transactions') loadTransactionsList();
        else if (screenName === 'reports') loadReportsData();
    }
}

// Event Listeners Configuration
function setupEventListeners() {
    // Navigation items
    document.querySelectorAll('.bottom-nav-item').forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            const target = item.getAttribute('href').substring(1);
            navigateTo(target);
        });
    });

    // Hash change handler
    window.addEventListener('hashchange', () => {
        const hash = window.location.hash.substring(1) || 'dashboard';
        if (hash !== activeScreen && !hash.startsWith('friend-profile/')) {
            navigateTo(hash);
        } else if (hash.startsWith('friend-profile/')) {
            navigateTo(hash);
        }
    });

    // FAB menu toggle
    const fabContainer = document.getElementById('fab-container');
    const fabBtn = document.getElementById('fab-btn');
    
    fabBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        fabContainer.classList.toggle('open');
    });

    document.addEventListener('click', () => {
        fabContainer.classList.remove('open');
    });

    // Quick Action Triggers
    document.getElementById('action-lend').addEventListener('click', () => {
        openTransactionModal('lend');
    });

    document.getElementById('action-repay').addEventListener('click', () => {
        openTransactionModal('repayment');
    });

    document.getElementById('action-add-friend').addEventListener('click', () => {
        openAddFriendModal();
    });

    // Forms Form-Submissions
    document.getElementById('form-setup').addEventListener('submit', handleSetupSubmit);
    document.getElementById('form-login').addEventListener('submit', handleLoginSubmit);
    document.getElementById('form-add-friend').addEventListener('submit', handleAddFriendSubmit);
    document.getElementById('form-transaction').addEventListener('submit', handleTransactionSubmit);
    
    // Logout btn
    document.getElementById('btn-logout').addEventListener('click', handleLogout);

    // Filter Badges
    document.querySelectorAll('.filter-badge').forEach(badge => {
        badge.addEventListener('click', () => {
            document.querySelectorAll('.filter-badge').forEach(b => b.classList.remove('active'));
            badge.classList.add('active');
            loadTransactionsList();
        });
    });

    // Search input typing timer
    let searchTimer;
    document.getElementById('search-transaction').addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            loadTransactionsList();
        }, 300);
    });
}

// --- Auth Submit Handlers ---
function handleSetupSubmit(e) {
    e.preventDefault();
    
    const usernameInput = document.getElementById('setup-username');
    const passwordInput = document.getElementById('setup-password');
    
    const username = usernameInput.value.trim();
    const password = passwordInput.value;
    
    if (!username) {
        showAlert('danger', 'Username is required.');
        usernameInput.focus();
        return;
    }
    
    if (!password) {
        showAlert('danger', 'Password is required.');
        passwordInput.focus();
        return;
    }
    
    if (password.length < 6) {
        showAlert('danger', 'Password must be at least 6 characters.');
        passwordInput.focus();
        return;
    }
    
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Initializing...';
    
    const fd = new FormData(this);
    
    fetch('api.php?action=setup', {
        method: 'POST',
        body: fd
    })
    .then(res => {
        if (!res.ok) {
            return res.json().then(errData => {
                throw new Error(errData.error || 'Setup failed.');
            }).catch(() => {
                throw new Error(`Setup failed with status ${res.status}`);
            });
        }
        return res.json();
    })
    .then(data => {
        if (data.success) {
            showAlert('success', 'Admin user registered.');
            checkAuthStatus();
        } else {
            showAlert('danger', data.error || 'Failed to complete setup.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    })
    .catch(err => {
        console.error(err);
        showAlert('danger', err.message || 'Failed to communicate with setup endpoint.');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}

function handleLoginSubmit(e) {
    e.preventDefault();
    
    const usernameInput = document.getElementById('login-username');
    const passwordInput = document.getElementById('login-password');
    
    const username = usernameInput.value.trim();
    const password = passwordInput.value;
    
    if (!username) {
        showAlert('danger', 'Username is required.');
        usernameInput.focus();
        return;
    }
    
    if (!password) {
        showAlert('danger', 'Password is required.');
        passwordInput.focus();
        return;
    }
    
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Logging in...';
    
    const fd = new FormData(this);
    
    fetch('api.php?action=login', {
        method: 'POST',
        body: fd
    })
    .then(res => {
        if (!res.ok) {
            return res.json().then(errData => {
                throw new Error(errData.error || 'Invalid username or password.');
            }).catch(() => {
                throw new Error(`Server returned status ${res.status}`);
            });
        }
        return res.json();
    })
    .then(data => {
        if (data.success) {
            checkAuthStatus();
        } else {
            showAlert('danger', data.error || 'Invalid credentials.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    })
    .catch(err => {
        console.error(err);
        showAlert('danger', err.message || 'Login failed. Please retry.');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}

function handleLogout() {
    if (confirm('Are you sure you want to logout?')) {
        fetch('api.php?action=logout')
            .then(res => res.json())
            .then(() => {
                currentUser = null;
                window.location.hash = '';
                checkAuthStatus();
            });
    }
}

// --- Data Loading Handlers ---

// Dashboard
function loadDashboardData() {
    fetch('api.php?action=dashboard_stats')
        .then(res => res.json())
        .then(stats => {
            document.getElementById('stat-total-lent').textContent = formatCurrency(stats.total_lent);
            document.getElementById('stat-total-recovered').textContent = formatCurrency(stats.total_recovered);
            document.getElementById('stat-outstanding').textContent = formatCurrency(stats.outstanding_balance);
            document.getElementById('stat-active-friends').textContent = stats.active_friends;
        })
        .catch(err => console.error('Dashboard Stats Error:', err));
}

// Friends List
function loadFriendsList() {
    fetch('api.php?action=friends_list')
        .then(res => res.json())
        .then(friends => {
            friendsCache = friends; // Save for dropdown populating
            const listContainer = document.getElementById('friends-container');
            listContainer.innerHTML = '';
            
            if (friends.length === 0) {
                listContainer.innerHTML = `
                    <div class="text-center py-5 text-muted-custom">
                        <i class="bi bi-people fs-1 d-block mb-3"></i>
                        <p>No friends added yet. Add a friend to start tracking!</p>
                        <button class="glass-btn glass-btn-primary mt-2" onclick="openAddFriendModal()">
                            <i class="bi bi-person-plus"></i> Add Friend
                        </button>
                    </div>
                `;
                return;
            }
            
            friends.forEach(f => {
                const initial = f.name.charAt(0).toUpperCase();
                let balanceBadge = '';
                
                if (f.balance > 0) {
                    balanceBadge = `<span class="text-danger fw-bold">${formatCurrency(f.balance)}</span>`;
                } else if (f.balance < 0) {
                    balanceBadge = `<span class="text-success fw-bold">Prepaid: ${formatCurrency(Math.abs(f.balance))}</span>`;
                } else {
                    balanceBadge = `<span class="text-muted fw-bold">Settled</span>`;
                }

                const item = document.createElement('div');
                item.className = 'glass-card friend-item';
                item.innerHTML = `
                    <div class="d-flex align-items-center gap-3">
                        <div class="friend-avatar">${initial}</div>
                        <div>
                            <div class="fw-bold fs-5">${escapeHTML(f.name)}</div>
                            <div class="small text-muted-custom">Lent: ${formatCurrency(f.total_lent)} • Paid: ${formatCurrency(f.total_repaid)}</div>
                        </div>
                    </div>
                    <div class="text-end">
                        ${balanceBadge}
                        <i class="bi bi-chevron-right ms-2 text-muted-custom"></i>
                    </div>
                `;
                
                item.addEventListener('click', () => {
                    navigateTo(`friend-profile/${f.id}`);
                });
                listContainer.appendChild(item);
            });
        })
        .catch(err => console.error('Friends Load Error:', err));
}

// Friend Profile Detail
function loadFriendProfile(friendId) {
    // Instantly hide nav tabs styling
    document.querySelectorAll('.bottom-nav-item').forEach(item => item.classList.remove('active'));
    
    // Hide screens
    document.querySelectorAll('.page-view').forEach(screen => screen.classList.remove('active'));
    const profileScreen = document.getElementById('screen-friend-profile');
    profileScreen.classList.add('active');

    fetch(`api.php?action=friend_profile&id=${friendId}`)
        .then(res => res.json())
        .then(data => {
            if (data.error) {
                showAlert('danger', data.error);
                navigateTo('friends');
                return;
            }
            
            const friend = data.friend;
            const timeline = data.timeline;
            
            // Render Friend Details
            document.getElementById('profile-name').textContent = friend.name;
            document.getElementById('profile-total-lent').textContent = formatCurrency(friend.total_lent);
            document.getElementById('profile-total-paid').textContent = formatCurrency(friend.total_repaid);
            
            const balVal = document.getElementById('profile-balance');
            balVal.textContent = formatCurrency(friend.balance);
            if (friend.balance > 0) {
                balVal.className = 'stat-value text-danger';
            } else if (friend.balance === 0) {
                balVal.className = 'stat-value text-muted-custom';
            } else {
                balVal.className = 'stat-value text-success';
            }
            
            // Render Timeline
            const timelineContainer = document.getElementById('profile-timeline');
            timelineContainer.innerHTML = '';
            
            if (timeline.length === 0) {
                timelineContainer.innerHTML = `
                    <div class="text-center py-4 text-muted-custom">
                        <i class="bi bi-journal-x fs-2 d-block mb-2"></i>
                        No transactions recorded yet.
                    </div>
                `;
                return;
            }
            
            timeline.forEach(t => {
                const isLend = t.type === 'lend';
                const dotClass = isLend ? 'lend' : 'repayment';
                const typeText = isLend ? 'Money Lent' : 'Payment Received';
                const amtColor = isLend ? 'text-danger' : 'text-success';
                const amtPrefix = isLend ? '+' : '-';
                
                const item = document.createElement('div');
                item.className = 'timeline-item';
                item.innerHTML = `
                    <div class="timeline-dot ${dotClass}">
                        <i class="bi ${isLend ? 'bi-arrow-up-right' : 'bi-arrow-down-left'} text-white" style="font-size:10px;"></i>
                    </div>
                    <div class="timeline-content">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-bold">${typeText}</span>
                            <span class="fw-bold ${amtColor}">${amtPrefix} ${formatCurrency(t.amount)}</span>
                        </div>
                        <div class="small text-muted-custom mb-1"><i class="bi bi-calendar3 me-1"></i> ${formatDate(t.date)}</div>
                        ${t.description ? `<div class="small mt-1 text-white-50">${escapeHTML(t.description)}</div>` : ''}
                        <div class="text-end mt-2">
                            <button class="btn btn-sm btn-link text-danger p-0 text-decoration-none" onclick="deleteTransaction(${t.id}, ${friend.id})">
                                <i class="bi bi-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                `;
                timelineContainer.appendChild(item);
            });
        })
        .catch(err => {
            console.error('Profile Load Error:', err);
            showAlert('danger', 'Failed to load profile.');
        });
}

// Transactions list view
function loadTransactionsList() {
    const activeBadge = document.querySelector('.filter-badge.active');
    const typeFilter = activeBadge ? activeBadge.getAttribute('data-type') : 'all';
    const searchVal = document.getElementById('search-transaction').value;

    fetch(`api.php?action=transactions_list&type=${typeFilter}&search=${encodeURIComponent(searchVal)}`)
        .then(res => res.json())
        .then(transactions => {
            const listContainer = document.getElementById('transactions-container');
            listContainer.innerHTML = '';
            
            if (transactions.length === 0) {
                listContainer.innerHTML = `
                    <div class="text-center py-5 text-muted-custom">
                        <i class="bi bi-search fs-1 d-block mb-3"></i>
                        No transactions match your search.
                    </div>
                `;
                return;
            }
            
            transactions.forEach(t => {
                const isLend = t.type === 'lend';
                const iconClass = isLend ? 'bi-arrow-up-right-circle-fill text-danger' : 'bi-arrow-down-left-circle-fill text-success';
                const amtColor = isLend ? 'text-danger' : 'text-success';
                
                const item = document.createElement('div');
                item.className = 'glass-card transaction-list-item';
                item.innerHTML = `
                    <div class="d-flex align-items-center gap-3">
                        <i class="bi ${iconClass} fs-3"></i>
                        <div>
                            <div class="fw-bold">${escapeHTML(t.friend_name)}</div>
                            <div class="small text-muted-custom">${formatDate(t.date)} ${t.description ? `• ${escapeHTML(t.description)}` : ''}</div>
                        </div>
                    </div>
                    <div class="text-end d-flex align-items-center gap-3">
                        <span class="fw-bold ${amtColor}">${isLend ? '+' : '-'}${formatCurrency(t.amount)}</span>
                        <button class="btn btn-sm btn-link text-danger p-0" onclick="deleteTransaction(${t.id}, null)">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                `;
                listContainer.appendChild(item);
            });
        })
        .catch(err => console.error('Transactions Load Error:', err));
}

// Reports
function loadReportsData() {
    fetch('api.php?action=reports_data')
        .then(res => res.json())
        .then(data => {
            // Lending Summary
            const ls = data.lending_summary;
            document.getElementById('report-lent-today').textContent = formatCurrency(ls.today);
            document.getElementById('report-lent-week').textContent = formatCurrency(ls.week);
            document.getElementById('report-lent-month').textContent = formatCurrency(ls.month);
            document.getElementById('report-lent-year').textContent = formatCurrency(ls.year);
            
            // Outstanding Summary
            const os = data.outstanding_summary;
            document.getElementById('report-total-outstanding').textContent = formatCurrency(os.total_outstanding);
            
            // Top Debtors Table
            const debtorsContainer = document.getElementById('report-top-debtors');
            debtorsContainer.innerHTML = '';
            if (os.top_debtors.length === 0) {
                debtorsContainer.innerHTML = `<tr><td colspan="2" class="text-center text-muted-custom">No outstanding balances.</td></tr>`;
            } else {
                os.top_debtors.forEach(d => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td class="fw-semibold">${escapeHTML(d.name)}</td>
                        <td class="text-end text-danger fw-bold">${formatCurrency(d.balance)}</td>
                    `;
                    debtorsContainer.appendChild(tr);
                });
            }
            
            // Fully Settled Friends Table
            const settledContainer = document.getElementById('report-settled-friends');
            settledContainer.innerHTML = '';
            if (os.settled_friends.length === 0) {
                settledContainer.innerHTML = `<tr><td colspan="2" class="text-center text-muted-custom">No settled records.</td></tr>`;
            } else {
                os.settled_friends.forEach(f => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${escapeHTML(f.name)}</td>
                        <td class="text-end text-success fw-semibold">Settled (Total: ${formatCurrency(f.total_lent)})</td>
                    `;
                    settledContainer.appendChild(tr);
                });
            }
        })
        .catch(err => console.error('Reports Load Error:', err));
}

// --- Quick Action Modal Controls ---

// Add Friend Modal
function openAddFriendModal() {
    document.getElementById('form-add-friend').reset();
    const modal = new bootstrap.Modal(document.getElementById('modal-add-friend'));
    modal.show();
}

function handleAddFriendSubmit(e) {
    e.preventDefault();
    
    const nameInput = document.getElementById('friend-name');
    const name = nameInput.value.trim();
    
    if (!name) {
        alert('Friend name is required.');
        nameInput.focus();
        return;
    }
    
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Adding...';
    
    const fd = new FormData(this);
    
    fetch('api.php?action=add_friend', {
        method: 'POST',
        body: fd
    })
    .then(res => {
        if (!res.ok) {
            return res.json().then(errData => {
                throw new Error(errData.error || 'Failed to add friend.');
            }).catch(() => {
                throw new Error('Failed to add friend.');
            });
        }
        return res.json();
    })
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('modal-add-friend')).hide();
            showAlert('success', 'Friend added!');
            // Refresh content based on screen
            if (activeScreen === 'friends') loadFriendsList();
        } else {
            alert(data.error || 'Failed to add friend.');
        }
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    })
    .catch(err => {
        console.error('Add Friend Error:', err);
        alert(err.message || 'Failed to add friend.');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}

// Transaction Modal (Lend / Repayment)
function openTransactionModal(type) {
    const modalEl = document.getElementById('modal-transaction');
    document.getElementById('form-transaction').reset();
    
    // Set type and adjust UI headers
    const typeSelect = document.getElementById('tx-type');
    typeSelect.value = type;
    
    const titleEl = document.getElementById('modal-transaction-title');
    if (type === 'lend') {
        titleEl.innerHTML = '<i class="bi bi-arrow-up-right-circle text-danger me-2"></i> Lend Money';
    } else {
        titleEl.innerHTML = '<i class="bi bi-arrow-down-left-circle text-success me-2"></i> Record Repayment';
    }
    
    // Default current date
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('tx-date').value = today;
    
    // Populate Friends Select
    const friendSelect = document.getElementById('tx-friend-id');
    friendSelect.innerHTML = '<option value="">-- Choose Friend --</option>';
    
    // Fetch friends list to guarantee up-to-date dropdown
    fetch('api.php?action=friends_list')
        .then(res => res.json())
        .then(friends => {
            friendsCache = friends;
            friends.forEach(f => {
                const option = document.createElement('option');
                option.value = f.id;
                option.textContent = f.name;
                // Pre-select friend if we are on a specific friend's profile page
                if (currentFriendId && f.id === currentFriendId && activeScreen.startsWith('friend-profile/')) {
                    option.selected = true;
                }
                friendSelect.appendChild(option);
            });
            
            const modal = new bootstrap.Modal(modalEl);
            modal.show();
        })
        .catch(err => {
            console.error('Fetch friends for modal failed:', err);
            showAlert('danger', 'Could not open modal. Please try again.');
        });
}

function handleTransactionSubmit(e) {
    e.preventDefault();
    
    const friendSelect = document.getElementById('tx-friend-id');
    const amountInput = document.getElementById('tx-amount');
    const dateInput = document.getElementById('tx-date');
    
    if (!friendSelect.value) {
        alert('Please select a friend.');
        friendSelect.focus();
        return;
    }
    
    const amount = parseFloat(amountInput.value);
    if (isNaN(amount) || amount <= 0) {
        alert('Amount must be greater than zero.');
        amountInput.focus();
        return;
    }
    
    if (!dateInput.value) {
        alert('Date is required.');
        dateInput.focus();
        return;
    }
    
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
    
    const fd = new FormData(this);
    
    fetch('api.php?action=record_transaction', {
        method: 'POST',
        body: fd
    })
    .then(res => {
        if (!res.ok) {
            return res.json().then(errData => {
                throw new Error(errData.error || 'Failed to record transaction.');
            }).catch(() => {
                throw new Error('Failed to record transaction.');
            });
        }
        return res.json();
    })
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('modal-transaction')).hide();
            showAlert('success', 'Transaction recorded!');
            
            // Refresh content based on current view
            if (activeScreen === 'dashboard') loadDashboardData();
            else if (activeScreen === 'friends') loadFriendsList();
            else if (activeScreen === 'transactions') loadTransactionsList();
            else if (activeScreen === 'reports') loadReportsData();
            else if (activeScreen.startsWith('friend-profile/')) {
                loadFriendProfile(currentFriendId);
            }
        } else {
            alert(data.error || 'Failed to record transaction.');
        }
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    })
    .catch(err => {
        console.error('Record Transaction Error:', err);
        alert(err.message || 'Failed to record transaction.');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}

// Delete transaction
function deleteTransaction(id, refreshFriendId) {
    if (confirm('Are you sure you want to delete this transaction record? This cannot be undone.')) {
        const fd = new FormData();
        fd.append('id', id);
        
        fetch('api.php?action=delete_transaction', {
            method: 'POST',
            body: fd
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showAlert('success', 'Transaction record deleted.');
                
                // Route refresh appropriately
                if (refreshFriendId) {
                    loadFriendProfile(refreshFriendId);
                } else if (activeScreen === 'transactions') {
                    loadTransactionsList();
                } else if (activeScreen === 'dashboard') {
                    loadDashboardData();
                } else if (activeScreen === 'reports') {
                    loadReportsData();
                }
            } else {
                alert(data.error || 'Failed to delete transaction.');
            }
        })
        .catch(err => console.error('Delete Transaction Error:', err));
    }
}

// PDF Export Helper
function printReport() {
    window.print();
}

// HTML Alert display
function showAlert(type, message) {
    const alertBox = document.createElement('div');
    alertBox.className = `alert alert-${type} glass-card position-fixed start-50 translate-middle-x text-center py-2 px-4 shadow-lg`;
    alertBox.style.cssText = 'top: 25px; z-index: 2000; border-radius: 12px; font-weight: 600; font-size:14px; min-width: 250px;';
    alertBox.textContent = message;
    
    document.body.appendChild(alertBox);
    setTimeout(() => {
        alertBox.style.transition = 'opacity 0.5s ease';
        alertBox.style.opacity = '0';
        setTimeout(() => alertBox.remove(), 500);
    }, 2500);
}

// Basic HTML escaping wrapper
function escapeHTML(str) {
    if (!str) return '';
    return str.replace(/[&<>'"]/g, 
        tag => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            "'": '&#39;',
            '"': '&quot;'
        }[tag] || tag)
    );
}
