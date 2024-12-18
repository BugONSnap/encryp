<script lang="ts">

  import Header from '$lib/header.svelte';
  import { onMount, onDestroy } from 'svelte';
  import { browser } from '$app/environment';
  import * as XLSX from 'xlsx';
  import { ApiService } from '$lib/services/api';
  import { userStore } from '$lib/auth';

  let y = 0;
  let innerWidth = 0;
  let innerHeight = 0;
  
  // Update the data storage variables
  let salesData = [];
  let chartData = {};  
  let dailyChartData = {};  // Add this new variable
  let filteredSalesData = [];
  
  // Filter states
  let searchQuery = '';
  let selectedDay = '';
  let selectedMonth = '';
  let selectedYear = '';
  
  // Unique years and months for filters
  $: years = [...new Set(salesData.map(sale => new Date(sale.order_date).getFullYear()))].sort((a, b) => b - a);
  $: months = [...Array(12)].map((_, i) => ({
    value: i + 1,
    label: new Date(2000, i, 1).toLocaleString('default', { month: 'long' })
  }));
  $: days = [...Array(31)].map((_, i) => i + 1);

  // Filter function
  $: {
    filteredSalesData = salesData.filter(sale => {
      const saleDate = new Date(sale.order_date);
      const matchesSearch = sale.product_name.toLowerCase().includes(searchQuery.toLowerCase());
      const matchesYear = !selectedYear || saleDate.getFullYear() === parseInt(selectedYear);
      const matchesMonth = !selectedMonth || saleDate.getMonth() + 1 === parseInt(selectedMonth);
      const matchesDay = !selectedDay || saleDate.getDate() === parseInt(selectedDay);
      
      return matchesSearch && matchesYear && matchesMonth && matchesDay;
    });
  }

  // Reset filters
  function resetFilters() {
    searchQuery = '';
    selectedDay = '';
    selectedMonth = '';
    selectedYear = '';
  }

  let ApexCharts;
  
  // Add these chart variable declarations
  let salesPerPeriodChart;
  let salesPerProductChart;
  let salesPerDayChart;
  let salesPerProductPerDayChart;
  
  // Function to prepare data for the sales per period chart
  function prepareSalesPerPeriodData(chartData) {
    if (!chartData || Object.keys(chartData).length === 0) {
        return { x: [], y: [] };
    }

    const salesByPeriod = {};
    
    Object.entries(chartData).forEach(([period, products]) => {
        salesByPeriod[period] = Object.values(products).reduce((sum, value) => sum + value, 0);
    });

    const sortedPeriods = Object.keys(salesByPeriod).sort();
    
    return {
        x: sortedPeriods,
        y: sortedPeriods.map(period => salesByPeriod[period])
    };
  }

  // Function to prepare data for the sales per product chart
  function prepareSalesPerProductData(data) {
    if (!data || !data.chartData || Object.keys(data.chartData).length === 0) {
        return [];
    }

    const products = new Set();
    const periods = new Set();
    const salesByProduct = {};
    
    Object.entries(data.chartData).forEach(([period, productSales]) => {
        periods.add(period);
        Object.entries(productSales).forEach(([product, amount]) => {
            products.add(product);
            if (!salesByProduct[product]) {
                salesByProduct[product] = {};
            }
            salesByProduct[product][period] = amount;
        });
    });

    const sortedPeriods = Array.from(periods).sort();
    
    return Array.from(products).map(product => ({
        name: product,
        data: sortedPeriods.map(period => ({
            x: period,
            y: salesByProduct[product][period] || 0
        }))
    }));
  }

  // Update the prepareSalesPerDayData function
  function prepareSalesPerDayData(data) {
    if (!data || data.length === 0) {
        return { x: [], y: [] };
    }

    const salesByDay = {};
    
    data.forEach(sale => {
        const date = new Date(sale.order_date);
        const day = date.toISOString().split('T')[0]; // Format: YYYY-MM-DD
        const products = sale.product_name.split(', ');
        const quantities = sale.quantity.split(', ').map(Number);
        
        if (!salesByDay[day]) {
            salesByDay[day] = 0;
        }

        // Calculate total for this sale based on individual products
        products.forEach((_, index) => {
            salesByDay[day] += parseFloat(sale.total_amount) * (quantities[index] / quantities.reduce((a, b) => a + b, 0));
        });
    });

    const sortedDays = Object.keys(salesByDay).sort();
    
    return {
        x: sortedDays,
        y: sortedDays.map(day => salesByDay[day])
    };
  }

  // Update the prepareSalesPerProductPerDayData function
  function prepareSalesPerProductPerDayData(data) {
    if (!data || !data.dailyChartData || Object.keys(data.dailyChartData).length === 0) {
        return [];
    }

    const products = new Set();
    const days = new Set();
    const salesByProduct = {};
    
    Object.entries(data.dailyChartData).forEach(([day, productSales]) => {
        days.add(day);
        Object.entries(productSales).forEach(([product, amount]) => {
            products.add(product);
            if (!salesByProduct[product]) {
                salesByProduct[product] = {};
            }
            salesByProduct[product][day] = amount;
        });
    });

    const sortedDays = Array.from(days).sort();
    
    return Array.from(products).map(product => ({
        name: product,
        data: sortedDays.map(day => ({
            x: day,
            y: salesByProduct[product][day] || 0
        }))
    }));
  }

  // Add cleanup function for charts
  onDestroy(() => {
    if (salesPerPeriodChart) {
      salesPerPeriodChart.destroy();
      salesPerPeriodChart = null;
    }
    if (salesPerProductChart) {
      salesPerProductChart.destroy();
      salesPerProductChart = null;
    }
    if (salesPerDayChart) {
      salesPerDayChart.destroy();
      salesPerDayChart = null;
    }
    if (salesPerProductPerDayChart) {
      salesPerProductPerDayChart.destroy();
      salesPerProductPerDayChart = null;
    }
  });

  // Update the initializeCharts function
  async function initializeCharts() {
    if (browser && !ApexCharts) {  // Add check for existing ApexCharts
      try {
        ApexCharts = (await import('apexcharts')).default;
        updateCharts();
      } catch (error) {
        console.error('Failed to load ApexCharts:', error);
      }
    } else if (browser) {
      updateCharts();
    }
  }

  // Update the updateCharts function to include null checks
  function updateCharts() {
    if (!browser || !ApexCharts || !document.querySelector("#salesPerPeriodChart")) {
      return;  // Exit if elements don't exist
    }
    
    const periodData = prepareSalesPerPeriodData(chartData);
    const productData = prepareSalesPerProductData({chartData}); // Pass the entire response
    const dailyData = prepareSalesPerDayData(salesData);
    const productDailyData = prepareSalesPerProductPerDayData({dailyChartData: dailyChartData}); // Pass the stored dailyChartData

    // Total sales per period chart
    const periodOptions = {
      series: [{
        name: 'Total Sales',
        data: periodData.y
      }],
      chart: {
        type: 'line',
        height: 350,
        toolbar: {
          show: true
        }
      },
      stroke: {
        curve: 'smooth',
        width: 3
      },
      xaxis: {
        categories: periodData.x,
        title: {
          text: 'Period'
        }
      },
      yaxis: {
        title: {
          text: 'Sales Amount (₱)'
        },
        labels: {
          formatter: (value) => `₱${value.toFixed(2)}`
        }
      },
      title: {
        text: 'Total Sales per Month',
        align: 'center'
      },
      grid: {
        borderColor: '#e0e0e0',
        row: {
          colors: ['#f5f5f5', 'transparent'],
          opacity: 0.5
        }
      },
      markers: {
        size: 6
      },
      tooltip: {
        y: {
          formatter: (value) => `₱${value.toFixed(2)}`
        }
      }
    };

    // Sales per product chart
    const productOptions = {
      series: productData,
      chart: {
        type: 'line',
        height: 350,
        toolbar: {
          show: true
        }
      },
      stroke: {
        curve: 'smooth',
        width: 3
      },
      xaxis: {
        type: 'category',
        title: {
          text: 'Period'
        }
      },
      yaxis: {
        title: {
          text: 'Sales Amount (₱)'
        },
        labels: {
          formatter: (value) => `₱${value.toFixed(2)}`
        }
      },
      title: {
        text: 'Sales per Product per Month',
        align: 'center'
      },
      grid: {
        borderColor: '#e0e0e0',
        row: {
          colors: ['#f5f5f5', 'transparent'],
          opacity: 0.5
        }
      },
      markers: {
        size: 6
      },
      tooltip: {
        y: {
          formatter: (value) => `₱${value.toFixed(2)}`
        }
      },
      legend: {
        position: 'top',
        horizontalAlign: 'center'
      },
      colors: ['#008FFB', '#00E396', '#FEB019', '#FF4560', '#775DD0']
    };

    // Add daily charts
    const dailyOptions = {
      series: [{
        name: 'Total Sales',
        data: dailyData.y
      }],
      chart: {
        type: 'line',
        height: 350,
        toolbar: {
          show: true
        }
      },
      stroke: {
        curve: 'smooth',
        width: 3
      },
      xaxis: {
        categories: dailyData.x,
        title: {
          text: 'Date'
        }
      },
      yaxis: {
        title: {
          text: 'Sales Amount (₱)'
        },
        labels: {
          formatter: (value) => `₱${value.toFixed(2)}`
        }
      },
      title: {
        text: 'Total Sales per Day',
        align: 'center'
      },
      grid: {
        borderColor: '#e0e0e0',
        row: {
          colors: ['#f5f5f5', 'transparent'],
          opacity: 0.5
        }
      },
      markers: {
        size: 6
      },
      tooltip: {
        y: {
          formatter: (value) => `₱${value.toFixed(2)}`
        }
      }
    };

    const productDailyOptions = {
      series: productDailyData,
      chart: {
        type: 'line',
        height: 350,
        toolbar: {
          show: true
        }
      },
      stroke: {
        curve: 'smooth',
        width: 3
      },
      xaxis: {
        type: 'category',
        title: {
          text: 'Date'
        }
      },
      yaxis: {
        title: {
          text: 'Sales Amount (₱)'
        },
        labels: {
          formatter: (value) => `₱${value.toFixed(2)}`
        }
      },
      title: {
        text: 'Sales per Product per Day',
        align: 'center'
      },
      grid: {
        borderColor: '#e0e0e0',
        row: {
          colors: ['#f5f5f5', 'transparent'],
          opacity: 0.5
        }
      },
      markers: {
        size: 6
      },
      tooltip: {
        y: {
          formatter: (value) => `₱${value.toFixed(2)}`
        }
      },
      legend: {
        position: 'top',
        horizontalAlign: 'center'
      },
      colors: ['#008FFB', '#00E396', '#FEB019', '#FF4560', '#775DD0']
    };

    // Destroy existing charts if they exist
    if (salesPerPeriodChart) {
      salesPerPeriodChart.destroy();
    }
    if (salesPerProductChart) {
      salesPerProductChart.destroy();
    }
    if (salesPerDayChart) {
      salesPerDayChart.destroy();
    }
    if (salesPerProductPerDayChart) {
      salesPerProductPerDayChart.destroy();
    }

    // Create new charts
    salesPerPeriodChart = new ApexCharts(document.querySelector("#salesPerPeriodChart"), periodOptions);
    salesPerProductChart = new ApexCharts(document.querySelector("#salesPerProductChart"), productOptions);
    salesPerDayChart = new ApexCharts(document.querySelector("#salesPerDayChart"), dailyOptions);
    salesPerProductPerDayChart = new ApexCharts(document.querySelector("#salesPerProductPerDayChart"), productDailyOptions);

    salesPerPeriodChart.render();
    salesPerProductChart.render();
    salesPerDayChart.render();
    salesPerProductPerDayChart.render();
  }

  // Add this function to handle Excel export
  function exportToExcel() {
    // Prepare the data for export
    const exportData = filteredSalesData.map(sale => ({
      Staff: sale.username,
      Product: sale.product_name,
      Quantity: sale.quantity,
      'Customer Name': sale.customer_name,
      'Amount Paid': `₱${parseFloat(sale.amount_paid).toFixed(2)}`,
      'Total Amount': `₱${parseFloat(sale.total_amount).toFixed(2)}`,
      Date: new Date(sale.order_date).toLocaleDateString()
    }));

    // Create worksheet
    const ws = XLSX.utils.json_to_sheet(exportData);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Sales Data');

    // Generate and download file
    XLSX.writeFile(wb, `sales_export_${new Date().toISOString().split('T')[0]}.xlsx`);
  }

  // Add print function
  function printCharts() {
    const printWindow = window.open('', '_blank');
    const currentDate = new Date().toLocaleDateString();
    
    const htmlContent = `
      <!DOCTYPE html>
      <html>
        <head>
          <title>Sales Reports</title>
          <style>
            body { 
              padding: 20px; 
              font-family: Arial, sans-serif;
              max-width: 1200px;
              margin: 0 auto;
            }
            .report-header { 
              text-align: center; 
              margin-bottom: 30px;
            }
            .charts-grid { 
              display: grid;
              grid-template-columns: repeat(2, 1fr);
              gap: 20px;
              width: 100%;
            }
            .chart-container { 
              page-break-inside: avoid;
              background: white;
              padding: 15px;
              border-radius: 8px;
              box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            @media print { 
              @page { 
                size: landscape;
                margin: 1cm;
              }
              .charts-grid {
                grid-template-columns: repeat(2, 1fr);
              }
              .chart-container {
                box-shadow: none;
                border: 1px solid #eee;
              }
            }
          </style>
        </head>
        <body>
          <div class="report-header">
            <h1>Sales Reports</h1>
            <p>Generated on ${currentDate}</p>
          </div>
          <div class="charts-grid">
            <div class="chart-container">
              ${(document.querySelector("#salesPerPeriodChart") || {}).innerHTML || ''}
            </div>
            <div class="chart-container">
              ${(document.querySelector("#salesPerProductChart") || {}).innerHTML || ''}
            </div>
            <div class="chart-container">
              ${(document.querySelector("#salesPerDayChart") || {}).innerHTML || ''}
            </div>
            <div class="chart-container">
              ${(document.querySelector("#salesPerProductPerDayChart") || {}).innerHTML || ''}
            </div>
          </div>
          <script src="https://cdn.jsdelivr.net/npm/apexcharts"><\/script>
          <script>
            window.onload = function() { window.print(); };
          <\/script>
        </body>
      </html>
    `;

    printWindow.document.write(htmlContent);
    printWindow.document.close();
  }

  // Update the deleteOrder function
  async function deleteOrder(orderId: number) {
    if (!confirm('Are you sure you want to delete this order? This action cannot be undone.')) {
      return;
    }

    try {
      const response = await ApiService.delete('delete-order', {
        order_id: orderId,
        user_id: $userStore.userId
      });

      if (response.status) {
        // Refresh the sales data
        const refreshResponse = await ApiService.get('get-sales-data');
        if (refreshResponse.status) {
          salesData = refreshResponse.data;
          chartData = refreshResponse.chartData;
          dailyChartData = refreshResponse.dailyChartData;
          filteredSalesData = salesData;
          await initializeCharts();
          alert('Order deleted successfully');
        }
      } else {
        alert('Failed to delete order: ' + response.message);
      }
    } catch (error) {
      console.error('Error deleting order:', error);
      alert('Error deleting order. Please try again.');
    }
  }

  // Update deleteAllOrders to only delete filtered data
  async function deleteAllOrders() {
    if (filteredSalesData.length === 0) {
      alert('No data to delete');
      return;
    }

    if (!confirm('Are you sure you want to delete ALL filtered orders? This action cannot be undone!')) {
      return;
    }

    try {
      const orderIds = filteredSalesData.map(sale => sale.order_id);
      const response = await ApiService.delete('delete-filtered-orders', {
        order_ids: orderIds,
        user_id: $userStore.userId
      });

      if (response.status) {
        // Refresh the sales data
        const refreshResponse = await ApiService.get('get-sales-data');
        if (refreshResponse.status) {
          salesData = refreshResponse.data;
          chartData = refreshResponse.chartData;
          dailyChartData = refreshResponse.dailyChartData;
          filteredSalesData = salesData;
          await initializeCharts();
          alert('Selected orders deleted successfully');
        }
      } else {
        alert('Failed to delete orders: ' + response.message);
      }
    } catch (error) {
      console.error('Error deleting orders:', error);
      alert('Error deleting orders. Please try again.');
    }
  }

  // Add this function to handle archiving
  async function archiveSale(orderId: number) {
    if (!confirm('Are you sure you want to archive this sale? This action cannot be undone.')) {
      return;
    }

    try {
      const response = await ApiService.post('archive-sales', {
        order_id: orderId,
        user_id: $userStore.userId
      });

      if (response.status) {
        // Refresh the sales data
        const refreshResponse = await ApiService.get('get-sales-data');
        if (refreshResponse.status) {
          salesData = refreshResponse.data;
          chartData = refreshResponse.chartData;
          dailyChartData = refreshResponse.dailyChartData;
          filteredSalesData = salesData;
          await initializeCharts();
          alert('Sale archived successfully');
        }
      } else {
        alert('Failed to archive sale: ' + response.message);
      }
    } catch (error) {
      console.error('Error archiving sale:', error);
      alert('Error archiving sale. Please try again.');
    }
  }

  // Add this function to handle archiving filtered data
  async function archiveFilteredSales() {
    if (filteredSalesData.length === 0) {
      alert('No data to archive');
      return;
    }

    if (!confirm('Are you sure you want to archive all filtered sales? This action cannot be undone.')) {
      return;
    }

    try {
      const orderIds = filteredSalesData.map(sale => sale.order_id);
      const response = await ApiService.post('archive-filtered-sales', {
        order_ids: orderIds,
        user_id: $userStore.userId
      });

      if (response.status) {
        // Refresh the sales data
        const refreshResponse = await ApiService.get('get-sales-data');
        if (refreshResponse.status) {
          salesData = refreshResponse.data;
          chartData = refreshResponse.chartData;
          dailyChartData = refreshResponse.dailyChartData;
          filteredSalesData = salesData;
          await initializeCharts();
          alert('Selected sales archived successfully');
        }
      } else {
        alert('Failed to archive sales: ' + response.message);
      }
    } catch (error) {
      console.error('Error archiving sales:', error);
      alert('Error archiving sales. Please try again.');
    }
  }

  onMount(async () => {
    try {
      const result = await ApiService.get<SalesData>('get-sales-data');
      if (result.status) {
        salesData = result.data;
        chartData = result.chartData;
        dailyChartData = result.dailyChartData;
        filteredSalesData = salesData;
        await initializeCharts();
      } else {
        console.error('Failed to fetch sales data:', result.message);
      }
    } catch (error) {
      console.error('Error fetching sales data:', error);
    }
  });
