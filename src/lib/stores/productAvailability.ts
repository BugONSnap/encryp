import { writable } from 'svelte/store';

export const productAvailability = writable<Record<number, boolean>>({});
export const availabilityLoading = writable<boolean>(true); 