<?php
include '../Includes/auth_admin.php';
include '../Includes/db.php';

$sql = "SELECT 
            o.id,
            u.name as user_name, 
            l.title as listing_title, 
            o.quantity, o.total_price, 
            o.status 
        FROM 
            orders o 
        JOIN 
            users u  ON o.buyer_id = u.id 
        JOIN 
            listings l ON o.listing_id = l.id";
$result = $conn->query($sql);

if($result->num_rows > 0){
    $orders = [];
    while($row = $result->fetch_assoc()){
        $orders[] = $row;
    }
} else {
    echo "No orders found.";
    exit();
}

if($_SERVER["REQUEST_METHOD"] == "POST"){
    $order_id = $_POST['order_id'];
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $sql = "UPDATE orders SET status = '$status' WHERE id = '$order_id'";
    $conn->query($sql);
    header("Location: orders.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Management</title>
    <link rel="stylesheet" href="../CSS/style.css">
</head>
<body>
    <div class="container">
        <h1>Order Management</h1>
        <p>Use the dropdown to update order status.</p>
        <a href="dashboard.php">Back to Dashboard</a>
        <table>
            <thead>
                <tr>
                    <th>User</th>
                    <th>Listing</th>
                    <th>Quantity</th>
                    <th>Total Price</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($orders as $order): ?>
                <tr>
                    <td><?php echo htmlspecialchars($order['user_name']); ?></td>
                    <td><?php echo htmlspecialchars($order['listing_title']); ?></td>
                    <td><?php echo $order['quantity']; ?></td>
                    <td>R<?php echo number_format($order['total_price'], 2); ?></td>
                    <td><?php echo ucfirst($order['status']); ?></td>
                    <td>
                        <form method="POST" action="orders.php">
                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                            <select name="status">
                                <option value="received" <?php if($order['status'] == 'received') echo 'selected'; ?>>Received</option>
                                <option value="inspecting" <?php if($order['status'] == 'inspecting') echo 'selected'; ?>>Inspecting</option>
                                <option value="ready" <?php if($order['status'] == 'ready') echo 'selected'; ?>>Ready</option>
                                <option value="delivered" <?php if($order['status'] == 'delivered') echo 'selected'; ?>>Delivered</option>
                                <option value="cancelled" <?php if($order['status'] == 'cancelled') echo 'selected'; ?>>Cancelled</option>
                            </select>
                            <button type="submit">Update</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>