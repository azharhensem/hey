@@ .. @@
   `delivery_status` int(11) DEFAULT NULL,
   `payment_method` varchar(10) DEFAULT 'cod',
-  `delivery_address` text DEFAULT NULL
+  `delivery_address` text DEFAULT NULL,
+  `payment_proof` varchar(255) DEFAULT NULL
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;