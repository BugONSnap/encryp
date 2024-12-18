import { writable } from 'svelte/store';
import { goto } from '$app/navigation';
import { browser } from '$app/environment';
import { encryptionService } from './services/encryption.js';
import { ApiService } from './services/api.js';
import { get } from 'svelte/store';

// Define the user store type
export interface User {
    userId: number;
    username: string;
    isAuthenticated: boolean;
    role?: number;
}

// Initialize with a clearly unauthenticated state
const initialState: User = {
    userId: 0,
    username: '',
    isAuthenticated: false,
    role: 0
};

// Create the store with initial state
function createUserStore() {
    const { subscribe, set, update } = writable<User>(initialState);

    // Enhanced initialization from localStorage
    async function initFromStorage() {
        if (browser) {
            const stored = localStorage.getItem('auth');
            if (stored) {
                try {
                    const decrypted = await encryptionService.decrypt(stored) as User;
                    if (decrypted.userId && decrypted.username && decrypted.isAuthenticated) {
                        set(decrypted);
                        return true;
                    }
                } catch (error) {
                    console.error('Error parsing auth data:', error);
                    localStorage.removeItem('auth');
                }
            }
            return false;
        }
        return false;
    }

    return {
        subscribe,
        set,
        update,
        clear: () => {
            set(initialState);
            if (browser) {
                localStorage.removeItem('auth');
            }
        },
        initFromStorage
    };
}

export const userStore = createUserStore();

export async function logout() {
    try {
        const data = await ApiService.post<{ status: boolean }>('logout', {});
        if (data.status) {
            userStore.clear();
            goto('/');
        }
    } catch (error) {
        console.error('Logout error:', error);
    }
}

export async function setUser(userData: { userId: number; username: string; role?: number }) {
    // Only allow roles 0 and 1
    if (userData.role !== 0 && userData.role !== 1) {
        throw new Error('Unauthorized role');
    }

    const user: User = {
        userId: userData.userId,
        username: userData.username,
        isAuthenticated: true,
        role: userData.role ?? 0
    };
    
    userStore.set(user);
    
    if (browser) {
        // Encrypt user data before storing
        const encrypted = await encryptionService.encrypt(user);
        localStorage.setItem('auth', encrypted);
    }
}

export async function checkAuth(): Promise<boolean> {
    if (!browser) return false;

    const stored = localStorage.getItem('auth');
    if (!stored) return false;

    try {
        const decrypted = await encryptionService.decrypt(stored) as User;
        if (decrypted.userId && decrypted.username && decrypted.isAuthenticated) {
            userStore.set(decrypted);
            return true;
        }
    } catch (error) {
        console.error('Error checking auth:', error);
        localStorage.removeItem('auth');
    }

    userStore.clear();
    return false;
}

// Add a new function to check current page authorization
export async function checkPageAuth(requiredRole: number = 0): Promise<boolean> {
    if (!browser) return false;

    const isAuthenticated = await checkAuth();
    if (!isAuthenticated) return false;

    const user = get(userStore);
    return (user.role ?? 0) >= requiredRole;
}
