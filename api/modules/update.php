<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

class Update {
    public function updateItemStock($data) {
        global $conn;
        
        try {
            // Get current item details
            $sql = "SELECT unit_of_measure, stock_quantity FROM inventory 
                    WHERE inventory_id = :inventory_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':inventory_id', $data['inventory_id']);
            $stmt->execute();
            $currentItem = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$currentItem) {
                throw new Exception("Item not found");
            }

            // Convert quantity if units are different
            $quantity = $data['stock_quantity'];
            if ($currentItem['unit_of_measure'] !== $data['unit_of_measure']) {
                try {
                    $quantity = $this->convertUnits(
                        $data['stock_quantity'],
                        $data['unit_of_measure'],
                        $currentItem['unit_of_measure']
                    );
                    
                    // Log the conversion for debugging
                    error_log(sprintf(
                        "Unit conversion: %f %s = %f %s",
                        $data['stock_quantity'],
                        $data['unit_of_measure'],
                        $quantity,
                        $currentItem['unit_of_measure']
                    ));
                } catch (Exception $e) {
                    return [
                        "status" => false,
                        "message" => "Unit conversion error: " . $e->getMessage()
                    ];
                }
            }

            // Update the inventory
            $sql = "UPDATE inventory 
                    SET stock_quantity = :stock_quantity,
                        unit_of_measure = :unit_of_measure,
                        last_updated = NOW() 
                    WHERE inventory_id = :inventory_id";

            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':inventory_id', $data['inventory_id']);
            $stmt->bindParam(':stock_quantity', $quantity);
            $stmt->bindParam(':unit_of_measure', $data['unit_of_measure']);
            $stmt->execute();

            // Fetch the updated record
            $sql = "SELECT * FROM inventory WHERE inventory_id = :inventory_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':inventory_id', $data['inventory_id']);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                "status" => true,
                "message" => "Item stock updated successfully",
                "data" => $result
            ];
        } catch (PDOException $e) {
            return [
                "status" => false,
                "message" => "Database error: " . $e->getMessage()
            ];
        } catch (Exception $e) {
            return [
                "status" => false,
                "message" => $e->getMessage()
            ];
        }
    }

    private function convertUnits($value, $fromUnit, $toUnit) {
        if ($fromUnit === $toUnit) return $value;

        // Define base units and conversion factors
        // Each unit's value represents how many base units it contains
        $conversions = [
            'mass' => [
                'grams' => 1000,         // 1 gram = 1 gram (base unit)
                'kilograms' => 1,        // 1 kg = 1000 grams
                'pounds' => 453.59237,   // 1 pound = 453.59237 grams
                'ounces' => 28.349523125 // 1 ounce = 28.349523125 grams
            ],
            'volume' => [
                'milliliters' => 1,      // 1 ml = 1 ml (base unit)
                'liters' => 1000,        // 1 liter = 1000 ml
                'cups' => 236.588237,    // 1 cup = 236.588237 ml
                'tablespoons' => 14.7867648,  // 1 tbsp = 14.7867648 ml
                'teaspoons' => 4.92892159     // 1 tsp = 4.92892159 ml
            ]
        ];

        $unitTypes = [
            // Mass units
            'grams' => ['type' => 'mass', 'abbr' => 'g'],
            'kilograms' => ['type' => 'mass', 'abbr' => 'kg'],
            'pounds' => ['type' => 'mass', 'abbr' => 'lbs'],
            'ounces' => ['type' => 'mass', 'abbr' => 'oz'],
            
            // Volume units
            'milliliters' => ['type' => 'volume', 'abbr' => 'ml'],
            'liters' => ['type' => 'volume', 'abbr' => 'l'],
            'cups' => ['type' => 'volume', 'abbr' => 'cup'],
            'tablespoons' => ['type' => 'volume', 'abbr' => 'tbsp'],
            'teaspoons' => ['type' => 'volume', 'abbr' => 'tsp'],
            
            // Count units
            'pieces' => ['type' => 'count', 'abbr' => 'pcs']
        ];

        // Validate units
        if (!isset($unitTypes[$fromUnit]) || !isset($unitTypes[$toUnit])) {
            throw new Exception("Invalid unit type: " . 
                (!isset($unitTypes[$fromUnit]) ? $fromUnit : $toUnit));
        }

        // Handle count units
        if ($unitTypes[$fromUnit]['type'] === 'count' || $unitTypes[$toUnit]['type'] === 'count') {
            if ($unitTypes[$fromUnit]['type'] !== $unitTypes[$toUnit]['type']) {
                throw new Exception("Cannot convert between count and " . 
                    $unitTypes[$toUnit]['type'] . " units");
            }
            return $value;
        }

        // Check if units are of the same type
        if ($unitTypes[$fromUnit]['type'] !== $unitTypes[$toUnit]['type']) {
            throw new Exception(sprintf(
                "Cannot convert between %s (%s) and %s (%s)",
                $unitTypes[$fromUnit]['type'],
                $fromUnit,
                $unitTypes[$toUnit]['type'],
                $toUnit
            ));
        }

        $type = $unitTypes[$fromUnit]['type'];

        try {
            // Convert to base unit first (e.g., everything to grams or milliliters)
            $baseValue = $value * $conversions[$type][$fromUnit];
            
            // Then convert from base unit to target unit
            // For example: if converting 1 kg to grams:
            // 1 kg * 1000 (to get grams) = 1000 grams
            // If converting 1000 grams to kg:
            // 1000 grams / 1000 = 1 kg
            $convertedValue = $baseValue / $conversions[$type][$toUnit];
            
            // Log the conversion for debugging
            error_log(sprintf(
                "Converting %f %s to %s: Base value = %f, Final value = %f",
                $value,
                $fromUnit,
                $toUnit,
                $baseValue,
                $convertedValue
            ));

            // Round to 4 decimal places to avoid floating point issues
            return round($convertedValue, 4);
        } catch (Exception $e) {
            error_log("Conversion error: " . $e->getMessage());
            error_log(sprintf(
                "Attempted conversion: %f %s to %s",
                $value,
                $fromUnit,
                $toUnit
            ));
            throw $e;
        }
    }

    public function updateMenuItem($data) {
        global $conn;
        
        try {
            if (!isset($data['product_id'])) {
                return [
                    "status" => false,
                    "message" => "Product ID is required"
                ];
            }

            // Check for existing product with same details but different ID
            $checkSql = "SELECT * FROM product WHERE 
                        name = :name AND 
                        category = :category AND 
                        price = :price AND
                        size = :size AND
                        product_id != :product_id";
            
            $checkStmt = $conn->prepare($checkSql);
            $checkParams = [
                ':name' => $data['name'],
                ':category' => $data['category'],
                ':price' => $data['price'],
                ':size' => $data['size'] ?? 'Standard',
                ':product_id' => $data['product_id']
            ];
            
            $checkStmt->execute($checkParams);
            
            if ($checkStmt->fetch()) {
                return [
                    "status" => false,
                    "message" => "A similar product already exists",
                    "duplicate" => true
                ];
            }

            // If no duplicate found, proceed with update
            $sql = "UPDATE product SET 
                    name = :name,
                    price = :price,
                    category = :category,
                    size = :size";
            
            if (isset($data['image']) && !empty($data['image'])) {
                $sql .= ", image = :image";
            }
            
            $sql .= " WHERE product_id = :product_id";
            
            $stmt = $conn->prepare($sql);
            
            // Always use the provided size or 'Standard' as default
            $size = isset($data['size']) && !empty($data['size']) ? $data['size'] : 'Standard';
            
            $params = [
                ':name' => $data['name'],
                ':price' => $data['price'],
                ':category' => $data['category'],
                ':size' => $size,
                ':product_id' => $data['product_id']
            ];
            
            if (isset($data['image']) && !empty($data['image'])) {
                $params[':image'] = $data['image'];
            }
            
            $stmt->execute($params);
            
            return [
                "status" => true,
                "message" => "Menu item updated successfully"
            ];
        } catch (PDOException $e) {
            error_log('Database error: ' . $e->getMessage());
            return [
                "status" => false,
                "message" => "Failed to update menu item: " . $e->getMessage()
            ];
        }
    }

    public function updateProductIngredient($data) {
        global $conn;
        
        if (!isset($data['product_ingredient_id'])) {
            return [
                "status" => false,
                "message" => "Product ingredient ID is required",
                "data" => null
            ];
        }

        try {
            $conn->beginTransaction();
            
            $stmt = $conn->prepare("UPDATE product_ingredients 
                                   SET quantity_needed = :quantity_needed,
                                       unit_of_measure = :unit_of_measure
                                   WHERE product_ingredient_id = :product_ingredient_id");
            
            $stmt->bindParam(':product_ingredient_id', $data['product_ingredient_id']);
            $stmt->bindParam(':quantity_needed', $data['quantity_needed']);
            $stmt->bindParam(':unit_of_measure', $data['unit_of_measure']);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                $conn->rollBack();
                return [
                    "status" => false, 
                    "message" => "No ingredient found to update",
                    "data" => null
                ];
            }
            
            $conn->commit();
            return [
                "status" => true, 
                "message" => "Product ingredient updated successfully",
                "data" => null
            ];
            
        } catch (PDOException $e) {
            $conn->rollBack();
            return [
                "status" => false, 
                "message" => "Failed to update product ingredient: " . $e->getMessage(),
                "data" => null
            ];
        }
    }
    
    public function updateStaffRole($data) {
        global $conn;
        
        if (!isset($data['user_id']) || !isset($data['role'])) {
            return [
                "status" => false,
                "message" => "User ID and role are required"
            ];
        }

        try {
            $sql = "UPDATE user_acc SET role = :role WHERE User_id = :user_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':role', $data['role']);
            $stmt->bindParam(':user_id', $data['user_id']);
            $stmt->execute();
            
            return [
                "status" => true,
                "message" => "Role updated successfully"
            ];
        } catch (PDOException $e) {
            return [
                "status" => false,
                "message" => "Error updating role: " . $e->getMessage()
            ];
        }
    }
}
