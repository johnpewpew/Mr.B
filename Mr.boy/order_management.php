<?php
include 'config.php';
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_name'])) {
    header('location:index.php');
    exit();
}

// Fetch all categories for category buttons
$categories = $conn->query("SELECT * FROM categories");

// Handle search and item addition
$search_term = $_GET['search'] ?? '';
$category_id = $_GET['category_id'] ?? null;

// Fetch items based on search or category
// Modify the item fetch query to include category name
if (!empty($search_term)) {
    $item_query = $conn->prepare("SELECT items.*, categories.name AS category_name FROM items 
                                  JOIN categories ON items.category_id = categories.id 
                                  WHERE items.name LIKE ?");
    $like_term = "%" . $search_term . "%";
    $item_query->bind_param("s", $like_term);
    $item_query->execute();
    $items = $item_query->get_result();
} else {
    if ($category_id) {
        $item_query = $conn->prepare("SELECT items.*, categories.name AS category_name FROM items 
                                      JOIN categories ON items.category_id = categories.id 
                                      WHERE items.category_id = ?");
        $item_query->bind_param("i", $category_id);
        $item_query->execute();
        $items = $item_query->get_result();
    } else {
        $items = $conn->query("SELECT items.*, categories.name AS category_name FROM items 
                               JOIN categories ON items.category_id = categories.id");
    }
}


// Initialize the current order array
if (!isset($_SESSION['order'])) {
    $_SESSION['order'] = [];
}

// Handle order actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Clear order action
    if (isset($_POST['clear_order'])) {
        $_SESSION['order'] = [];
    }
    // Pay now action
    elseif (isset($_POST['pay_now'])) {
        $payment = $change = $error = "";
        $popupVisible = false;

        // Calculate total amount for the order
        $totalAmount = array_reduce($_SESSION['order'], function ($sum, $order_item) {
            return $sum + ($order_item['price'] * $order_item['quantity']);
        }, 0);

        if (empty($_SESSION['order'])) {
            $error = "No items in the order to process payment.";
            $popupVisible = true;
        } else {
            if (!empty($_POST["payment-input"])) {
                $payment = floatval($_POST["payment-input"]);
                if ($payment >= $totalAmount) {
                    $change = $payment - $totalAmount;
                    $today = date('Y-m-d');

                    // Update daily sales
                    $check_sales_query = $conn->prepare("SELECT total_sales FROM daily_sales WHERE date = ?");
                    $check_sales_query->bind_param("s", $today);
                    $check_sales_query->execute();
                    $result = $check_sales_query->get_result();

                    if ($result->num_rows > 0) {
                        $current_sales = $result->fetch_assoc()['total_sales'];
                        $new_sales = $current_sales + $totalAmount;
                        $update_sales_query = $conn->prepare("UPDATE daily_sales SET total_sales = ? WHERE date = ?");
                        $update_sales_query->bind_param("ds", $new_sales, $today);
                        $update_sales_query->execute();
                    } else {
                        $insert_sales_query = $conn->prepare("INSERT INTO daily_sales (date, total_sales) VALUES (?, ?)");
                        $insert_sales_query->bind_param("sd", $today, $totalAmount);
                        $insert_sales_query->execute();
                    }

                    // Deduct item quantities from inventory and record sales
                    foreach ($_SESSION['order'] as $order_item) {
                        $size_column = ($order_item['size'] === 'Large') ? 'large_quantity' : 'medium_quantity';

                        // Update inventory for all items with the same size
                        $update_query = $conn->prepare("UPDATE items SET $size_column = $size_column - ? WHERE $size_column >= ?");
                        $update_query->bind_param("ii", $order_item['quantity'], $order_item['quantity']);
                        $update_query->execute();

                        // Record sale in the sales table
                        $insert_sales_query = $conn->prepare("INSERT INTO sales (item_id, quantity, size) VALUES (?, ?, ?)");
                        $insert_sales_query->bind_param("iis", $order_item['id'], $order_item['quantity'], $order_item['size']);
                        $insert_sales_query->execute();
                    }

                    // Prepare order details for transaction record
                    $orderDetails = '';
                    foreach ($_SESSION['order'] as $order_item) {
                        $orderDetails .= $order_item['name'] . ' x ' . $order_item['quantity'] . ' (' . $order_item['price'] . ' each)\n';
                    }

                    // Insert transaction record
                    $paymentStatus = "Paid";
                    $transaction_query = $conn->prepare("INSERT INTO transactions (total_amount, order_details, payment_status) VALUES (?, ?, ?)");
                    $transaction_query->bind_param("dss", $totalAmount, $orderDetails, $paymentStatus);
                    $transaction_query->execute();

                    $_SESSION['order'] = [];
                    $popupVisible = false;
                } else {
                    $error = "Insufficient payment. Please enter a valid amount.";
                    $popupVisible = true;
                }
            } else {
                $error = "Please enter a valid payment amount.";
                $popupVisible = true;
            }
        }
    }
    // Add item to order action
    elseif (isset($_POST['add_item'])) {
        $item_id = $_POST['item_id'];
        $quantity = $_POST['quantity'];
        $size = $_POST['size'];

        // Fetch the item details from the database
        $item_query = $conn->prepare("SELECT * FROM items WHERE id = ?");
        $item_query->bind_param("i", $item_id);
        $item_query->execute();
        $item = $item_query->get_result()->fetch_assoc();

        $price = ($size == 'Large') ? $item['large_price'] : $item['medium_price'];
        $size_column = ($size == 'Large') ? 'large_quantity' : 'medium_quantity';

        // Check stock for the selected size
        $available_quantity = $item[$size_column];

        if ($available_quantity > 0) {
            // Check if there's enough stock for the requested quantity
            if ($available_quantity >= $quantity) {
                // Find if item already exists in the current order
                $existing_item_index = null;
                foreach ($_SESSION['order'] as $index => $order_item) {
                    if ($order_item['id'] === $item['id'] && $order_item['size'] === $size) {
                        $existing_item_index = $index;
                        break;
                    }
                }

                // If item is new in the order, add it
                if ($existing_item_index === null) {
                    $_SESSION['order'][] = [
                        'id' => $item['id'],
                        'name' => $item['name'] . " ($size)",
                        'quantity' => $quantity,
                        'price' => $price,
                        'size' => $size,
                        'image' => $item['image']
                    ];
                } else {
                    // Update quantity for an existing item
                    $remaining_quantity = $available_quantity - $_SESSION['order'][$existing_item_index]['quantity'];
                    if ($remaining_quantity >= $quantity) {
                        $_SESSION['order'][$existing_item_index]['quantity'] += $quantity;
                    } else {
                        echo "<script>alert('Insufficient stock for {$item['name']}. Available: $remaining_quantity');</script>";
                    }
                }
            } else {
                // Show alert only if there is some stock, but it's less than requested
                echo "<script>alert('Insufficient stock for {$item['name']}. Available: $available_quantity');</script>";
            }
        }
        // Do not show any alert if stock is zero, simply do not add to order
    }
}

// Calculate total amount for the order
$totalAmount = array_reduce($_SESSION['order'], function ($sum, $order_item) {
    return $sum + ($order_item['price'] * $order_item['quantity']);
}, 0);



// Payment logic
$payment = $change = $error = "";

$popupVisible = false; // Variable to control popup visibility

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["pay_now"])) {
    if (empty($_SESSION['order'])) {
        $error = "No items in the order to process payment.";
        $popupVisible = true; // Keep the popup open on error
    } else {
        if (!empty($_POST["payment-input"])) {
            $payment = floatval($_POST["payment-input"]);
            if ($payment >= $totalAmount) {
                $change = $payment - $totalAmount;

                // Update daily sales
                $today = date('Y-m-d');
                $check_sales_query = $conn->prepare("SELECT total_sales FROM daily_sales WHERE date = ?");
                $check_sales_query->bind_param("s", $today);
                $check_sales_query->execute();
                $result = $check_sales_query->get_result();

                if ($result->num_rows > 0) {
                    $current_sales = $result->fetch_assoc()['total_sales'];
                    $new_sales = $current_sales + $totalAmount;
                    $update_sales_query = $conn->prepare("UPDATE daily_sales SET total_sales = ? WHERE date = ?");
                    $update_sales_query->bind_param("ds", $new_sales, $today);
                    $update_sales_query->execute();
                } else {
                    $insert_sales_query = $conn->prepare("INSERT INTO daily_sales (date, total_sales) VALUES (?, ?)");
                    $insert_sales_query->bind_param("sd", $today, $totalAmount);
                    $insert_sales_query->execute();
                }

                // Deduct item quantities from inventory
                foreach ($_SESSION['order'] as $order_item) {
                    $item_id = $order_item['id'];
                    $item_query = $conn->prepare("SELECT quantity FROM items WHERE id = ?");
                    $item_query->bind_param("i", $item_id);
                    $item_query->execute();
                    $item_result = $item_query->get_result()->fetch_assoc();

                    $new_quantity = $item_result['quantity'] - $order_item['quantity'];
                    $update_query = $conn->prepare("UPDATE items SET quantity = ? WHERE id = ?");
                    $update_query->bind_param("ii", $new_quantity, $item_id);
                    $update_query->execute();
                }

                // Prepare order details for transaction record
                $orderDetails = '';
                foreach ($_SESSION['order'] as $order_item) {
                    $orderDetails .= $order_item['name'] . ' x ' . $order_item['quantity'] . ' (' . $order_item['price'] . ' each)\n';
                }

                // Insert transaction into transactions table only if order is not empty
                if (!empty($_SESSION['order'])) {
                    $paymentStatus = "Paid";
                    $transaction_query = $conn->prepare("INSERT INTO transactions (total_amount, order_details, payment_status) VALUES (?, ?, ?)");
                    $transaction_query->bind_param("dss", $totalAmount, $orderDetails, $paymentStatus);
                    $transaction_query->execute();
                }

                $_SESSION['order'] = []; // Clear the order after successful payment
                $totalAmount = 0; // Reset total amount
                $popupVisible = false; // Hide popup after successful payment
            } else {
                $error = "Insufficient payment. Please enter a valid amount.";
                $popupVisible = true; // Keep the popup open on insufficient payment
            }
        } else {
            $error = "Please enter a valid payment amount.";
            $popupVisible = true; // Keep the popup open if no payment input
        }
    }
}
$categories = $conn->query("SELECT * FROM categories");

