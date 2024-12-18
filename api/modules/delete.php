<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

class Delete {
    public function deleteItemStock($data) {
        global $conn;
        
        if (!isset($data['inventory_id'])) {
            return [
                "status" => false,
                "message" => "Inventory ID is required"
            ];
        }

        $inventory_id = $data['inventory_id'];

        try {
            $conn->beginTransaction();

            // Get all products using this ingredient
            $checkSql = "SELECT p.name as product_name, p.product_id, pi.quantity_needed 
                        FROM product_ingredients pi 
                        JOIN product p ON pi.product_id = p.product_id 
                        WHERE pi.inventory_id = :inventory_id";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bindParam(':inventory_id', $inventory_id);
            $checkStmt->execute();
            $usedInProducts = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($usedInProducts) > 0) {
                $conn->rollBack();
                return [
                    "status" => false, 
                    "message" => "Cannot delete this ingredient as it is being used in products",
                    "products" => $usedInProducts
                ];
            }

            // If not used in any products, proceed with deletion
            $sql = "DELETE FROM inventory WHERE inventory_id = :inventory_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':inventory_id', $inventory_id);
            $stmt->execute();
            
            $conn->commit();
            return [
                "status" => true, 
                "message" => "Item stock deleted successfully"
            ];

        } catch (PDOException $e) {
            $conn->rollBack();
            return [
                "status" => false, 
                "message" => "Failed to delete item stock: " . $e->getMessage()
            ];
        }
    }

    public function deleteMenuItem($data) {
        global $conn;
        
        if (!isset($data['product_id'])) {
            return [
                "status" => false,
                "message" => "Product ID is required"
            ];
        }

        $id = $data['product_id'];
        
        try {
            $conn->beginTransaction();
            
            // First delete from product_ingredients
            $deleteIngredientsSQL = "DELETE FROM product_ingredients WHERE product_id = :id";
            $stmt = $conn->prepare($deleteIngredientsSQL);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            // Delete from order_item
            $deleteOrderItemSql = "DELETE FROM order_item WHERE product_id = :id";
            $stmt = $conn->prepare($deleteOrderItemSql);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            // Delete from cart
            $deleteCartSql = "DELETE FROM cart WHERE product_id = :id";
            $stmt = $conn->prepare($deleteCartSql);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            // Finally delete the product
            $deleteProductSql = "DELETE FROM product WHERE product_id = :id";
            $stmt = $conn->prepare($deleteProductSql);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            $conn->commit();
            return [
                "status" => true, 
                "message" => "Menu item deleted successfully"
            ];
            
        } catch (PDOException $e) {
            $conn->rollBack();
            return [
                "status" => false, 
                "message" => "Failed to delete menu item: " . $e->getMessage()
            ];
        }
    }

    public function deleteOrder($data) {
        global $conn;
        
        if (!isset($data['order_id'])) {
            return [
                "status" => false,
                "message" => "Order ID is required"
            ];
        }
        
        try {
            $conn->beginTransaction();
            
            // Delete from sales first
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
                "message" => "Order deleted successfully"
            ];
        } catch (PDOException $e) {
            $conn->rollBack();
            return [
                "status" => false,
                "message" => "Failed to delete order: " . $e->getMessage()
            ];
        }
    }

    public function deleteAllOrders() {
        global $conn;
        
        try {
            $conn->beginTransaction();
            
            // Delete from all related tables in the correct order
            // Delete from receipt table first
            $sql = "DELETE FROM receipt";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            
            // Delete from sales table
            $sql = "DELETE FROM sales";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            
            // Delete from order_item table
            $sql = "DELETE FROM order_item";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            
            // Delete from order table
            $sql = "DELETE FROM `order`";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            
            // Finally, delete all customers
            $sql = "DELETE FROM customer";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            
            $conn->commit();
            return ["status" => true, "message" => "All orders and customers deleted successfully"];
            
        } catch (PDOException $e) {
            $conn->rollBack();
            return ["status" => false, "message" => "Failed to delete orders: " . $e->getMessage()];
        }
    }

    public function deleteProductIngredient($data) {
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
            
            $stmt = $conn->prepare("DELETE FROM product_ingredients WHERE product_ingredient_id = :product_ingredient_id");
            $stmt->bindParam(':product_ingredient_id', $data['product_ingredient_id']);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                $conn->rollBack();
                return [
                    "status" => false, 
                    "message" => "No ingredient found to delete",
                    "data" => null
                ];
            }
            
            $conn->commit();
            return [
                "status" => true, 
                "message" => "Product ingredient deleted successfully",
                "data" => null
            ];
            
        } catch (PDOException $e) {
            $conn->rollBack();
            return [
                "status" => false, 
                "message" => "Failed to delete product ingredient: " . $e->getMessage(),
                "data" => null
            ];
        }
    }

    public function clearCart($userId) {
        global $conn;
        try {
            $sql = "DELETE FROM cart WHERE user_id = :user_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            
            return [
                "status" => true,
                "message" => "Cart cleared successfully"
            ];
        } catch (PDOException $e) {
            return [
                "status" => false,
                "message" => $e->getMessage()
            ];
        }
    }

    public function removeFromCart($data) {
        global $conn;
        
        // Validate required fields
        if (!isset($data['product_id']) || !isset($data['user_id'])) {
            return [
                "status" => false,
                "message" => "Product ID and User ID are required"
            ];
        }

        try {
            $sql = "DELETE FROM cart 
                    WHERE product_id = :product_id 
                    AND user_id = :user_id";
                    
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':product_id', $data['product_id']);
            $stmt->bindParam(':user_id', $data['user_id']);
            $stmt->execute();
            
            return [
                "status" => true,
                "message" => "Item removed from cart successfully"
            ];
        } catch (PDOException $e) {
            error_log("Error removing from cart: " . $e->getMessage());
            return [
                "status" => false,
                "message" => "Database error: " . $e->getMessage()
            ];
        }
    }

    public function deleteFilteredOrders($data) {
        global $conn;
        
        try {
            $conn->beginTransaction();
            
            foreach ($data['order_ids'] as $orderId) {
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
                "message" => "Orders deleted successfully"
            ];
        } catch (PDOException $e) {
            $conn->rollBack();
            return [
                "status" => false,
                "message" => "Failed to delete orders: " . $e->getMessage()
            ];
        }
    }
}
