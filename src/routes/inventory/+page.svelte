<script lang="ts">
    import { goto } from '$app/navigation';
    import Header from '$lib/header.svelte';
    import SideNav from '$lib/sideNav.svelte';
    import { ApiService } from '$lib/services/api';
    import type { ProductIngredient, ProductIngredientResponse, AlertType } from '$lib/types';
    import { encryptionService } from '$lib/services/encryption';

    interface InventoryItem {
        inventory_id: number;
        item_name: string;
        stock_quantity: number;
        unit_of_measure: string;
        last_updated?: string;
    }

    let selectedCategory: string = 'All Item Stocks';
    let itemName = '';
    let stockQuantity = 0;
    let unitOfMeasure = 'pieces';
    let searchQuery = '';
    let isEditModalOpen = false;

    let y = 0;
    let innerWidth = 0;
    let innerHeight = 0;

    let items: InventoryItem[] = [];
    let editingItem: number | null = null;

    $: filteredItems = items.filter(item => 
        item.item_name.toLowerCase().includes(searchQuery.toLowerCase())
    );

    async function fetchItems() {
        try {
            const result = await ApiService.get<InventoryItem[]>('get-items');
            if (result.status) {
                items = result.data.map(item => ({
                    ...item,
                    last_updated: new Date(item.last_updated).toLocaleString()
                }));
            }
        } catch (error) {
            console.error('Error fetching items:', error);
            items = [];
        }
    }

    function startEdit(item: InventoryItem) {
        editingItem = item.inventory_id;
        itemName = item.item_name;
        stockQuantity = item.stock_quantity;
        unitOfMeasure = item.unit_of_measure;
        isEditModalOpen = true;
    }

    function closeEditModal() {
        isEditModalOpen = false;
        editingItem = null;
        itemName = '';
        stockQuantity = 0;
    }

    let errorMessage = '';
    let showError = false;

    async function addItemStock() {
        if (!itemName.trim() || stockQuantity <= 0) {
            errorMessage = "Product name and quantity are required. Quantity must be greater than 0.";
            showError = true;
            setTimeout(() => showError = false, 3000);
            return;
        }

        const data = {
            item_name: itemName,
            stock_quantity: stockQuantity,
            unit_of_measure: unitOfMeasure
        };

        try {
            const result = await ApiService.post<{status: boolean; message: string}>('add-item-stock', data);

            if (result.status) {
                itemName = '';
                stockQuantity = 0;
                unitOfMeasure = 'pieces';
                await fetchItems();
            } else {
                errorMessage = result.message;
                showError = true;
                setTimeout(() => showError = false, 3000);
            }
        } catch (error) {
            console.error('Error:', error);
            errorMessage = "Failed to add item";
            showError = true;
            setTimeout(() => showError = false, 3000);
        }
    }

    async function updateItemStock(inventoryId: number) {
        try {
            const result = await ApiService.put('update-item-stock', {
                inventory_id: inventoryId,
                stock_quantity: stockQuantity,
                unit_of_measure: unitOfMeasure
            });

            if (result.status) {
                editingItem = null;
                await fetchItems();
                showAlert = true;
                alertType = 'success';
                alertMessage = 'Stock updated successfully';
            } else {
                showAlert = true;
                alertType = 'error';
                alertMessage = result.message || 'Failed to update stock';
            }
            setTimeout(() => showAlert = false, 3000);
        } catch (error) {
            console.error('Error:', error);
            showAlert = true;
            alertType = 'error';
            alertMessage = 'Failed to update stock';
            setTimeout(() => showAlert = false, 3000);
        }
    }

    let showAlert = false;
    let alertType: AlertType = 'success';
    let alertMessage = '';
    let showProductsModal = false;
    let selectedItem: any = null;
    let productsUsingIngredient: any[] = [];

    async function showProductsUsingIngredient(item: InventoryItem) {
        try {
            // console.log('Fetching products for item:', item);
            
            const response = await fetch(`${import.meta.env.VITE_API_URL}/get-products-using-ingredient?inventory_id=${item.inventory_id}`);
            const result = await response.json();
            
            // console.log('Raw API Response:', result);

            if (result.status && Array.isArray(result.data)) {
                productsUsingIngredient = result.data;
                selectedItem = item;
                showProductsModal = true;
            } else {
                console.error('API error:', result.message);
                alertMessage = result.message || "Failed to fetch products";
                alertType = 'error';
                showAlert = true;
            }
        } catch (error) {
            console.error('Network error:', error);
            alertMessage = "Failed to fetch products";
            alertType = 'error';
            showAlert = true;
        }
    }

    import { onMount } from 'svelte';
    onMount(fetchItems);

    async function deleteItemStock(inventoryId: number) {
        if (confirm('Are you sure you want to delete this item?')) {
            try {
                const result = await ApiService.delete<{
                    status: boolean;
                    message: string;
                    products?: any[];
                }>('delete-item-stock', { 
                    inventory_id: inventoryId 
                });
                
                if (result.status) {
                    await fetchItems();
                    showAlert = true;
                    alertType = 'success';
                    alertMessage = result.message;
                } else {
                    if (result.products && result.products.length > 0) {
                        productsUsingIngredient = result.products;
                        selectedItem = items.find(i => i.inventory_id === inventoryId);
                        showProductsModal = true;
                    } else {
                        showAlert = true;
                        alertType = 'error';
                        alertMessage = result.message || 'Failed to delete item';
                    }
                }
            } catch (error) {
                console.error('Error deleting item:', error);
                showAlert = true;
                alertType = 'error';
                alertMessage = 'Failed to delete item';
                setTimeout(() => showAlert = false, 3000);
            }
        }
    }
