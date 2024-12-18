import { redirect } from '@sveltejs/kit';
import type { PageLoad } from './$types';
import { requireAuth } from '$lib/authGuard';
import { browser } from '$app/environment';

export const load: PageLoad = async () => {
    if (!browser) return {};

    try {
        // Require admin role (1) for this page
        const isAuthorized = await requireAuth(1);
        if (!isAuthorized) {
            throw redirect(302, '/order'); // Redirect to order page if not admin
        }
    } catch (error) {
        console.error('Auth error:', error);
        throw redirect(302, '/'); // Redirect to login if not authenticated
    }

    return {
        requiresAuth: true,
        requiredRole: 1
    };
};