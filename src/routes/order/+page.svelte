<script lang="ts">
  import { browser } from '$app/environment';
  import SideNav from '$lib/sideNav.svelte';
  import Header from '$lib/header.svelte';
  import ItemCard from '$lib/itemCard.svelte';
  import Cart from '../cart/+page.svelte';
  import { onMount } from 'svelte';
  import { userStore } from '$lib/auth';
  import { goto } from '$app/navigation';
  import { checkAuth } from '$lib/auth';
  import { productAvailability, availabilityLoading } from '$lib/stores/productAvailability';
  import { ApiService } from '$lib/services/api';
  import type { BatchAvailabilityResponse } from '$lib/types';
  import { encryptionService } from '$lib/services/encryption';

  type Product = {
    product_id: number;
    name: string;
    image: string;
    price: number;
    category: string;
    size?: string;
    isAvailable?: boolean;
  };
  type CartItem = {
    product_id: number;
    id: number;
    name: string;
    image: string;
    price: number;
    category: string;
    quantity: number;
  };

  type GroupedProduct = {
    name: string;
    image: string;
    category: string;
    variants: Product[];
  };

  let y = 0;
  let innerWidth = 0;
  let innerHeight = 0;
  let products: Product[] = [];
  let cartItems: CartItem[] = [];
  let selectedCategory = 'All';
  let searchQuery = '';
  let userId: number;
  let showSizeModal = false;
  let selectedProduct: GroupedProduct | null = null;
  let showMobileCart = false;

  userStore.subscribe(user => {
    userId = user.userId;
  });

  onMount(async () => {
    if (!checkAuth()) {
      goto('/');
    }
    await fetchItems();
    await fetchCartItems();
  });

  async function fetchItems() {
    try {
        const data = await ApiService.get<Product[]>('get-menu-items');
        products = data.map(p => ({ 
            ...p,
            price: Number(p.price),
            product_id: p.product_id || p.id,
            image: p.image || ''
        }));
    } catch (error) {
        console.error('Error fetching items:', error);
        products = [];
    }
  }

  async function fetchCartItems() {
    try {
      const result = await ApiService.get<CartItem[]>(`get-cart-items`, { user_id: userId.toString() });
      if (result.status && Array.isArray(result.data)) {
        cartItems = result.data.map(item => ({
          product_id: item.product_id,
          id: item.product_id,
          name: item.name,
          price: Number(item.price),
          quantity: Number(item.quantity),
          image: item.image ? `https://formalytics.me/uploads/${item.image}` : '/images/placeholder.jpg',
          category: item.category
        }));
      } else {
        cartItems = [];
      }
    } catch (error) {
      console.error('Error fetching cart items:', error);
      cartItems = [];
    }
  }

  async function checkInventoryStock(product: Product, quantity: number = 1): Promise<{ available: boolean; message: string }> {
    try {
      const result = await ApiService.get<{available: boolean; message: string}>('get-product-ingredients', {
        product_id: product.product_id.toString()
      });
      
      if (!result.status || !result.data || result.data.length === 0) {
        return { available: false, message: 'No Recipe Set' };
      }

      // Check if any ingredient is insufficient
      for (const ingredient of result.data) {
        const requiredQuantity = ingredient.quantity_needed * quantity;
        if (ingredient.stock_quantity < requiredQuantity) {
          return { available: false, message: 'Insufficient Stock' };
        }
      }
      
      return { available: true, message: '' };
    } catch (error) {
      console.error('Error checking inventory:', error);
      return { available: false, message: 'Error' };
    }
  }

  async function updateCartQuantity(productId: number, newQuantity: number) {
    try {
      const result = await ApiService.get<{
        is_available: boolean, 
        max_quantity: number, 
        debug_info: any
      }>(
        'check-ingredient-availability',
        {
          product_id: productId.toString(),
          quantity: newQuantity.toString()
        }
      );
      
      if (!result.is_available || newQuantity > result.max_quantity) {
        alert(`Cannot update quantity: Maximum available is ${result.max_quantity}`);
        return false;
      }
      
      cartItems = cartItems.map(item =>
        item.id === productId
          ? { ...item, quantity: newQuantity }
          : item
      );

      if (browser) {
        localStorage.setItem(`cart_${$userStore.userId}`, JSON.stringify(cartItems));
      }
      
      return true;
    } catch (error) {
      console.error('Error updating cart quantity:', error);
      return false;
    }
  }

  async function addToCart(product: Product) {
    const existingItem = cartItems.find(item => item.id === product.product_id);
    const newQuantity = existingItem ? existingItem.quantity + 1 : 1;
    
    try {
      const result = await ApiService.get<{
        is_available: boolean, 
        max_quantity: number, 
        debug_info: any
      }>(
        'check-ingredient-availability',
        {
          product_id: product.product_id.toString(),
          quantity: newQuantity.toString()
        }
      );
      
      if (!result.is_available || newQuantity > result.max_quantity) {
        alert(`Cannot add more of this item: Maximum available is ${result.max_quantity}`);
        return;
      }
      
      if (existingItem) {
        const updated = await updateCartQuantity(product.product_id, newQuantity);
        if (!updated) return;
      } else {
        cartItems = [
          ...cartItems,
          {
            product_id: product.product_id,
            id: product.product_id,
            name: product.name,
            price: product.price,
            quantity: 1,
            image: product.image ? `https://formalytics.me/uploads/${product.image}` : '/images/placeholder.jpg',
            category: product.category
          },
        ];

        if (browser) {
          localStorage.setItem(`cart_${$userStore.userId}`, JSON.stringify(cartItems));
        }
      }
    } catch (error) {
      console.error('Error checking availability:', error);
      alert('Unable to add item to cart');
    }
  }

  async function removeFromCart(productId: number) {
    try {
        const result = await ApiService.delete<{
            status: boolean;
            message: string;
        }>('remove-from-cart', {
            product_id: productId,
            user_id: userId
        });

        if (result.status) {
            cartItems = cartItems.filter(item => item.product_id !== productId);
            if (browser) {
                localStorage.setItem(`cart_${$userStore.userId}`, JSON.stringify(cartItems));
            }
        } else {
            alert(result.message);
        }
    } catch (error) {
        console.error('Error removing item from cart:', error);
        alert('Failed to remove item from cart');
    }
  }

  function updateQuantity(productId: number, newQuantity: number) {
    if (newQuantity < 1) {
      removeFromCart(productId);
      return;
    }
    cartItems = cartItems.map(item =>
      item.product_id === productId 
        ? { ...item, quantity: newQuantity }
        : item
    );
  }

  function getTotal() {
    return cartItems.reduce((sum, item) => sum + (Number(item.price) * item.quantity), 0);
  }

  $: groupedProducts = products.reduce((groups: { [key: string]: GroupedProduct }, product) => {
    const key = `${product.name}-${product.category}`;
    if (!groups[key]) {
      groups[key] = {
        name: product.name,
        image: product.image,
        category: product.category,
        variants: []
      };
    }
    groups[key].variants.push(product);
    return groups;
  }, {});

  $: filteredGroupedProducts = Object.values(groupedProducts)
    .filter(group => selectedCategory === 'All' || group.category === selectedCategory)
    .filter(group => group.name.toLowerCase().includes(searchQuery.toLowerCase()));

  $: categories = ['All', ...new Set(products.map(p => p.category))];
  $: total = getTotal();

  async function handleProductClick(group: GroupedProduct) {
    if (['Drinks', 'Pizza', 'Burger & Fries', 'Chocolate Series', 'Nachos', 'Cheesecake Series'].includes(group.category) && group.variants.length > 1) {
      selectedProduct = group;
      showSizeModal = true;
    } else {
      await addToCart(group.variants[0]);
    }
  }

  async function handleSizeSelection(variant: Product) {
    const stockCheck = await checkInventoryStock(variant);
    if (!stockCheck.available) {
        alert(stockCheck.message);
        return;
    }
    
    showSizeModal = false;
    await addToCart(variant);
    selectedProduct = null;
  }

  function toggleMobileCart() {
    showMobileCart = !showMobileCart;
  }

  async function checkBatchAvailability(products: Product[]) {
    try {
        availabilityLoading.set(true);
        const productIds = products.map(p => p.product_id);
        
        // console.log("Checking availability for products:", productIds);
        
        const result = await ApiService.get<any>('get-batch-product-ingredients', {
            product_ids: JSON.stringify(productIds)
        });
        
        // console.log("Raw API Response:", result);
        
        // Handle the response whether it's encrypted or not
        let availabilityData;
        if (result && typeof result === 'object') {
            if (result.status && typeof result.data === 'string') {
                // Handle encrypted data
                availabilityData = await encryptionService.decrypt(result.data);
            } else if (result.status && typeof result.data === 'object') {
                // Handle unencrypted data
                availabilityData = result.data;
            } else {
                // Direct unencrypted response
                availabilityData = result;
            }
        }
        
        // console.log("Processed availability data:", availabilityData);
        
        if (availabilityData) {
            const availability: Record<number, boolean> = {};
            Object.entries(availabilityData).forEach(([productId, data]: [string, any]) => {
                availability[Number(productId)] = data.isAvailable ?? true;
            });
            
            // console.log("Final availability map:", availability);
            productAvailability.set(availability);
        } else {
            // Default to available if no valid data
            const availability: Record<number, boolean> = {};
            products.forEach(p => {
                availability[p.product_id] = true;
            });
            productAvailability.set(availability);
        }
    } catch (error) {
        console.error('Error checking batch availability:', error);
        // Set all products as available on error
        const availability: Record<number, boolean> = {};
        products.forEach(p => {
            availability[p.product_id] = true;
        });
        productAvailability.set(availability);
    } finally {
        availabilityLoading.set(false);
    }
  }

  // Call this when products are loaded
  $: if (products.length > 0) {
    checkBatchAvailability(products);
  }
