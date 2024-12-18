<script lang="ts">
    import { onMount } from 'svelte';
    import { ApiService } from './services/api';
    
    type Ingredient = {
      inventory_id: number;
      item_name: string;
      stock_quantity: number;
      unit_of_measure: string;
    };
  
    type Recipe = {
      product_ingredient_id?: number;
      inventory_id: number;
      ingredient_name: string;
      quantity_needed: number;
      unit_of_measure: string;
      stock_quantity: number;
    };
  
    export let productId: number;
    export let productName: string;
  
    let ingredients: Ingredient[] = [];
    let productRecipe: Recipe[] = [];
    let newIngredient = {
      inventory_id: 0,
      quantity_needed: 0,
    };
  
    let editingIngredient: number | null = null;
    let editQuantity: number = 0;
  
    let searchIngredient = '';
    let searchRecipe = '';
  
    $: filteredIngredients = ingredients.filter(ingredient => 
      ingredient.item_name.toLowerCase().includes(searchIngredient.toLowerCase())
    );
  
    $: filteredRecipe = productRecipe.filter(ingredient => 
      ingredient.ingredient_name.toLowerCase().includes(searchRecipe.toLowerCase())
    );
  
    onMount(async () => {
      await Promise.all([
        fetchIngredients(),
        fetchProductRecipe()
      ]);
    });
  
    async function fetchIngredients() {
      try {
        const result = await ApiService.get<{status: boolean; data: Ingredient[]}>('get-items');
        if (result.status) {
          ingredients = result.data;
        }
      } catch (error) {
        console.error('Error fetching ingredients:', error);
      }
    }
  
    async function fetchProductRecipe() {
      try {
        const result = await ApiService.get<{status: boolean; data: Recipe[]}>('get-product-ingredients', {
          product_id: productId.toString()
        });
        
        if (result.status) {
          productRecipe = result.data;
        }
      } catch (error) {
        console.error('Error fetching recipe:', error);
      }
    }
  
    async function addIngredient() {
      if (!newIngredient.inventory_id || !newIngredient.quantity_needed) {
        alert('Please select an ingredient and specify quantity');
        return;
      }
  
      try {
        const requestData = {
          product_id: productId,
          inventory_id: newIngredient.inventory_id,
          quantity_needed: newIngredient.quantity_needed,
          unit_of_measure: selectedIngredient?.unit_of_measure || 'pieces'
        };
  
        const result = await ApiService.post<{
          status: boolean;
          message: string;
          data?: any;
        }>('add-product-ingredient', requestData);
  
        if (result && result.status) {
          await fetchProductRecipe();
          newIngredient = {
            inventory_id: 0,
            quantity_needed: 0,
          };
        } else {
          alert(result?.message || 'Failed to add ingredient');
        }
      } catch (error) {
        console.error('Error:', error);
        alert('Failed to add ingredient');
      }
    }
  
    async function removeIngredient(ingredientId: number) {
      if (!confirm('Are you sure you want to remove this ingredient?')) return;
  
      try {
        const result = await ApiService.delete<{
          status: boolean;
          message: string;
          data?: any;
        }>('delete-product-ingredient', {
          product_ingredient_id: ingredientId
        });
  
        if (result && result.status) {
          await fetchProductRecipe();
        } else {
          alert(result?.message || 'Failed to remove ingredient');
        }
      } catch (error) {
        console.error('Error:', error);
        alert('Failed to remove ingredient');
      }
    }
  
    $: selectedIngredient = ingredients.find(i => i.inventory_id === newIngredient.inventory_id);
  </script>
  
  <div class="recipe-manager">
    <h2 class="text-xl font-bold mb-4">Recipe Management - {productName}</h2>
  
    <div class="recipe-container">
      <!-- Add Ingredient Section -->
      <div class="add-ingredient-section">
        <h3 class="text-lg font-semibold mb-2">Add Ingredient</h3>
        <div class="grid grid-cols-1 gap-2">
          <input 
            type="text"
            bind:value={searchIngredient}
            placeholder="Search ingredients..."
            class="p-2 border rounded-md w-full"
          />
          <select 
            bind:value={newIngredient.inventory_id}
            class="p-2 border rounded-md w-full"
          >
            <option value={0}>Select Ingredient</option>
            {#each filteredIngredients as ingredient}
              <option value={ingredient.inventory_id}>
                {ingredient.item_name} ({ingredient.stock_quantity} {ingredient.unit_of_measure})
              </option>
            {/each}
          </select>
          
          <div class="flex gap-2">
            <input 
              type="number" 
              bind:value={newIngredient.quantity_needed}
              placeholder="Quantity needed"
              class="p-2 border rounded-md w-full"
              min="0"
              step="0.01"
            />
            <button 
              on:click={addIngredient}
              class="bg-green-500 text-white px-4 py-2 rounded-md whitespace-nowrap"
            >
              Add
            </button>
          </div>
        </div>
      </div>
  
      <!-- Recipe List Section -->
      <div class="recipe-list-section">
        <div class="sticky top-0 bg-white py-2 space-y-2">
          <h3 class="text-lg font-semibold">Current Recipe</h3>
          <input 
            type="text"
            bind:value={searchRecipe}
            placeholder="Search recipe ingredients..."
            class="p-2 border rounded-md w-full"
          />
        </div>
        <div class="recipe-table-container">
          {#if filteredRecipe.length === 0}
            {#if searchRecipe && productRecipe.length > 0}
              <p class="text-gray-500">No matching ingredients found</p>
            {:else}
              <p class="text-gray-500">No ingredients added yet</p>
            {/if}
          {:else}
            <table class="w-full">
              <thead class="sticky top-0 bg-white">
                <tr>
                  <th class="text-left py-2">Ingredient</th>
                  <th class="text-left py-2">Qty</th>
                  <th class="text-left py-2">Unit</th>
                  <th class="text-left py-2">Stock</th>
                  <th class="text-center py-2">Actions</th>
                </tr>
              </thead>
              <tbody>
                {#each filteredRecipe as ingredient}
                  <tr class="border-t">
                    <td class="py-2">{ingredient.ingredient_name}</td>
                    <td class="py-2">
                      {ingredient.quantity_needed}
                    </td>
                    <td class="py-2">{ingredient.unit_of_measure}</td>
                    <td class="py-2">{ingredient.stock_quantity}</td>
                    <td class="py-2 flex justify-center gap-2">
                      <button
                        on:click={() => removeIngredient(ingredient.product_ingredient_id)}
                        class="text-red-500 hover:text-red-700"
                      >
                        Remove
                      </button>
                    </td>
                  </tr>
                {/each}
              </tbody>
            </table>
          {/if}
        </div>
      </div>
    </div>
  </div>
  
  <style>
    .recipe-manager {
      background: white;
      border-radius: 8px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      height: 100%;
      max-height: 80vh;
      display: flex;
      flex-direction: column;
    }
  
    .recipe-container {
      display: grid;
      grid-template-rows: auto 1fr;
      gap: 1rem;
      padding: 1rem;
      height: 100%;
      overflow: hidden;
    }
  
    .add-ingredient-section {
      background: #f8f9fa;
      padding: 1rem;
      border-radius: 6px;
    }
  
    .recipe-list-section {
      display: flex;
      flex-direction: column;
      min-height: 0;
    }
  
    .recipe-table-container {
      overflow-y: auto;
      border: 1px solid #eee;
      border-radius: 4px;
      padding: 0.5rem;
      flex: 1;
    }
  
    table {
      border-collapse: collapse;
      width: 100%;
    }
  
    th {
      background: white;
      z-index: 10;
    }
  
    tr:hover {
      background-color: #f8f9fa;
    }
  
    @media (max-width: 640px) {
      .recipe-manager {
        max-height: 90vh;
      }
  
      .recipe-container {
        padding: 0.5rem;
        gap: 0.5rem;
      }
  
      table {
        font-size: 0.875rem;
      }
  
      th, td {
        padding: 0.25rem;
      }
    }
  </style>