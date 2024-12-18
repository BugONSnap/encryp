<script lang="ts">
  import { onMount } from 'svelte';
  import Header from '$lib/header.svelte';
  import Alert from '$lib/components/Alert.svelte';
  import RecipeManager from '$lib/RecipeManager.svelte';
  import { ApiService } from '$lib/services/api.js';
  import type { MenuItem } from '$lib/types.js';

  let y = 0;
  let innerHeight = 0;

  // Define types for items and newItem
  type Item = { product_id: number; name: string; image: string | File; price: string; category: string; size?: string; imageUrl?: string };
  type NewItem = { name: string; image: File; price: string; category: string; size?: string };

  let items: MenuItem[] = [];
  const categories = ['Pizza', 'Burger & Fries', 'Nachos', 'Drinks', 'Chocolate Series', 'Cheesecake Series']; // Add your categories here
  const sizeOptions = {
    'Pizza': ['Small', 'Standard', 'Large'],
    'Burger & Fries': ['Small', 'Standard', 'Large'],
    'Nachos': ['Small', 'Standard', 'Large'],
    'Drinks': ['Small', 'Standard', 'Large'],
    'Chocolate Series': ['Small', 'Standard', 'Large'],
    'Cheesecake Series': ['Small', 'Standard', 'Large']
  };
  let newItem: MenuItem = { name: '', image: new File([], ''), price: '', category: categories[0], size: '', product_id: 0 };
  let editItem: MenuItem | null = null; // Change the type declaration to allow null
  let isEditModalOpen = false;
  let searchQuery = '';
  let showAlert = false;
  let alertMessage = '';
  let alertType: 'success' | 'warning' | 'error' = 'warning';
  let showRecipeManager = false;
  let selectedProduct = null;

  onMount(async () => {
    await fetchItems();
  });

  async function fetchItems() {
    try {
      const result = await ApiService.get<MenuItem[]>('get-menu-items');
      if (result) {
        items = result.map(item => ({
          ...item,
          product_id: item.product_id,
          name: item.name,
          price: Number(item.price),
          category: item.category,
          size: item.size || 'Standard',
          image: item.image,
          imageUrl: item.image ? `/POS/uploads/${item.image}` : '/placeholder.jpg'
        }));
      }
    } catch (error) {
      console.error('Error fetching items:', error);
      showAlert = true;
      alertType = 'error';
      alertMessage = "Failed to fetch menu items";
      setTimeout(() => showAlert = false, 3000);
    }
  }

  async function addMenuItem() {
    if (!newItem.name.trim() || !newItem.price || !newItem.category) {
        showAlert = true;
        alertType = 'error';
        alertMessage = "Please fill in all required fields";
        setTimeout(() => showAlert = false, 3000);
        return;
    }

    try {
        let imageFilename = '';
        
        // Handle image upload if there is one
        if (newItem.image instanceof File && newItem.image.size > 0) {
            const formData = new FormData();
            formData.append('image', newItem.image);
            
            const uploadResult = await ApiService.post<{
                status: boolean;
                filename: string;
                message?: string;
            }>('upload-image', formData);

            if (!uploadResult.status) {
                throw new Error(uploadResult.message || 'Failed to upload image');
            }
            imageFilename = uploadResult.filename;
        }

        // Send the menu item data
        const itemData = {
            name: newItem.name,
            price: parseFloat(newItem.price.toString()),
            category: newItem.category,
            size: newItem.size || 'Standard',
            image: imageFilename
        };

        const result = await ApiService.post<ApiResponse<{product_id: number}>>('add-menu-item', itemData);
        
        showAlert = true;
        if (result.status) {
            alertMessage = "Item added successfully";
            alertType = 'success';
            await fetchItems();
            
            // Reset form
            newItem = { 
                name: '', 
                image: new File([], ''), 
                price: '', 
                category: categories[0], 
                size: '', 
                product_id: 0 
            };
            const fileInput = document.querySelector('input[type="file"]') as HTMLInputElement;
            if (fileInput) fileInput.value = '';
        } else {
            alertType = 'error';
            alertMessage = result.message || "Failed to add item";
        }
        
        setTimeout(() => showAlert = false, 3000);
        
    } catch (error) {
        console.error('Error:', error);
        showAlert = true;
        alertType = 'error';
        alertMessage = "Error adding item: " + (error instanceof Error ? error.message : 'Network error');
        setTimeout(() => showAlert = false, 3000);
    }
  }

  async function updateMenuItem() {
    if (!editItem) return;

    try {
        const updateData = {
            product_id: editItem.product_id,
            name: editItem.name,
            price: editItem.price,
            category: editItem.category,
            size: editItem.size || 'Standard'
        };

        // If there's a new image, upload it first
        if (editItem.image instanceof File) {
            const formData = new FormData();
            formData.append('image', editItem.image);
            
            const imageResult = await ApiService.post<{
                status: boolean;
                filename: string;
                message?: string;
            }>('upload-image', formData);
            
            if (imageResult.status) {
                updateData.image = imageResult.filename;
            } else {
                throw new Error(imageResult.message || 'Failed to upload image');
            }
        }

        // Send the update request
        const result = await ApiService.put<ApiResponse<void>>('update-menu-item', updateData);
        
        showAlert = true;
        if (result.status) {
            alertMessage = "Menu item updated successfully";
            alertType = 'success';
            await fetchItems();
            isEditModalOpen = false;
        } else {
            alertType = 'error';
            if (result.duplicate) {
                alertMessage = "A similar product already exists";
            } else {
                alertMessage = result.message || "Failed to update item";
            }
        }
        
        setTimeout(() => showAlert = false, 3000);
    } catch (error) {
        alertMessage = "Error updating item: Network error";
        alertType = 'error';
        showAlert = true;
        setTimeout(() => showAlert = false, 3000);
        console.error('Error:', error);
    }
  }

  async function deleteMenuItem(itemId: number) {
    if (!confirm('Are you sure you want to delete this item?')) {
        return;
    }

    try {
        const result = await ApiService.delete<ApiResponse<void>>('delete-menu-item', {
            product_id: itemId
        });
        
        if (result.status) {
            items = items.filter(item => item.product_id !== itemId);
            alertMessage = "Item deleted successfully";
            alertType = 'success';
        } else {
            alertMessage = result.message || "Failed to delete item";
            alertType = 'error';
        }
        
        showAlert = true;
        setTimeout(() => showAlert = false, 3000);
    } catch (error) {
        console.error('Error:', error);
        alertMessage = "Error deleting item";
        alertType = 'error';
        showAlert = true;
        setTimeout(() => showAlert = false, 3000);
    }
  }

  function startEdit(item: MenuItem) {
    editItem = { 
        ...item,
        size: item.size === 'base-size' ? 'Standard' : (item.size || 'Standard')
    };
    isEditModalOpen = true;
  }

  function closeEditModal() {
    isEditModalOpen = false;
    // editItem = null;
  }

  function filterItems(items: Item[], query: string) {
    const lowerQuery = query.toLowerCase();
    return items.filter(item => 
      item.name.toLowerCase().includes(lowerQuery) || 
      item.category.toLowerCase().includes(lowerQuery)
    );
  }

  // Add this function to handle category changes
  function handleCategoryChange(newCategory: string) {
    editItem.category = newCategory;
    // Set Standard as default size for all categories
    editItem.size = 'Standard';
  }

  async function openRecipeManager(product: MenuItem) {
    try {
        // Encrypt and fetch product ingredients
        const result = await ApiService.get<{status: boolean; data: any[]}>('get-product-ingredients', {
            product_id: product.product_id.toString()
        });

        if (result.status) {
            selectedProduct = {
                ...product,
                ingredients: result.data
            };
            showRecipeManager = true;
        } else {
            alertMessage = "Failed to load recipe ingredients";
            alertType = 'error';
            showAlert = true;
            setTimeout(() => showAlert = false, 3000);
        }
    } catch (error) {
        console.error('Error loading recipe:', error);
        alertMessage = "Error loading recipe";
        alertType = 'error';
        showAlert = true;
        setTimeout(() => showAlert = false, 3000);
    }
  }