</script>

<Header {y} {innerHeight} />
<div class="container">
  <div class="content">
    <SideNav 
      activeMenu="pos"
      bind:selectedCategory={selectedCategory}
      onToggleCart={toggleMobileCart}
    />
    
    <div class="main-content">
      <!-- Search Bar -->
      <div class="search-controls">
        <div class="search-bar">
          <input
            type="text"
            bind:value={searchQuery}
            placeholder="Search products..."
            class="search-input"
          />
        </div>
        
        <!-- Category Labels -->
        <div class="category-tabs">
          {#each categories as category}
            <button 
              class="category-tab {selectedCategory === category ? 'active' : ''}"
              on:click={() => selectedCategory = category}
            >
              {category}
            </button>
          {/each}
        </div>
      </div>

      <!-- Products Grid -->
      <div class="products-container">
        <div class="products-section">
          <div class="grid grid-cols-3 gap-4">
            {#each filteredGroupedProducts as group}
              <button 
                type="button" 
                class="product-card"
              >
                <ItemCard 
                  product={{
                    product_id: group.variants[0].product_id,
                    name: group.name,
                    image: group.variants[0].image,
                    price: group.variants[0].price.toString(),
                    category: group.category
                  }} 
                  onAddToCart={() => handleProductClick(group)}
                />
              </button>
            {/each}
          </div>
        </div>
      </div>
    </div>

    <!-- Desktop Cart -->
    <div class="hidden md:block">
      <Cart 
        {cartItems} 
        {userId}
        onUpdateQuantity={updateQuantity}
        onRemoveFromCart={removeFromCart}
        {total}
        on:cartCleared={() => {
            cartItems = [];
            if (browser) {
                localStorage.removeItem(`cart_${$userStore.userId}`);
            }
        }}
      />
    </div>
  </div>
</div>

{#if showSizeModal && selectedProduct}
  <div class="modal-backdrop" on:click={() => {
    showSizeModal = false;
    selectedProduct = null;
  }}>
    <div class="modal-content" on:click|stopPropagation>
      <div class="modal-header">
        <h2 class="text-xl font-bold">Select Size for {selectedProduct.name}</h2>
        <button 
          class="close-modal-btn"
          on:click={() => {
            showSizeModal = false;
            selectedProduct = null;
          }}
        >
          ×
        </button>
      </div>
      <div class="grid gap-4">
        {#each selectedProduct.variants as variant}
          <button
            class="size-option"
            on:click={() => handleSizeSelection(variant)}
          >
            {variant.size} - ₱{variant.price}
          </button>
        {/each}
      </div>
    </div>
  </div>
{/if}

<!-- Mobile Cart Modal -->
{#if showMobileCart}
  <div class="fixed inset-0 z-50 md:hidden">
    <div class="absolute inset-0 bg-black bg-opacity-50" on:click={toggleMobileCart}></div>
    <div class="mobile-cart-container">
      <div class="h-full overflow-y-auto">
        <div class="p-4 border-b">
          <div class="flex justify-between items-center">
            <h2 class="text-xl font-bold">Your Cart</h2>
            <button 
              class="text-gray-500 hover:text-gray-700"
              on:click={toggleMobileCart}
            >
              <i class="fas fa-times"></i>
            </button>
          </div>
        </div>
        <Cart 
          {cartItems} 
          {userId}
          onUpdateQuantity={updateQuantity}
          onRemoveFromCart={removeFromCart}
          {total}
          on:cartCleared={() => {
              cartItems = [];
              if (browser) {
                  localStorage.removeItem(`cart_${$userStore.userId}`);
              }
              toggleMobileCart();
          }}
        />
      </div>
    </div>
  </div>
{/if}

<style>
  .search-controls {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-bottom: 1rem;
  }

  .search-bar {
    width: 100%;
  }

  .search-input {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid #e5e7eb;
    border-radius: 0.5rem;
    font-size: 1rem;
    transition: all 0.2s;
  }

  .search-input:focus {
    outline: none;
    border-color: #d4a373;
    box-shadow: 0 0 0 3px rgba(212, 163, 115, 0.1);
  }

  .category-tabs {
    display: flex;
    gap: 0.5rem;
    overflow-x: auto;
    padding: 0.5rem 0;
    scrollbar-width: none;
    -ms-overflow-style: none;
    -webkit-overflow-scrolling: touch;
  }

  .category-tabs::-webkit-scrollbar {
    display: none;
  }

  .category-tab {
    padding: 0.5rem 1rem;
    background: #faedcd;
    border: none;
    border-radius: 0.5rem;
    white-space: nowrap;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.875rem;
    color: #4b5563;
  }

  .category-tab:hover {
    background: #d4a373;
    color: white;
  }

  .category-tab.active {
    background: #d4a373;
    color: white;
  }

  .container {
    width: 100%;
    height: 100vh;
    background-color: #fefae0;
    overflow: hidden;
  }

  .content {
    display: flex;
    height: calc(100vh - 4rem);
    margin-top: 4rem;
    overflow: hidden;
  }

  .main-content {
    flex: 1;
    padding: 1rem;
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: #fefae0 #fefae0;
    margin-top: 20px;
  }

  .main-content::-webkit-scrollbar {
    width: 8px;
  }

  .main-content::-webkit-scrollbar-track {
    background: #faedcd;
  }

  .main-content::-webkit-scrollbar-thumb {
    background: #d4a373;
    border-radius: 4px;
  }

  .products-container {
    flex: 1;
    overflow-y: auto;
    min-height: 0;
    scrollbar-width: thin;
    scrollbar-color: #fefae0 #fefae0;
  }

  .products-section {
    padding: 1rem;
  }

  .product-card {
    cursor: pointer;
    transition: transform 0.2s;
  }

  .product-card:hover {
    transform: translateY(-2px);
  }

  .modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1000;
  }

  .modal-content {
    background: white;
    padding: 2rem;
    border-radius: 0.5rem;
    width: 90%;
    max-width: 400px;
  }

  .size-option {
    width: 100%;
    padding: 1rem;
    background: #faedcd;
    border-radius: 0.5rem;
    text-align: center;
    transition: background-color 0.2s;
  }

  .size-option:hover {
    background: #d4a373;
    color: white;
  }

  .cart-container {
    position: fixed;
    right: 0;
    top: 0rem;
    width: 400px;
    height: calc(100vh - 4rem);
    background: #fefae0;
    border-left: 1px solid #faedcd;
    overflow-y: auto;
    box-shadow: -2px 0 10px rgba(0, 0, 0, 0.1);
    transition: width 0.3s ease;
  }

  .mobile-cart-container {
    position: absolute;
    top: 18.5rem;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 90%;
    max-width: 400px;
    max-height: 80vh;
    background: white;
    border-radius: 0.5rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  }

  /* Responsive styles */
  @media (min-width: 1401px) {
    .main-content {
          }
  }

  @media (max-width: 1400px) and (min-width: 1024px) {
    .cart-container {
      width: 350px;
    }
    .main-content {
      
    }
  }

  @media (max-width: 1023px) and (min-width: 768px) {
    .cart-container {
      width: 300px;
    }
    .main-content {
    }
  }

  @media (max-width: 767px) {
    .cart-container {
      display: none;
    }
    .main-content {
      margin-right: 0;
      padding: 0.5rem;
    }
    .cart-container.mobile-visible {
      display: block;
      width: 100%;
      z-index: 50;
    }
    :global(.grid-cols-3) {
      grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
      gap: 0.75rem;
      padding: 0.5rem;
    }
  }

  :global(.grid-cols-3) {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1rem;
    padding: 1rem;
  }

  .cart-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    display: flex;
    flex-direction: column;
    height: 100%;
    width: 100%;
    max-width: 400px;
  }

  .cart-title {
    padding: 1.5rem;
    font-size: 1.25rem;
    font-weight: bold;
    border-bottom: 1px solid #eee;
  }

  .cart-items-container {
    flex: 1;
    overflow-y: auto;
    padding: 1rem;
  }

  .cart-item {
    display: flex;
    gap: 1rem;
    padding: 1rem;
    border-bottom: 1px solid #eee;
    align-items: center;
  }

  .cart-item-image {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 8px;
  }

  .cart-item-details {
    flex: 1;
  }

  .item-name {
    font-weight: 500;
    margin-bottom: 0.25rem;
  }

  .item-price {
    color: #666;
    font-size: 0.875rem;
  }

  .cart-item-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
  }

  .quantity-btn {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f3f4f6;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: background 0.2s;
  }

  .quantity-btn:hover {
    background: #e5e7eb;
  }

  .quantity {
    font-weight: 500;
    min-width: 20px;
    text-align: center;
  }

  .cart-footer {
    padding: 1.5rem;
    border-top: 1px solid #eee;
    background: #fafafa;
    border-radius: 0 0 12px 12px;
  }

  .customer-name-input {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: 6px;
    margin-bottom: 1rem;
  }

  .total-section {
    display: flex;
    justify-content: space-between;
    font-weight: bold;
    font-size: 1.125rem;
    margin-bottom: 1rem;
  }

  .total-amount {
    color: #DEB887;
  }

  .checkout-button {
    width: 100%;
    padding: 1rem;
    background: #DEB887;
    color: white;
    border: none;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.2s;
  }

  .checkout-button:hover {
    background: #d4a373;
  }

  /* Responsive styles */
  @media (max-width: 768px) {
    .cart-container {
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      max-height: 60vh;
      border-radius: 12px 12px 0 0;
      z-index: 100;
    }

    .cart-items-container {
      max-height: calc(60vh - 200px);
    }
  }
</style>