// Fetch the first item only to calculate remaining cups
$first_cup_query = $conn->query("SELECT id, name, medium_quantity, large_quantity FROM items LIMIT 1");
$first_cup = $first_cup_query->fetch_assoc();

// Initialize remaining cups for the first item
$remainingMedium = $first_cup ? $first_cup['medium_quantity'] : 0;
$remainingLarge = $first_cup ? $first_cup['large_quantity'] : 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_item') {
    $itemIdToRemove = $_POST['id'];

    // Find the item in the session order and remove it
    foreach ($_SESSION['order'] as $index => $order_item) {
        if ($order_item['id'] == $itemIdToRemove) {
            // Remove the item from the order array
            unset($_SESSION['order'][$index]);
            $_SESSION['order'] = array_values($_SESSION['order']); // Reindex the array

            // Calculate new total
            $newTotalAmount = array_reduce($_SESSION['order'], function ($sum, $order_item) {
                return $sum + ($order_item['price'] * $order_item['quantity']);
            }, 0);

            // Respond with new total amount and success status
            echo json_encode([
                'success' => true,
                'newTotalAmount' => $newTotalAmount
            ]);
            exit;
        }
    }

    // If item wasn't found, return failure response
    echo json_encode(['success' => false]);
    exit;
}

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Management</title>
    <link rel="stylesheet" href="meme.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100..900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
