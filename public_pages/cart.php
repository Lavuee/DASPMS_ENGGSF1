<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Customer') {
    header("Location: ../views/login.php");
    exit;
}

$cart = $_SESSION['cart'] ?? [];
$total = 0;

foreach ($cart as $item) {
    $total += $item['price'] * $item['qty'];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Your Cart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<?php include '../partials/public-navbar.php'; ?>

<div class="container mt-5">

    <h2 class="mb-4">🛒 Your Cart</h2>

    <?php if(isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
    <?php endif; ?>

    <?php if(isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
    <?php endif; ?>

    <?php if (empty($cart)): ?>
        <div class="alert alert-info">Your cart is empty.</div>
    <?php else: ?>

        <table class="table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Price</th>
                    <th>Qty</th>
                    <th>Total</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cart as $part_id => $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['name']) ?></td>
                        <td>₱<?= number_format($item['price'], 2) ?></td>
                        <td>
                            <form action="../controllers/CartController.php" method="POST" class="d-flex">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="part_id" value="<?= $part_id ?>">

                                <input type="number" name="quantity" value="<?= $item['qty'] ?>" min="1" class="form-control me-2" style="width: 80px;">

                                <button class="btn btn-primary btn-sm">Update</button>
                            </form>
                        </td>
                        <td>₱<?= number_format($item['price'] * $item['qty'], 2) ?></td>
                        <td>
                            <form action="../controllers/CartController.php" method="POST">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="part_id" value="<?= $part_id ?>">

                                <button class="btn btn-danger btn-sm">Remove</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h4 class="text-end">Total: ₱<?= number_format($total, 2) ?></h4>

        <div class="d-flex justify-content-between mt-4">
            <a href="parts.php" class="btn btn-secondary">← Continue Shopping</a>

            <form action="../controllers/CheckoutController.php" method="POST">
                <button class="btn btn-success">Reserve Items</button>
            </form>
        </div>

    <?php endif; ?>

</div>

</body>
</html>