<?php
include "../Includes/auth_admin.php";
include "../Includes/db.php";

$migration_path = __DIR__ . "/../database/messages_migration.sql";
$status   = null;
$error    = null;
$exists   = false;

$check = $conn->query("SHOW TABLES LIKE 'messages'");
if ($check && $check->num_rows > 0) {
    $exists = true;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && !$exists) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "CSRF token validation failed.";
    } else {
        $sql = file_get_contents($migration_path);
        if ($sql === false) {
            $error = "Could not read migration file: $migration_path";
        } elseif ($conn->multi_query($sql)) {
            do {
                if ($result = $conn->store_result()) {
                    $result->free();
                }
            } while ($conn->more_results() && $conn->next_result());

            $check = $conn->query("SHOW TABLES LIKE 'messages'");
            $exists = $check && $check->num_rows > 0;
            $status = $exists
                ? "Messages table installed successfully."
                : "Migration ran but messages table is still missing.";
        } else {
            $error = "Install failed: " . $conn->error;
        }
    }
}

include "../Includes/header.php";
?>

<div class="container">
    <h1>Install Messages Table</h1>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($status): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($status); ?></div>
    <?php endif; ?>

    <?php if ($exists): ?>
        <div class="alert alert-info">
            The <code>messages</code> table already exists. Nothing to do.
        </div>
        <p><a href="../Messages/inbox.php" class="btn btn-primary">Open Inbox</a></p>
    <?php else: ?>
        <p>This will create the <code>messages</code> table required by the in-app messaging feature.</p>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <button type="submit" class="btn btn-primary">Run migration now</button>
        </form>
    <?php endif; ?>
</div>

<?php include "../Includes/footer.php"; ?>