</script>

<Header {y} {innerHeight} />

{#if showAlert}
  <div class="fixed-alert">
    <Alert type={alertType} message={alertMessage} />
  </div>
{/if}

<div class="settings-container">
  <div class="settings-content">
    <h1 class="text-2xl font-bold mb-6">Manage Menu Items</h1>

    <div class="form-section bg-white p-6 rounded-lg shadow-md mb-6">
      <form on:submit|preventDefault={addMenuItem} class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <input 
            type="text" 
            bind:value={newItem.name} 
            placeholder="Item Name" 
            class="p-2 border rounded-md w-full"
            required 
          />
          <input 
            type="file" 
            accept="image/*" 
            on:change={e => {
              const input = e.target as HTMLInputElement;
              const files = input.files;
              if (files && files.length > 0) {
                newItem.image = files[0] as File;
              }
            }} 
            class="p-2 border rounded-md w-full"
            required 
          />
          <input 
            type="number" 
            bind:value={newItem.price} 
            placeholder="Price" 
            class="p-2 border rounded-md w-full"
            required 
            min="0"
            step="0.01"
          />
          <select 
            bind:value={newItem.category} 
            class="p-2 border rounded-md w-full"
            required 
          >
            {#each categories as category}
              <option value={category}>{category}</option>
            {/each}
          </select>
          <select 
            bind:value={newItem.size} 
            class="p-2 border rounded-md w-full"
            required
          >
            <option value="" disabled>Select size</option>
            {#each sizeOptions[newItem.category] as size}
              <option value={size} selected={size === 'Standard'}>{size}</option>
            {/each}
          </select>
        </div>
        <button 
          type="submit" 
          class="bg-[#ccd5ae] text-white px-4 py-2 rounded-md w-full hover:bg-[#bcc39e]"
          disabled={!newItem.name || !newItem.price || !newItem.category}
        >
          Add Item
        </button>
      </form>
    </div>

    <div class="mb-6">
      <input 
        type="text" 
        bind:value={searchQuery}
        placeholder="Search items by name or category..." 
        class="p-2 border rounded-md w-full"
      />
    </div>

    {#if isEditModalOpen && editItem}
      <div class="modal-backdrop">
        <div class="modal-content">
          <div class="modal-header">
            <h2 class="text-xl font-bold">Edit Item</h2>
            <button class="close-btn" on:click={closeEditModal}>&times;</button>
          </div>
          <form on:submit|preventDefault={updateMenuItem} class="space-y-4">
            <div class="grid grid-cols-1 gap-4">
              <div class="current-image">
                <img 
                  src={editItem.image instanceof File 
                    ? URL.createObjectURL(editItem.image) 
                    : `https://formalytics.me/uploads/${editItem.image}`}
                  alt={editItem.name}
                  class="w-full h-48 object-cover rounded-md mb-2"
                  on:error={(e) => (e.currentTarget as HTMLImageElement).src = '/images/logo.png'}
                />
                <input 
                  type="file" 
                  accept="image/*" 
                  on:change={e => {
                    const input = e.target as HTMLInputElement;
                    const files = input.files;
                    if (files && files.length > 0) {
                      editItem.image = files[0] as File;
                    }
                  }} 
                  class="p-2 border rounded-md w-full"
                />
              </div>
              <input type="text" bind:value={editItem.name} placeholder="Item Name" class="p-2 border rounded-md" required />
              <input type="text" bind:value={editItem.price} placeholder="Price" class="p-2 border rounded-md" required />
              <select 
                bind:value={editItem.category} 
                on:change={(e) => handleCategoryChange(e.target.value)}
                class="p-2 border rounded-md"
                required 
              >
                {#each categories as category}
                  <option value={category}>{category}</option>
                {/each}
              </select>
              <select 
                bind:value={editItem.size} 
                class="p-2 border rounded-md"
                required
              >
                <option value="" disabled>Select size</option>
                {#each sizeOptions[editItem.category] as size}
                  <option value={size} selected={size === 'Standard'}>{size}</option>
                {/each}
              </select>
            </div>
            <div class="flex gap-2">
              <button type="submit" class="bg-[#d4a373] text-white px-4 py-2 rounded-md flex-1 hover:bg-[#bcc39e]">Update</button>
              <button type="button" on:click={closeEditModal} class="bg-gray-500 text-white px-4 py-2 rounded-md flex-1">Cancel</button>
            </div>
          </form>
        </div>
      </div>
    {/if}

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
      {#each filterItems(items, searchQuery) as item (item.product_id)}
        <div class="item-card">
          <img 
            src={item.image instanceof File ? 
                URL.createObjectURL(item.image) : 
                `https://formalytics.me/uploads/${item.image}`}
            alt={item.name} 
            class="item-image" 
            on:error={(e) => (e.currentTarget as HTMLImageElement).src = '/images/logo.png'}
          />
          <div class="item-details">
            <h3 class="font-bold">{item.name}</h3>
            <p>₱{item.price}</p>
            <p class="text-gray-600">{item.category}</p>
            {#if item.size && item.size !== 'base-size'}
              <p class="text-sm text-gray-500">Size: {item.size}</p>
            {/if}
            <div class="item-actions">
              <button on:click={() => startEdit(item)} class="edit-btn">Edit</button>
              <button on:click={() => deleteMenuItem(item.product_id)} class="delete-btn">Delete</button>
              <button
                on:click={() => openRecipeManager(item)}
                class="bg-[#d4a373] text-white px-2 py-1 rounded-md text-sm hover:bg-[#bcc39e]"
              >
                Manage Recipe
              </button>
            </div>
          </div>
        </div>
      {/each}
    </div>
  </div>
</div>

{#if showRecipeManager && selectedProduct}
  <div class="modal-backdrop">
    <div class="modal-content max-w-2xl">
      <RecipeManager 
        productId={selectedProduct.product_id}
        productName={selectedProduct.name}
      />
      <button
        class="mt-4 bg-gray-500 text-white px-4 py-2 rounded-md"
        on:click={() => showRecipeManager = false}
      >
        Close
      </button>
    </div>
  </div>
{/if}

<style>
  .settings-container {
    width: 100%;
    min-height: 100vh;
    padding: 1rem;
    background-color: #fefae0;
  }

  .settings-content {
    max-width: 1200px;
    margin: 5rem auto 2rem auto;
    padding: 1rem;
  }

  .item-card {
    background: #faedcd;
    border-radius: 8px;
    overflow: hidden;
    transition: transform 0.2s;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  }

  .item-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
  }

  .item-image {
    width: 100%;
    height: 200px;
    object-fit: cover;
  }

  .item-details {
    padding: 1rem;
  }

  .item-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
  }

  .edit-btn, .delete-btn {
    padding: 0.5rem 1rem;
    border-radius: 4px;
    border: none;
    cursor: pointer;
    flex: 1;
  }

  .edit-btn {
    background-color: #ccd5ae;
    color: white;
  }

  .delete-btn {
    background-color: #f44336;
    color: white;
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
    z-index: 9000;
  }

  .modal-content {
    background: #faedcd;
    padding: 2rem;
    border-radius: 8px;
    width: 90%;
    max-width: 500px;
    position: relative;
    z-index: 9001;
  }

  .modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
  }

  .close-btn {
    font-size: 1.5rem;
    background: none;
    border: none;
    cursor: pointer;
    padding: 0.5rem;
  }

  .close-btn:hover {
    color: #d4a373;
  }

  .current-image {
    border: 1px solid #d4a373;
    padding: 1rem;
    border-radius: 0.5rem;
  }

  .current-image img {
    border: 1px solid #d4a373;
  }

  .fixed-alert {
    position: fixed;
    top: 20px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 9999;
    width: 90%;
    max-width: 500px;
    animation: slideDown 0.3s ease-out;
  }

  @keyframes slideDown {
    from {
      transform: translate(-50%, -100%);
      opacity: 0;
    }
    to {
      transform: translate(-50%, 0);
      opacity: 1;
    }
  }

  .header {
    z-index: 8000;
  }
</style>
