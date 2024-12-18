<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

class Post {
 
    public function registerUser($data) {
        global $conn;
        // Validate decrypted data
        if (!isset($data['username']) || !isset($data['password'])) {
            return [
                "status" => false,
                "message" => "Missing required fields"
            ];
        }

        $username = $data['username'];
        $password = $data['password'];
        $role = isset($data['role']) ? $data['role'] : 2;

        try {
            $checkUserSql = "SELECT * FROM user_acc WHERE username = :username";
            $stmt = $conn->prepare($checkUserSql);
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                if ($result['role'] == 0) {
                    return [
                        "status" => false,
                        "message" => "Account is still not approved by developers or Username already taken"
                    ];
                } else if ($result['role'] == 1) {
                    return [
                        "status" => false,
                        "message" => "Account Username is already used"
                    ];
                } else {
                    return [
                        "status" => false,
                        "message" => "Username already taken"
                    ];
                }
            } else {
                // Ensure password meets minimum requirements
                if (strlen($password) < 8) {
                    return [
                        "status" => false,
                        "message" => "Password must be at least 8 characters long"
                    ];
                }

                // Additional password validation if needed
                if (!preg_match("/[A-Z]/", $password) || 
                    !preg_match("/[a-z]/", $password) || 
                    !preg_match("/[0-9]/", $password) || 
                    !preg_match("/[!@#$%^&*(),.?\":{}|<>]/", $password)) {
                    return [
                        "status" => false,
                        "message" => "Password must contain uppercase, lowercase, numbers, and special characters"
                    ];
                }

                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                $sql = "INSERT INTO user_acc (username, password, role) VALUES (:username, :password, :role)";
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':password', $hashedPassword);
                $stmt->bindParam(':role', $role);
                
                if ($stmt->execute()) {
                    return [
                        "status" => true,
                        "message" => "Account created successfully",
                        "userId" => $conn->lastInsertId()
                    ];
                } else {
                    return [
                        "status" => false,
                        "message" => "Failed to create account"
                    ];
                }
            }
        } catch (PDOException $e) {
            return [
                "status" => false,
                "message" => "Database error: " . $e->getMessage()
            ];
        }
    }

    public function loginUser($data) {
        global $conn;
        $username = $data['username'];
        $password = $data['password'];

        try {
            $sql = "SELECT * FROM user_acc WHERE username = :username";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return ["status" => false, "message" => "User not found"];
            }

            // Check if user has permission (role 0 or 1)
            if ($user['role'] > 1) {
                return [
                    "status" => false, 
                    "message" => "Your account is pending approval. Please contact an administrator."
                ];
            }

            if (password_verify($password, $user['password'])) {
                return [
                    "status" => true, 
                    "message" => "Login successful",
                    "userId" => $user['User_id'],
                    "username" => $user['username'],
                    "role" => (int)$user['role']
                ];
            }
            
            return ["status" => false, "message" => "Incorrect credentials"];
        } catch (PDOException $e) {
            return ["status" => false, "message" => "Database error: " . $e->getMessage()];
        }
    }

    public function addItemStock($data) {
        global $conn;
        try {
            // Validate decrypted data
            if (empty($data['item_name']) || !isset($data['stock_quantity']) || empty($data['unit_of_measure'])) {
                return [
                    "status" => false,
                    "message" => "All fields are required"
                ];
            }

            // Check if item already exists
            $checkSql = "SELECT inventory_id FROM inventory WHERE item_name = :item_name";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bindParam(':item_name', $data['item_name']);
            $checkStmt->execute();
            
            if ($checkStmt->fetch()) {
                return [
                    "status" => false,
                    "message" => "This ingredient already exists!"
                ];
            }

            $sql = "INSERT INTO inventory (item_name, stock_quantity, unit_of_measure, last_updated) 
                    VALUES (:item_name, :stock_quantity, :unit_of_measure, NOW())";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':item_name', $data['item_name']);
            $stmt->bindParam(':stock_quantity', $data['stock_quantity']);
            $stmt->bindParam(':unit_of_measure', $data['unit_of_measure']);
            $stmt->execute();
            
            return [
                "status" => true,
                "message" => "Item added successfully",
                "inventory_id" => $conn->lastInsertId()
            ];
        } catch (PDOException $e) {
            return [
                "status" => false,
                "message" => "Error adding item: " . $e->getMessage()
            ];
        }
    }

    // public function addItem($data) {
    //     global $conn;
    //     $name = $data['name'];
    //     $image = $data['image'];
    //     $price = $data['price'];

    //     $sql = "INSERT INTO product (name, image, Price, category ) VALUES (:name, :image, :Price, :category)";
    //     $stmt = $conn->prepare($sql);
    //     $stmt->bindParam(':name', $name);
    //     $stmt->bindParam(':image', $image);
    //     $stmt->bindParam(':Price', $price);

    //     try {
    //         $stmt->execute();
    //         return ["status" => true, "message" => "Item added successfully"];
    //     } catch (PDOException $e) {
    //         return ["status" => false, "message" => "Failed to add item: " . $e->getMessage()];
    //     }
    // }

    public function addMenuItem($data) {
        global $conn;
        
        try {
            // Validate decrypted data
            if (empty($data['name']) || !isset($data['price']) || empty($data['category'])) {
                return [
                    "status" => false,
                    "message" => "All fields are required"
                ];
            }

            // Check for existing product
            $sql = "SELECT * FROM product WHERE 
                    name = :name AND 
                    category = :category AND 
                    price = :price";
            
            if (in_array($data['category'], ['Pizza', 'Drinks'])) {
                $sql .= " AND size = :size";
            }
            
            $stmt = $conn->prepare($sql);
            $params = [
                ':name' => $data['name'],
                ':category' => $data['category'],
                ':price' => $data['price']
            ];
            
            if (in_array($data['category'], ['Pizza', 'Drinks'])) {
                $params[':size'] = $data['size'] ?? 'Standard';
            }
            
            $stmt->execute($params);
            
            if ($stmt->fetch()) {
                return [
                    "status" => false,
                    "message" => "A similar product already exists",
                    "duplicate" => true
                ];
            }
            
            // Insert new product
            $sql = "INSERT INTO product (name, image, price, category, size) 
                    VALUES (:name, :image, :price, :category, :size)";
            $stmt = $conn->prepare($sql);
            
            $size = $data['size'] ?? 'Standard';
            $image = $data['image'] ?? '';
            
            $stmt->bindParam(':name', $data['name']);
            $stmt->bindParam(':image', $image);
            $stmt->bindParam(':price', $data['price']);
            $stmt->bindParam(':category', $data['category']);
            $stmt->bindParam(':size', $size);
            
            $stmt->execute();
            
            return [
                "status" => true,
                "message" => "Menu item added successfully",
                "product_id" => $conn->lastInsertId()
            ];
            
        } catch (PDOException $e) {
            return [
                "status" => false,
                "message" => "Failed to add menu item: " . $e->getMessage()
            ];
        }
    }

    public function addToCart($data) {
        global $conn;
        try {
            // Check if item already exists in cart
            $checkSql = "SELECT quantity FROM cart 
                        WHERE product_id = :product_id 
                        AND user_id = :user_id";
            
            $stmt = $conn->prepare($checkSql);
            $stmt->bindParam(':product_id', $data['product_id']);
            $stmt->bindParam(':user_id', $data['user_id']);
            $stmt->execute();
            $existingItem = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingItem) {
                // Update quantity if item exists
                $sql = "UPDATE cart 
                        SET quantity = quantity + :quantity 
                        WHERE product_id = :product_id 
                        AND user_id = :user_id";
            } else {
                // Insert new item if it doesn't exist
                $sql = "INSERT INTO cart (product_id, user_id, quantity) 
                        VALUES (:product_id, :user_id, :quantity)";
            }
            
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':product_id', $data['product_id']);
            $stmt->bindParam(':user_id', $data['user_id']);
            $stmt->bindParam(':quantity', $data['quantity']);
            $stmt->execute();
            
            return [
                "status" => true,
                "message" => "Item added to cart successfully"
            ];
        } catch (PDOException $e) {
            return [
                "status" => false,
                "message" => $e->getMessage()
            ];
        }
    }

    public function createOrder($data) {
        global $conn;
        
        try {
            $conn->beginTransaction();
            
            // Verify stock availability for all items
            foreach ($data['order_items'] as $item) {
                $sql = "SELECT pi.inventory_id, pi.quantity_needed, i.stock_quantity 
                        FROM product_ingredients pi
                        JOIN inventory i ON i.inventory_id = pi.inventory_id
                        WHERE pi.product_id = :product_id";
                
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':product_id', $item['product_id']);
                $stmt->execute();
                $ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($ingredients as $ingredient) {
                    $requiredQuantity = $ingredient['quantity_needed'] * $item['quantity'];
                    if ($ingredient['stock_quantity'] < $requiredQuantity) {
                        $conn->rollBack();
                        return [
                            "status" => false,
                            "message" => "Insufficient stock for some ingredients"
                        ];
                    }
                }
            }
            
            // Create the order
            $sql = "INSERT INTO `order` (customer_id, order_date, total_amount, user_id, payment_status) 
                    VALUES (:customer_id, NOW(), :total_amount, :user_id, :payment_status)";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':customer_id', $data['customer_id']);
            $stmt->bindParam(':total_amount', $data['total_amount']);
            $stmt->bindParam(':user_id', $data['user_id']);
            $stmt->bindParam(':payment_status', $data['payment_status']);
            $stmt->execute();
            
            $order_id = $conn->lastInsertId();
            
            // Create order items
            foreach ($data['order_items'] as $item) {
                $sql = "INSERT INTO order_item (order_id, product_id, quantity, price) 
                        VALUES (:order_id, :product_id, :quantity, :price)";
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':order_id', $order_id);
                $stmt->bindParam(':product_id', $item['product_id']);
                $stmt->bindParam(':quantity', $item['quantity']);
                $stmt->bindParam(':price', $item['price']);
                $stmt->execute();
                
                // Update inventory quantities
                $sql = "SELECT pi.inventory_id, pi.quantity_needed 
                        FROM product_ingredients pi 
                        WHERE pi.product_id = :product_id";
                
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':product_id', $item['product_id']);
                $stmt->execute();
                $ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($ingredients as $ingredient) {
                    $updateQuantity = $ingredient['quantity_needed'] * $item['quantity'];
                    $updateSql = "UPDATE inventory 
                                 SET stock_quantity = stock_quantity - :update_quantity,
                                     last_updated = NOW()
                                 WHERE inventory_id = :inventory_id";
                    
                    $updateStmt = $conn->prepare($updateSql);
                    $updateStmt->bindParam(':update_quantity', $updateQuantity);
                    $updateStmt->bindParam(':inventory_id', $ingredient['inventory_id']);
                    $updateStmt->execute();
                }
            }
            
            $conn->commit();
            return [
                "status" => true,
                "message" => "Order created successfully",
                "order_id" => $order_id
            ];
            
        } catch (PDOException $e) {
            $conn->rollBack();
            return [
                "status" => false,
                "message" => "Failed to create order: " . $e->getMessage()
            ];
        }
    }

    public function addCustomer($data) {
        global $conn;
        
        try {
            if (!isset($data['Name']) || !isset($data['total_amount'])) {
                return [
                    "status" => false,
                    "message" => "Customer name and total amount are required"
                ];
            }

            $sql = "INSERT INTO customer (Name, total_amount) 
                    VALUES (:name, :total_amount)";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':name', $data['Name']);
            $stmt->bindParam(':total_amount', $data['total_amount']);
            
            if ($stmt->execute()) {
                $customer_id = $conn->lastInsertId();
                return [
                    "status" => true,
                    "message" => "Customer added successfully",
                    "customer_id" => $customer_id
                ];
            } else {
                return [
                    "status" => false,
                    "message" => "Failed to add customer"
                ];
            }
        } catch (PDOException $e) {
            return [
                "status" => false,
                "message" => "Database error: " . $e->getMessage()
            ];
        }
    }

    public function addSale($data) {
        global $conn;
        
        try {
            $sql = "INSERT INTO sales (order_id, total_sales, sales_date, user_id) 
                    VALUES (:order_id, :total_sales, NOW(), :user_id)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':order_id', $data['order_id']);
            $stmt->bindParam(':total_sales', $data['total_sales']);
            $stmt->bindParam(':user_id', $data['user_id']);
            
            if ($stmt->execute()) {
                return [
                    "status" => true,
                    "message" => "Sale recorded successfully"
                ];
            } else {
                return [
                    "status" => false,
                    "message" => "Failed to record sale"
                ];
            }
        } catch (PDOException $e) {
            return [
                "status" => false,
                "message" => "Database error: " . $e->getMessage()
            ];
        }
    }

    public function addReceipt($data) {
        global $conn;
        
        try {
            // First check if the order exists
            $checkOrder = "SELECT order_id FROM `order` WHERE order_id = :order_id";
            $stmt = $conn->prepare($checkOrder);
            $stmt->bindParam(':order_id', $data['order_id']);
            $stmt->execute();
            
            if (!$stmt->fetch()) {
                return [
                    "status" => false,
                    "message" => "Invalid order ID"
                ];
            }

            $sql = "INSERT INTO receipt (order_id, generated_at, total_amount) 
                    VALUES (:order_id, NOW(), :total_amount)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':order_id', $data['order_id']);
            $stmt->bindParam(':total_amount', $data['total_amount']);
            
            if ($stmt->execute()) {
                return [
                    "status" => true,
                    "message" => "Receipt generated successfully",
                    "receipt_id" => $conn->lastInsertId()
                ];
            } else {
                return [
                    "status" => false,
                    "message" => "Failed to generate receipt"
                ];
            }
        } catch (PDOException $e) {
            return [
                "status" => false,
                "message" => "Database error: " . $e->getMessage()
            ];
        }
    }

    public function processCartCheckout($data) {
        global $conn;
        
        try {
            $conn->beginTransaction();
            
            // Existing checkout code...
            
            // After processing the order, update inventory
            $sql = "SELECT c.product_id, c.quantity, pi.inventory_id, pi.quantity_needed, i.stock_quantity 
                   FROM cart c
                   JOIN product_ingredients pi ON c.product_id = pi.product_id
                   JOIN inventory i ON pi.inventory_id = i.inventory_id
                   WHERE c.user_id = :user_id";
                   
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':user_id', $data['user_id']);
            $stmt->execute();
            $ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Update inventory quantities
            foreach ($ingredients as $ingredient) {
                $consumed_quantity = $ingredient['quantity_needed'] * $ingredient['quantity'];
                $new_stock = $ingredient['stock_quantity'] - $consumed_quantity;
                
                $updateSql = "UPDATE inventory 
                             SET stock_quantity = :new_stock,
                                 last_updated = NOW() 
                             WHERE inventory_id = :inventory_id";
                
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->bindParam(':new_stock', $new_stock);
                $updateStmt->bindParam(':inventory_id', $ingredient['inventory_id']);
                $updateStmt->execute();
            }
            
            $conn->commit();
            return [
                "status" => true,
                "message" => "Order processed and inventory updated successfully"
            ];
            
        } catch (Exception $e) {
            $conn->rollback();
            return [
                "status" => false,
                "message" => "Error processing checkout: " . $e->getMessage()
            ];
        }
    }

    private function logInventoryUpdate($productId, $ingredientId, $oldQuantity, $newQuantity) {
        error_log(sprintf(
            "Inventory Update - Product: %d, Ingredient: %d, Old Qty: %f, New Qty: %f",
            $productId,
            $ingredientId,
            $oldQuantity,
            $newQuantity
        ));
    }

    public function addProductIngredient($data) {
        global $conn;
        
        try {
            // Check if ingredient already exists in recipe
            $checkSql = "SELECT COUNT(*) FROM product_ingredients 
                        WHERE product_id = :product_id AND inventory_id = :inventory_id";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bindParam(':product_id', $data['product_id']);
            $checkStmt->bindParam(':inventory_id', $data['inventory_id']);
            $checkStmt->execute();
            
            if ($checkStmt->fetchColumn() > 0) {
                return [
                    "status" => false,
                    "message" => "This ingredient is already in the recipe",
                    "data" => null
                ];
            }

            // Get ingredient name from inventory
            $getNameSql = "SELECT item_name FROM inventory WHERE inventory_id = :inventory_id";
            $stmt = $conn->prepare($getNameSql);
            $stmt->bindParam(':inventory_id', $data['inventory_id']);
            $stmt->execute();
            $ingredient = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$ingredient) {
                return [
                    "status" => false,
                    "message" => "Ingredient not found",
                    "data" => null
                ];
            }

            // Insert new ingredient
            $sql = "INSERT INTO product_ingredients 
                    (product_id, inventory_id, ingredient_name, quantity_needed, unit_of_measure) 
                    VALUES 
                    (:product_id, :inventory_id, :ingredient_name, :quantity_needed, :unit_of_measure)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':product_id', $data['product_id']);
            $stmt->bindParam(':inventory_id', $data['inventory_id']);
            $stmt->bindParam(':ingredient_name', $ingredient['item_name']);
            $stmt->bindParam(':quantity_needed', $data['quantity_needed']);
            $stmt->bindParam(':unit_of_measure', $data['unit_of_measure']);
            $stmt->execute();

            return [
                "status" => true, 
                "message" => "Ingredient added successfully",
                "data" => [
                    "product_ingredient_id" => $conn->lastInsertId(),
                    "ingredient_name" => $ingredient['item_name']
                ]
            ];
        } catch (PDOException $e) {
            return [
                "status" => false, 
                "message" => $e->getMessage(),
                "data" => null
            ];
        }
    }

    // public function updateProductIngredient($data) {
    //     global $conn;
    //     try {
    //         $sql = "UPDATE product_ingredients 
    //                 SET quantity_needed = :quantity_needed,
    //                     unit_of_measure = :unit_of_measure
    //                 WHERE product_ingredient_id = :product_ingredient_id";
            
    //         $stmt = $conn->prepare($sql);
    //         $stmt->bindParam(':product_ingredient_id', $data['product_ingredient_id']);
    //         $stmt->bindParam(':quantity_needed', $data['quantity_needed']);
    //         $stmt->bindParam(':unit_of_measure', $data['unit_of_measure']);
    //         $stmt->execute();
            
    //         return ["status" => true, "message" => "Ingredient updated successfully"];
    //     } catch (PDOException $e) {
    //         return ["status" => false, "message" => $e->getMessage()];
    //     }
    // }

    public function processOrder($data) {
        global $conn;
        
        try {
            $conn->beginTransaction();
            
            // First, verify stock availability for all items
            foreach ($data['items'] as $item) {
                $sql = "SELECT pi.inventory_id, pi.quantity_needed, pi.unit_of_measure as recipe_unit,
                        i.stock_quantity, i.unit_of_measure as stock_unit 
                        FROM product_ingredients pi
                        JOIN inventory i ON i.inventory_id = pi.inventory_id
                        WHERE pi.product_id = :product_id";
                
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':product_id', $item['product_id']);
                $stmt->execute();
                $ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($ingredients as $ingredient) {
                    $convertedQuantity = $this->convertUnits(
                        $ingredient['quantity_needed'] * $item['quantity'],
                        $ingredient['recipe_unit'],
                        $ingredient['inventory_unit']
                    );
                    
                    if ($ingredient['stock_quantity'] < $convertedQuantity) {
                        $conn->rollBack();
                        return [
                            "status" => false,
                            "message" => "Insufficient stock for ingredients"
                        ];
                    }
                }
            }
            
            // Update inventory quantities
            foreach ($data['items'] as $item) {
                $sql = "SELECT pi.inventory_id, pi.quantity_needed, pi.unit_of_measure as recipe_unit,
                        i.unit_of_measure as stock_unit 
                        FROM product_ingredients pi
                        JOIN inventory i ON i.inventory_id = pi.inventory_id
                        WHERE pi.product_id = :product_id";
                
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':product_id', $item['product_id']);
                $stmt->execute();
                $ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($ingredients as $ingredient) {
                    $convertedQuantity = $this->convertUnits(
                        $ingredient['quantity_needed'] * $item['quantity'],
                        $ingredient['recipe_unit'],
                        $ingredient['inventory_unit']
                    );
                    
                    $updateSql = "UPDATE inventory 
                                 SET stock_quantity = stock_quantity - :quantity_needed,
                                     last_updated = NOW() 
                                 WHERE inventory_id = :inventory_id";
                    
                    $updateStmt = $conn->prepare($updateSql);
                    $updateStmt->bindParam(':quantity_needed', $convertedQuantity);
                    $updateStmt->bindParam(':inventory_id', $ingredient['inventory_id']);
                    $updateStmt->execute();
                }
            }
            
            $conn->commit();
            return [
                "status" => true,
                "message" => "Order processed successfully"
            ];
            
        } catch (PDOException $e) {
            $conn->rollBack();
            return [
                "status" => false,
                "message" => "Error processing order: " . $e->getMessage()
            ];
        }
    }

    private function convertUnits($quantity, $fromUnit, $toUnit) {
        if ($fromUnit === $toUnit) {
            return $quantity;
        }

        // Define conversion factors
        $conversions = [
            'g' => ['kg' => 0.001],
            'kg' => ['g' => 1000],
            'ml' => ['l' => 0.001],
            'l' => ['ml' => 1000]
        ];

        if (isset($conversions[$fromUnit][$toUnit])) {
            return $quantity * $conversions[$fromUnit][$toUnit];
        }

        // If conversion not found, return original quantity
        return $quantity;
    }

    public function archiveSales($data) {
        global $conn;
        
        try {
            $conn->beginTransaction();
            
            // Insert into archived_sales
            $archiveSql = "INSERT INTO archived_sales 
                           (order_id, customer_id, order_date, total_amount, user_id, 
                            payment_status, archived_date, archived_by)
                           SELECT o.order_id, o.customer_id, o.order_date, o.total_amount, 
                                  o.user_id, o.payment_status, NOW(), :archived_by
                           FROM `order` o
                           WHERE o.order_id = :order_id";
            
            $stmt = $conn->prepare($archiveSql);
            $stmt->bindParam(':order_id', $data['order_id']);
            $stmt->bindParam(':archived_by', $data['user_id']);
            $stmt->execute();

            // Delete related records in reverse order of dependencies
            // Delete from sales
            $deleteSalesSql = "DELETE FROM sales WHERE order_id = :order_id";
            $stmt = $conn->prepare($deleteSalesSql);
            $stmt->bindParam(':order_id', $data['order_id']);
            $stmt->execute();

            // Delete from receipt
            $deleteReceiptSql = "DELETE FROM receipt WHERE order_id = :order_id";
            $stmt = $conn->prepare($deleteReceiptSql);
            $stmt->bindParam(':order_id', $data['order_id']);
            $stmt->execute();

            // Delete from order_item
            $deleteOrderItemSql = "DELETE FROM order_item WHERE order_id = :order_id";
            $stmt = $conn->prepare($deleteOrderItemSql);
            $stmt->bindParam(':order_id', $data['order_id']);
            $stmt->execute();

            // Delete from order
            $deleteOrderSql = "DELETE FROM `order` WHERE order_id = :order_id";
            $stmt = $conn->prepare($deleteOrderSql);
            $stmt->bindParam(':order_id', $data['order_id']);
            $stmt->execute();
            
            $conn->commit();
            return [
                "status" => true,
                "message" => "Sales data archived successfully"
            ];
        } catch (PDOException $e) {
            $conn->rollBack();
            return [
                "status" => false,
                "message" => "Failed to archive sales: " . $e->getMessage()
            ];
        }
    }

    public function archiveFilteredSales($data) {
        global $conn;
        
        try {
            $conn->beginTransaction();
            
            foreach ($data['order_ids'] as $orderId) {
                // Insert into archived_sales
                $archiveSql = "INSERT INTO archived_sales 
                              (order_id, customer_id, order_date, total_amount, user_id, 
                               payment_status, archived_date, archived_by)
                              SELECT o.order_id, o.customer_id, o.order_date, o.total_amount, 
                                     o.user_id, o.payment_status, NOW(), :archived_by
                              FROM `order` o
                              WHERE o.order_id = :order_id";
                
                $stmt = $conn->prepare($archiveSql);
                $stmt->bindParam(':order_id', $orderId);
                $stmt->bindParam(':archived_by', $data['user_id']);
                $stmt->execute();

                // Delete related records in reverse order of dependencies
                // Delete from sales
                $deleteSalesSql = "DELETE FROM sales WHERE order_id = :order_id";
                $stmt = $conn->prepare($deleteSalesSql);
                $stmt->bindParam(':order_id', $orderId);
                $stmt->execute();

                // Delete from receipt
                $deleteReceiptSql = "DELETE FROM receipt WHERE order_id = :order_id";
                $stmt = $conn->prepare($deleteReceiptSql);
                $stmt->bindParam(':order_id', $orderId);
                $stmt->execute();

                // Delete from order_item
                $deleteOrderItemSql = "DELETE FROM order_item WHERE order_id = :order_id";
                $stmt = $conn->prepare($deleteOrderItemSql);
                $stmt->bindParam(':order_id', $orderId);
                $stmt->execute();

                // Delete from order
                $deleteOrderSql = "DELETE FROM `order` WHERE order_id = :order_id";
                $stmt = $conn->prepare($deleteOrderSql);
                $stmt->bindParam(':order_id', $orderId);
                $stmt->execute();
            }
            
            $conn->commit();
            return [
                "status" => true,
                "message" => "Sales data archived successfully"
            ];
        } catch (PDOException $e) {
            $conn->rollBack();
            return [
                "status" => false,
                "message" => "Failed to archive sales: " . $e->getMessage()
            ];
        }
    }

}
?>