</head>

<body>
    <div class="container">
        <!-- Sidebar Navigation -->
        <div class="sidebar">
            <?php include 'order_side.php' ?>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="search-container">
                <form method="GET" action="order_management.php">
                    <input type="text" placeholder="Search" name="search" id="search-input" autocomplete="off" list="suggestions-list">
                    <datalist id="suggestions-list"></datalist>
                    <button class="search-button" type="submit">
                        <h3>Search</h3>
                    </button>
                </form>
            </div>

            <div class="buttons-container">
                <?php while ($row = $categories->fetch_assoc()) { ?>
                    <form method="GET" action="order_management.php" style="display: inline;">
                        <input type="hidden" name="category_id" value="<?= $row['id'] ?>">
                        <button class="category-button" type="submit">
                            <h5><?= $row['name'] ?></h5>
                        </button>
                    </form>
                <?php } ?>
                <form method="GET" action="order_management.php" style="display: inline;">
                    <button class="category-button" type="submit">
                        <h5>All Items</h5>
                    </button>
                </form>
            </div>

            <div class="product-container">
                <div class="product-grid">
                    <?php while ($item = $items->fetch_assoc()) {
                        $mediumOutOfStock = ($item['medium_quantity'] <= 0);
                        $largeOutOfStock = ($item['large_quantity'] <= 0);
                        $outOfStockClass = ($mediumOutOfStock && $largeOutOfStock) ? 'out-of-stock' : '';
                    ?>
                        <div class="product-item <?= $item['status'] == 0 ? 'out-of-stock' : '' ?>" data-item-id="<?= $item['id'] ?>">
                            <small class="category-name"><?= htmlspecialchars($item['category_name']) ?></small> <!-- Display category name here -->
                            <img src="<?= $item['image'] ?>" alt="Product Image">
                            <h5 class="<?= $outOfStockClass ?>"><?= htmlspecialchars($item['name']) ?></h5>
                            <p>Medium Price: ₱<?= number_format($item['medium_price'], 2) ?>
                                <?= $mediumOutOfStock ? '<span class="out-of-stock-text">(Out of Stock)</span>' : '' ?>
                            </p>
                            <p>Large Price: ₱<?= number_format($item['large_price'], 2) ?>
                                <?= $largeOutOfStock ? '<span class="out-of-stock-text">(Out of Stock)</span>' : '' ?>
                            </p>
                        </div>
                    <?php } ?>
                </div>
            </div>


            <div class="pagination">
                <!-- Add pagination logic here -->
            </div>
        </div>

        <!-- Order Section -->
        <div class="order-section">
            <div class="order-header">
                <h1>Current Order</h1>
                <form method="POST" action="order_management.php" onsubmit="return confirmClearOrder();">
                    <button class="clear-button" type="submit" name="clear_order">
                        <h3>Clear</h3>
                    </button>
                </form>
            </div>

            <!-- JavaScript for Confirmation Dialog -->
            <script>
                function confirmClearOrder() {
                    return confirm("Are you sure you want to clear the current order?");
                }
            </script>

            <div class="order-list" id="order-list">
                <?php if (!empty($_SESSION['order'])): ?>
                    <ul>
                        <?php foreach ($_SESSION['order'] as $order_item): ?>
                            <li class="order-item" data-item-id="<?= $order_item['id'] ?>">
                                <img src="<?= $order_item['image'] ?>" alt="Item Image" class="order-item-image">
                                <div class="order-item-details">
                                    <h5><?= $order_item['name'] ?></h5>
                                    <p>₱<?= $order_item['price'] ?></p>
                                </div>
                                <div class="order-item-actions">
                                    <input type="number" value="<?= $order_item['quantity'] ?>" class="item-quantity" min="1">
                                    <span class="material-icons-outlined delete-icon">delete</span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>No items in the order</p>
                <?php endif; ?>
            </div>

            <!-- Order Section -->
            <div class="order-footer">
                <div class="quantity-labels">
                    <p>Medium Stock: <?= $remainingMedium ?></p>
                    <p>Large Stock: <?= $remainingLarge ?></p>
                </div>
            </div>
            <div class="order-footer1">
                <p>Total: ₱<span id="total-amount"><?= number_format($totalAmount, 2); ?></span></p>
                <div class="payment-buttons">
                    <button class="pay-now" id="pay-now-btn">
                        <h1>Pay Now</h1>
                    </button>
                </div>
            </div>

        </div>
    </div>

    <!-- Quantity Input Popup -->
    <!-- Quantity Input Popup -->
    <div class="popup" id="quantity-popup">
        <form method="POST" action="order_management.php">
            <input type="hidden" id="item-id-input" name="item_id">
            <label for="size">Select Size:</label>
            <select id="size" name="size" required>
                <option value="Medium">Medium</option>
                <option value="Large">Large</option>
            </select>
            
            <label for="quantity">Enter Quantity:</label>
            <input type="number" id="quantity" name="quantity" min="1" required>

            <button type="submit" name="add_item">Add to Order</button>
        </form>
    </div>

    <div class="overlay" id="overlay"></div>

    <script>
       // Select all necessary elements
