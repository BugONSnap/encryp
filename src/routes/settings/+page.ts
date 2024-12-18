import { requireAuth } from '$lib/authGuard.js';
import type { PageLoad } from './$types.js';

export const load: PageLoad = async () => {
    await requireAuth(1);
    return {};
};