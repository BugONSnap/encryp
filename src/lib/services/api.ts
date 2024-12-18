import { encryptionService } from './encryption.js';
import { userStore } from '../auth.js';
import { get } from 'svelte/store';

// Use the API URL from environment variables set in vite config
const API_BASE = import.meta.env.VITE_API_URL;

if (!API_BASE) {
    console.error('VITE_API_URL is not defined in environment variables');
}

export class ApiService {
    static async post<T>(endpoint: string, data: unknown): Promise<T> {
        try {
            const user = get(userStore);
            
            // Check if data is FormData
            if (data instanceof FormData) {
                const response = await fetch(`${API_BASE}/${endpoint}`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${user.userId}`
                    },
                    body: data  // Send FormData directly
                });

                if (response.status === 401) {
                    userStore.clear();
                    window.location.href = '/';
                    throw new Error('Unauthorized');
                }

                return await response.json();
            }

            // Handle regular JSON data with encryption
            const encryptedData = await encryptionService.encrypt(data);
            
            const response = await fetch(`${API_BASE}/${endpoint}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${user.userId}`
                },
                body: JSON.stringify({ data: encryptedData })
            });

            if (response.status === 401) {
                userStore.clear();
                window.location.href = '/';
                throw new Error('Unauthorized');
            }

            const result = await response.json();
            if (result.data) {
                return await encryptionService.decrypt(result.data) as T;
            }
            return result as T;
        } catch (error) {
            console.error(`API Error (${endpoint}):`, error);
            throw error;
        }
    }

    static async get<T>(endpoint: string, params?: Record<string, string>): Promise<T> {
        try {
            const user = get(userStore);
            const queryString = params ? 
                '?' + new URLSearchParams(params).toString() : '';
            
            const response = await fetch(`${API_BASE}/${endpoint}${queryString}`, {
                headers: {
                    'Authorization': `Bearer ${user.userId}`
                }
            });

            if (response.status === 401) {
                userStore.clear();
                window.location.href = '/';
                throw new Error('Unauthorized');
            }

            const result = await response.json();
            if (result.data) {
                return await encryptionService.decrypt(result.data) as T;
            }
            return result as T;
        } catch (error) {
            console.error(`API Error (${endpoint}):`, error);
            throw error;
        }
    }

    static async put<T>(endpoint: string, data: unknown): Promise<T> {
        try {
            const encryptedData = await encryptionService.encrypt(data);
            const user = get(userStore);
            
            const response = await fetch(`${API_BASE}/${endpoint}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${user.userId}`
                },
                body: JSON.stringify({ data: encryptedData })
            });

            if (response.status === 401) {
                userStore.clear();
                window.location.href = '/';
                throw new Error('Unauthorized');
            }

            const result = await response.json();
            if (result.data) {
                return await encryptionService.decrypt(result.data) as T;
            }
            return result as T;
        } catch (error) {
            console.error(`API Error (${endpoint}):`, error);
            throw error;
        }
    }

    static async delete<T>(endpoint: string, data: unknown): Promise<T> {
        try {
            const encryptedData = await encryptionService.encrypt(data);
            const user = get(userStore);
            
            const response = await fetch(`${API_BASE}/${endpoint}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${user.userId}`
                },
                body: JSON.stringify({ data: encryptedData })
            });

            if (response.status === 401) {
                userStore.clear();
                window.location.href = '/';
                throw new Error('Unauthorized');
            }

            const result = await response.json();
            if (result.data) {
                return await encryptionService.decrypt(result.data) as T;
            }
            return result as T;
        } catch (error) {
            console.error(`API Error (${endpoint}):`, error);
            throw error;
        }
    }
} 