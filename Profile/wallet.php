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

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        set_flash('error', "CSRF token validation failed.");
        header("Location: wallet.php");
        exit();
    }
    $action = $_POST['action'];
    $amount = filter_var($_POST['amount'] ?? '', FILTER_VALIDATE_FLOAT);

    if ($action === 'deposit') {
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

                $stmt = $conn->prepare("SELECT balance FROM wallet WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $balance_after = (float)$stmt->get_result()->fetch_assoc()['balance'];
                $stmt->close();

                $order_id = null;
                $stmt = $conn->prepare("INSERT INTO wallet_transactions (user_id, order_id, amount, type, balance_after) VALUES (?, ?, ?, 'deposit', ?)");
                $stmt->bind_param("iidd", $user_id, $order_id, $amount, $balance_after);
                $stmt->execute();
                $stmt->close();

                $conn->commit();

                $success = "Deposited R" . number_format($amount, 2) . " successfully.";
                $balance = $balance_after;
            } catch (Throwable $e) {
                $conn->rollback();
                $error = "Deposit failed. Please try again.";
            }
        }
    } elseif ($action === 'withdraw') {
        if ($amount === false || $amount <= 0) {
            $error = "Please enter a valid withdrawal amount greater than 0.";
        } elseif ($amount > 100000) {
            $error = "Maximum single withdrawal is R100 000.";
        } else {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("SELECT balance FROM wallet WHERE user_id = ? FOR UPDATE");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $current = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$current || (float)$current['balance'] < $amount) {
                    throw new Exception("Insufficient wallet balance for that withdrawal.");
                }

                $stmt = $conn->prepare("UPDATE wallet SET balance = balance - ? WHERE user_id = ?");
                $stmt->bind_param("di", $amount, $user_id);
                $stmt->execute();
                $stmt->close();

                $balance_after = (float)$current['balance'] - $amount;

                $order_id = null;
                $stmt = $conn->prepare("INSERT INTO wallet_transactions (user_id, order_id, amount, type, balance_after) VALUES (?, ?, ?, 'withdraw', ?)");
                $stmt->bind_param("iidd", $user_id, $order_id, $amount, $balance_after);
                $stmt->execute();
                $stmt->close();

                $conn->commit();

                $success = "Withdrew R" . number_format($amount, 2) . " successfully.";
                $balance = $balance_after;
            } catch (Throwable $e) {
                $conn->rollback();
                $error = $e->getMessage();
            }
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

    <div style="display:flex; flex-wrap:wrap; gap:2rem;">
        <div style="flex:1; min-width:260px;">
            <h3>Deposit Funds</h3>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <label for="deposit_amount">Amount (ZAR):</label>
                <input type="number" name="amount" id="deposit_amount" min="1" step="0.01" required>
                <button type="submit" name="action" value="deposit" class="btn btn-primary">Deposit</button>
            </form>
            <p><small>Demo deposit only — no real payment gateway is connected. Production would route via Payfast / Yoco.</small></p>
        </div>

        <div style="flex:1; min-width:260px;">
            <h3>Withdraw Funds</h3>
            <form method="post" onsubmit="return confirm('Withdraw these funds from your wallet?');">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <label for="withdraw_amount">Amount (ZAR):</label>
                <input type="number" name="amount" id="withdraw_amount" min="1" step="0.01" max="<?php echo htmlspecialchars((string)$balance); ?>" required>
                <button type="submit" name="action" value="withdraw" class="btn btn-secondary" <?php if ($balance <= 0) echo 'disabled'; ?>>Withdraw</button>
            </form>
            <p><small>Available to withdraw: R<?php echo number_format($balance, 2); ?>. Funds in escrow for active orders cannot be withdrawn.</small></p>
        </div>
    </div>

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
                        $is_credit = in_array($type, ['deposit', 'release', 'refund'], true);
                        $sign = $is_credit ? '+' : '-';
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
