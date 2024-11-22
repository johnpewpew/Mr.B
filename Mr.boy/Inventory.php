<?php
$conn = new mysqli('localhost', 'root', '', 'database_pos');
if ($conn->connect_error) {
    die('Connection Failed: ' . $conn->connect_error);
}

// Function to validate and upload image
function uploadImage($file) {
    $targetDir = 'uploads/';
    $imageName = basename($file['name']);
    $imagePath = $targetDir . $imageName;
    $imageFileType = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));

    // Validate the uploaded file is an image
    $check = getimagesize($file['tmp_name']);
    if ($check === false) {
        return ["error" => "File is not an image."];
    }

    // Limit the size to 5MB
    if ($file['size'] > 5000000) {
        return ["error" => "Sorry, your file is too large."];
    }

    // Allow only certain image formats
    $allowedFormats = ['jpg', 'jpeg', 'png', 'gif'];
    if (!in_array($imageFileType, $allowedFormats)) {
        return ["error" => "Sorry, only JPG, JPEG, PNG, and GIF files are allowed."];
    }

    // Move the uploaded file to the target directory
    if (!move_uploaded_file($file['tmp_name'], $imagePath)) {
        return ["error" => "Sorry, there was an error uploading your file."];
    }

    return ["path" => $imagePath];
}

// Handle form submission for adding, updating, or deleting items
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $item_name = $_POST['item_name'];
    $category_id = $_POST['category_id'];
    $quantity = $_POST['item_quantity'];
    $medium_price = $_POST['medium_price'];
    $large_price = $_POST['large_price'];
    $imagePath = '';

    // Handle image upload
    if (!empty($_FILES['image']['name'])) {
        $uploadResult = uploadImage($_FILES['image']);
        if (isset($uploadResult['error'])) {
            die($uploadResult['error']);
        }
        $imagePath = $uploadResult['path'];
    }

    // Prepare statement to prevent SQL injection
    $stmt = $conn->prepare("INSERT INTO items (name, category_id, quantity, medium_price, large_price, image) VALUES (?, ?, ?, ?, ?, ?)");
    
    if (isset($_POST['add_item'])) {
        $stmt->bind_param("siidds", $item_name, $category_id, $quantity, $medium_price, $large_price, $imagePath);
        $stmt->execute();
    }

    if (isset($_POST['update_item'])) {
        $item_id = $_POST['item_id'];
        if (!empty($imagePath)) {
            $stmt = $conn->prepare("UPDATE items SET name=?, category_id=?, quantity=?, medium_price=?, large_price=?, image=? WHERE id=?");
            $stmt->bind_param("siiddsi", $item_name, $category_id, $quantity, $medium_price, $large_price, $imagePath, $item_id);
        } else {
            $stmt = $conn->prepare("UPDATE items SET name=?, category_id=?, quantity=?, medium_price=?, large_price=? WHERE id=?");
            $stmt->bind_param("siiddi", $item_name, $category_id, $quantity, $medium_price, $large_price, $item_id);
        }
        $stmt->execute();
    }

    if (isset($_POST['delete_item'])) {
        $item_id = $_POST['item_id'];
        $stmt = $conn->prepare("DELETE FROM items WHERE id=?");
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
    }

    $stmt->close();
    header("Location: item.php");
    exit();
}