</script>

{#if showError}
    <div class="alert-container">
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline">{errorMessage}</span>
        </div>
    </div>
{/if}

{#if showAlert}
    <div class="alert-container">
        <div class={`px-4 py-3 rounded relative ${
            alertType === 'success' 
                ? 'bg-green-100 border border-green-400 text-green-700' 
                : 'bg-red-100 border border-red-400 text-red-700'
        }`} role="alert">
            <span class="block sm:inline">{alertMessage}</span>
        </div>
    </div>
{/if}

<div class="layout">
    <Header {y} {innerHeight} />
    <div class="content">
        <main>
            <div class="product-details">
                <div class="details">
                    <input
                        type="text"
                        bind:value={itemName}
                        placeholder="Product name"
                        required
                        class="input-field"
                    />
                    <div class="quantity-input">
                        <input
                            type="number"
                            bind:value={stockQuantity}
                            min="0"
                            required
                            placeholder="Quantity"
                            class="input-field"
                        />
                        <select bind:value={unitOfMeasure} class="input-field">
                            <option value="pieces">Pieces</option>
                            <option value="grams">Grams</option>
                            <option value="kilograms">Kilograms</option>
                            <option value="milliliters">Milliliters</option>
                            <option value="liters">Liters</option>
                            <option value="cups">Cups</option>
                            <option value="tablespoons">Tablespoons</option>
                            <option value="teaspoons">Teaspoons</option>
                        </select>
                    </div>
                    <button on:click={addItemStock}>Add Item</button>
                </div>
            </div>

            <div class="search-bar">
                <input 
                    type="text" 
                    bind:value={searchQuery}
                    placeholder="Search items..." 
                    class="search-input"
                />
            </div>

            <div class="stock-table">
                <table>
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Stock Quantity</th>
                            <th>Unit of Measure</th>
                            <th>Last Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each filteredItems as item}
                            <tr>
                                <td>{item.item_name}</td>
                                <td>{item.stock_quantity}</td>
                                <td>{item.unit_of_measure}</td>
                                <td>{item.last_updated}</td>
                                <td class="flex gap-2">
                                    <button on:click={() => startEdit(item)}>Edit</button>
                                    <button on:click={() => deleteItemStock(item.inventory_id)}>Delete</button>
                                    <button 
                                        on:click={() => showProductsUsingIngredient(item)}
                                        class="bg-[#d4a373] text-white px-3 py-1 rounded hover:bg-[#c49363] text-sm"
                                    >
                                        Used In Products
                                    </button>
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</div>

{#if isEditModalOpen && editingItem}
    <div class="modal-backdrop">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Item Stock</h2>
                <button class="close-btn" on:click={closeEditModal}>&times;</button>
            </div>
            <div class="modal-body">
                <input 
                    type="text" 
                    placeholder="Product name" 
                    bind:value={itemName} 
                    class="w-full p-2 border rounded-md"
                />
                <div class="quantity-input">
                    <input 
                        type="number" 
                        placeholder="Quantity" 
                        bind:value={stockQuantity} 
                        class="flex-1 p-2 border rounded-md"
                    />
                    <select 
                        bind:value={unitOfMeasure}
                        class="p-2 border rounded-md min-w-[120px]"
                    >
                        <option value="pieces">Pieces</option>
                        <option value="grams">Grams</option>
                        <option value="kilograms">Kilograms</option>
                        <option value="milliliters">Milliliters</option>
                        <option value="liters">Liters</option>
                        <option value="cups">Cups</option>
                        <option value="tablespoons">Tablespoons</option>
                        <option value="teaspoons">Teaspoons</option>
                    </select>
                </div>
                <div class="modal-actions">
                    <button class="update-btn" on:click={() => {
                        updateItemStock(editingItem!);
                        closeEditModal();
                    }}>Update</button>
                    <button class="cancel-btn" on:click={closeEditModal}>Cancel</button>
                </div>
            </div>
        </div>
    </div>
{/if}

{#if showProductsModal && selectedItem}
    <div class="modal-overlay" on:click|self={() => showProductsModal = false}>
        <div class="modal-content">
            <h2 class="text-xl font-bold mb-4">
                Products Using {selectedItem.item_name}
            </h2>
            
            {#if productsUsingIngredient.length === 0}
                <p class="text-gray-500">No products are using this ingredient.</p>
            {:else}
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr>
                                <th class="text-left py-2">Product Name</th>
                                <th class="text-left py-2">Category</th>
                                <th class="text-right py-2">Quantity Needed</th>
                                <th class="text-left py-2">Unit</th>
                            </tr>
                        </thead>
                        <tbody>
                            {#each productsUsingIngredient as product}
                                <tr class="border-t">
                                    <td class="py-2">{product.product_name}</td>
                                    <td class="py-2">{product.category}</td>
                                    <td class="py-2 text-right">{product.quantity_needed}</td>
                                    <td class="py-2">{product.unit_of_measure}</td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
            {/if}
            
            <button 
                class="mt-4 w-full bg-[#d4a373] text-white py-2 px-4 rounded hover:bg-[#c49363]"
                on:click={() => showProductsModal = false}
            >
                Close
            </button>
        </div>
    </div>
{/if}

<style>
    .layout {
        display: flex;
        height: 100vh;
        -webkit-box-flex: 1;
        -webkit-flex: 1;
        display: -webkit-box;
        display: -webkit-flex;
        -webkit-overflow-scrolling: touch;
    }
    .content {
        display: flex;
        flex-direction: column;
        flex-grow: 1;
        padding: 20px;
        margin-top: 4rem;
        justify-content: flex-start;
        overflow-x: hidden;
        overflow-y: auto;
        background: #faedcd;
        max-width: 1200px;
        margin-left: auto;
        margin-right: auto;
        scrollbar-width: none;
        scroll-behavior: smooth;
        scrollbar-color:   #d4a373 #faedcd;
    }
    .content::-webkit-scrollbar {
        width: 2px;
        background-color: #faedcd;
    }
    .content::-webkit-scrollbar-track {
        background: #faedcd;
        border-radius: 4px;
    }
    .content::-webkit-scrollbar-thumb {
        background: #d4a373;
        border-radius: 4px;
        border: 1px solid #faedcd;
    }
    .content::-webkit-scrollbar-thumb:hover {
        background: #c49262;
    }
    .product-details {
        margin: 20px;
        text-align: center;
    }
    .stock-table {
        margin: 20px;
        overflow-x: auto;
        background: #faedcd;
        padding: 1.5rem;
        border-radius: 0.5rem;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .side-nav {
        margin-top: 50px;
    }
    table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        background: #fefae0;
        border-radius: 0.5rem;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    th, td {
        text-align: center;
        border-color: #e9edc9;
        padding: 0.875rem;
        transition: background-color 0.2s ease;
    }
    th {
        background: #d4a373;
        color: white;
        text-align: center;
        border: none;
        padding: 1rem;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.875rem;
        letter-spacing: 0.05em;
    }
    button {
        padding: 0.625rem 1rem;
        border-radius: 0.5rem;
        font-weight: 500;
        border: none;
        cursor: pointer;
        transition: all 0.2s ease;
        background: #d4a373;
        color: white;
        min-width: 80px;
        font-size: 0.875rem;
    }
    
    .details {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        padding: 1.5rem;
        width: 100%;
        background: #faedcd;
        border-radius: 0.5rem;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        margin-bottom: 2rem;
    }
    
    .input-field,
    .quantity-input input,
    .quantity-input select {
        padding: 0.75rem;
        border: 1px solid #ddd;
        border-radius: 0.5rem;
        font-size: 1rem;
        min-height: 2.5rem;
        width: 100%;
        background: #fefae0;
        border: 1px solid #d4a373;
    }
    
    .quantity-input {
        display: flex;
        gap: 0.5rem;
        width: 100%;
    }
    
    button {
        padding: 0.75rem 1rem;
        border-radius: 0.5rem;
        font-weight: 500;
        min-height: 2.5rem;
        width: auto;
        white-space: nowrap;
    }
    
    .search-bar {
        margin: 1rem auto 2rem;
        text-align: center;
        max-width: 500px;
        width: 100%;
    }
    
    .search-input {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid #d4a373;
        border-radius: 8px;
        font-size: 16px;
        background: #fefae0;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    
    .search-input:focus {
        outline: none;
        border-color: #c49262;
        box-shadow: 0 2px 8px rgba(212, 163, 115, 0.2);
    }
    
    .modal-backdrop {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 1000;
        padding: 1rem;
    }
    
    .modal-content {
        background: white;
        padding: 1.5rem;
        border-radius: 0.5rem;
        width: 95%;
        max-width: 500px;
        max-height: 85vh;
        overflow-y: auto;
        position: relative;
        background: #fefae0;
    }
    
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    
    .close-btn {
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
    }
    
    .modal-body {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    
    .modal-actions {
        display: flex;
        gap: 10px;
        margin-top: 20px;
    }
    
    .update-btn {
        background: #4CAF50;
        color: white;
    }
    
    .cancel-btn {
        background: #f44336;
        color: white;
    }
    
    .details button {
        background-color: #4CAF50;
        color: white;
    }
    
    .details button:hover {
        background-color: #45a049;
    }
    
    td button:first-child {
        background-color: #2196F3;
        color: white;
    }
    
    td button:first-child:hover {
        background-color: #1976D2;
    }
    
    td button:last-child {
        background-color: #f44336;
        color: white;
    }
    
    td button:last-child:hover {
        background-color: #d32f2f;
    }
    
    .quantity-input {
        display: flex;
        gap: 5px;
        display: -webkit-box;
        display: -webkit-flex;
        -webkit-flex-wrap: wrap;
    }
    
    .quantity-input input,
    .quantity-input select {
        padding: 5px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    
    .quantity-input select {
        min-width: 120px;
    }
    
    .alert-container {
        position: fixed;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 1000;
        width: 90%;
        max-width: 500px;
    }
    
    .input-field {
        /* Add your existing input styles */
    }
    
    .input-field:invalid {
        border-color: red;
    }
    
    .product-list {
        margin: 1rem 0;
        padding: 0;
        list-style: none;
    }
    
    .product-list li {
        padding: 0.5rem;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .close-btn {
        margin-top: 1rem;
        padding: 0.5rem 1rem;
        background-color: #ef4444;
        color: white;
        border-radius: 0.25rem;
    }
    
    .close-btn:hover {
        background-color: #dc2626;
    }
    
    .stock-table::-webkit-scrollbar {
        width: 1px;
        height: 1px;
        background-color: #faedcd;
    }
    
    .stock-table::-webkit-scrollbar-track {
        background: #faedcd;
        border-radius: 3px;
    }
    
    .stock-table::-webkit-scrollbar-thumb {
        background: #d4a373;
        border-radius: 3px;
        border: 1px solid #faedcd;
    }
    
    @media screen and (max-width: 768px) {
        .content {
            padding: 10px;
            overflow-x: hidden;
        }
    
        table {
            display: block;
            overflow-x: auto;
            white-space: nowrap;
            width: 100%;
        }
    
        .modal-content {
            width: 95%;
            max-height: 90vh;
            overflow-y: auto;
            overflow-x: hidden;
        }
    }
    
    /* Add these media queries to handle mobile responsiveness */
    @media screen and (max-width: 768px) {
        .details {
            flex-direction: column;
            gap: 10px;
            width: 100%;
            padding: 0 10px;
        }
    
        .quantity-input {
            flex-direction: column;
            width: 100%;
        }
    
        .quantity-input input,
        .quantity-input select,
        .details input,
        .details button {
            width: 100%;
            min-height: 40px;
        }
    
        .stock-table {
            margin: 10px 0;
            padding: 1rem;
            background-color: #faedcd;
        }
    
        table {
            min-width: 600px; /* Minimum width to ensure content is readable */
        }
    
        td {
            white-space: nowrap;
            padding: 8px 4px;
        }
    
        td.flex {
            display: flex;
            flex-wrap: nowrap;
            gap: 4px;
        }
    
        td button {
            padding: 6px 8px;
            font-size: 12px;
            white-space: nowrap;
            min-width: auto;
        }
    }
    
    /* Add these to your existing modal styles for better mobile display */
    @media screen and (max-width: 768px) {
        .modal-content {
            width: 95%;
            padding: 15px;
            margin: 10px;
        }
    
        .modal-body .quantity-input {
            flex-direction: column;
            width: 100%;
        }
    
        .modal-body input,
        .modal-body select {
            width: 100%;
            min-height: 40px;
        }
    
        .modal-actions {
            flex-direction: column;
            gap: 8px;
        }
    
        .modal-actions button {
            width: 100%;
        }
    }
    
    /* Responsive adjustments */
    @media screen and (min-width: 768px) {
        .details {
            flex-wrap: nowrap;
            align-items: center;
        }
    
        .input-field {
            flex: 2;
        }
    
        .quantity-input {
            flex: 2;
            flex-direction: row;
        }
    
        .quantity-input input {
            flex: 1;
        }
    
        .quantity-input select {
            width: auto;
            min-width: 120px;
        }
    
        button {
            flex: 1;
            max-width: 150px;
        }
    }
    
    /* Table responsive styles */
    .stock-table {
        overflow-x: auto;
        margin: 1rem;
        border-radius: 0.5rem;
        background: #faedcd;
    }
    
    table {
        min-width: 100%;
    }
    
    @media screen and (max-width: 767px) {
        td.flex {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
    
        td.flex button {
            width: 100%;
            margin: 0;
        }
    }
    
    td.flex {
        display: flex;
        gap: 4px;
        justify-content: center;
        align-items: center;
        flex-wrap: wrap;
    }
    
    td.flex button {
        font-size: 0.75rem; /* 12px */
        padding: 0.375rem 0.5rem; /* 6px 8px */
        min-width: auto;
        flex: 1;
        white-space: nowrap;
        max-width: fit-content;
    }
    
    @media screen and (max-width: 768px) {
        .details {
            flex-direction: column;
            gap: 10px;
            width: 100%; /* Changed from 60% to 100% */
            padding: 0 10px;
        }
    
        td.flex {
            padding: 4px;
            min-width: 120px;
        }
    
        td.flex button {
            font-size: 0.7rem;
            padding: 4px 6px;
            margin: 2px;
        }
    
        /* Adjust the "Used In Products" button specifically */
        td.flex button:last-child {
            font-size: 0.7rem;
            padding: 4px 6px;
            white-space: normal;
            text-align: center;
            line-height: 1;
        }
    
        table {
            min-width: 600px;
            font-size: 0.875rem; /* Slightly smaller font for table content */
        }
    
        th, td {
            padding: 6px 4px;
        }
    }
    
    /* Add specific styles for the action buttons container */
    .action-buttons {
        display: flex;
        gap: 4px;
        flex-wrap: wrap;
        justify-content: center;
    }
    
    /* Adjust the main add item button for mobile */
    @media screen and (max-width: 768px) {
        .details button {
            padding: 8px 12px;
            font-size: 0.875rem;
            height: auto;
            width: auto;
        }
    
        .input-field,
        .quantity-input input,
        .quantity-input select {
            padding: 8px;
            font-size: 0.875rem;
        }
    }
    
    /* Add smooth scrolling for modal content */
    .modal-content {
        scrollbar-width: thin;
        scrollbar-color: #d4a373 #faedcd;
    }
    
    .modal-content::-webkit-scrollbar {
        width: 1px;
    }
    
    .modal-content::-webkit-scrollbar-track {
        background: #faedcd;
        border-radius: 3px;
    }
    
    .modal-content::-webkit-scrollbar-thumb {
        background: #d4a373;
        border-radius: 3px;
        border: 1px solid #faedcd;
    }
    
    @media screen and (max-width: 768px) {
        .modal-content button {
            width: 22%;
            padding: 6px;
            font-size: 1rem;
            margin-top: 1rem;
            text-align: center;
        }

        .modal-content {
            width: 95%;
            max-width: none;
            margin: 10px;
            padding: 1rem;
        }
    }
    
    /* Add hover effect for scrollbar thumb */
    .stock-table::-webkit-scrollbar-thumb:hover,
    .modal-content::-webkit-scrollbar-thumb:hover {
        background: #c49262;
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
        max-width: 600px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
    }
</style>