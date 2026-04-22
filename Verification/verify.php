<?php
include "../Includes/auth.php";
include "../Includes/db.php";

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT is_verified FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare("SELECT * FROM verifications WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

$error = null;
$success = null;

function save_uploaded_file($field, $allowed_mimes, $target_subdir) {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== 0) {
        return [null, "Missing or failed upload for {$field}."];
    }
    $tmp  = $_FILES[$field]['tmp_name'];
    $size = $_FILES[$field]['size'];

    if ($size <= 0 || $size > 50 * 1024 * 1024) {
        return [null, "{$field} is too large (max 50 MB)."];
    }

    $mime = mime_content_type($tmp);
    if (!isset($allowed_mimes[$mime])) {
        return [null, "{$field} has an unsupported file type ({$mime})."];
    }
    $ext_in_name = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext_in_name, $allowed_mimes[$mime], true)) {
        return [null, "{$field} extension does not match its content."];
    }

    $target_dir = $_SERVER['DOCUMENT_ROOT'] . "/ITECA-Website/Uploads/" . $target_subdir . "/";
    if (!is_dir($target_dir) && !mkdir($target_dir, 0755, true)) {
        return [null, "Server cannot write to {$target_subdir} folder."];
    }

    $ext = $allowed_mimes[$mime][0];

    if (str_starts_with($mime, 'image/')) {
        $img = false;
        switch ($mime) {
            case 'image/jpeg': $img = @imagecreatefromjpeg($tmp); break;
            case 'image/png':  $img = @imagecreatefrompng($tmp);  break;
            case 'image/webp': $img = @imagecreatefromwebp($tmp); break;
        }
        if (!$img) return [null, "{$field} could not be processed as an image."];

        $unique_name = uniqid('', true) . "." . $ext;
        $target_file = $target_dir . $unique_name;
        $saved = false;
        switch ($mime) {
            case 'image/jpeg': $saved = imagejpeg($img, $target_file, 85); break;
            case 'image/png':  $saved = imagepng($img, $target_file);      break;
            case 'image/webp': $saved = imagewebp($img, $target_file, 85); break;
        }
        imagedestroy($img);
        if (!$saved) return [null, "Could not save {$field}."];
    } else {
        $unique_name = uniqid('', true) . "." . $ext;
        $target_file = $target_dir . $unique_name;
        if (!move_uploaded_file($tmp, $target_file)) {
            return [null, "Could not save {$field}."];
        }
    }

    return ["Uploads/" . $target_subdir . "/" . $unique_name, null];
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("CSRF token validation failed."); // can be replaced with a more user-friendly error handling in production
    }
    $full_name = trim($_POST['full_name'] ?? '');
    $id_number = trim($_POST['id_number'] ?? '');
    $address   = trim($_POST['address'] ?? '');
    $consent   = isset($_POST['consent']);

    if ($full_name === '' || $id_number === '' || $address === '') {
        $error = "Please fill in all personal information fields.";
    } elseif (!preg_match('/^[A-Za-z0-9\-]{6,20}$/', $id_number)) {
        $error = "ID number must be 6-20 characters (letters, digits, or hyphens).";
    } elseif (!$consent) {
        $error = "You must agree to the consent statement before submitting.";
    } else {
        $image_mimes = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png'  => ['png'],
            'image/webp' => ['webp'],
        ];
        $video_mimes = [
            'video/mp4'      => ['mp4'],
            'video/webm'     => ['webm'],
            'video/quicktime'=> ['mov'],
        ];

        [$id_path, $err1]     = save_uploaded_file('id_document',       $image_mimes, 'verification');
        [$selfie_path, $err2] = save_uploaded_file('selfie_photo',      $image_mimes, 'verification');
        [$video_path, $err3]  = save_uploaded_file('verification_video', $video_mimes, 'verification');

        $first_err = $err1 ?? $err2 ?? $err3;
        if ($first_err) {
            $error = $first_err;
        } else {
            $sql = "
                INSERT INTO verifications
                    (user_id, id_document, selfie_photo, verification_video, full_name, id_number, address, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
                ON DUPLICATE KEY UPDATE
                    id_document = VALUES(id_document),
                    selfie_photo = VALUES(selfie_photo),
                    verification_video = VALUES(verification_video),
                    full_name = VALUES(full_name),
                    id_number = VALUES(id_number),
                    address = VALUES(address),
                    status = 'pending',
                    rejection_reason = NULL,
                    submitted_at = CURRENT_TIMESTAMP,
                    reviewed_at = NULL,
                    reviewed_by = NULL
            ";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issssss", $user_id, $id_path, $selfie_path, $video_path, $full_name, $id_number, $address);

            if ($stmt->execute()) {
                $success = "Verification submitted. An admin will review your details shortly.";
                $stmt->close();

                $stmt = $conn->prepare("SELECT * FROM verifications WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $existing = $stmt->get_result()->fetch_assoc();
                $stmt->close();
            } else {
                $error = "Submission failed. Please try again.";
                $stmt->close();
            }
        }
    }
}

