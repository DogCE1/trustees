<?php
include '../Includes/auth_admin.php';
include '../Includes/db.php';
include '../Includes/notifications.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        set_flash('error', "CSRF token validation failed.");
        header("Location: verifications.php");
        exit();
    }

    $action          = $_POST['action'] ?? '';
    $verification_id = (int)($_POST['verification_id'] ?? 0);
    $reason          = trim($_POST['rejection_reason'] ?? '');
    $admin_id        = (int)$_SESSION['user_id'];

    if ($verification_id > 0 && in_array($action, ['approve', 'reject'], true)) {
        $stmt = $conn->prepare("
            SELECT v.user_id, v.full_name, u.name AS user_name
            FROM verifications v
            JOIN users u ON v.user_id = u.id
            WHERE v.id = ?
        ");
        $stmt->bind_param("i", $verification_id);
        $stmt->execute();
        $info = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$info) {
            set_flash('error', "Verification request not found.");
            header("Location: verifications.php");
            exit();
        }

        $target_user_id = (int)$info['user_id'];

        if ($action === 'approve') {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("
                    UPDATE verifications
                    SET status = 'approved', rejection_reason = NULL, reviewed_at = CURRENT_TIMESTAMP, reviewed_by = ?
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

                notify($conn, $target_user_id, "Your account has been verified. You can now create listings.", "/ITECA-Website/Listings/create.php");
                set_flash('success', "Verification approved for " . ($info['user_name'] ?? 'user') . ".");
            } catch (Exception $e) {
                $conn->rollback();
                set_flash('error', "Could not approve verification: " . $e->getMessage());
            }
        } else { // reject
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("
                    UPDATE verifications
                    SET status = 'rejected', rejection_reason = ?, reviewed_at = CURRENT_TIMESTAMP, reviewed_by = ?
                    WHERE id = ?
                ");
                $reason_or_null = ($reason === '') ? null : $reason;
                $stmt->bind_param("sii", $reason_or_null, $admin_id, $verification_id);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("UPDATE users SET is_verified = 0 WHERE id = ?");
                $stmt->bind_param("i", $target_user_id);
                $stmt->execute();
                $stmt->close();

                $conn->commit();

                $msg = "Your verification request was rejected.";
                if ($reason !== '') {
                    $msg .= " Reason: $reason";
                }
                notify($conn, $target_user_id, $msg, "/ITECA-Website/Verification/verify.php");
                set_flash('success', "Verification rejected for " . ($info['user_name'] ?? 'user') . ".");
            } catch (Exception $e) {
                $conn->rollback();
                set_flash('error', "Could not reject verification: " . $e->getMessage());
            }
        }
    }

    header("Location: verifications.php");
    exit();
}

$filter = $_GET['status'] ?? 'pending';
$valid_filters = ['all', 'pending', 'approved', 'rejected'];
if (!in_array($filter, $valid_filters, true)) {
    $filter = 'pending';
}

$search = trim($_GET['q'] ?? '');

$where = [];
$params = [];
$types = '';

if ($filter !== 'all') {
    $where[] = 'v.status = ?';
    $params[] = $filter;
    $types .= 's';
}
if ($search !== '') {
    $where[] = '(u.name LIKE ? OR u.surname LIKE ? OR u.email LIKE ? OR v.full_name LIKE ? OR v.id_number LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like, $like);
    $types .= 'sssss';
}

$sql = "
    SELECT v.id, v.user_id, v.id_document, v.selfie_photo, v.verification_video,
           v.full_name, v.id_number, v.address, v.status, v.rejection_reason,
           v.submitted_at, v.reviewed_at,
           u.name AS user_name, u.surname AS user_surname, u.email AS user_email,
           u.is_verified AS user_is_verified,
           reviewer.name AS reviewer_name
    FROM verifications v
    JOIN users u ON v.user_id = u.id
    LEFT JOIN users reviewer ON v.reviewed_by = reviewer.id
