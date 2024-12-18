import { goto } from '$app/navigation';
import { get } from 'svelte/store';
import { userStore, checkAuth } from '$lib/auth.js';
import type { PageLoad } from './$types.js';
import { browser } from '$app/environment';

export const load: PageLoad = async () => {
    if (!browser) return {};

    const user = get(userStore);
    
    // If not authenticated, check localStorage
    if (!user.isAuthenticated) {
        const isAuthenticated = await checkAuth();
        if (isAuthenticated) {
            goto('/order');
            return {};
        }
    } else {
        // If already authenticated, redirect to order page
        goto('/order');
    }
    
    return {};
};