include "../Includes/header.php";
?>

<div class="container">
    <h1>Seller Verification</h1>

    <?php if ((int)($user['is_verified'] ?? 0) === 1): ?>
        <div class="alert alert-success">
            Your account is already verified. You can list items for sale.
        </div>
        <a href="../Listings/create.php" class="btn btn-primary">Create a Listing</a>

    <?php else: ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($existing && $existing['status'] === 'pending'): ?>
            <div class="alert alert-info">
                Your verification is <strong>pending review</strong>. Submitted on
                <?php echo date("Y-m-d H:i", strtotime($existing['submitted_at'])); ?>.
                You may resubmit below to replace your documents.
            </div>
        <?php elseif ($existing && $existing['status'] === 'rejected'): ?>
            <div class="alert alert-danger">
                Your previous submission was <strong>rejected</strong>.
                <?php if (!empty($existing['rejection_reason'])): ?>
                    <br>Reason: <?php echo htmlspecialchars($existing['rejection_reason']); ?>
                <?php endif; ?>
                Please correct the issue and resubmit.
            </div>
        <?php endif; ?>

        <p>
            To sell on Trustees we need to confirm your identity. Submit a clear photo of your ID,
            a selfie holding the ID, and a short video saying today's date.
            Your documents are stored privately and only reviewed by Trustees admins.
        </p>

        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <h3>Personal Information</h3>
            <label for="full_name">Full name (as on ID):</label><br>
            <input type="text" name="full_name" id="full_name" required maxlength="200"
                   value="<?php echo htmlspecialchars($_POST['full_name'] ?? $existing['full_name'] ?? ''); ?>"><br>

            <label for="id_number">ID number:</label><br>
            <input type="text" name="id_number" id="id_number" required maxlength="50"
                   value="<?php echo htmlspecialchars($_POST['id_number'] ?? $existing['id_number'] ?? ''); ?>"><br>

            <label for="address">Residential address:</label><br>
            <textarea name="address" id="address" rows="3" cols="40" required><?php echo htmlspecialchars($_POST['address'] ?? $existing['address'] ?? ''); ?></textarea><br>

            <h3>Documents</h3>
            <label for="id_document">Photo of your ID document (JPG / PNG / WEBP):</label><br>
            <input type="file" name="id_document" id="id_document" accept="image/*" required><br>

            <label for="selfie_photo">Selfie holding your ID (JPG / PNG / WEBP):</label><br>
            <input type="file" name="selfie_photo" id="selfie_photo" accept="image/*" capture="user" required><br>

            <label for="verification_video">Short video saying today's date (MP4 / WEBM / MOV, max 50 MB):</label><br>
            <input type="file" name="verification_video" id="verification_video" accept="video/*" capture="user" required><br>

            <h3>Consent</h3>
            <label>
                <input type="checkbox" name="consent" required>
                I confirm the information above is accurate and consent to Trustees storing
                and processing my ID, selfie and video for the purpose of seller verification (POPIA).
            </label><br>

            <button type="submit" class="btn btn-primary">Submit for Verification</button>
        </form>
    <?php endif; ?>
</div>

<?php include "../Includes/footer.php"; ?>
