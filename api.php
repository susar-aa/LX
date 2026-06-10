<?php
// api.php - Backend API for LX
require_once 'db.php';

// Helper to send JSON responses
function sendJson($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

$action = $_GET['action'] ?? '';
$conn = get_db_connection();

switch ($action) {
    case 'dashboard_stats':
        // Calculate Total Lent
        $res_lent = $conn->query("SELECT SUM(amount) as total FROM transactions WHERE type = 'lend'");
        $total_lent = floatval($res_lent->fetch_assoc()['total'] ?? 0);
        
        // Calculate Total Recovered
        $res_rec = $conn->query("SELECT SUM(amount) as total FROM transactions WHERE type = 'repayment'");
        $total_recovered = floatval($res_rec->fetch_assoc()['total'] ?? 0);
        
        $outstanding = $total_lent - $total_recovered;
        
        // Count Active Friends (friends with balance > 0)
        // Subquery calculating balances per friend
        $res_active = $conn->query("
            SELECT COUNT(*) as active_count FROM (
                SELECT f.id,
                       (COALESCE(SUM(CASE WHEN t.type = 'lend' THEN t.amount ELSE 0 END), 0) -
                        COALESCE(SUM(CASE WHEN t.type = 'repayment' THEN t.amount ELSE 0 END), 0)) as balance
                FROM friends f
                LEFT JOIN transactions t ON f.id = t.friend_id
                GROUP BY f.id
                HAVING balance > 0
            ) AS friend_balances
        ");
        $active_friends = intval($res_active->fetch_assoc()['active_count'] ?? 0);
        
        sendJson([
            'total_lent' => $total_lent,
            'total_recovered' => $total_recovered,
            'outstanding_balance' => $outstanding,
            'active_friends' => $active_friends
        ]);
        break;

    case 'friends_list':
        $res = $conn->query("
            SELECT f.id, f.name,
                   COALESCE(SUM(CASE WHEN t.type = 'lend' THEN t.amount ELSE 0 END), 0) AS total_lent,
                   COALESCE(SUM(CASE WHEN t.type = 'repayment' THEN t.amount ELSE 0 END), 0) AS total_repaid,
                   (COALESCE(SUM(CASE WHEN t.type = 'lend' THEN t.amount ELSE 0 END), 0) -
                    COALESCE(SUM(CASE WHEN t.type = 'repayment' THEN t.amount ELSE 0 END), 0)) AS balance
            FROM friends f
            LEFT JOIN transactions t ON f.id = t.friend_id
            GROUP BY f.id, f.name
            ORDER BY f.name ASC
        ");
        
        $friends = [];
        while ($row = $res->fetch_assoc()) {
            $friends[] = [
                'id' => intval($row['id']),
                'name' => $row['name'],
                'total_lent' => floatval($row['total_lent']),
                'total_repaid' => floatval($row['total_repaid']),
                'balance' => floatval($row['balance'])
            ];
        }
        sendJson($friends);
        break;

    case 'add_friend':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            sendJson(['error' => 'Invalid request method.'], 400);
        }
        
        $name = trim($_POST['name'] ?? '');
        if (empty($name)) {
            sendJson(['error' => 'Friend name is required.'], 400);
        }
        
        // Check if friend already exists
        $stmt_check = $conn->prepare("SELECT id FROM friends WHERE name = ?");
        $stmt_check->bind_param("s", $name);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) {
            sendJson(['error' => 'A friend with this name already exists.'], 400);
        }
        
        $stmt = $conn->prepare("INSERT INTO friends (name) VALUES (?)");
        $stmt->bind_param("s", $name);
        
        if ($stmt->execute()) {
            sendJson(['success' => 'Friend added successfully.', 'id' => $stmt->insert_id]);
        } else {
            sendJson(['error' => 'Failed to add friend: ' . $conn->error], 500);
        }
        break;

    case 'friend_profile':
        $friend_id = intval($_GET['id'] ?? 0);
        if ($friend_id <= 0) {
            sendJson(['error' => 'Invalid Friend ID.'], 400);
        }
        
        // Fetch friend details
        $stmt_friend = $conn->prepare("
            SELECT f.id, f.name,
                   COALESCE(SUM(CASE WHEN t.type = 'lend' THEN t.amount ELSE 0 END), 0) AS total_lent,
                   COALESCE(SUM(CASE WHEN t.type = 'repayment' THEN t.amount ELSE 0 END), 0) AS total_repaid
            FROM friends f
            LEFT JOIN transactions t ON f.id = t.friend_id
            WHERE f.id = ?
            GROUP BY f.id, f.name
        ");
        $stmt_friend->bind_param("i", $friend_id);
        $stmt_friend->execute();
        $res_friend = $stmt_friend->get_result();
        
        if ($res_friend->num_rows === 0) {
            sendJson(['error' => 'Friend not found.'], 404);
        }
        
        $friend = $res_friend->fetch_assoc();
        $total_lent = floatval($friend['total_lent']);
        $total_repaid = floatval($friend['total_repaid']);
        $balance = $total_lent - $total_repaid;
        
        // Fetch transactions timeline
        $stmt_timeline = $conn->prepare("
            SELECT id, type, amount, date, description
            FROM transactions
            WHERE friend_id = ?
            ORDER BY date DESC, id DESC
        ");
        $stmt_timeline->bind_param("i", $friend_id);
        $stmt_timeline->execute();
        $res_timeline = $stmt_timeline->get_result();
        
        $timeline = [];
        while ($row = $res_timeline->fetch_assoc()) {
            $timeline[] = [
                'id' => intval($row['id']),
                'type' => $row['type'],
                'amount' => floatval($row['amount']),
                'date' => $row['date'],
                'description' => $row['description']
            ];
        }
        
        sendJson([
            'friend' => [
                'id' => intval($friend['id']),
                'name' => $friend['name'],
                'total_lent' => $total_lent,
                'total_repaid' => $total_repaid,
                'balance' => $balance
            ],
            'timeline' => $timeline
        ]);
        break;

    case 'transactions_list':
        $type_filter = $_GET['type'] ?? 'all';
        $search = trim($_GET['search'] ?? '');
        
        $query = "
            SELECT t.id, t.friend_id, f.name AS friend_name, t.type, t.amount, t.date, t.description
            FROM transactions t
            JOIN friends f ON t.friend_id = f.id
            WHERE 1=1
        ";
        
        $params = [];
        $types = "";
        
        if ($type_filter === 'lend' || $type_filter === 'repayment') {
            $query .= " AND t.type = ?";
            $params[] = $type_filter;
            $types .= "s";
        }
        
        if (!empty($search)) {
            $query .= " AND f.name LIKE ?";
            $params[] = "%" . $search . "%";
            $types .= "s";
        }
        
        $query .= " ORDER BY t.date DESC, t.id DESC";
        
        $stmt = $conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        
        $transactions = [];
        while ($row = $res->fetch_assoc()) {
            $transactions[] = [
                'id' => intval($row['id']),
                'friend_id' => intval($row['friend_id']),
                'friend_name' => $row['friend_name'],
                'type' => $row['type'],
                'amount' => floatval($row['amount']),
                'date' => $row['date'],
                'description' => $row['description']
            ];
        }
        sendJson($transactions);
        break;

    case 'record_transaction':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            sendJson(['error' => 'Invalid request method.'], 400);
        }
        
        $friend_id = intval($_POST['friend_id'] ?? 0);
        $type = $_POST['type'] ?? '';
        $amount = floatval($_POST['amount'] ?? 0);
        $date = $_POST['date'] ?? '';
        $description = trim($_POST['description'] ?? '');
        
        if ($friend_id <= 0) {
            sendJson(['error' => 'Please select a valid friend.'], 400);
        }
        
        if ($type !== 'lend' && $type !== 'repayment') {
            sendJson(['error' => 'Invalid transaction type.'], 400);
        }
        
        if ($amount <= 0) {
            sendJson(['error' => 'Amount must be greater than zero.'], 400);
        }
        
        if (empty($date)) {
            sendJson(['error' => 'Date is required.'], 400);
        }
        
        // Verify friend exists
        $stmt_friend = $conn->prepare("SELECT id FROM friends WHERE id = ?");
        $stmt_friend->bind_param("i", $friend_id);
        $stmt_friend->execute();
        if ($stmt_friend->get_result()->num_rows === 0) {
            sendJson(['error' => 'Selected friend does not exist.'], 400);
        }
        
        $stmt = $conn->prepare("INSERT INTO transactions (friend_id, type, amount, date, description) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("isdss", $friend_id, $type, $amount, $date, $description);
        
        if ($stmt->execute()) {
            sendJson(['success' => 'Transaction recorded successfully.', 'id' => $stmt->insert_id]);
        } else {
            sendJson(['error' => 'Failed to record transaction: ' . $conn->error], 500);
        }
        break;

    case 'delete_transaction':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            sendJson(['error' => 'Invalid request method.'], 400);
        }
        
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            sendJson(['error' => 'Invalid transaction ID.'], 400);
        }
        
        $stmt = $conn->prepare("DELETE FROM transactions WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            sendJson(['success' => 'Transaction deleted successfully.']);
        } else {
            sendJson(['error' => 'Failed to delete transaction: ' . $conn->error], 500);
        }
        break;

    case 'reports_data':
        // 1. Lending Summary (Today, This Week, This Month, This Year)
        // Today
        $res_today = $conn->query("SELECT SUM(amount) as total FROM transactions WHERE type = 'lend' AND date = CURDATE()");
        $today = floatval($res_today->fetch_assoc()['total'] ?? 0);
        
        // This Week
        $res_week = $conn->query("SELECT SUM(amount) as total FROM transactions WHERE type = 'lend' AND YEARWEEK(date, 1) = YEARWEEK(CURDATE(), 1)");
        $week = floatval($res_week->fetch_assoc()['total'] ?? 0);
        
        // This Month
        $res_month = $conn->query("SELECT SUM(amount) as total FROM transactions WHERE type = 'lend' AND MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE())");
        $month = floatval($res_month->fetch_assoc()['total'] ?? 0);
        
        // This Year
        $res_year = $conn->query("SELECT SUM(amount) as total FROM transactions WHERE type = 'lend' AND YEAR(date) = YEAR(CURDATE())");
        $year = floatval($res_year->fetch_assoc()['total'] ?? 0);
        
        // 2. Outstanding Summary
        // Total Outstanding
        $res_out = $conn->query("
            SELECT SUM(CASE WHEN type = 'lend' THEN amount ELSE -amount END) as outstanding 
            FROM transactions
        ");
        $total_outstanding = floatval($res_out->fetch_assoc()['outstanding'] ?? 0);
        
        // Top Debtors
        $res_debtors = $conn->query("
            SELECT f.id, f.name,
                   COALESCE(SUM(CASE WHEN t.type = 'lend' THEN t.amount ELSE 0 END), 0) AS total_lent,
                   COALESCE(SUM(CASE WHEN t.type = 'repayment' THEN t.amount ELSE 0 END), 0) AS total_repaid,
                   (COALESCE(SUM(CASE WHEN t.type = 'lend' THEN t.amount ELSE 0 END), 0) -
                    COALESCE(SUM(CASE WHEN t.type = 'repayment' THEN t.amount ELSE 0 END), 0)) AS balance
            FROM friends f
            JOIN transactions t ON f.id = t.friend_id
            GROUP BY f.id, f.name
            HAVING balance > 0
            ORDER BY balance DESC
            LIMIT 5
        ");
        
        $top_debtors = [];
        while ($row = $res_debtors->fetch_assoc()) {
            $top_debtors[] = [
                'id' => intval($row['id']),
                'name' => $row['name'],
                'balance' => floatval($row['balance'])
            ];
        }
        
        // Fully Settled Friends
        $res_settled = $conn->query("
            SELECT f.id, f.name,
                   COALESCE(SUM(CASE WHEN t.type = 'lend' THEN t.amount ELSE 0 END), 0) AS total_lent,
                   COALESCE(SUM(CASE WHEN t.type = 'repayment' THEN t.amount ELSE 0 END), 0) AS total_repaid
            FROM friends f
            JOIN transactions t ON f.id = t.friend_id
            GROUP BY f.id, f.name
            HAVING (total_lent - total_repaid) = 0
            ORDER BY f.name ASC
        ");
        
        $settled_friends = [];
        while ($row = $res_settled->fetch_assoc()) {
            $settled_friends[] = [
                'id' => intval($row['id']),
                'name' => $row['name'],
                'total_lent' => floatval($row['total_lent'])
            ];
        }
        
        sendJson([
            'lending_summary' => [
                'today' => $today,
                'week' => $week,
                'month' => $month,
                'year' => $year
            ],
            'outstanding_summary' => [
                'total_outstanding' => $total_outstanding,
                'top_debtors' => $top_debtors,
                'settled_friends' => $settled_friends
            ]
        ]);
        break;

    case 'export_excel':
        // Generate CSV file for Excel
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="LX_transactions_export_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Write BOM for Excel compatibility (UTF-8)
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Headers
        fputcsv($output, ['Transaction ID', 'Friend Name', 'Type', 'Amount', 'Date', 'Description']);
        
        $res = $conn->query("
            SELECT t.id, f.name AS friend_name, t.type, t.amount, t.date, t.description
            FROM transactions t
            JOIN friends f ON t.friend_id = f.id
            ORDER BY t.date DESC, t.id DESC
        ");
        
        while ($row = $res->fetch_assoc()) {
            fputcsv($output, [
                $row['id'],
                $row['friend_name'],
                ucfirst($row['type']),
                $row['amount'],
                $row['date'],
                $row['description']
            ]);
        }
        fclose($output);
        exit;

    default:
        sendJson(['error' => 'Unknown action.'], 400);
}
?>