const productItems = document.querySelectorAll('.product-item');
const orderItems = document.querySelectorAll('.order-item');
const deleteIcons = document.querySelectorAll('.delete-icon');
const popup = document.getElementById('quantity-popup');
const overlay = document.getElementById('overlay');
const itemIdInput = document.getElementById('item-id-input');
const sizeInput = document.getElementById('size');
const quantityInput = document.getElementById('quantity');

// Open popup for products
productItems.forEach(item => {
    item.addEventListener('click', () => {
        const itemId = item.getAttribute('data-item-id');
        itemIdInput.value = itemId;
        sizeInput.value = "Medium"; // Default size
        quantityInput.value = 1; // Default quantity
        popup.style.display = 'block';
        overlay.style.display = 'block';
    });
});

// Open popup for order items (except delete action)
orderItems.forEach(order => {
    order.addEventListener('click', (e) => {
        if (e.target.classList.contains('delete-icon')) {
            // Prevent opening the popup if the delete icon is clicked
            return;
        }

        const itemId = order.getAttribute('data-item-id');
        const orderDetails = order.querySelector('.order-item-details');
        const currentSize = orderDetails.getAttribute('data-size');
        const currentQuantity = order.querySelector('.item-quantity').value;

        itemIdInput.value = itemId;
        sizeInput.value = currentSize;
        quantityInput.value = currentQuantity;
        popup.style.display = 'block';
        overlay.style.display = 'block';
    });
});

