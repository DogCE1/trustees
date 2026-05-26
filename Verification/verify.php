<?php
require_once "../Includes/auth.php";
require_once "../Includes/db.php";
require_once "../Includes/rate_limit.php";
require_once "../Includes/otp.php";

$user_id = $_SESSION['user_id'];
// Fetch user info if is_verified status
$stmt = $conn->prepare("SELECT is_verified FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
// Fetch existing verification if any
$stmt = $conn->prepare("SELECT * FROM verifications WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Check OTP status for phone verification step
$otp_pending   = otp_pending($conn, $user_id, 'seller_verification');
$otp_confirmed = !empty($_SESSION['otp_verified']);

// If user is already verified, no need to check OTP
$error = null;
$success = null;

// Handle form submission
function save_uploaded_file($field, $allowed_mimes, $target_subdir) {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== 0) {
        return [null, "Missing or failed upload for {$field}."];
    }
    $tmp  = $_FILES[$field]['tmp_name'];
    $size = $_FILES[$field]['size'];

    // Basic checks
    if ($size <= 0 || $size > 50 * 1024 * 1024) {
        return [null, "{$field} is too large (max 50 MB)."];
    }

    // Validate MIME type and extension
    $mime = mime_content_type($tmp);
    if (!isset($allowed_mimes[$mime])) {
        return [null, "{$field} has an unsupported file type ({$mime})."];
    }
    $ext_in_name = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext_in_name, $allowed_mimes[$mime], true)) {
        return [null, "{$field} extension does not match its content."];
    }

    // Ensure target directory exists
    $target_dir = __DIR__ . "/../Uploads/" . $target_subdir . "/";
    if (!is_dir($target_dir) && !mkdir($target_dir, 0755, true)) {
        return [null, "Server cannot write to {$target_subdir} folder."];
    }

    // Generate unique filename and save file
    $ext = $allowed_mimes[$mime][0];

    // For images, re-process to prevent malicious files disguised as images
    if (str_starts_with($mime, 'image/')) {
        $img = false;
        // Use GD to read and re-save the image, which can help strip out malicious content
        switch ($mime) {
            case 'image/jpeg': $img = @imagecreatefromjpeg($tmp); break;
            case 'image/png':  $img = @imagecreatefrompng($tmp);  break;
            case 'image/webp': $img = @imagecreatefromwebp($tmp); break;
        }
        if (!$img) return [null, "{$field} could not be processed as an image."];

        // Generate unique filename and save re-processed image
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
        // For videos, just move the uploaded file (after MIME check)
        $unique_name = uniqid('', true) . "." . $ext;
        $target_file = $target_dir . $unique_name;
        if (!move_uploaded_file($tmp, $target_file)) {
            return [null, "Could not save {$field}."];
        }
    }

    // Return the relative path to store in DB
    return ["Uploads/" . $target_subdir . "/" . $unique_name, null];
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // CSRF token validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        set_flash("Invalid CSRF token. Please try again.", "danger");
        header("Location: verify.php");
        exit();
    }

    // Ensure OTP verification step is complete before allowing submission
    if (empty($_SESSION['otp_verified'])) {
        $error = "Please complete phone verification before submitting your details.";
    } else {
        $full_name = trim($_POST['full_name'] ?? '');
        $id_number = trim($_POST['id_number'] ?? '');
        $address   = trim($_POST['address'] ?? '');
        $consent   = isset($_POST['consent']);

        // Validate inputs with rate limiting to prevent abuse
        if (rate_limit_exceeded($conn, 'verify_submit', (string)$user_id, 3, 3600)) {
            $error = "Too many verification submissions. Please wait an hour and try again.";
        } elseif ($full_name === '' || $id_number === '' || $address === '') {
            $error = "Please fill in all personal information fields.";
        } elseif (!preg_match('/^[A-Za-z0-9\-]{6,20}$/', $id_number)) {
            $error = "ID number must be 6-20 characters (letters, digits, or hyphens).";
        } elseif (!$consent) {
            $error = "You must agree to the consent statement before submitting.";
        } elseif (mb_strlen($full_name) > 200) {
            $error = "Full name cannot exceed 200 characters.";
        } elseif (mb_strlen($address) > 1000) {
            $error = "Address cannot exceed 1000 characters.";
        } else {
            // Validate and save uploaded files
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

            // Save files and capture any errors
            [$id_path, $err1]     = save_uploaded_file('id_document',       $image_mimes, 'verification');
            [$selfie_path, $err2] = save_uploaded_file('selfie_photo',      $image_mimes, 'verification');
            [$video_path, $err3]  = save_uploaded_file('verification_video', $video_mimes, 'verification');

            // If all files are saved successfully, insert or update verification record
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

                // Execute and check for success
                if ($stmt->execute()) {
                    rate_limit_log($conn, 'verify_submit', (string)$user_id);
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
}

require_once "../Includes/header.php";
?>

<div class="container">
     <h1>Seller Verification</h1>
     <p><a href="<?php echo BASE_URL; ?>/Listings/browse.php">&larr; Back to browse</a></p>
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
        
        <?php if ($otp_confirmed): ?>
            <div class="alert alert-success">
                Email verification complete. You can now submit your details below.
            </div>
        <?php elseif ($otp_pending): ?>
            <div class="otp-step">
                <h3>Enter the code we sent you</h3>
                <p>We've sent a 6-digit code to your registered email address. It expires in 5 minutes. Check your spam folder if you don't see it.</p>
                <form method="post" action="confirm_otp.php">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="text" name="code" inputmode="numeric" pattern="\d{6}" maxlength="6" required>
                    <button type="submit" class="btn btn-primary">Confirm Code</button>
                </form>
                <form method="post" action="request_otp.php" style="margin-top:0.5rem;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <button type="submit" class="btn btn-secondary">Resend code</button>
                </form>
            </div>
        <?php else: ?>
            <div class="otp-step">
                <h3>Verify your email address</h3>
                <p>We'll send a 6-digit code to the email address on your account.</p>
                <form method="post" action="request_otp.php">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <button type="submit" class="btn btn-primary">Send verification code</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($otp_confirmed): ?>
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
    <?php endif; ?>
</div>

<?php include "../Includes/footer.php"; ?>
