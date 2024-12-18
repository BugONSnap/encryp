<script lang="ts">
  import { createEventDispatcher } from 'svelte';
  import { ApiService } from '$lib/services/api';
  import Alert from '$lib/components/Alert.svelte';

  const dispatch = createEventDispatcher();

  interface CartItem {
    id: number;
    name: string;
    price: number;
    quantity: number;
    image?: string;
  }

  export let cartItems: CartItem[] = [];
  export let onUpdateQuantity: (productId: number, newQuantity: number) => void;
  export let onRemoveFromCart: (productId: number) => void;
  export let userId: number;
  export let total: number;

  let customerName = '';
  let amountPaid = 0;
  let change = 0;

  // Calculate total from cart items
  $: total = cartItems.reduce((sum, item) => sum + (item.price * item.quantity), 0);
  // Calculate change whenever amountPaid or total changes
  $: change = amountPaid - total;

  // Add new interface for receipt data
  interface ReceiptData {
    receipt_id: number;
    customer_name: string;
    date: string;
    items: CartItem[];
    total_amount: number;
    amount_paid: number;
    change: number;
  }

  // Add new state variables
  let showReceiptModal = false;
  let receiptData: ReceiptData | null = null;

  // Add new interface for ingredient availability
  interface IngredientAvailability {
    product_id: number;
    max_possible_quantity: number;
  }

  // Add new state variable
  let productAvailability: Record<number, number> = {};

  // Alert state management
  let showAlert = false;
  let alertMessage = '';
  let alertType: 'success' | 'error' | 'warning' = 'error';

  function showAlertMessage(message: string, type: 'success' | 'error' | 'warning' = 'error') {
    alertMessage = message;
    alertType = type;
    showAlert = true;
    setTimeout(() => {
      showAlert = false;
    }, 3000);
  }

  // Function to check ingredient availability
  async function checkIngredientAvailability(productId: number, requestedQuantity: number): Promise<boolean> {
    try {
      const result = await ApiService.get<{
        status: boolean;
        max_possible_quantity: number;
      }>('check-ingredient-availability', {
        product_id: productId.toString(),
        quantity: requestedQuantity.toString()
      });

      if (result.status) {
        productAvailability[productId] = result.max_possible_quantity;
        productAvailability = { ...productAvailability }; // Force Svelte reactivity
        return requestedQuantity <= result.max_possible_quantity;
      }
      return false;
    } catch (error) {
      console.error('Error checking ingredient availability:', error);
      return false;
    }
  }

  async function saveCustomer() {
    if (!customerName.trim()) {
      showAlertMessage('Please enter customer name');
      return;
    }

    if (!amountPaid) {
      showAlertMessage('Please enter amount paid');
      return;
    }

    if (amountPaid < total) {
      showAlertMessage('Amount paid cannot be less than the total amount');
      return;
    }

    try {
      // First save customer info
      const customerResult = await ApiService.post('add-customer', {
        Name: customerName,
        total_amount: amountPaid
      });

      if (customerResult.status) {
        // Create order
        const orderResult = await ApiService.post('create-order', {
          customer_id: customerResult.customer_id,
          total_amount: total,
          user_id: userId,
          payment_status: 'paid',
          order_items: cartItems.map(item => ({
            product_id: item.id,
            quantity: item.quantity,
            price: item.price
          }))
        });

        if (orderResult.status) {
          // Record sale
          const saleResult = await ApiService.post('add-sale', {
            order_id: orderResult.order_id,
            total_sales: total,
            user_id: userId
          });

          if (saleResult.status) {
            // Generate receipt
            const receiptResult = await ApiService.post('add-receipt', {
              order_id: orderResult.order_id,
              total_amount: total
            });

            if (receiptResult.status) {
              // Clear cart
              await ApiService.delete('clear-cart', { user_id: userId });
              
              // Show receipt modal
              receiptData = {
                receipt_id: receiptResult.receipt_id,
                customer_name: customerName,
                date: new Date().toLocaleString(),
                items: cartItems,
                total_amount: total,
                amount_paid: amountPaid,
                change: change
              };
              
              showReceiptModal = true;
              
              // Clear all cart-related data
              cartItems = [];
              customerName = '';
              amountPaid = 0;
              change = 0;
              
              // Dispatch event to notify parent component
              dispatch('cartCleared');
            }
          }
        }
      }
    } catch (error) {
      console.error('Error saving information:', error);
      showAlertMessage('Failed to process order');
    }
  }

  function closeReceiptModal() {
    showReceiptModal = false;
    receiptData = null;
  }

  function printReceipt() {
    if (!receiptData) return;

    // Create a hidden iframe for printing
    const iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    document.body.appendChild(iframe);

    const printContent = `
      <!DOCTYPE html>
      <html>
        <head>
          <title>Receipt #${receiptData.receipt_id}</title>
          <style>
            body {
              font-family: 'Courier New', Courier, monospace;
              padding: 20px;
              max-width: 300px;
              margin: 0 auto;
            }
            .header {
              text-align: center;
              margin-bottom: 20px;
            }
            .receipt-details {
              margin-bottom: 20px;
            }
            table {
              width: 100%;
              border-collapse: collapse;
              margin: 20px 0;
            }
            th, td {
              text-align: left;
              padding: 5px;
            }
            .summary {
              border-top: 1px dashed #000;
              margin-top: 20px;
              padding-top: 10px;
            }
            .summary p {
              margin: 5px 0;
            }
            @media print {
              body {
                width: 80mm; /* Standard receipt width */
              }
            }
          </style>
        </head>
        <body>
          <div class="header">
            <h2>Laz Bean Cafe</h2>
            <p>Receipt #${receiptData.receipt_id}</p>
          </div>
          
          <div class="receipt-details">
            <p>Date: ${receiptData.date}</p>
            <p>Customer: ${receiptData.customer_name}</p>
          </div>

          <table>
            <thead>
              <tr>
                <th>Item</th>
                <th>Qty</th>
                <th>Price</th>
                <th>Total</th>
              </tr>
            </thead>
            <tbody>
              ${receiptData.items.map(item => `
                <tr>
                  <td>${item.name}</td>
                  <td>${item.quantity}</td>
                  <td>₱${item.price}</td>
                  <td>₱${(item.price * item.quantity).toFixed(2)}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>

          <div class="summary">
            <p><strong>Total Amount:</strong> ₱${receiptData.total_amount.toFixed(2)}</p>
            <p><strong>Amount Paid:</strong> ₱${receiptData.amount_paid.toFixed(2)}</p>
            <p><strong>Change:</strong> ₱${receiptData.change.toFixed(2)}</p>
          </div>

          <div class="footer" style="text-align: center; margin-top: 30px;">
            <p>Thank you for your purchase!</p>
            <p>Please come again</p>
          </div>
        </body>
      </html>
    `;

    const doc = iframe.contentWindow?.document;
    if (!doc) return;

    doc.open();
    doc.write(printContent);
    doc.close();

    // Wait for content to load before printing
    iframe.onload = function() {
        try {
            iframe.contentWindow?.print();
            
            // Remove the iframe after printing
            setTimeout(() => {
                document.body.removeChild(iframe);
                // Close the receipt modal
                closeReceiptModal();
            }, 500);
        } catch (error) {
            console.error('Error printing:', error);
        }
    };
  }

  async function handleQuantityChange(item: CartItem, change: number) {
    const newQuantity = item.quantity + change;
    if (newQuantity < 1) return;
    
    try {
      const result = await ApiService.get<{
        is_available: boolean, 
        max_quantity: number, 
        debug_info: any
      }>(
        'check-ingredient-availability',
        {
          product_id: item.id.toString(),
          quantity: newQuantity.toString()
        }
      );
      
      if (!result.is_available || newQuantity > result.max_quantity) {
        showAlertMessage(`Cannot change quantity: Insufficient ingredients. Maximum available: ${result.max_quantity}`);
        return;
      }
      
      onUpdateQuantity(item.id, newQuantity);
    } catch (error) {
      console.error('Error checking quantity availability:', error);
    }
  }
</script>

<div class="cart-container">
  {#if showAlert}
    <Alert type={alertType} message={alertMessage} />
  {/if}
  
  <h2 class="cart-title">Order Summary</h2>
  
  <div class="cart-items-container">
    <div class="cart-items">
      {#each cartItems as item}
        <div class="cart-item">
          <img 
            src={item.image.startsWith('http') ? item.image : `https://formalytics.me/uploads/${item.image}`}
            alt={item.name}
            class="cart-item-image"
            on:error={(e) => {
                const img = e.currentTarget as HTMLImageElement;
                img.src = '/images/placeholder.jpg';
            }}
          />
          <div class="cart-item-details">
            <h3>{item.name}</h3>
            <p>₱{item.price}</p>
            <div class="quantity-controls">
              <button 
                class="quantity-btn"
                on:click={() => handleQuantityChange(item, -1)}
                disabled={item.quantity <= 1}
              >
                -
              </button>
              <span>{item.quantity}</span>
              <button 
                class="quantity-btn"
                on:click={() => handleQuantityChange(item, 1)}
              >
                +
              </button>
            </div>
            {#if productAvailability[item.id] !== undefined && item.quantity >= productAvailability[item.id]}
              <p class="text-red-500 text-sm">Maximum available quantity reached ({productAvailability[item.id]})</p>
            {/if}
          </div>
          <button class="remove-btn" on:click={() => onRemoveFromCart(item.id)}>×</button>
        </div>
      {/each}
    </div>
  </div>

  <div class="customer-details">
    <input
      type="text"
      bind:value={customerName}
      placeholder="Customer Name"
      class="input-field"
    />
    <input
      type="number"
      bind:value={amountPaid}
      placeholder="Amount Paid"
      min={total}
      class="input-field {amountPaid < total ? 'invalid-amount' : ''}"
    />
    {#if amountPaid > 0}
      <div class="change-display">
        <span>Change: ₱{change.toFixed(2)}</span>
      </div>
      {#if amountPaid < total}
        <div class="error-message">
          Amount paid must be at least ₱{total.toFixed(2)}
        </div>
      {/if}
    {/if}
  </div>

  <div class="cart-total">
    <h3>Total: ₱{total.toFixed(2)}</h3>
    <div class="customer-actions">
      <button 
        class="save-customer-btn"
        on:click={saveCustomer}
        disabled={!customerName.trim() || !amountPaid || amountPaid < total}
      >
        Proceed To Checkout
      </button>
    </div>
  </div>
</div>

{#if showReceiptModal && receiptData}
  <div class="modal-overlay">
    <div class="modal-content">
      <div class="receipt">
        <h2>Receipt #{receiptData.receipt_id}</h2>
        <p class="receipt-date">Date: {receiptData.date}</p>
        <p class="customer-name">Customer: {receiptData.customer_name}</p>
        
        <div class="receipt-items">
          <table>
            <thead>
              <tr>
                <th>Item</th>
                <th>Qty</th>
                <th>Price</th>
                <th>Total</th>
              </tr>
            </thead>
            <tbody>
              {#each receiptData.items as item}
                <tr>
                  <td>{item.name}</td>
                  <td>{item.quantity}</td>
                  <td>₱{item.price}</td>
                  <td>₱{(item.price * item.quantity).toFixed(2)}</td>
                </tr>
              {/each}
            </tbody>
          </table>
        </div>

        <div class="receipt-summary">
          <p><strong>Total Amount:</strong> ₱{receiptData.total_amount.toFixed(2)}</p>
          <p><strong>Amount Paid:</strong> ₱{receiptData.amount_paid.toFixed(2)}</p>
          <p><strong>Change:</strong> ₱{receiptData.change.toFixed(2)}</p>
        </div>

        <button class="close-btn" on:click={closeReceiptModal}>Close</button>
        <button class="print-btn" on:click={printReceipt}>Print</button>
      </div>
    </div>
  </div>
{/if}

<style>
  .cart-container {
    width: 100%;
    max-width: 400px;
    background: #faedcd;
    border-radius: 0.5rem;
    padding: 1.5rem;
    display: flex
;
    flex-direction: column;
    height: calc(100vh - 4rem);
    
    
  }

  .cart-title {
    font-size: 1.5rem;
    font-weight: bold;
    margin-bottom: 1rem;
    margin-top: 20px;
  }

  .cart-items-container {
    flex: 1;
    overflow: hidden;
    position: relative;
    margin: 1rem 0;
  }

  .cart-items {
    height: 100%;
    overflow-y: auto;
    padding-right: 0.5rem;
    scrollbar-width: thin;
    scrollbar-color: #47cb50 #f5f5f5;
  }

  .cart-items::-webkit-scrollbar {
    width: 6px;
  }

  .cart-items::-webkit-scrollbar-track {
    background: #f5f5f5;
    border-radius: 3px;
  }

  .cart-items::-webkit-scrollbar-thumb {
    background: #47cb50;
    border-radius: 3px;
  }

  .cart-item {
    display: flex;
    align-items: center;
    padding: 0.5rem;
    border-bottom: 1px solid #e5e7eb;
    gap: 1rem;
  }

  .cart-item-image {
    width: 50px;
    height: 50px;
    object-fit: cover;
    border-radius: 0.25rem;
  }

  .cart-item-details {
    flex: 1;
  }

  .quantity-controls {
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  .quantity-controls button {
    padding: 0.5rem;
    border-radius: 0.25rem;
    border: none;
    cursor: pointer;
  }

  .remove-btn {
    padding: 0.25rem 0.5rem;
    border: none;
    background: none;
    cursor: pointer;
    font-size: 1.25rem;
    color: #ef4444;
  }

  .cart-total {
    padding-top: 1rem;
    border-top: 2px solid #e5e7eb;
  }

  .customer-details {
    padding: 1rem;
    border-top: 1px solid #e5e7eb;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
  }

  .input-field {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid #e5e7eb;
    border-radius: 0.25rem;
    font-size: 1rem;
  }

  .change-display {
    padding: 0.5rem;
    background: #f3f4f6;
    border-radius: 0.25rem;
    text-align: right;
    font-weight: bold;
  }

  .customer-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
  }

  .save-customer-btn {
    flex: 1;
    padding: 0.75rem;
    background: #4B5563;
    color: white;
    border: none;
    border-radius: 0.5rem;
    cursor: pointer;
  }

  .save-customer-btn:hover {
    background: #374151;
  }

  .save-customer-btn:disabled {
    background: #9CA3AF;
    cursor: not-allowed;
  }

  .modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1000;
  }

  .modal-content {
    background: white;
    padding: 2rem;
    border-radius: 0.5rem;
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
  }

  .receipt {
    font-family: 'Courier New', Courier, monospace;
  }

  .receipt h2 {
    text-align: center;
    margin-bottom: 1rem;
  }

  .receipt-date, .customer-name {
    margin-bottom: 0.5rem;
  }

  .receipt-items {
    margin: 1rem 0;
  }

  .receipt-items table {
    width: 100%;
    border-collapse: collapse;
  }

  .receipt-items th, .receipt-items td {
    padding: 0.5rem;
    text-align: left;
    border-bottom: 1px solid #eee;
  }

  .receipt-summary {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 2px dashed #ccc;
  }

  .receipt-summary p {
    margin: 0.5rem 0;
  }

  .close-btn, .print-btn {
    display: block;
    width: 100%;
    padding: 0.75rem;
    background: #4B5563;
    color: white;
    border: none;
    border-radius: 0.5rem;
    margin-top: 1rem;
    cursor: pointer;
  }

  .close-btn:hover, .print-btn:hover {
    background: #374151;
  }

  .invalid-amount {
    border-color: #ef4444;
    background-color: #fee2e2;
  }

  .invalid-amount:focus {
    outline: none;
    border-color: #ef4444;
    box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.2);
  }

  .error-message {
    color: #ef4444;
    font-size: 0.875rem;
    margin-top: 0.25rem;
  }

  @media (max-width: 768px) {
    .cart-container {
      padding: 1rem;
      padding-bottom: 6rem;
    }

    .cart-item {
      padding: 0.5rem;
    }

    .quantity-controls {
      flex-direction: row;
      align-items: center;
      gap: 0.5rem;
    }

    .quantity-controls button {
      padding: 0.25rem 0.5rem;
    }

    .save-customer-btn {
      position: sticky;
      bottom: 0;
      margin: 0;
      border-radius: 0;
    }
  }
</style>
