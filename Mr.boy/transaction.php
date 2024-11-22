<?php
include 'config.php';
session_start();

if (!isset($_SESSION['admin_name'])) {
    header('location:index.php');
}

// Fetch all transactions
$transactions_query = $conn->query("SELECT * FROM transactions ORDER BY transaction_date DESC");
$transactions = $transactions_query->fetch_all(MYSQLI_ASSOC);

// Get the current date for filtering
$currentDate = new DateTime();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="transaction.css">
    <title>Transactions</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" type="text/css" href="./css/main.css">
    <link rel="stylesheet" type="text/css" href="./css/admin.css">
    <link rel="stylesheet" type="text/css" href="./css/util.css">
</head>
<body>
    <?php include 'sidebar.php' ?>
    <div class="container">
        <h1>Transactions Record</h1>

        <div class="search-section">
            <input type="text" class="search-bar" placeholder="Search" id="search-transaction" onkeyup="searchTransactions()">
            <button class="search-button">Search</button>
            <select class="filter-dropdown" id="filter-dropdown" onchange="filterTransactions()">
                <option value="daily">Daily</option>
                <option value="weekly">Weekly</option>
                <option value="monthly">Monthly</option>
                <option value="yearly">Yearly</option>
            </select>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Trans #</th>
                        <th>Date</th>
                        <th>Order</th>
                        <th>Total</th>
                        <th>Action</th>
                    </tr>
                </thead>
            </table>
            <div class="scrollable-tbody">
                <table>
                    <tbody id="transaction-table-body">
                        <!-- Content will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Receipt Modal -->
    <div id="receiptModal" class="modal">
        <div class="modal-content receipt-layout">
            <span class="close" onclick="closeModal()">&times;</span>
            <div id="receiptDetails" class="receipt-content">
                <!-- Receipt content will be dynamically generated here -->
            </div>
        </div>
    </div>

    <script>
        const transactions = <?= json_encode($transactions) ?>;

        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('filter-dropdown').value = 'daily';
            filterTransactions(); // Load default filter
        });

        function searchTransactions() {
            const searchTerm = document.getElementById('search-transaction').value.toLowerCase();
            const rows = document.querySelectorAll('#transaction-table-body tr');

            rows.forEach(row => {
                const orderDetails = row.cells[2].textContent.toLowerCase();
                row.style.display = orderDetails.includes(searchTerm) ? '' : 'none';
            });
        }

        function filterTransactions() {
            const filterValue = document.getElementById('filter-dropdown').value;
            const filteredTransactions = transactions.filter(transaction => {
                const transactionDate = new Date(transaction.transaction_date);
                const now = new Date();
                let startDate, endDate;

                switch (filterValue) {
                    case 'daily':
                        startDate = new Date(now.getFullYear(), now.getMonth(), now.getDate());
                        endDate = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1);
                        break;
                    case 'weekly':
                        const weekStart = new Date(now.getFullYear(), now.getMonth(), now.getDate() - now.getDay());
                        startDate = weekStart;
                        endDate = new Date(weekStart.getFullYear(), weekStart.getMonth(), weekStart.getDate() + 7);
                        break;
                    case 'monthly':
                        startDate = new Date(now.getFullYear(), now.getMonth(), 1);
                        endDate = new Date(now.getFullYear(), now.getMonth() + 1, 1);
                        break;
                    case 'yearly':
                        startDate = new Date(now.getFullYear(), 0, 1);
                        endDate = new Date(now.getFullYear() + 1, 0, 1);
                        break;
                    default:
                        startDate = new Date(now.getFullYear(), now.getMonth(), now.getDate());
                        endDate = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1);
                }

                return transactionDate >= startDate && transactionDate < endDate;
            });

            const tbody = document.getElementById('transaction-table-body');
            tbody.innerHTML = '';

            filteredTransactions.forEach(transaction => {
                const row = `
                <tr>
                    <td>${transaction.id}</td>
                    <td>${new Date(transaction.transaction_date).toLocaleString()}</td>
                    <td class="order-cell">${transaction.order_details}</td>
                    <td>${parseFloat(transaction.total_amount).toFixed(2)}</td>
                    <td>
                        <button class="view-receipt" onclick="viewReceipt(${transaction.id})">View Receipt</button>
                    </td>
                </tr>
                `;
                tbody.innerHTML += row;
            });
        }

        function viewReceipt(transactionId) {
            const transaction = transactions.find(t => t.id === transactionId);

            // Extracting the details
            const orderDetails = JSON.parse(transaction.order_details);  
            const total = parseFloat(transaction.total_amount).toFixed(2);
            const cashGiven = 100;  // Placeholder cash value
            const change = (cashGiven - total).toFixed(2);

            // Build the receipt content dynamically
            let receiptDetails = `
                <div class="receipt-header">
                    <h3>Mr. Boy Special Tea</h3>
                    <hr>
                    <p><strong>CASH RECEIPT</strong></p>
                    <hr>
                </div>
                <div class="receipt-body">
                    <table>
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th>Price</th>
                            </tr>
                        </thead>
                        <tbody>`;
            
            orderDetails.forEach(item => {
                receiptDetails += `
                    <tr>
                        <td>${item.name}</td>
                        <td>${parseFloat(item.price).toFixed(2)}</td>
                    </tr>`;
            });

            receiptDetails += `
                        </tbody>
                    </table>
                    <hr>
                    <p><strong>Total:</strong> ${total}</p>
                    <p><strong>Cash:</strong> ${cashGiven}</p>
                    <p><strong>Change:</strong> ${change}</p>
                    <hr>
                </div>
                <div class="receipt-footer">
                    <p><strong>THANK YOU!</strong></p>
                </div>
            `;

            // Inject and display the receipt in the modal
            document.getElementById('receiptDetails').innerHTML = receiptDetails;
            document.getElementById('receiptModal').style.display = "block";
        }

        function closeModal() {
            document.getElementById('receiptModal').style.display = "none";
        }

        window.onclick = function(event) {
            const modal = document.getElementById('receiptModal');
            if (event.target === modal) {
                closeModal();
            }
        };
    </script>
</body>
</html>