</script>

<Header {y} {innerHeight} />

<div class="content">
  <div class="sales-container">
    <h2 class="text-2xl font-bold mb-4">Sales History</h2>
    
    <!-- Charts Section -->
    <div class="charts-container grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
      <div class="chart-wrapper bg-white p-4 rounded-lg shadow">
        <div id="salesPerPeriodChart"></div>
      </div>
      <div class="chart-wrapper bg-white p-4 rounded-lg shadow">
        <div id="salesPerProductChart"></div>
      </div>
      <div class="chart-wrapper bg-white p-4 rounded-lg shadow">
        <div id="salesPerDayChart"></div>
      </div>
      <div class="chart-wrapper bg-white p-4 rounded-lg shadow">
        <div id="salesPerProductPerDayChart"></div>
      </div>
    </div>

    <!-- Filter Section -->
    <div class="filters mb-4 space-y-4">
      <!-- Search Bar -->
      <div class="flex items-center space-x-4">
        <input
          type="text"
          bind:value={searchQuery}
          placeholder="Search by product name..."
          class="px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
        />
      </div>
      
      <!-- Date Filters -->
      <div class="flex flex-wrap gap-4">
        <select
          bind:value={selectedYear}
          class="px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
        >
          <option value="">All Years</option>
          {#each years as year}
            <option value={year}>{year}</option>
          {/each}
        </select>

        <select
          bind:value={selectedMonth}
          class="px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
        >
          <option value="">All Months</option>
          {#each months as month}
            <option value={month.value}>{month.label}</option>
          {/each}
        </select>

        <select
          bind:value={selectedDay}
          class="px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
        >
          <option value="">All Days</option>
          {#each days as day}
            <option value={day}>{day}</option>
          {/each}
        </select>

        <button
          on:click={resetFilters}
          class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg transition-colors"
        >
          Reset Filters
        </button>

        <button
          on:click={exportToExcel}
          class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg transition-colors"
        >
          Export to Excel
        </button>

        <button
          on:click={printCharts}
          class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition-colors"
        >
          Print Charts
        </button>

        <button
          on:click={deleteAllOrders}
          class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg transition-colors"
        >
          Delete Filtered Orders
        </button>

        <button
          on:click={archiveFilteredSales}
          class="px-4 py-2 bg-purple-500 hover:bg-purple-600 text-white rounded-lg transition-colors"
        >
          Archive Filtered Sales
        </button>
      </div>
    </div>

    <!-- Results Count -->
    <div class="mb-4 text-gray-600">
      Showing {filteredSalesData.length} of {salesData.length} records
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
      <table class="min-w-full bg-white rounded-lg overflow-hidden">
        <thead class="bg-gray-100">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staff</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer Name</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount Paid</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Amount</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          {#if filteredSalesData.length === 0}
            <tr>
              <td colspan="8" class="px-6 py-4 text-center text-gray-500">
                No sales data available
              </td>
            </tr>
          {:else}
            {#each filteredSalesData as sale}
              <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap">{sale.username}</td>
                <td class="px-6 py-4 whitespace-nowrap">{sale.product_name}</td>
                <td class="px-6 py-4 whitespace-nowrap">{sale.quantity}</td>
                <td class="px-6 py-4 whitespace-nowrap">{sale.customer_name}</td>
                <td class="px-6 py-4 whitespace-nowrap">₱{parseFloat(sale.amount_paid).toFixed(2)}</td>
                <td class="px-6 py-4 whitespace-nowrap">₱{parseFloat(sale.total_amount).toFixed(2)}</td>
                <td class="px-6 py-4 whitespace-nowrap">{new Date(sale.order_date).toLocaleDateString()}</td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <button
                    on:click={() => deleteOrder(sale.order_id)}
                    class="text-red-600 hover:text-red-900 font-medium mr-2"
                  >
                    Delete
                  </button>
                  <button
                    on:click={() => archiveSale(sale.order_id)}
                    class="text-blue-600 hover:text-blue-900 font-medium"
                  >
                    Archive
                  </button>
                </td>
              </tr>
            {/each}
          {/if}
        </tbody>
      </table>
    </div>
  </div>
</div>

<style>
  .content {
    margin-top: 4rem;
    padding: 2rem;
    background-color: #fefae0;
    font-family: 'DynaPuff', cursive;
  }

  .sales-container {
    background: #faedcd;
    border-radius: 0.5rem;
    padding: 1.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  }

  /* Make table header sticky */
  thead {
    position: sticky;
    top: 0;
    z-index: 1;
    background-color: #d4a373;
    color: white;
  }

  /* Add some responsive styles */
  @media (max-width: 1024px) {
    .overflow-x-auto {
      max-width: 100vw;
    }
    
    .filters {
      flex-direction: column;
    }
    
    .filters > * {
      width: 100%;
    }
  }

  .chart-wrapper {
    min-height: 400px;
    background-color: white; /* Keep chart background white for better visibility */
  }

  /* Add global font */
  :global(body) {
    font-family: 'DynaPuff', cursive;
  }
</style>

<!-- Add font import in the head of the document -->
<svelte:head>
  <link href="https://fonts.googleapis.com/css2?family=DynaPuff&display=swap" rel="stylesheet">
</svelte:head>