


CREATE TABLE `users`(
  `id` INT(10) NOT NULL AUTO_INCREMENT , 
  `name` VARCHAR(50) NOT NULL , 
  `email` VARCHAR(50) NOT NULL , 
  `password` VARCHAR(50) NOT NULL , 
  `user_type` VARCHAR(50) NOT NULL DEFAULT 'user' , PRIMARY KEY (`id`)
) ENGINE = InnoDB;



INSERT INTO `users` (`id`, `name`, `email`, `password`, `user_type`) VALUES 
(NULL, 'admin', 'admin@gmail.com', '0192023a7bbd73250516f069df18b500', 'admin'),
(NULL, 'cashier', 'cashier@gmail.com', '84c8137f06fd53b0636e0818f3954cdb', 'user');


CREATE TABLE `items` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `name` varchar(100) NOT NULL,
 `category_id` int(11) NOT NULL,
 `image` varchar(255) DEFAULT NULL,
 `medium_price` decimal(10,2) NOT NULL,
 `large_price` decimal(10,2) NOT NULL,
 `medium_quantity` int(11) DEFAULT 0,
 `large_quantity` int(11) DEFAULT 0,
 `is_deleted` tinyint(1) DEFAULT 0,
 `status` tinyint(1) DEFAULT 1,
 PRIMARY KEY (`id`),
 KEY `category_id` (`category_id`),
 CONSTRAINT `items_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=113 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci

CREATE TABLE `categories` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `name` varchar(255) NOT NULL,
 `image` varchar(255) NOT NULL,
 PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci

CREATE TABLE `daily_sales` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `date` date NOT NULL,
 `total_sales` decimal(10,2) NOT NULL DEFAULT 0.00,
 PRIMARY KEY (`id`),
 UNIQUE KEY `date` (`date`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci

CREATE TABLE `employees` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `name` varchar(100) NOT NULL,
 `email` varchar(100) NOT NULL,
 `phone_no` varchar(15) DEFAULT NULL,
 `birthdate` date DEFAULT NULL,
 `age` int(11) DEFAULT NULL,
 `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
 PRIMARY KEY (`id`),
 UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci

CREATE TABLE `product_sales` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `product_id` int(11) DEFAULT NULL,
 `quantity_sold` int(11) DEFAULT NULL,
 `date_sold` date DEFAULT NULL,
 PRIMARY KEY (`id`),
 KEY `product_id` (`product_id`),
 CONSTRAINT `product_sales_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `items` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=99 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci

CREATE TABLE `sales` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `item_id` int(11) NOT NULL,
 `quantity` int(11) NOT NULL,
 `size` enum('Medium','Large') NOT NULL,
 `sale_date` timestamp NOT NULL DEFAULT current_timestamp(),
 PRIMARY KEY (`id`),
 KEY `item_id` (`item_id`),
 CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=203 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci

CREATE TABLE `transactions` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `transaction_date` timestamp NOT NULL DEFAULT current_timestamp(),
 `total_amount` decimal(10,2) NOT NULL,
 `order_details` text NOT NULL,
 `payment_status` varchar(20) NOT NULL,
 PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=294 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
