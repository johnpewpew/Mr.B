<?php
$conn = new mysqli('localhost', 'root', '', 'database_pos');
if ($conn->connect_error) {
    die('Connection Failed: ' . $conn->connect_error);
}

// Handle form submission for adding or updating items
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $item_name = $_POST['item_name'];
    $category_id = $_POST['category_id'];
    $medium_quantity = $_POST['medium_quantity'];
    $large_quantity = $_POST['large_quantity'];
    $medium_price = $_POST['medium_price'];
    $large_price = $_POST['large_price'];
    $imagePath = '';
    $status = isset($_POST['status']) ? 1 : 0; // Get the availability status

    // Handle image upload
    if (!empty($_FILES['image']['name'])) {
        $targetDir = 'uploads/';
        $imageName = basename($_FILES['image']['name']);
        $imagePath = $targetDir . $imageName;
        $imageFileType = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));

        // Validate the uploaded file is an image
        if (getimagesize($_FILES['image']['tmp_name']) === false) {
            die("File is not an image.");
        }

        // Limit the size to 5MB
        if ($_FILES['image']['size'] > 5000000) {
            die("Sorry, your file is too large.");
        }

        // Allow only certain image formats
        $allowedFormats = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($imageFileType, $allowedFormats)) {
            die("Sorry, only JPG, JPEG, PNG, and GIF files are allowed.");
        }

        // Move the uploaded file to the target directory
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $imagePath)) {
            die("Sorry, there was an error uploading your file.");
        }
    } else {
        // If no image is uploaded, keep the existing image
        if (isset($_POST['item_id'])) {
            $item_id = $_POST['item_id'];
            $result = $conn->query("SELECT image FROM items WHERE id = $item_id");
            $item = $result->fetch_assoc();
            $imagePath = $item['image']; // Preserve existing image
        }
    }

    // Adding or Updating Item
    if (isset($_POST['add_item']) || isset($_POST['update_item'])) {
        if (isset($_POST['add_item'])) {
            $stmt = $conn->prepare("INSERT INTO items (name, category_id, medium_quantity, large_quantity, medium_price, large_price, image, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("siiddssi", $item_name, $category_id, $medium_quantity, $large_quantity, $medium_price, $large_price, $imagePath, $status);
            $stmt->execute();
            $stmt->close();
        } elseif (isset($_POST['update_item'])) {
            $item_id = $_POST['item_id'];
            $stmt = $conn->prepare("UPDATE items SET name=?, category_id=?, medium_quantity=?, large_quantity=?, medium_price=?, large_price=?, image=?, status=? WHERE id=?");
            $stmt->bind_param("siiddssii", $item_name, $category_id, $medium_quantity, $large_quantity, $medium_price, $large_price, $imagePath, $status, $item_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    header("Location: item.php");
    exit();
}

// Fetch items and categories
$items = $conn->query("SELECT items.*, categories.name as category_name FROM items JOIN categories ON items.category_id = categories.id");
$categories = $conn->query("SELECT * FROM categories");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Items</title>
    <link rel="stylesheet" href="item.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" type="text/css" href="./css/main.css">
    <link rel="stylesheet" type="text/css" href="./css/admin.css">
    <link rel="stylesheet" type="text/css" href="./css/util.css">
    
</head>

<body>
    <?php include 'sidebar.php'; ?>
    <div class="container">
        <div class="item-details">
            <h2>Item Details</h2>
            <form id="item-form" action="item.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="item_id" id="item-id">
                <div>
                    <label for="item-name">Item Name</label>
                    <input type="text" name="item_name" id="item-name" required>
                </div>

                <div>
                    <label for="item-category">Category</label>
                    <select name="category_id" id="item-category" required class="custom-select">
                        <?php while ($row = $categories->fetch_assoc()) { ?>
                            <option value="<?= $row['id'] ?>"><?= $row['name'] ?></option>
                        <?php } ?>
                    </select>
                </div>

                <div>
                    <label for="medium-quantity">Medium Quantity</label>
                    <input type="number" name="medium_quantity" id="medium-quantity" required>
                </div>
                
                <div>
                    <label for="large-quantity">Large Quantity</label>
                    <input type="number" name="large_quantity" id="large-quantity" required>
                </div>

                <div>
                    <label for="medium-price">Medium Price</label>
                    <input type="number" step="0.01" name="medium_price" id="medium-price" required>
                </div>
                
                <div>
                    <label for="large-price">Large Price</label>
                    <input type="number" step="0.01" name="large_price" id="large-price" required>
                </div>

                <div>
                    <label for="item-status">Available</label>
                    <input type="checkbox" name="status" id="item-status" checked>
                </div>

                <div>
                    <label for="item-image">Image</label>
                    <input type="file" name="image" id="item-image" accept="image/*">
                </div>
                <button type="submit" name="add_item" id="add-item-btn">Add Item</button>
                <button type="submit" name="update_item" id="update-item-btn" style="display:none;">Update Item</button>
                <button type="submit" name="delete_item" id="delete-item-btn" style="display:none;">Delete Item</button>
            </form>
        </div>

        <div class="item-list">
            <h2>Item List</h2>
            <input type="text" id="search-input" placeholder="Search for items..." onkeyup="searchItems()">
            <table>
                <thead>
                    <tr>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Medium Price</th>
                        <th>Large Price</th>
                        <th>Image</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="item-table-body">
                    <?php while ($item = $items->fetch_assoc()) { ?>
                        <tr class="item-row" onclick="editItem(<?= $item['id'] ?>, '<?= htmlspecialchars($item['name']) ?>', <?= $item['category_id'] ?>, <?= $item['medium_quantity'] ?>, <?= $item['large_quantity'] ?>, <?= $item['medium_price'] ?>, <?= $item['large_price'] ?>, '<?= htmlspecialchars($item['image']) ?>', <?= $item['status'] ?>)">
                            <td><?= htmlspecialchars($item['name']) ?></td>
                            <td><?= htmlspecialchars($item['category_name']) ?></td>
                            <td><?= htmlspecialchars($item['medium_price']) ?></td>
                            <td><?= htmlspecialchars($item['large_price']) ?></td>
                            <td><img src="<?= htmlspecialchars($item['image']) ?>" alt="Image" width="50"></td>
                            <td class="<?= $item['status'] == 1 ? 'available' : 'unavailable' ?>">
                                <?= $item['status'] == 1 ? 'Available' : 'Unavailable' ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function editItem(id, name, categoryId, mediumQuantity, largeQuantity, mediumPrice, largePrice, image, status) {
            document.getElementById('item-id').value = id;
            document.getElementById('item-name').value = name;
            document.getElementById('item-category').value = categoryId;
            document.getElementById('medium-quantity').value = mediumQuantity;
            document.getElementById('large-quantity').value = largeQuantity;
            document.getElementById('medium-price').value = mediumPrice;
            document.getElementById('large-price').value = largePrice;
            document.getElementById('item-image').value = ''; // Clear image input
            document.getElementById('add-item-btn').style.display = 'none';
            document.getElementById('update-item-btn').style.display = 'inline';
            document.getElementById('delete-item-btn').style.display = 'inline';
    
            // Set the availability status checkbox
            document.getElementById('item-status').checked = status === 1;
        }

        function searchItems() {
            const input = document.getElementById('search-input').value.toLowerCase();
            const rows = document.querySelectorAll('.item-row');
            rows.forEach(row => {
                const itemName = row.cells[0].innerText.toLowerCase();
                row.style.display = itemName.includes(input) ? '' : 'none';
            });
        }
    </script>
</body>
</html>
