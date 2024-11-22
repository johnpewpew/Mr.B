<?php
// Sample data for the receipt
$shopName = "Mr. Boy Special Tea";
$receiptTitle = "CASH RECEIPT";
$items = [
    ["description" => "Tea", "price" => 30],
    ["description" => "Cookies", "price" => 50],
];
$total = 80;
$cash = 100;
$change = $cash - $total;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background-color: #f4f4f4;
        }

        .receipt {
            width: 300px;
            border: 1px solid black;
            padding: 20px;
            background-color: white;
            text-align: center;
        }

        .receipt h1 {
            font-size: 18px;
            font-weight: bold;
            margin: 0;
        }

        .receipt .line {
            margin: 10px 0;
            border-top: 1px dashed black;
        }

        .receipt h2 {
            margin: 10px 0;
            font-size: 16px;
            font-weight: bold;
        }

        .description-table {
            width: 100%;
            margin: 10px 0;
            text-align: left;
        }

        .description-table th, .description-table td {
            padding: 10px;
        }

        .description-table th {
            text-align: left;
            font-weight: bold;
        }

        .description-table td {
            text-align: right;
        }

        .total {
            text-align: left;
            margin: 11px 0;
        }

        .total .amount {
            float: right;
            font-weight: bold;
        }

        .thank-you {
            margin: 20px 0;
            font-weight: bold;
            font-size: 25px;
        }
    </style>
</head>
<body>

    <div class="receipt">
        <h1><?php echo $shopName; ?></h1>
        <div class="line"></div>
        <h2><?php echo $receiptTitle; ?></h2>
        <div class="line"></div>

        <table class="description-table">
            <tr>
                <th>Description</th>
                <th>Price</th>
            </tr>
            <?php foreach ($items as $item): ?>
            <tr>
                <td><?php echo htmlspecialchars($item['description']); ?></td>
                <td><?php echo htmlspecialchars($item['price']); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>

        <div class="line"></div>

        <div class="total">
            <span>Total</span>
            <span class="amount"><?php echo $total; ?></span>
        </div>
        <div class="total">
            <span>Cash</span>
            <span class="amount"><?php echo $cash; ?></span>
        </div>
        <div class="total">
            <span>Change</span>
            <span class="amount"><?php echo $change; ?></span>
        </div>

        <div class="line"></div>

        <div class="thank-you">THANK YOU!</div>
    </div>

</body>
</html>
