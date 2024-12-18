<script lang="ts">
    export let type: 'success' | 'error' | 'warning' = 'success';
    export let message: string;
  </script>
  
  <div class="alert {type}">
    {message}
  </div>
  
  <style>
    .alert {
      padding: 1rem;
      border-radius: 0.5rem;
      margin-bottom: 1rem;
      animation: slideIn 0.3s ease-out;
    }
  
    @keyframes slideIn {
      from {
        transform: translateY(-100%);
        opacity: 0;
      }
      to {
        transform: translateY(0);
        opacity: 1;
      }
    }
  
    .success {
      background-color: #d1fae5;
      color: #065f46;
      border: 1px solid #34d399;
    }
  
    .error {
      background-color: #fee2e2;
      color: #991b1b;
      border: 1px solid #f87171;
    }
  
    .warning {
      background-color: #fef3c7;
      color: #92400e;
      border: 1px solid #fbbf24;
    }
  </style>