<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

class Get {
    public function getMenuItems() {
        global $conn;
        
        try {
            $sql = "SELECT * FROM product ORDER BY name";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($items as &$item) {
                if (isset($item['image'])) {
                    // Convert BLOB to string if needed
                    if (is_resource($item['image'])) {
                        $item['image'] = stream_get_contents($item['image']);
                    }
                    // Ensure image path is properly formatted
                    if (!empty($item['image'])) {
                        $item['image'] = trim($item['image']);
                    }
                }
            }
            
            return $items;
            
        } catch (PDOException $e) {
            error_log("Database error in getMenuItems: " . $e->getMessage());
            return [
                "status" => false,
                "message" => "Failed to fetch menu items",
                "error" => $e->getMessage()
            ];
        }
    }
    public function getItems() {
        global $conn;
        
        try {
            $sql = "SELECT inventory_id, item_name, stock_quantity, unit_of_measure, last_updated 
                    FROM inventory 
                    ORDER BY item_name";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            
            return [
                "status" => true,
                "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ];
        } catch (PDOException $e) {
            return [
                "status" => false,
                "message" => "Error fetching items: " . $e->getMessage()
            ];
        }
    }
    public function getSalesData() {
        global $conn;
        
        try {
            $sql = "SELECT 
                    o.order_id,
                    ua.username,
                    c.Name as customer_name,
                    c.total_amount as amount_paid,
                    o.total_amount,
                    o.order_date,
                    p.name as product_name,
                    p.price as unit_price,
                    oi.quantity,
                    (p.price * oi.quantity) as line_total
                FROM `order` o
                JOIN user_acc ua ON o.user_id = ua.User_id
                JOIN order_item oi ON o.order_id = oi.order_id
                JOIN product p ON oi.product_id = p.product_id
                JOIN customer c ON o.customer_id = c.customer_id
                ORDER BY o.order_date DESC";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Group results by order_id for table display
            $groupedResults = [];
            foreach ($results as $row) {
                $orderId = $row['order_id'];
                if (!isset($groupedResults[$orderId])) {
                    $groupedResults[$orderId] = [
                        'order_id' => $row['order_id'],
                        'username' => $row['username'],
                        'customer_name' => $row['customer_name'],
                        'amount_paid' => $row['amount_paid'],
                        'total_amount' => $row['total_amount'],
                        'order_date' => $row['order_date'],
                        'products' => [],
                        'quantities' => []
                    ];
                }
                $groupedResults[$orderId]['products'][] = $row['product_name'];
                $groupedResults[$orderId]['quantities'][] = $row['quantity'];
            }
            
            // Format results for table display
            $formattedResults = array_map(function($group) {
                return [
                    'order_id' => $group['order_id'],
                    'username' => $group['username'],
                    'product_name' => implode(', ', $group['products']),
                    'quantity' => implode(', ', $group['quantities']),
                    'customer_name' => $group['customer_name'],
                    'amount_paid' => $group['amount_paid'],
                    'total_amount' => $group['total_amount'],
                    'order_date' => $group['order_date']
                ];
            }, $groupedResults);
            
            // Calculate chart data using actual unit prices and quantities
            $chartData = [];
            foreach ($results as $row) {
                $date = date('Y-m', strtotime($row['order_date'])); // For monthly data
                $product = $row['product_name'];
                $actualTotal = floatval($row['unit_price']) * floatval($row['quantity']); // Calculate using unit price
                
                if (!isset($chartData[$date])) {
                    $chartData[$date] = [];
                }
                if (!isset($chartData[$date][$product])) {
                    $chartData[$date][$product] = 0;
                }
                $chartData[$date][$product] += $actualTotal;
            }
            
            // Add daily chart data
            $dailyChartData = [];
            foreach ($results as $row) {
                $date = date('Y-m-d', strtotime($row['order_date']));
                $product = $row['product_name'];
                $actualTotal = floatval($row['unit_price']) * floatval($row['quantity']);
                
                if (!isset($dailyChartData[$date])) {
                    $dailyChartData[$date] = [];
                }
                if (!isset($dailyChartData[$date][$product])) {
                    $dailyChartData[$date][$product] = 0;
                }
                $dailyChartData[$date][$product] += $actualTotal;
            }
            
            // Add chart data to the response
            return [
                "status" => true,
                "data" => array_values($formattedResults),
                "chartData" => $chartData,
                "dailyChartData" => $dailyChartData
            ];
        } catch (PDOException $e) {
            return [
                "status" => false,
                "message" => "Failed to fetch sales data: " . $e->getMessage()
            ];
        }
    }
    public function checkIngredientAvailability($product_id, $quantity = 1) {
        global $conn;
        
        // Log the input parameters
        error_log("Checking ingredient availability for Product ID: $product_id, Quantity: $quantity");
        
        try {
            // First check if the product has any recipe/ingredients defined
            $checkRecipeSql = "SELECT COUNT(*) as recipe_count 
                              FROM product_ingredients 
                              WHERE product_id = :product_id";
            $stmt = $conn->prepare($checkRecipeSql);
            $stmt->bindParam(':product_id', $product_id);
            $stmt->execute();
            $recipeResult = $stmt->fetch(PDO::FETCH_ASSOC);

            // Log recipe count
            error_log("Recipe count for Product ID $product_id: " . $recipeResult['recipe_count']);

            // If no recipe exists, return false
            if ($recipeResult['recipe_count'] == 0) {
                error_log("No recipe found for Product ID: $product_id");
                return [
                    "status" => true,
                    "is_available" => false,
                    "message" => "No recipe defined for this product",
                    "max_quantity" => 0
                ];
            }

            // Detailed ingredient availability check
            $sql = "SELECT 
                        i.inventory_id,
                        i.item_name,
                        i.stock_quantity, 
                        pi.quantity_needed,
                        FLOOR(i.stock_quantity / pi.quantity_needed) as possible_quantity
                    FROM product_ingredients pi
                    JOIN inventory i ON i.inventory_id = pi.inventory_id
                    WHERE pi.product_id = :product_id";
                
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':product_id', $product_id);
            $stmt->execute();
            $ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Log ingredient details
            error_log("Ingredients for Product ID $product_id:");
            foreach ($ingredients as $ingredient) {
                error_log(json_encode([
                    "item_name" => $ingredient['item_name'],
                    "stock_quantity" => $ingredient['stock_quantity'],
                    "quantity_needed" => $ingredient['quantity_needed'],
                    "possible_quantity" => $ingredient['possible_quantity']
                ]));
            }

            $maxQuantity = PHP_INT_MAX;
            $isAvailable = true;

            foreach ($ingredients as $ingredient) {
                $possibleQuantity = floor($ingredient['stock_quantity'] / $ingredient['quantity_needed']);
                $maxQuantity = min($maxQuantity, $possibleQuantity);
                
                if ($possibleQuantity < $quantity) {
                    $isAvailable = false;
                    error_log("Insufficient ingredient: " . $ingredient['item_name'] . 
                              ", Stock: " . $ingredient['stock_quantity'] . 
                              ", Needed: " . ($ingredient['quantity_needed'] * $quantity) . 
                              ", Possible Quantity: " . $possibleQuantity);
                }
            }
            
            // Log final availability
            error_log("Availability for Product ID $product_id: " . 
                      ($isAvailable ? "Available" : "Not Available") . 
                      ", Max Quantity: $maxQuantity");

            return [
                "status" => true,
                "is_available" => $isAvailable,
                "message" => $isAvailable ? "Product available" : "Insufficient ingredients",
                "max_quantity" => max(0, $maxQuantity)
            ];
            
        } catch (PDOException $e) {
            error_log("Error checking ingredient availability: " . $e->getMessage());
            return [
                "status" => false,
                "is_available" => false,
                "message" => "Error checking availability: " . $e->getMessage(),
                "max_quantity" => 0
            ];
        }
    }
    private function convertUnits($value, $fromUnit, $toUnit) {
        if ($fromUnit === $toUnit) return $value;

        $conversions = [
            'mass' => [
                'grams' => 1,
                'kilograms' => 1000,
                'pounds' => 453.592,
                'ounces' => 28.3495
            ],
            'volume' => [
                'milliliters' => 1,
                'liters' => 1000,
                'cups' => 236.588,
                'tablespoons' => 14.7868,
                'teaspoons' => 4.92892
            ]
        ];

        $unitTypes = [
            'grams' => 'mass',
            'kilograms' => 'mass',
            'pounds' => 'mass',
            'ounces' => 'mass',
            'milliliters' => 'volume',
            'liters' => 'volume',
            'cups' => 'volume',
            'tablespoons' => 'volume',
            'teaspoons' => 'volume'
        ];

        // If units are pieces, no conversion needed
        if ($fromUnit === 'pieces' || $toUnit === 'pieces') {
            return $value;
        }

        // Get unit types
        $fromType = $unitTypes[$fromUnit] ?? null;
        $toType = $unitTypes[$toUnit] ?? null;

        // Check if units are of the same type
        if ($fromType !== $toType) {
            throw new Exception("Cannot convert between different types of units");
        }

        // Convert to base unit first
        $baseValue = $value * $conversions[$fromType][$fromUnit];
        
        // Then convert to target unit
        return $baseValue / $conversions[$fromType][$toUnit];
    }
    public function getProductIngredients($product_id) {
        global $conn;
        
        try {
            $sql = "SELECT pi.product_ingredient_id, pi.inventory_id, 
                    i.item_name as ingredient_name, pi.quantity_needed,
                    i.stock_quantity, i.unit_of_measure
                    FROM product_ingredients pi 
                    JOIN inventory i ON i.inventory_id = pi.inventory_id 
                    WHERE pi.product_id = :product_id";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':product_id', $product_id);
            $stmt->execute();
            
            return [
                "status" => true,
                "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ];
        } catch (PDOException $e) {
            return [
                "status" => false,
                "message" => "Error fetching ingredients: " . $e->getMessage()
            ];
        }
    }
    public function getProductsUsingIngredient($inventory_id) {
        global $conn;
        
        try {
            error_log("Fetching products for inventory_id: " . $inventory_id);
            
            $sql = "SELECT p.name as product_name, p.category, pi.quantity_needed, pi.unit_of_measure, pi.product_ingredient_id
                    FROM product_ingredients pi 
                    JOIN product p ON pi.product_id = p.product_id 
                    WHERE pi.inventory_id = :inventory_id";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':inventory_id', $inventory_id);
            $stmt->execute();
            
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Found products: " . json_encode($products));
            
            return [
                "status" => true,
                "data" => $products,
                "message" => count($products) . " products found using this ingredient"
            ];
            
        } catch (PDOException $e) {
            error_log("Database error in getProductsUsingIngredient: " . $e->getMessage());
            return [
                "status" => false,
                "message" => "Error fetching products: " . $e->getMessage()
            ];
        }
    }
    public function getCartItem($productId, $userId) {
        global $conn;
        
        try {
            $sql = "SELECT quantity FROM cart WHERE product_id = :product_id AND user_id = :user_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':product_id', $productId);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                "status" => true,
                "data" => $result
            ];
        } catch (PDOException $e) {
            return [
                "status" => false,
                "message" => $e->getMessage()
            ];
        }
    }
    public function getCartItems($userId) {
        global $conn;
        
        try {
            $sql = "SELECT c.*, p.name, p.price, p.image, p.category 
                    FROM cart c 
                    JOIN product p ON c.product_id = p.product_id 
                    WHERE c.user_id = :user_id";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            
            return [
                "status" => true,
                "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ];
        } catch (PDOException $e) {
            return [
                "status" => false,
                "message" => $e->getMessage()
            ];
        }
    }
    public function getMaxPossibleQuantity($product_id) {
        global $conn;
        
        try {
            $sql = "SELECT 
                    i.stock_quantity / pi.quantity_needed as possible_quantity,
                    i.unit_of_measure as stock_unit,
                    pi.unit_of_measure as recipe_unit
                    FROM product_ingredients pi
                    JOIN inventory i ON i.inventory_id = pi.inventory_id
                    WHERE pi.product_id = :product_id";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':product_id', $product_id);
            $stmt->execute();
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($results)) {
                return [
                    "status" => false,
                    "message" => "No recipe found for this product"
                ];
            }
            
            // Convert units and find minimum possible quantity
            $min_quantity = PHP_FLOAT_MAX;
            foreach ($results as $result) {
                $converted_quantity = $this->convertUnits(
                    $result['possible_quantity'],
                    $result['stock_unit'],
                    $result['recipe_unit']
                );
                $min_quantity = min($min_quantity, floor($converted_quantity));
            }
            
            return [
                "status" => true,
                "max_possible_quantity" => $min_quantity
            ];
            
        } catch (PDOException $e) {
            return [
                "status" => false,
                "message" => "Database error: " . $e->getMessage()
            ];
        }
    }
    public function getBatchProductIngredients($product_ids) {
        global $conn;
        
        try {
            // First, check which products have recipes
            $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
            $recipeSql = "SELECT DISTINCT product_id 
                          FROM product_ingredients 
                          WHERE product_id IN ($placeholders)";
            
            $stmt = $conn->prepare($recipeSql);
            $stmt->execute($product_ids);
            $productsWithRecipes = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Initialize all products as unavailable by default
            $productAvailability = array_fill_keys($product_ids, [
                'isAvailable' => false,
                'ingredients' => [],
                'hasRecipe' => false
            ]);
            
            // Mark products with recipes and check their ingredients
            if (!empty($productsWithRecipes)) {
                // Create new placeholders specifically for products with recipes
                $recipePlaceholders = str_repeat('?,', count($productsWithRecipes) - 1) . '?';
                
                $sql = "SELECT pi.product_id, pi.quantity_needed, i.stock_quantity, i.unit_of_measure, 
                               i.inventory_id, i.item_name
                    FROM product_ingredients pi 
                    JOIN inventory i ON i.inventory_id = pi.inventory_id 
                    WHERE pi.product_id IN ($recipePlaceholders)";
                
                $stmt = $conn->prepare($sql);
                
                // Debug log
                error_log("Checking ingredients for products: " . implode(', ', $productsWithRecipes));
                
                // Execute with products that have recipes
                $stmt->execute(array_values($productsWithRecipes));
                $ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Debug log
                error_log("Found ingredients: " . json_encode($ingredients));
                
                // Group ingredients by product
                $productIngredients = [];
                foreach ($ingredients as $ingredient) {
                    $productId = $ingredient['product_id'];
                    if (!isset($productIngredients[$productId])) {
                        $productIngredients[$productId] = [];
                    }
                    $productIngredients[$productId][] = $ingredient;
                }
                
                // Check availability for each product with recipe
                foreach ($productsWithRecipes as $productId) {
                    $productAvailability[$productId]['hasRecipe'] = true;
                    
                    if (isset($productIngredients[$productId])) {
                        $productAvailability[$productId]['ingredients'] = $productIngredients[$productId];
                        $productAvailability[$productId]['isAvailable'] = true; // Start as available
                        
                        foreach ($productIngredients[$productId] as $ingredient) {
                            if ($ingredient['stock_quantity'] <= 0 || 
                                $ingredient['stock_quantity'] < $ingredient['quantity_needed']) {
                                $productAvailability[$productId]['isAvailable'] = false;
                                error_log(sprintf(
                                    "Product %d marked unavailable: Ingredient %s (ID: %d) has stock %f, needs %f",
                                    $productId,
                                    $ingredient['item_name'],
                                    $ingredient['inventory_id'],
                                    $ingredient['stock_quantity'],
                                    $ingredient['quantity_needed']
                                ));
                                break;
                            }
                        }
                    }
                }
            }
            
            // Debug logs
            error_log("Products with recipes: " . implode(', ', $productsWithRecipes));
            error_log("Final Availability Map: " . json_encode($productAvailability));
            
            return [
                "status" => true,
                "data" => $productAvailability
            ];
            
        } catch (PDOException $e) {
            error_log("Error in getBatchProductIngredients: " . $e->getMessage());
            return [
                "status" => false,
                "message" => "Error fetching ingredients: " . $e->getMessage()
            ];
        }
    }
    public function getStaff() {
        global $conn;
        
        try {
            $sql = "SELECT User_id, username, role FROM user_acc ORDER BY User_id";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [
                "status" => false,
                "message" => "Error fetching staff: " . $e->getMessage()
            ];
        }
    }
}