";
if ($where) {
    $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= " ORDER BY
            CASE v.status WHEN 'pending' THEN 0 WHEN 'rejected' THEN 1 ELSE 2 END,
            v.submitted_at DESC";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$verifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$flash_success = get_flash('success');

include '../Includes/header.php';

function video_mime_for($path) {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    switch ($ext) {
        case 'mp4':  return 'video/mp4';
        case 'webm': return 'video/webm';
        case 'mov':  return 'video/quicktime';
        default:     return 'video/mp4';
    }
}
?>

<div class="container">
    <h1>Account Verifications</h1>
    <p><a href="dashboard.php" class="btn btn-secondary"><strong>&larr; Back to Dashboard</strong></a></p>

    <?php if ($flash_success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($flash_success); ?></div>
    <?php endif; ?>

    <div class="dispute-filters" style="margin:1em 0;">
        <strong>Filter:</strong>
        <a href="verifications.php?status=all">All</a> |
        <a href="verifications.php?status=pending">Pending</a> |
        <a href="verifications.php?status=approved">Approved</a> |
        <a href="verifications.php?status=rejected">Rejected</a>
        <span style="margin-left:1em;">Showing: <strong><?php echo htmlspecialchars(ucfirst($filter)); ?></strong></span>
    </div>

    <form method="get" action="verifications.php" style="margin-bottom:1em; display:flex; gap:.5rem; max-width:500px;">
        <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter); ?>">
        <input type="search" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name, email, ID number…" style="flex:1;">
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if ($search !== ''): ?>
            <a href="verifications.php?status=<?php echo urlencode($filter); ?>" class="btn btn-secondary">Clear</a>
        <?php endif; ?>
    </form>

    <?php if (empty($verifications)): ?>
        <p>No verification requests match the current filter.</p>
    <?php else: ?>
        <?php foreach ($verifications as $v): ?>
            <div class="listing-card" style="margin-bottom:1.5rem;">
                <h2>
                    <?php echo htmlspecialchars(($v['user_name'] ?? '') . ' ' . ($v['user_surname'] ?? '')); ?>
                    <small style="font-weight:normal;">— <?php echo htmlspecialchars($v['user_email'] ?? ''); ?></small>
                </h2>
                <p>
                    <strong>Status:</strong>
                    <?php echo htmlspecialchars(ucfirst($v['status'])); ?>
                    <?php if (!empty($v['user_is_verified'])): ?>
                        <span style="color:#16a34a;">(account is verified)</span>
                    <?php endif; ?>
                </p>
                <p>
                    <strong>Submitted:</strong>
                    <?php echo date("Y-m-d H:i", strtotime($v['submitted_at'])); ?>
                    <?php if (!empty($v['reviewed_at'])): ?>
                        &middot; <strong>Reviewed:</strong>
                        <?php echo date("Y-m-d H:i", strtotime($v['reviewed_at'])); ?>
                        <?php if (!empty($v['reviewer_name'])): ?>
                            by <?php echo htmlspecialchars($v['reviewer_name']); ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </p>

                <p><strong>Full name on ID:</strong> <?php echo htmlspecialchars($v['full_name'] ?? ''); ?></p>
                <p><strong>ID number:</strong> <?php echo htmlspecialchars($v['id_number'] ?? ''); ?></p>
                <p><strong>Address:</strong> <?php echo nl2br(htmlspecialchars($v['address'] ?? '')); ?></p>

                <?php if (!empty($v['rejection_reason'])): ?>
                    <div class="alert alert-danger" style="margin-top:.5rem;">
                        <strong>Previous rejection reason:</strong>
                        <?php echo nl2br(htmlspecialchars($v['rejection_reason'])); ?>
                    </div>
                <?php endif; ?>

                <div style="display:flex; flex-wrap:wrap; gap:1rem; margin:1rem 0;">
                    <?php if (!empty($v['id_document'])): ?>
                        <div>
                            <p style="margin:0 0 .25rem;"><strong>ID document</strong></p>
                            <a href="../<?php echo htmlspecialchars($v['id_document']); ?>" target="_blank">
                                <img src="../<?php echo htmlspecialchars($v['id_document']); ?>" alt="ID document" style="max-width:220px; max-height:160px; object-fit:cover; border:1px solid #ccc; border-radius:4px;">
                            </a>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($v['selfie_photo'])): ?>
                        <div>
                            <p style="margin:0 0 .25rem;"><strong>Selfie with ID</strong></p>
                            <a href="../<?php echo htmlspecialchars($v['selfie_photo']); ?>" target="_blank">
                                <img src="../<?php echo htmlspecialchars($v['selfie_photo']); ?>" alt="Selfie" style="max-width:220px; max-height:160px; object-fit:cover; border:1px solid #ccc; border-radius:4px;">
                            </a>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($v['verification_video'])): ?>
                        <div>
                            <p style="margin:0 0 .25rem;"><strong>Verification video</strong></p>
                            <video controls style="max-width:260px; max-height:200px; border:1px solid #ccc; border-radius:4px;">
                                <source src="../<?php echo htmlspecialchars($v['verification_video']); ?>" type="<?php echo htmlspecialchars(video_mime_for($v['verification_video'])); ?>">
                                Your browser does not support the video tag.
                            </video>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($v['status'] === 'pending'): ?>
                    <form method="POST" action="verifications.php" style="display:flex; gap:.5rem; flex-wrap:wrap; align-items:flex-start;">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="verification_id" value="<?php echo (int)$v['id']; ?>">
                        <textarea name="rejection_reason" rows="2" cols="40" placeholder="Reason (required for rejection)"></textarea>
                        <button type="submit" name="action" value="approve" class="btn btn-success" onclick="return confirm('Approve this verification and mark the user as verified?');">Approve</button>
                        <button type="submit" name="action" value="reject" class="btn btn-danger" onclick="return confirm('Reject this verification? The user will be notified.');">Reject</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include '../Includes/footer.php'; ?>
