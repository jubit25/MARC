<?php
require_once 'auth.php';
require_once 'db_connect.php';

header('Content-Type: application/json');

if (!in_array($_SESSION["role"] ?? '', ['registrar', 'system_admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$action = $_POST['action'] ?? '';

function respond_and_exit($ok, $msg = '', $extra = []) {
    echo json_encode(array_merge(['ok' => $ok, 'message' => $msg], $extra));
    exit;
}

try {
    if ($action === 'create_category') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $amount = $_POST['amount'] ?? '';
        $is_recurring = isset($_POST['is_recurring']) ? 1 : 0;
        $frequency = $_POST['frequency'] ?? 'one_time';
        if ($name === '' || $amount === '') {
            respond_and_exit(false, 'Name and amount are required.');
        }
        $stmt = $conn->prepare("INSERT INTO payment_categories (name, description, amount, is_recurring, frequency) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('ssdss', $name, $description, $amount, $is_recurring, $frequency);
        if (!$stmt->execute()) {
            respond_and_exit(false, 'Failed to create category.');
        }
        respond_and_exit(true, 'Category created.', ['id' => $stmt->insert_id]);
    }

    if ($action === 'update_category') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $amount = $_POST['amount'] ?? '';
        $is_recurring = isset($_POST['is_recurring']) ? 1 : 0;
        $frequency = $_POST['frequency'] ?? 'one_time';
        if ($id <= 0 || $name === '' || $amount === '') {
            respond_and_exit(false, 'Invalid payload.');
        }
        $stmt = $conn->prepare("UPDATE payment_categories SET name=?, description=?, amount=?, is_recurring=?, frequency=? WHERE id=?");
        $stmt->bind_param('ssdssi', $name, $description, $amount, $is_recurring, $frequency, $id);
        if (!$stmt->execute()) {
            respond_and_exit(false, 'Failed to update category.');
        }
        respond_and_exit(true, 'Category updated.');
    }

    if ($action === 'delete_category') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) respond_and_exit(false, 'Invalid id.');
        $stmt = $conn->prepare("DELETE FROM payment_categories WHERE id=?");
        $stmt->bind_param('i', $id);
        if (!$stmt->execute()) {
            respond_and_exit(false, 'Failed to delete category.');
        }
        respond_and_exit(true, 'Category deleted.');
    }

    if ($action === 'assign_payments') {
        $category_id = (int)($_POST['payment_category_id'] ?? 0);
        $due_date = $_POST['due_date'] ?? '';
        $amount_due = $_POST['amount_due'] ?? '';
        $student_ids = $_POST['student_ids'] ?? [];
        if ($category_id <= 0 || $due_date === '' || $amount_due === '' || !is_array($student_ids) || count($student_ids) === 0) {
            respond_and_exit(false, 'Missing fields.');
        }
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO payment_schedule (student_id, payment_category_id, due_date, amount_due, status) VALUES (?, ?, ?, ?, 'pending')");
            foreach ($student_ids as $sid) {
                $sid = (int)$sid;
                if ($sid <= 0) continue;
                $stmt->bind_param('iisd', $sid, $category_id, $due_date, $amount_due);
                if (!$stmt->execute()) throw new Exception('Insert failed');
            }
            $conn->commit();
            respond_and_exit(true, 'Assigned payment to selected students.');
        } catch (Exception $e) {
            $conn->rollback();
            respond_and_exit(false, 'Failed to assign payments.');
        }
    }

    if ($action === 'record_payment') {
        $student_id = (int)($_POST['student_id'] ?? 0);
        $payment_category_id = (int)($_POST['payment_category_id'] ?? 0);
        $amount_paid = (float)($_POST['amount_paid'] ?? 0);
        $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
        $payment_method = $_POST['payment_method'] ?? 'cash';
        $reference_number = trim($_POST['reference_number'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $received_by = (int)($_SESSION['id'] ?? 0);
        if ($student_id <= 0 || $payment_category_id <= 0 || $amount_paid <= 0 || $received_by <= 0) {
            respond_and_exit(false, 'Invalid payload.');
        }
        $stmt = $conn->prepare("INSERT INTO payments (student_id, payment_category_id, amount_paid, payment_date, payment_method, reference_number, received_by, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('iidsssis', $student_id, $payment_category_id, $amount_paid, $payment_date, $payment_method, $reference_number, $received_by, $notes);
        if (!$stmt->execute()) {
            respond_and_exit(false, 'Failed to record payment.');
        }
        // Optionally, update schedule statuses to 'paid' when fully paid
        $update = $conn->prepare("UPDATE payment_schedule ps
            LEFT JOIN (
                SELECT student_id, payment_category_id, SUM(amount_paid) AS total_paid
                FROM payments
                GROUP BY student_id, payment_category_id
            ) p ON p.student_id = ps.student_id AND p.payment_category_id = ps.payment_category_id
            SET ps.status = CASE WHEN COALESCE(p.total_paid,0) >= ps.amount_due THEN 'paid' ELSE ps.status END
            WHERE ps.student_id = ? AND ps.payment_category_id = ?");
        $update->bind_param('ii', $student_id, $payment_category_id);
        $update->execute();
        respond_and_exit(true, 'Payment recorded.');
    }

    respond_and_exit(false, 'Unknown action.');
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error']);
    exit;
}
