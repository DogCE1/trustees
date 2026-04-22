<?php
include "../Includes/auth_admin.php";
include "../Includes/db.php";

$admin_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'], $_POST['verification_id'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("CSRF token validation failed."); // can be replaced with a more user-friendly error handling in production
    }
    $verification_id = (int)$_POST['verification_id'];
    $action = $_POST['action'];
    $reason = trim($_POST['rejection_reason'] ?? '');

    $stmt = $conn->prepare("SELECT user_id FROM verifications WHERE id = ?");
    $stmt->bind_param("i", $verification_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $target_user_id = (int)$row['user_id'];

        if ($action === 'approve') {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("
                    UPDATE verifications
                    SET status = 'approved', rejection_reason = NULL,
                        reviewed_at = CURRENT_TIMESTAMP, reviewed_by = ?
                    WHERE id = ?
                ");
                $stmt->bind_param("ii", $admin_id, $verification_id);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("UPDATE users SET is_verified = 1 WHERE id = ?");
                $stmt->bind_param("i", $target_user_id);
                $stmt->execute();
                $stmt->close();

                $conn->commit();
            } catch (Exception $e) {
                $conn->rollback();
            }
        } elseif ($action === 'reject') {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("
                    UPDATE verifications
                    SET status = 'rejected', rejection_reason = ?,
                        reviewed_at = CURRENT_TIMESTAMP, reviewed_by = ?
                    WHERE id = ?
                ");
                $stmt->bind_param("sii", $reason, $admin_id, $verification_id);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("UPDATE users SET is_verified = 0 WHERE id = ?");
                $stmt->bind_param("i", $target_user_id);
                $stmt->execute();
                $stmt->close();

                $conn->commit();
            } catch (Exception $e) {
                $conn->rollback();
            }
        }
    }
    header("Location: verify_users.php");
    exit();
}

$detail = null;
if (isset($_GET['id']) && ctype_digit((string)$_GET['id'])) {
    $vid = (int)$_GET['id'];
    $stmt = $conn->prepare("
        SELECT v.*, u.name, u.surname, u.email, u.phonenr, u.created_at AS user_created_at,
               r.name AS reviewer_name
        FROM verifications v
        JOIN users u ON v.user_id = u.id
        LEFT JOIN users r ON v.reviewed_by = r.id
        WHERE v.id = ?
    ");
    $stmt->bind_param("i", $vid);
    $stmt->execute();
    $detail = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$detail) {
    $stmt = $conn->prepare("
        SELECT v.id, v.full_name, v.status, v.submitted_at, v.reviewed_at,
               u.name, u.surname, u.email
        FROM verifications v
        JOIN users u ON v.user_id = u.id
        ORDER BY
            CASE v.status WHEN 'pending' THEN 0 WHEN 'rejected' THEN 1 ELSE 2 END,
            v.submitted_at DESC
    ");
    $stmt->execute();
    $verifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

include "../Includes/header.php";
?>

<div class="container">
    <?php if ($detail): ?>
        <a href="verify_users.php">&larr; Back to verification list</a>
        <h1>Verification Review</h1>

        <div class="verify-detail">
            <h2><?php echo htmlspecialchars($detail['full_name'] ?? ($detail['name'] . ' ' . $detail['surname'])); ?></h2>
            <p><strong>Account:</strong>
                <?php echo htmlspecialchars($detail['name'] . ' ' . $detail['surname']); ?>
                (<?php echo htmlspecialchars($detail['email']); ?>)
            </p>
            <p><strong>Phone:</strong> <?php echo htmlspecialchars($detail['phonenr'] ?? ''); ?></p>
            <p><strong>ID number:</strong> <?php echo htmlspecialchars($detail['id_number'] ?? ''); ?></p>
            <p><strong>Address:</strong><br><?php echo nl2br(htmlspecialchars($detail['address'] ?? '')); ?></p>
            <p><strong>Submitted:</strong> <?php echo date("Y-m-d H:i", strtotime($detail['submitted_at'])); ?></p>
            <p><strong>Status:</strong> <?php echo htmlspecialchars(ucfirst($detail['status'])); ?>
                <?php if ($detail['reviewed_at']): ?>
                    (reviewed <?php echo date("Y-m-d H:i", strtotime($detail['reviewed_at'])); ?>
                    by <?php echo htmlspecialchars($detail['reviewer_name'] ?? 'unknown'); ?>)
                <?php endif; ?>
            </p>
            <?php if ($detail['status'] === 'rejected' && !empty($detail['rejection_reason'])): ?>
                <p><strong>Rejection reason:</strong> <?php echo htmlspecialchars($detail['rejection_reason']); ?></p>
            <?php endif; ?>

            <h3>Documents</h3>
            <div class="verify-docs">
                <?php if (!empty($detail['id_document'])): ?>
                    <div>
                        <p><strong>ID document</strong></p>
                        <img src="../Verification/file.php?vid=<?php echo (int)$detail['id']; ?>&field=id_document"
                             alt="ID document" style="max-width:380px;border:1px solid #ccc;">
                    </div>
                <?php endif; ?>
                <?php if (!empty($detail['selfie_photo'])): ?>
                    <div>
                        <p><strong>Selfie with ID</strong></p>
                        <img src="../Verification/file.php?vid=<?php echo (int)$detail['id']; ?>&field=selfie_photo"
                             alt="Selfie" style="max-width:380px;border:1px solid #ccc;">
                    </div>
                <?php endif; ?>
                <?php if (!empty($detail['verification_video'])): ?>
                    <div>
                        <p><strong>Verification video</strong></p>
                        <video controls style="max-width:380px;">
                            <source src="../Verification/file.php?vid=<?php echo (int)$detail['id']; ?>&field=verification_video">
                        </video>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($detail['status'] === 'pending' || $detail['status'] === 'rejected'): ?>
                <h3>Decision</h3>
                <form method="post" style="display:inline-block;margin-right:10px;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="verification_id" value="<?php echo (int)$detail['id']; ?>">
                    <button type="submit" name="action" value="approve" class="btn btn-success">Approve & Verify User</button>
                </form>

                <form method="post" style="display:inline-block;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="verification_id" value="<?php echo (int)$detail['id']; ?>">
                    <label for="rejection_reason">Rejection reason:</label><br>
                    <textarea name="rejection_reason" id="rejection_reason" rows="2" cols="40" required></textarea><br>
                    <button type="submit" name="action" value="reject" class="btn btn-danger">Reject</button>
                </form>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <h1>Verify Users</h1>
        <p>Pending submissions are listed first. Click a row to review documents.</p>
        <a href="dashboard.php">Back to Dashboard</a>

        <table>
            <thead>
                <tr>
                    <th>Submitted</th>
                    <th>Account</th>
                    <th>Email</th>
                    <th>Full name on submission</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($verifications)): ?>
                    <tr><td colspan="6">No verification submissions yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($verifications as $v): ?>
                        <tr>
                            <td><?php echo date("Y-m-d H:i", strtotime($v['submitted_at'])); ?></td>
                            <td><?php echo htmlspecialchars($v['name'] . ' ' . $v['surname']); ?></td>
                            <td><?php echo htmlspecialchars($v['email']); ?></td>
                            <td><?php echo htmlspecialchars($v['full_name'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($v['status'])); ?></td>
                            <td><a href="verify_users.php?id=<?php echo (int)$v['id']; ?>" class="btn btn-primary">Review</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include "../Includes/footer.php"; ?>