// Fetch all items with remaining medium and large cup quantities
$items = $conn->query("
    SELECT items.id, items.name, items.image, items.medium_quantity, items.large_quantity,
    COALESCE((SELECT SUM(quantity) FROM sales WHERE sales.item_id = items.id AND sales.size = 'Medium'), 0) AS total_medium_sold,
    COALESCE((SELECT SUM(quantity) FROM sales WHERE sales.item_id = items.id AND sales.size = 'Large'), 0) AS total_large_sold,
    COALESCE((SELECT SUM(quantity * CASE WHEN size = 'Medium' THEN medium_price ELSE large_price END) 
              FROM sales 
              WHERE sales.item_id = items.id), 0) AS total_sales,
    COALESCE((SELECT SUM(quantity) FROM sales WHERE sales.item_id = items.id), 0) AS total_items_sold
    FROM items
");

// Calculate total cups sold for all items
$totalMediumSold = $conn->query("SELECT SUM(quantity) AS total_medium_sold FROM sales WHERE size = 'Medium'")->fetch_assoc()['total_medium_sold'] ?? 0;
$totalLargeSold = $conn->query("SELECT SUM(quantity) AS total_large_sold FROM sales WHERE size = 'Large'")->fetch_assoc()['total_large_sold'] ?? 0;

$topProducts = $conn->query("
    SELECT items.name, 
    COALESCE((SELECT SUM(quantity * CASE WHEN size = 'Medium' THEN medium_price ELSE large_price END) 
              FROM sales 
              WHERE sales.item_id = items.id), 0) AS total_sales,
    COALESCE((SELECT SUM(quantity) FROM sales WHERE sales.item_id = items.id), 0) AS total_items_sold
    FROM items
    ORDER BY total_sales DESC
    LIMIT 3
");

$topLabels = [];
$topData = [];
while ($topItem = $topProducts->fetch_assoc()) {
    $topLabels[] = htmlspecialchars($topItem['name']);
    $topData[] = (int) htmlspecialchars($topItem['total_sales']);
}

$items->data_seek(0); 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Sales Dashboard</title>
    <link rel="stylesheet" href="Inventory.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" type="text/css" href="./css/main.css">
    <link rel="stylesheet" type="text/css" href="./css/admin.css">
    <link rel="stylesheet" type="text/css" href="./css/util.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<?php include 'sidebar.php'; ?>
    <div class="container">
        <div class="scrollable-table">
            <table class="product-table">
                <thead>
                    <tr>
                        <th>Name of Products</th>
                        <th>Medium Sales</th>
                        <th>Large Sales</th>
                        <th>Profit Accumulation</th>
                        <th>Total Items Sold</th>
                        <th>Image</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($item = $items->fetch_assoc()) { ?>
                        <tr>
                            <td><?= htmlspecialchars($item['name']) ?></td>
                            <td><?= htmlspecialchars($item['total_medium_sold']) ?></td>
                            <td><?= htmlspecialchars($item['total_large_sold']) ?></td>
                            <td><?= htmlspecialchars($item['total_sales']) ?></td> 
                            <td><?= htmlspecialchars($item['total_items_sold']) ?></td> 
                            <td><img src="<?= htmlspecialchars($item['image']) ?>" alt="Image" width="50"></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <div class="cups-info">
            <div class="cups-left">
                <h4>Remaining Cups</h4>
                <?php
                // Reset pointer to fetch the first item for displaying remaining cups
                $items->data_seek(0);
                $firstItem = $items->fetch_assoc();
                ?>
                <div class="cup-size">
                    <label>Large</label>
                    <input type="number" value="<?= htmlspecialchars($firstItem['large_quantity']) ?>" readonly>
                </div>
                <div class="cup-size">
                    <label>Medium</label>
                    <input type="number" value="<?= htmlspecialchars($firstItem['medium_quantity']) ?>" readonly>
                </div>
                <button></button>
            </div>

            <div class="cups-sold">
                <h4>Number of Cups Sold</h4>
                <div class="cup-size">
                    <label>Large</label>
                    <input type="number" value="<?= htmlspecialchars($totalLargeSold) ?>" readonly>
                </div>
                <div class="cup-size">
                    <label>Medium</label>
                    <input type="number" value="<?= htmlspecialchars($totalMediumSold) ?>" readonly>
                </div>
            </div>
            <div class="chart">
                <h4>Top Products</h4>
                <canvas id="myChart" width="400" height="200"></canvas>
            </div>
        </div>
    </div>

    <script>
        const labels = <?= json_encode($topLabels) ?>;
        const data = {
            labels: labels,
            datasets: [{
                label: 'Total Sales',
                backgroundColor: '#FF4D00',
                borderWidth: 1,
                data: <?= json_encode($topData) ?>,
            }]
        };

        const config = {
            type: 'bar',
            data: data,
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        };

        const myChart = new Chart(
            document.getElementById('myChart'),
            config
        );
    </script>
</body>
</html>
