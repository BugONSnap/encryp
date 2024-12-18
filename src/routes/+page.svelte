<script lang="ts">
    import { onMount } from 'svelte';
    import { goto } from '$app/navigation';
    import { setUser } from '$lib/auth';
    import { ApiService } from '$lib/services/api';

    interface LoginResponse {
        status: boolean;
        userId: number;
        username: string;
        role: number;
        message?: string;
    }

    let username = '';
    let password = '';
    let errorMessage = '';
    let showPassword = false;

    async function handleSubmit(event?: Event) {
        if (event) {
            event.preventDefault();
        }
        
        try {
            const result = await ApiService.post<LoginResponse>('login', {
                username,
                password
            });

            if (result.status && (result.role === 0 || result.role === 1)) {
                await setUser({
                    userId: result.userId,
                    username: result.username,
                    role: result.role
                });
                goto('/order');
            } else {
                errorMessage = result.message || 'Login failed. Your account may be pending approval.';
            }
        } catch (error) {
            console.error('Login error:', error);
            errorMessage = 'Failed to login. Please try again.';
        }
    }

    function togglePassword() {
        showPassword = !showPassword;
    }
</script>

<style>
    @keyframes float {
        0%, 100% {
            transform: translateY(0);
        }
        50% {
            transform: translateY(-10px);
        }
    }

    .animate-float {
        animation: float 3s ease-in-out infinite;
    }
</style>

<div class="flex flex-col items-center w-full px-4 pt-[5vh] bg-gradient-to-b from-[#faedcd] to-white min-h-screen">
    <div class="logo-img mx-auto mb-2 mt-8 relative animate-float">
        <div class="absolute inset-0 blur-xl bg-[#d4a373]/30 rounded-full"></div>
        <img 
            src="/images/last.png" 
            class="logo w-[250px] h-[250px] md:w-[1000px] md:h-[400px] relative z-10 filter drop-shadow-[0_10px_15px_rgba(212,163,115,0.3)]" 
            alt="Logo"
        >
    </div>
    
    <h2 class="text-2xl font-dynapuff text-[#d4a373] mb-6 text-center">Welcome to Lazt Bean Cafe</h2>
    
    <div class="card bg-white/80 backdrop-blur-sm outline-none mb-6 border-none rounded-[20px] mt-4 pt-4 shadow-2xl w-full max-w-md mx-auto">
        <div class="card-body overflow-hidden p-5">
            <form on:submit={handleSubmit}>
                <div class="form-group mb-4">
                    <input 
                        type="text" 
                        class="form-control form-control-sm w-full rounded-full px-4 py-2 border-2 border-[#faedcd] focus:border-[#d4a373] focus:outline-none transition-colors" 
                        id="username" 
                        bind:value={username} 
                        placeholder="Username"
                    >
                </div>
                <div class="form-group mb-4">
                    <input 
                        type="password" 
                        class="form-control form-control-sm w-full rounded-full px-4 py-2 border-2 border-[#faedcd] focus:border-[#d4a373] focus:outline-none transition-colors" 
                        id="password" 
                        bind:value={password} 
                        placeholder="Password"
                    >
                </div>
                {#if errorMessage}
                    <div class="text-red-500 text-sm mb-4 text-center">{errorMessage}</div>
                {/if}
                <button 
                    type="submit" 
                    class="btn w-full rounded-full bg-[#d4a373] hover:bg-[#c49363] text-white py-3 font-medium transition-colors shadow-md"
                >
                    Login
                </button>
            </form>
            <div class="account-section mt-4 text-right">
                <a 
                    href="/register" 
                    class="new-account-link text-[#d4a373] font-bold text-sm hover:text-[#c49363] transition-colors"
                >
                    Create a new account
                </a>
            </div>
        </div>
    </div>
</div>