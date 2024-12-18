import { goto } from '$app/navigation';
import { userStore } from './auth.js';
import { get } from 'svelte/store';
import { browser } from '$app/environment';
import { encryptionService } from './services/encryption.js';
import type { User } from './auth.js';

export async function requireAuth(requiredRole: number = 0): Promise<boolean> {
    if (!browser) return false;
    
    const user = get(userStore);
    
    if (!user.isAuthenticated) {
        const stored = localStorage.getItem('auth');
        if (stored) {
            try {
                const decryptedUser = await encryptionService.decrypt(stored) as User;
                if (decryptedUser.userId && decryptedUser.username && decryptedUser.isAuthenticated) {
                    userStore.set({
                        ...decryptedUser,
                        role: decryptedUser.role ?? 0
                    });
                    return requireAuth(requiredRole);
                }
            } catch (error) {
                console.error('Error parsing auth data:', error);
                localStorage.removeItem('auth');
                goto('/');
                return false;
            }
        }
        goto('/');
        return false;
    }

    const userRole = user.role ?? 0;
    if (userRole < requiredRole) {
        goto('/order');
        return false;
    }
    return true;
}

export function canAccessAdmin(user: User): boolean {
    return user.isAuthenticated && (user.role === 1);
}