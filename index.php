
<?php
session_start();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {

    $product_id = (int)($_POST['product_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 1);
    $buyer_name = trim($_POST['buyer_name'] ?? '');
    $mobile_number = trim($_POST['mobile_number'] ?? '');

    if ($product_id <= 0 || $quantity < 1 || $quantity > 99 ||
        $buyer_name === '' || $mobile_number === '') {
        die("Please complete all order information.");
    }

    $stmt = $conn->prepare("SELECT id FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$product) {
        die("Product not found.");
    }

    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    $_SESSION['cart'][$product_id] =
        ($_SESSION['cart'][$product_id] ?? 0) + $quantity;

    $_SESSION['buyer_name'] = $buyer_name;
    $_SESSION['mobile_number'] = $mobile_number;

    header("Location: insert.php");
    exit;
}

$result = $conn->query("SELECT * FROM products");

if (!$result) {
    die("Error loading products: " . $conn->error);
}

$images = [
    1 => "cookies_and_cream.jpg",
    2 => "dark_choco.webp",
    3 => "matcha.webp",
    4 => "okinawa.webp",
    5 => "strawberry.webp",
    6 => "taro.webp"
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Milk Tea Kiosk</title>

<style>
* { box-sizing: border-box; }

body {
    font-family: Arial, sans-serif;
    background: #f8f9fa;
    margin: 0;
    padding: 20px;
}

h1 {
    text-align: center;
    color: #333;
}

.menu-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    max-width: 900px;
    margin: 30px auto;
}

.card {
    background: white;
    padding: 15px;
    border-radius: 10px;
    box-shadow: 0 4px 8px rgba(0,0,0,.1);
    text-align: center;
}

.card img {
    width: 100%;
    height: 200px;
    object-fit: cover;
    border-radius: 8px;
}

.card p {
    color: #e67e22;
    font-weight: bold;
}

button {
    background: #27ae60;
    color: white;
    border: none;
    padding: 12px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 16px;
    width: 100%;
}

button:hover { background: #219150; }

.cart-btn {
    display: block;
    width: 230px;
    margin: 30px auto;
    text-align: center;
    background: #2980b9;
    color: white;
    padding: 15px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: bold;
}

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    inset: 0;
    background: rgba(0,0,0,.6);
    justify-content: center;
    align-items: center;
    padding: 15px;
}

.modal-content {
    background: white;
    width: 100%;
    max-width: 400px;
    padding: 25px;
    border-radius: 15px;
    position: relative;
}

.close {
    position: absolute;
    right: 15px;
    top: 10px;
    width: auto;
    background: none;
    color: #777;
    font-size: 28px;
    padding: 0;
}

.modal-product { text-align: center; }

.modal-product img {
    width: 160px;
    height: 140px;
    object-fit: contain;
}

.price {
    color: #e67e22;
    font-size: 21px;
    font-weight: bold;
}

.modal-content label {
    display: block;
    margin: 12px 0 5px;
    font-weight: bold;
}

.modal-content input {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 15px;
}

.submit-btn { margin-top: 20px; }

@media(max-width:650px) {
    .menu-grid { grid-template-columns: repeat(2, 1fr); }
}

@media(max-width:450px) {
    .menu-grid { grid-template-columns: 1fr; }
}
</style>
</head>

<body>

<h1>🧋 Milk Tea Kiosk</h1>

<div class="menu-grid">

<?php while ($row = $result->fetch_assoc()): ?>

<?php
$id = (int)$row['id'];
$imageName = $images[$id] ?? ($row['image'] ?? '');
$imagePath = "images/" . basename($imageName);
?>

<div class="card">

    <img
        src="<?= htmlspecialchars($imagePath) ?>"
        alt="<?= htmlspecialchars($row['name']) ?>"
        onerror="this.style.display='none';"
    >

    <h3><?= htmlspecialchars($row['name']) ?></h3>

    <p>₱<?= number_format($row['price'], 2) ?></p>

    <button
        type="button"
        class="order-btn"
        data-id="<?= $id ?>"
        data-name="<?= htmlspecialchars($row['name'], ENT_QUOTES) ?>"
        data-price="<?= htmlspecialchars($row['price'], ENT_QUOTES) ?>"
        data-image="<?= htmlspecialchars($imagePath, ENT_QUOTES) ?>"
    >
        Add to Order
    </button>

</div>

<?php endwhile; ?>

</div>

<a href="insert.php" class="cart-btn">
    🛒 Review Order & Checkout
</a>

<!-- ORDER POPUP -->
<div class="modal" id="orderModal">

<div class="modal-content">

    <button type="button" class="close" id="closeModal">&times;</button>

    <h2 style="text-align:center;">🧋 Your Milk Tea Order</h2>

    <div class="modal-product">
        <img id="modalImage" src="" alt="Selected milk tea">
        <h3 id="modalName"></h3>
        <div class="price" id="modalPrice"></div>
    </div>

    <form method="POST">

        <input type="hidden" name="product_id" id="productId">

        <label>Quantity:</label>
        <input type="number" name="quantity"
               id="quantity" value="1" min="1" max="99" required>

        <label>Buyer's Name:</label>
        <input type="text" name="buyer_name"
               placeholder="Enter your name"
               value="<?= htmlspecialchars($_SESSION['buyer_name'] ?? '') ?>"
               required>

        <label>Mobile Number:</label>
        <input type="tel" name="mobile_number"
               placeholder="Enter mobile number"
               value="<?= htmlspecialchars($_SESSION['mobile_number'] ?? '') ?>"
               pattern="[0-9+\-\s]{7,20}" required>

        <button type="submit" name="add_to_cart" class="submit-btn">
            Submit Order
        </button>

    </form>

</div>
</div>

<script>
const modal = document.getElementById("orderModal");

document.querySelectorAll(".order-btn").forEach(button => {

    button.addEventListener("click", function() {

        document.getElementById("productId").value = this.dataset.id;
        document.getElementById("modalName").textContent = this.dataset.name;

        document.getElementById("modalPrice").textContent =
            "₱" + parseFloat(this.dataset.price).toFixed(2);

        document.getElementById("modalImage").src = this.dataset.image;
        document.getElementById("quantity").value = 1;

        modal.style.display = "flex";
    });

});

document.getElementById("closeModal").onclick = function() {
    modal.style.display = "none";
};

window.onclick = function(event) {
    if (event.target === modal) {
        modal.style.display = "none";
    }
};
</script>

</body>
</html>