// Handle delete icon click
deleteIcons.forEach(icon => {
    icon.addEventListener('click', (e) => {
        const itemId = icon.closest('.order-item').getAttribute('data-item-id');

        // Send a POST request to remove the item via AJAX
        fetch('order_management.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'remove_item', id: itemId })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update the UI, e.g., remove the item and update total amount
                    icon.closest('.order-item').remove();
                    document.getElementById('total-amount').textContent = data.newTotalAmount.toFixed(2);
                } else {
                    alert('Failed to remove the item. Please try again.');
                }
            })
            .catch(error => console.error('Error:', error));

        e.stopPropagation(); // Prevent triggering order item click event
    });
});

// Close popup when clicking outside
overlay.addEventListener('click', () => {
    popup.style.display = 'none';
    overlay.style.display = 'none';
});

// Dynamically update price based on size selection
sizeInput.addEventListener('change', () => {
    const size = sizeInput.value;
    const itemId = itemIdInput.value;

    fetch(`get_item_price.php?item_id=${itemId}&size=${size}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const newPrice = data.price;
                document.querySelector('#popup-price').textContent = `Price: ₱${newPrice}`;
            }
        })
        .catch(error => console.error('Error fetching price:', error));
});

    </script>



    <!-- Payment Input Popup -->
    <div class="popup" id="payment-popup" style="display: <?= $popupVisible ? 'block' : 'none'; ?>;">
        <h2>Payment</h2>
        <p>Total: ₱<span id="popup-total"><?= number_format($totalAmount, 2); ?></span></p>
        <p>Change: ₱ -<span id="popup-change">0.00</span></p>

        <form method="POST" action="">
            <div class="input-display">
                <input type="text" id="payment-input" name="payment-input" placeholder="0"
                    value="<?= htmlspecialchars($payment); ?>" oninput="calculateChange(<?= $totalAmount; ?>)">
                <button type="button" onclick="clearInput()">⨉</button>
            </div>

            <!-- Numpad for Payment Input -->
            <div class="num-pad-container">
                <div class="num-pad">
                    <button type="button" onclick="addNumber(7)">7</button>
                    <button type="button" onclick="addNumber(8)">8</button>
                    <button type="button" onclick="addNumber(9)">9</button>
                    <button type="button" onclick="addNumber(4)">4</button>
                    <button type="button" onclick="addNumber(5)">5</button>
                    <button type="button" onclick="addNumber(6)">6</button>
                    <button type="button" onclick="addNumber(1)">1</button>
                    <button type="button" onclick="addNumber(2)">2</button>
                    <button type="button" onclick="addNumber(3)">3</button>
                    <button type="button" onclick="addNumber('.')">.</button>
                    <button type="button" onclick="addNumber(0)">0</button>
                    <button type="button" onclick="addNumber('00')">00</button>
                </div>
            </div>

            <!-- Submit Payment Button -->
            <button type="submit" class="pay-button" name="pay_now">Pay</button>
        </form>

        <!-- Cancel Button -->
        <button class="cancel-button" onclick="cancelOrder()">Cancel</button>

        <?php if (!empty($error)): ?>
            <p style="color: red;"><?= $error; ?></p>
        <?php endif; ?>
    </div>

    <!-- Overlay -->
    <div class="overlay" id="popup-overlay" style="display: <?= $popupVisible ? 'block' : 'none'; ?>;"></div>

    <script>
        // Show Payment Popup
        document.getElementById('pay-now-btn').addEventListener('click', function() {
            document.getElementById('payment-popup').style.display = 'block';
            document.getElementById('popup-overlay').style.display = 'block';
        });

        // Close Popup when clicking outside
        document.getElementById('popup-overlay').addEventListener('click', () => {
            cancelOrder();
        });

        // Add Number to Payment Input
        function addNumber(number) {
            let input = document.getElementById('payment-input');
            input.value += number;
            calculateChange(<?= $totalAmount; ?>); // Recalculate change
        }

        // Clear Input
        function clearInput() {
            document.getElementById('payment-input').value = '';
            document.getElementById('popup-change').innerText = '0.00'; // Reset change display
        }

        // Calculate Change
        function calculateChange(totalAmount) {
            const paymentInput = parseFloat(document.getElementById('payment-input').value) || 0;
            const change = paymentInput - totalAmount;

            // Set change to 0 if negative
            const displayChange = change > 0 ? change.toFixed(2) : "0.00";

            // Update the change display
            document.getElementById('popup-change').innerText = displayChange;
        }

        // Cancel Payment Process
        function cancelOrder() {
            document.getElementById('payment-popup').style.display = 'none';
            document.getElementById('popup-overlay').style.display = 'none';
        }


        // Attach event listener to delete icons
        document.addEventListener('DOMContentLoaded', function() {
            const deleteIcons = document.querySelectorAll('.delete-icon');

            deleteIcons.forEach(icon => {
                icon.addEventListener('click', function(event) {
                    const itemId = event.target.closest('.order-item').dataset.itemId;

                    // Send a request to remove the item from the session
                    fetch('order_management.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: `action=remove_item&id=${itemId}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Remove item from the list
                                event.target.closest('.order-item').remove();
                                updateTotalAmount(data.newTotalAmount);
                            } else {
                                alert('Error removing item');
                            }
                        })
                        .catch(error => console.error('Error:', error));
                });
            });

            // Function to update total amount displayed
            function updateTotalAmount(newTotalAmount) {
                document.getElementById('total-amount').textContent = newTotalAmount.toFixed(2);
            }
        });


        const searchInput = document.getElementById('search-input');
        const suggestionsList = document.getElementById('suggestions-list');

        searchInput.addEventListener('input', () => {
            const query = searchInput.value;

            if (query.length > 0) {
                fetch(`search_suggestions.php?query=${query}`)
                    .then(response => response.json())
                    .then(data => {
                        suggestionsList.innerHTML = ''; // Clear previous suggestions

                        data.forEach(item => {
                            const option = document.createElement('option');
                            option.value = item; // Display the suggestion in the dropdown
                            suggestionsList.appendChild(option);
                        });
                    })
                    .catch(error => console.error('Error fetching suggestions:', error));
            } else {
                suggestionsList.innerHTML = ''; // Clear suggestions if input is empty
            }
        });
    </script>
</body>
</html>
