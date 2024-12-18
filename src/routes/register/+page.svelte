<script lang="ts">
    import { goto } from '$app/navigation';
    import { ApiService } from '$lib/services/api';

    let username = '';
    let password = '';
    let confirmPassword = '';
    let errorMessage = '';
    let showPassword = false;
    let showConfirmPassword = false;

    async function register() {
        try {
            // Reset error message
            errorMessage = '';

            // Validate password length
            if (password.length < 8) {
                errorMessage = 'Password must be at least 8 characters long';
                return;
            }

            // Check if passwords match
            if (password !== confirmPassword) {
                errorMessage = 'Passwords do not match';
                return;
            }

            // Check password strength
            const hasUpperCase = /[A-Z]/.test(password);
            const hasLowerCase = /[a-z]/.test(password);
            const hasNumbers = /\d/.test(password);
            const hasSpecialChar = /[!@#$%^&*(),.?":{}|<>]/.test(password);

            if (!(hasUpperCase && hasLowerCase && hasNumbers && hasSpecialChar)) {
                errorMessage = 'Password must contain uppercase, lowercase, numbers, and special characters';
                return;
            }

            const result = await ApiService.post<{
                status: boolean;
                message?: string;
            }>('add-account', {
                username,
                password,
                role: 2 // Set default role to 2
            });

            if (result.status) {
                alert('Registration successful');
                goto('/');
            } else {
                errorMessage = result.message || 'Registration failed';
            }
        } catch (error) {
            console.error('Error during registration:', error);
            errorMessage = 'An error occurred. Please try again.';
        }
    }

    function togglePassword(field: 'password' | 'confirm') {
        if (field === 'password') {
            showPassword = !showPassword;
        } else {
            showConfirmPassword = !showConfirmPassword;
        }
    }
</script>

<div class="flex flex-col items-center w-full px-4 pt-[5vh] bg-gradient-to-b from-[#faedcd] to-white min-h-screen">
    <div class="logo-img mx-auto mb-2 mt-8 relative animate-float">
        <div class="absolute inset-0 blur-xl bg-[#d4a373]/30 rounded-full"></div>
        <img 
            src="/images/logo.png" 
            class="logo w-[250px] h-[250px] md:w-[1000px] md:h-[400px] relative z-10 filter drop-shadow-[0_10px_15px_rgba(212,163,115,0.3)]" 
            alt="Logo"
        >
    </div>
    
    <h2 class="text-2xl font-dynapuff text-[#d4a373] mb-6 text-center">Create Your Account</h2>
    
    <div class="card bg-white/80 backdrop-blur-sm outline-none mb-6 border-none rounded-[20px] mt-4 pt-4 shadow-2xl w-full max-w-md mx-auto">
        <div class="card-body overflow-hidden p-5">
            <form on:submit|preventDefault={register}>
                <div class="form-group mb-4">
                    <input 
                        type="text" 
                        class="form-control form-control-sm w-full rounded-full px-4 py-2 border-2 border-[#faedcd] focus:border-[#d4a373] focus:outline-none transition-colors" 
                        id="username" 
                        bind:value={username} 
                        placeholder="Username"
                        required
                    >
                </div>
                <div class="form-group mb-4 relative">
                    <input 
                        type={showPassword ? "text" : "password"}
                        class="form-control form-control-sm w-full rounded-full px-4 py-2 border-2 border-[#faedcd] focus:border-[#d4a373] focus:outline-none transition-colors pr-10" 
                        id="password" 
                        bind:value={password} 
                        placeholder="Password"
                        required
                    >
                    <button 
                        type="button"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"
                        on:click={() => togglePassword('password')}
                    >
                        {#if showPassword}
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                <line x1="1" y1="1" x2="23" y2="23"></line>
                            </svg>
                        {:else}
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        {/if}
                    </button>
                </div>
                <div class="form-group mb-4 relative">
                    <input 
                        type={showConfirmPassword ? "text" : "password"}
                        class="form-control form-control-sm w-full rounded-full px-4 py-2 border-2 border-[#faedcd] focus:border-[#d4a373] focus:outline-none transition-colors pr-10" 
                        id="confirmPassword" 
                        bind:value={confirmPassword} 
                        placeholder="Confirm Password"
                        required
                    >
                    <button 
                        type="button"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"
                        on:click={() => togglePassword('confirm')}
                    >
                        {#if showConfirmPassword}
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                <line x1="1" y1="1" x2="23" y2="23"></line>
                            </svg>
                        {:else}
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        {/if}
                    </button>
                </div>
                {#if errorMessage}
                    <div class="text-red-500 text-sm mb-4 text-center">{errorMessage}</div>
                {/if}
                <button 
                    type="submit" 
                    class="btn w-full rounded-full bg-[#d4a373] hover:bg-[#c49363] text-white py-3 font-medium transition-colors shadow-md disabled:bg-gray-400 disabled:cursor-not-allowed"
                    disabled={!username || !password || !confirmPassword}
                >
                    Create Account
                </button>
            </form>
            <div class="account-section mt-4 text-right">
                <a 
                    href="/" 
                    class="new-account-link text-[#d4a373] font-bold text-sm hover:text-[#c49363] transition-colors"
                >
                    Back to Login
                </a>
            </div>
        </div>
    </div>
</div>

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