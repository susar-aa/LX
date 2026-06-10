<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>LX - Personal Lending Tracker</title>
    
    <!-- PWA Settings -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#6366f1">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="LX">
    <link rel="apple-touch-icon" href="assets/icons/icon-192.png">
    
    <!-- Tailwind or Bootstrap 5 (Bootstrap 5 requested) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Custom Style Sheet -->
    <link rel="stylesheet" href="assets/css/style.css?v=2">
</head>
<body>

    <!-- ================= AUTH: REGISTER/SETUP ================= -->
    <div id="view-setup" class="auth-wrapper" style="display: none;">
        <div class="glass-card auth-card text-center">
            <div class="mb-4">
                <div class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary rounded-4 p-3 mb-3" style="width: 70px; height: 70px;">
                    <i class="bi bi-shield-lock-fill fs-1"></i>
                </div>
                <h2>Initialize LX</h2>
                <p class="text-muted-custom">Create the single administrator account to begin tracking debts.</p>
            </div>
            
            <form id="form-setup">
                <div class="text-start mb-3">
                    <label for="setup-username" class="glass-label">Username</label>
                    <input type="text" id="setup-username" name="username" class="form-control glass-input" placeholder="admin" required autocomplete="username">
                </div>
                <div class="text-start mb-4">
                    <label for="setup-password" class="glass-label">Password</label>
                    <input type="password" id="setup-password" name="password" class="form-control glass-input" placeholder="••••••••" required autocomplete="new-password">
                    <div class="form-text text-muted-custom small mt-1">Must be at least 6 characters.</div>
                </div>
                <button type="submit" class="glass-btn glass-btn-primary w-100">
                    <i class="bi bi-check-circle-fill"></i> Complete Setup
                </button>
            </form>
        </div>
    </div>

    <!-- ================= AUTH: LOGIN ================= -->
    <div id="view-login" class="auth-wrapper" style="display: none;">
        <div class="glass-card auth-card text-center">
            <div class="mb-4">
                <div class="d-inline-flex align-items-center justify-content-center bg-primary bg-gradient text-white rounded-4 p-3 mb-3 shadow" style="width: 75px; height: 75px;">
                    <span class="fs-2 fw-bold">LX</span>
                </div>
                <h2>Welcome Back</h2>
                <p class="text-muted-custom">Log in to manage your lending records.</p>
            </div>
            
            <form id="form-login">
                <div class="text-start mb-3">
                    <label for="login-username" class="glass-label">Username</label>
                    <input type="text" id="login-username" name="username" class="form-control glass-input" placeholder="Enter username" required autocomplete="username">
                </div>
                <div class="text-start mb-4">
                    <label for="login-password" class="glass-label">Password</label>
                    <input type="password" id="login-password" name="password" class="form-control glass-input" placeholder="Enter password" required autocomplete="current-password">
                </div>
                <button type="submit" class="glass-btn glass-btn-primary w-100">
                    <i class="bi bi-box-arrow-in-right"></i> Log In
                </button>
            </form>
        </div>
    </div>

    <!-- ================= MAIN APP SYSTEM ================= -->
    <div id="view-app" style="display: none;">
        
        <!-- App Header Sticky -->
        <header class="app-header">
            <div class="app-title"><i class="bi bi-wallet2 text-primary me-2"></i>LX</div>
            <div class="d-flex align-items-center gap-3">
                <span class="small text-muted-custom d-none d-sm-inline">Logged in as: <strong class="text-white" id="display-username"></strong></span>
                <button class="logout-icon-btn" id="btn-logout" title="Log Out">
                    <i class="bi bi-box-arrow-right"></i>
                </button>
            </div>
        </header>

        <!-- Main Views Container -->
        <main class="container py-4 px-3">
            
            <!-- 1. Screen: Dashboard -->
            <div id="screen-dashboard" class="page-view">
                <div class="screen-header">
                    <h2>Dashboard</h2>
                    <p class="text-muted-custom">Quick overview of outstanding debts</p>
                </div>
                
                <div class="row g-3">
                    <!-- Total Lent -->
                    <div class="col-6">
                        <div class="glass-card stat-card">
                            <div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
                            <div class="stat-value text-white" id="stat-total-lent">Rs. 0.00</div>
                            <div class="stat-label">Total Lent</div>
                        </div>
                    </div>
                    <!-- Total Recovered -->
                    <div class="col-6">
                        <div class="glass-card stat-card stat-card-success">
                            <div class="stat-icon stat-icon-success"><i class="bi bi-check-all"></i></div>
                            <div class="stat-value text-success" id="stat-total-recovered">Rs. 0.00</div>
                            <div class="stat-label">Recovered</div>
                        </div>
                    </div>
                    <!-- Outstanding Balance -->
                    <div class="col-12">
                        <div class="glass-card stat-card stat-card-danger text-center py-4">
                            <div class="stat-icon stat-icon-danger mx-auto mb-2"><i class="bi bi-exclamation-circle"></i></div>
                            <div class="stat-value text-danger fs-1" id="stat-outstanding">Rs. 0.00</div>
                            <div class="stat-label">Outstanding Balance</div>
                        </div>
                    </div>
                    <!-- Active Friends -->
                    <div class="col-12">
                        <div class="glass-card stat-card text-center py-3">
                            <div class="d-flex justify-content-center align-items-center gap-3">
                                <span class="fs-1 fw-bold text-primary" id="stat-active-friends">0</span>
                                <div class="text-start">
                                    <div class="fw-bold">Active Friends</div>
                                    <div class="small text-muted-custom">Friends with outstanding balances</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Screen: Friends -->
            <div id="screen-friends" class="page-view">
                <div class="screen-header d-flex justify-content-between align-items-center">
                    <div>
                        <h2>Friends</h2>
                        <p class="text-muted-custom">Manage friend listings & balances</p>
                    </div>
                    <button class="glass-btn glass-btn-primary" onclick="openAddFriendModal()">
                        <i class="bi bi-person-plus"></i> Add
                    </button>
                </div>
                
                <!-- Friends dynamic injection container -->
                <div id="friends-container" class="d-flex flex-column gap-2">
                    <!-- Loaded dynamically -->
                </div>
            </div>

            <!-- 3. Screen: Friend Profile / Detail -->
            <div id="screen-friend-profile" class="page-view">
                <div class="screen-header mb-4">
                    <button class="btn btn-link text-white-50 p-0 mb-3 text-decoration-none" onclick="navigateTo('friends')">
                        <i class="bi bi-arrow-left"></i> Back to Friends
                    </button>
                    <h2 class="fs-1" id="profile-name">Friend Name</h2>
                    <p class="text-muted-custom">Lending transaction timeline</p>
                </div>

                <div class="glass-card p-4 mb-4 text-center">
                    <div class="stat-label mb-2">Current Balance</div>
                    <div class="stat-value mb-3" id="profile-balance">Rs. 0.00</div>
                    <div class="row pt-3 border-top border-secondary border-opacity-10">
                        <div class="col-6">
                            <div class="small text-muted-custom">Total Lent</div>
                            <div class="fw-bold" id="profile-total-lent">Rs. 0.00</div>
                        </div>
                        <div class="col-6">
                            <div class="small text-muted-custom">Total Paid</div>
                            <div class="fw-bold text-success" id="profile-total-paid">Rs. 0.00</div>
                        </div>
                    </div>
                </div>

                <h4 class="mb-3">Timeline</h4>
                <!-- Transaction Timeline Container -->
                <div id="profile-timeline" class="timeline">
                    <!-- Loaded dynamically -->
                </div>
            </div>

            <!-- 4. Screen: Transactions -->
            <div id="screen-transactions" class="page-view">
                <div class="screen-header">
                    <h2>Transactions</h2>
                    <p class="text-muted-custom">All financial logs & filter listings</p>
                </div>

                <!-- Search and Filters -->
                <div class="mb-4">
                    <div class="search-wrapper mb-3">
                        <i class="bi bi-search"></i>
                        <input type="text" id="search-transaction" class="form-control glass-input" placeholder="Search by friend name...">
                    </div>
                    
                    <div class="d-flex flex-wrap">
                        <div class="filter-badge active" data-type="all">All</div>
                        <div class="filter-badge" data-type="lend">Lending</div>
                        <div class="filter-badge" data-type="repayment">Repayments</div>
                    </div>
                </div>

                <!-- Transactions dynamic injection container -->
                <div id="transactions-container" class="d-flex flex-column gap-2">
                    <!-- Loaded dynamically -->
                </div>
            </div>

            <!-- 5. Screen: Reports -->
            <div id="screen-reports" class="page-view">
                <div class="screen-header d-flex justify-content-between align-items-center">
                    <div>
                        <h2>Reports</h2>
                        <p class="text-muted-custom">Simple summaries & files export</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="api.php?action=export_excel" class="glass-btn" title="Export Excel">
                            <i class="bi bi-file-earmark-excel text-success"></i> Excel
                        </a>
                        <button class="glass-btn" onclick="printReport()" title="Print / PDF">
                            <i class="bi bi-printer text-info"></i> PDF
                        </button>
                    </div>
                </div>

                <!-- Lending Summary Period Cards -->
                <h4 class="mb-3">Lending Summary</h4>
                <div class="row g-3 mb-4">
                    <div class="col-6 col-sm-3">
                        <div class="glass-card p-3">
                            <div class="small text-muted-custom">Today</div>
                            <div class="fw-bold fs-5" id="report-lent-today">Rs. 0.00</div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-3">
                        <div class="glass-card p-3">
                            <div class="small text-muted-custom">This Week</div>
                            <div class="fw-bold fs-5" id="report-lent-week">Rs. 0.00</div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-3">
                        <div class="glass-card p-3">
                            <div class="small text-muted-custom">This Month</div>
                            <div class="fw-bold fs-5" id="report-lent-month">Rs. 0.00</div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-3">
                        <div class="glass-card p-3">
                            <div class="small text-muted-custom">This Year</div>
                            <div class="fw-bold fs-5" id="report-lent-year">Rs. 0.00</div>
                        </div>
                    </div>
                </div>

                <!-- Outstanding summary block -->
                <div class="glass-card p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="m-0">Outstanding Summary</h4>
                        <span class="badge bg-danger bg-opacity-25 text-danger py-2 px-3 rounded-pill fw-bold" id="report-total-outstanding">Rs. 0.00</span>
                    </div>
                    
                    <h5 class="small text-muted-custom mb-2">Top Debtors</h5>
                    <div class="table-responsive mb-4">
                        <table class="glass-table">
                            <thead>
                                <tr>
                                    <th>Friend</th>
                                    <th class="text-end">Owed Amount</th>
                                </tr>
                            </thead>
                            <tbody id="report-top-debtors">
                                <!-- Loaded dynamically -->
                            </tbody>
                        </table>
                    </div>

                    <h5 class="small text-muted-custom mb-2">Fully Settled Friends</h5>
                    <div class="table-responsive">
                        <table class="glass-table">
                            <thead>
                                <tr>
                                    <th>Friend</th>
                                    <th class="text-end">Status</th>
                                </tr>
                            </thead>
                            <tbody id="report-settled-friends">
                                <!-- Loaded dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </main>

        <!-- ================= QUICK ACTIONS FAB ================= -->
        <div id="fab-container" class="fab-container">
            <button id="fab-btn" class="fab-btn" aria-label="Quick Actions">
                <i class="bi bi-plus"></i>
            </button>
            <div class="fab-menu">
                <div class="fab-menu-item" id="action-lend">
                    <span class="fab-menu-label">Lend Money</span>
                    <div class="fab-menu-icon" style="background: rgba(244, 63, 94, 0.15); border-color: rgba(244, 63, 94, 0.3); color: var(--danger);">
                        <i class="bi bi-arrow-up-right"></i>
                    </div>
                </div>
                <div class="fab-menu-item" id="action-repay">
                    <span class="fab-menu-label">Record Repayment</span>
                    <div class="fab-menu-icon" style="background: rgba(16, 185, 129, 0.15); border-color: rgba(16, 185, 129, 0.3); color: var(--success);">
                        <i class="bi bi-arrow-down-left"></i>
                    </div>
                </div>
                <div class="fab-menu-item" id="action-add-friend">
                    <span class="fab-menu-label">Add Friend</span>
                    <div class="fab-menu-icon" style="background: rgba(99, 102, 241, 0.15); border-color: rgba(99, 102, 241, 0.3); color: var(--primary);">
                        <i class="bi bi-person-plus"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= BOTTOM NAVIGATION BAR ================= -->
        <nav class="bottom-nav">
            <a class="bottom-nav-item active" href="#dashboard">
                <i class="bi bi-grid-1x2-fill"></i>
                Dashboard
            </a>
            <a class="bottom-nav-item" href="#friends">
                <i class="bi bi-people-fill"></i>
                Friends
            </a>
            <a class="bottom-nav-item" href="#transactions">
                <i class="bi bi-arrow-left-right"></i>
                History
            </a>
            <a class="bottom-nav-item" href="#reports">
                <i class="bi bi-file-bar-graph-fill"></i>
                Reports
            </a>
        </nav>

        <!-- ================= MODALS ================= -->
        
        <!-- Modal: Add Friend -->
        <div class="modal fade" id="modal-add-friend" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-person-plus-fill me-2"></i>Add Friend</h5>
                        <button type="button" class="btn-close-custom" data-bs-dismiss="modal" aria-label="Close">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <form id="form-add-friend">
                        <div class="modal-body py-3">
                            <label for="friend-name" class="glass-label">Friend Name</label>
                            <input type="text" id="friend-name" name="name" class="form-control glass-input" placeholder="e.g. Kasun" required autocomplete="off">
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="glass-btn glass-btn-primary w-100">Add Friend</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal: Transaction (Lend / Repayment) -->
        <div class="modal fade" id="modal-transaction" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modal-transaction-title">Transaction</h5>
                        <button type="button" class="btn-close-custom" data-bs-dismiss="modal" aria-label="Close">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <form id="form-transaction">
                        <!-- Hidden input for type -->
                        <input type="hidden" id="tx-type" name="type" value="lend">
                        
                        <div class="modal-body py-3">
                            <div class="mb-3">
                                <label for="tx-friend-id" class="glass-label">Friend</label>
                                <select id="tx-friend-id" name="friend_id" class="form-select glass-input" required>
                                    <!-- Populated dynamically -->
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="tx-amount" class="glass-label">Amount (Rs.)</label>
                                <input type="number" id="tx-amount" name="amount" step="0.01" class="form-control glass-input" placeholder="0.00" required>
                            </div>
                            <div class="mb-3">
                                <label for="tx-date" class="glass-label">Date</label>
                                <input type="date" id="tx-date" name="date" class="form-control glass-input" required>
                            </div>
                            <div>
                                <label for="tx-desc" class="glass-label">Description (Optional)</label>
                                <textarea id="tx-desc" name="description" class="form-control glass-input" rows="2" placeholder="e.g. Emergency cash"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="glass-btn glass-btn-primary w-100">Submit Record</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Application Logic -->
    <script src="assets/js/app.js?v=2"></script>
</body>
</html>
