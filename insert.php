
<?php
session_start();
require 'db.php';

$error = "";
$success = false;

// Remove product
if (isset($_GET['remove'])) {

    $id = (int)$_GET['remove'];

    unset($_SESSION['cart'][$id]);

    header("Location: insert.php");
    exit;
}

// Confirm and save order
if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['checkout'])) {

    if (empty($_SESSION['cart'])) {
        $error = "Your cart is empty.";
    } else {

        $buyer_name = $_SESSION['buyer_name'] ?? '';
        $mobile_number = $_SESSION['mobile_number'] ?? '';

        if ($buyer_name === '' || $mobile_number === '') {
            $error = "Customer information is missing.";
        } else {

            $order_number = "MT-" . date("YmdHis");
            $total_amount = 0;

            try {

                $conn->begin_transaction();

                // Calculate total from database prices
                foreach ($_SESSION['cart'] as $id => $qty) {

                    $id = (int)$id;
                    $qty = (int)$qty;

                    if ($qty < 1) {
                        throw new Exception("Invalid quantity.");
                    }

                    $stmt = $conn->prepare(
                        "SELECT price FROM products WHERE id = ?"
                    );

                    $stmt->bind_param("i", $id);
                    $stmt->execute();

                    $product = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if (!$product) {
                        throw new Exception("Product not found.");
                    }

                    $total_amount += $product['price'] * $qty;
                }

                // Insert order
                $stmt = $conn->prepare(
                    "INSERT INTO orders
                    (order_number, buyer_name, mobile_number, total_amount)
                    VALUES (?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    "sssd",
                    $order_number,
                    $buyer_name,
                    $mobile_number,
                    $total_amount
                );

                $stmt->execute();

                $order_id = $conn->insert_id;
                $stmt->close();

                // Insert ordered products
                $stmt_item = $conn->prepare(
                    "INSERT INTO order_items
                    (order_id, product_id, quantity)
                    VALUES (?, ?, ?)"
                );

                foreach ($_SESSION['cart'] as $id => $qty) {

                    $id = (int)$id;
                    $qty = (int)$qty;

                    $stmt_item->bind_param(
                        "iii",
                        $order_id,
                        $id,
                        $qty
                    );

                    $stmt_item->execute();
                }

                $stmt_item->close();

                // Commit all database changes
                $conn->commit();

                // Remember receipt details, then clear cart
                $_SESSION['last_order'] = [
                    'order_number' => $order_number,
                    'buyer_name' => $buyer_name,
                    'mobile_number' => $mobile_number,
                    'total_amount' => $total_amount
                ];

                unset($_SESSION['cart']);
                unset($_SESSION['buyer_name']);
                unset($_SESSION['mobile_number']);

                $success = true;

            } catch (Throwable $e) {

                $conn->rollback();

                $error = "Order was not saved: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Milk Tea Checkout</title>

<style>
* { box-sizing: border-box; }

body {
    font-family: Arial, sans-serif;
    background: #f8f9fa;
    padding: 30px 15px;
}

.receipt {
    background: white;
    max-width: 500px;
    margin: auto;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 4px 10px rgba(0,0,0,.1);
}

h2 { text-align: center; }

.customer {
    background: #f2f9f4;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.item {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 12px;
    border-bottom: 1px solid #eee;
    padding-bottom: 12px;
}

.total {
    font-size: 21px;
    font-weight: bold;
    margin-top: 20px;
    text-align: right;
    color: #27ae60;
}

.btn {
    display: block;
    margin-top: 15px;
    padding: 13px;
    background: #2980b9;
    color: white;
    text-decoration: none;
    border-radius: 6px;
    text-align: center;
    width: 100%;
    border: none;
    cursor: pointer;
    font-size: 16px;
}

.btn-pay {
    background: #27ae60;
    font-weight: bold;
}

.remove-link {
    color: red;
    text-decoration: none;
    font-weight: bold;
    margin-left: 8px;
}

.success {
    color: #27ae60;
    text-align: center;
    font-size: 20px;
    font-weight: bold;
}

.error {
    color: red;
    text-align: center;
}
</style>
</head>

<body>

<div class="receipt">

<h2>🧾 Your Milk Tea Order</h2>

<?php if ($success): ?>

    <?php $receipt = $_SESSION['last_order']; ?>

    <p class="success">✓ Order Placed Successfully!</p>

    <p>Order Number:
        <strong><?= htmlspecialchars($receipt['order_number']) ?></strong>
    </p>

    <p>Customer:
        <?= htmlspecialchars($receipt['buyer_name']) ?>
    </p>

    <p>Mobile:
        <?= htmlspecialchars($receipt['mobile_number']) ?>
    </p>

    <div class="total">
        Total: ₱<?= number_format($receipt['total_amount'], 2) ?>
    </div>

    <p style="text-align:center;">
        Please proceed to the counter to pay.
    </p>

    <a href="index.php" class="btn">Start New Order</a>

<?php else: ?>

    <?php if ($error): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php if (!empty($_SESSION['cart'])): ?>

        <div class="customer">

            <h3>👤 Customer Information</h3>

            <p>Name:
                <strong>
                    <?= htmlspecialchars($_SESSION['buyer_name'] ?? '') ?>
                </strong>
            </p>

            <p>Mobile:
                <strong>
                    <?= htmlspecialchars($_SESSION['mobile_number'] ?? '') ?>
                </strong>
            </p>

        </div>

        <h3>Ordered Products</h3>

        <?php
        $grand_total = 0;

        foreach ($_SESSION['cart'] as $id => $qty):

            $id = (int)$id;
            $qty = (int)$qty;

            $stmt = $conn->prepare(
                "SELECT name, price FROM products WHERE id = ?"
            );

            $stmt->bind_param("i", $id);
            $stmt->execute();

            $p = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($p):

                $subtotal = $p['price'] * $qty;
                $grand_total += $subtotal;
        ?>

            <div class="item">

                <div>
                    <strong><?= htmlspecialchars($p['name']) ?></strong>
                    <br>
                    <small>
                        ₱<?= number_format($p['price'], 2) ?>
                        × <?= $qty ?>
                    </small>
                </div>

                <div>
                    ₱<?= number_format($subtotal, 2) ?>

                    <a href="?remove=<?= $id ?>"
                       class="remove-link"
                       onclick="return confirm('Remove this product?')">
                        &times;
                    </a>
                </div>

            </div>

        <?php
            endif;
        endforeach;
        ?>

        <div class="total">
            Total: ₱<?= number_format($grand_total, 2) ?>
        </div>

        <form method="POST">
            <button type="submit" name="checkout" class="btn btn-pay">
                ✓ Confirm Order
            </button>
        </form>

        <a href="index.php" class="btn">+ Add More Milk Tea</a>

    <?php else: ?>

        <p style="text-align:center;">Your cart is empty.</p>

        <a href="index.php" class="btn">Back to Menu</a>

    <?php endif; ?>

<?php endif; ?>

</div>

</body>
</html>
