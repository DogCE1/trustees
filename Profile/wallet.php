<?php
include "../Includes/auth.php";
include "../Includes/db.php";

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT balance FROM wallet WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$wallet = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$wallet) {
    $stmt = $conn->prepare("INSERT INTO wallet (user_id, balance) VALUES (?, 0.00)");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    $balance = 0.00;
} else {
    $balance = (float)$wallet['balance'];
}

$error = null;
$success = null;

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['amount'])) {
     if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        set_flash('error', "CSRF token validation failed."); 
        header("Location: wallet.php");
        exit();
    }
    $amount = filter_var($_POST['amount'], FILTER_VALIDATE_FLOAT);

    if ($amount === false || $amount <= 0) {
        $error = "Please enter a valid deposit amount greater than 0.";
    } elseif ($amount > 100000) {
        $error = "Maximum single deposit is R100 000.";
    } else {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("UPDATE wallet SET balance = balance + ? WHERE user_id = ?");
            $stmt->bind_param("di", $amount, $user_id);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("INSERT INTO wallet_transactions (user_id, amount, type) VALUES (?, ?, 'deposit')");
            $stmt->bind_param("id", $user_id, $amount);
            $stmt->execute();
            $stmt->close();

            $conn->commit();

            $success = "Deposited R" . number_format($amount, 2) . " successfully.";
            $balance += $amount;
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Deposit failed. Please try again.";
        }
    }
}

$stmt = $conn->prepare("
    SELECT amount, type, created_at
    FROM wallet_transactions
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 50
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include "../Includes/header.php";
?>

<div class="container">
    <h1>My Wallet</h1>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div class="wallet-balance">
        <h2>Available Balance: R<?php echo number_format($balance, 2); ?></h2>
        <p>Funds held for active orders are deducted from this balance until you confirm delivery.</p>
    </div>

    <h3>Deposit Funds</h3>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <label for="amount">Amount (ZAR):</label>
        <input type="number" name="amount" id="amount" min="1" step="0.01" required>
        <button type="submit" class="btn btn-primary">Deposit</button>
    </form>
    <p><small>Demo deposit only — no real payment gateway is connected. Production would route via Payfast / Yoco.</small></p>

    <h3>Recent Transactions</h3>
    <?php if (empty($transactions)): ?>
        <p>No transactions yet.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $tx): ?>
                    <?php
                        $type = $tx['type'];
                        $sign = $type === 'deposit' || $type === 'release' ? '+' : '-';
                    ?>
                    <tr>
                        <td><?php echo date("Y-m-d H:i", strtotime($tx['created_at'])); ?></td>
                        <td><?php echo htmlspecialchars(ucfirst($type)); ?></td>
                        <td><?php echo $sign; ?>R<?php echo number_format((float)$tx['amount'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include "../Includes/footer.php"; ?>
