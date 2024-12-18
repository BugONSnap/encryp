<script lang="ts">
    import { onMount } from 'svelte';
    import Header from '$lib/header.svelte';
    import { ApiService } from '$lib/services/api';
    import { userStore } from '$lib/auth';
    import Alert from '$lib/components/Alert.svelte';
    import { browser } from '$app/environment';
    import { requireAuth } from '$lib/authGuard';

    interface StaffMember {
        User_id: number;
        username: string;
        role: number;
    }

    let y = 0;
    let innerHeight = 0;
    let staff: StaffMember[] = [];
    let showAlert = false;
    let alertMessage = '';
    let alertType: 'success' | 'error' | 'warning' = 'success';
    let isLoading = true;

    function getRoleDisplay(role: number): string {
        switch (role) {
            case 1:
                return 'Admin';
            case 0:
                return 'Staff';
            default:
                return 'Pending Role';
        }
    }

    async function fetchStaff() {
        try {
            const result = await ApiService.get<StaffMember[]>('get-staff');
            if (result) {
                staff = result;
            }
        } catch (error) {
            console.error('Error fetching staff:', error);
            showAlert = true;
            alertType = 'error';
            alertMessage = 'Failed to fetch staff members';
            setTimeout(() => showAlert = false, 3000);
        }
    }

    async function updateRole(userId: number, newRole: number) {
        try {
            const result = await ApiService.put('update-staff-role', {
                user_id: userId,
                role: newRole
            });

            if (result.status) {
                showAlert = true;
                alertType = 'success';
                alertMessage = 'Role updated successfully';
                await fetchStaff();
            } else {
                showAlert = true;
                alertType = 'error';
                alertMessage = result.message || 'Failed to update role';
            }
            setTimeout(() => showAlert = false, 3000);
        } catch (error) {
            console.error('Error updating role:', error);
            showAlert = true;
            alertType = 'error';
            alertMessage = 'Failed to update role';
            setTimeout(() => showAlert = false, 3000);
        }
    }

    function isCurrentUser(userId: number): boolean {
        return userId === $userStore.userId;
    }

    async function checkAuth() {
        if (browser) {
            const isAuthorized = await requireAuth(1);
            if (!isAuthorized) {
                return false;
            }
            return true;
        }
        return false;
    }

    onMount(async () => {
        isLoading = true;
        const authorized = await checkAuth();
        if (authorized) {
            await fetchStaff();
        }
        isLoading = false;
    });
</script>

<Header {y} {innerHeight} />

{#if showAlert}
    <div class="fixed top-20 right-4 z-50">
        <Alert type={alertType} message={alertMessage} />
    </div>
{/if}

<div class="container mx-auto px-4 pt-24">
    {#if isLoading}
        <div class="flex justify-center items-center h-64">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-[#d4a373]"></div>
        </div>
    {:else}
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h1 class="text-2xl font-bold mb-6">Staff Management</h1>
            
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                User ID
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Username
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Current Role
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Change Role
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        {#each staff as member}
                            <tr class={isCurrentUser(member.User_id) ? 'bg-[#faedcd] bg-opacity-50' : ''}>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {member.User_id}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap flex items-center gap-2">
                                    {member.username}
                                    {#if isCurrentUser(member.User_id)}
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-[#d4a373] text-white">
                                            Current User
                                        </span>
                                    {/if}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        {member.role === 1 ? 'bg-green-100 text-green-800' : 
                                         member.role === 0 ? 'bg-blue-100 text-blue-800' : 
                                         'bg-yellow-100 text-yellow-800'}">
                                        {getRoleDisplay(member.role)}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <select 
                                        class="block w-full px-3 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md
                                            {isCurrentUser(member.User_id) ? 'bg-gray-100' : ''}"
                                        value={member.role}
                                        on:change={(e) => updateRole(member.User_id, parseInt(e.currentTarget.value))}
                                        disabled={isCurrentUser(member.User_id)}
                                    >
                                        <option value={1}>Admin-access</option>
                                        <option value={0}>Restricted-access</option>
                                        <option value={2}>No-access</option>
                                    </select>
                                    {#if isCurrentUser(member.User_id)}
                                        <div class="text-xs text-gray-500 mt-1">
                                            Cannot modify your own role
                                        </div>
                                    {/if}
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        </div>
    {/if}
</div>

<style>
    .container {
        background-color: #fefae0;
        min-height: calc(100vh - 4rem);
    }

    /* Add smooth transition for row highlight */
    tr {
        transition: background-color 0.2s ease;
    }
    /* Add hover effect except for current user row */
    tr:not([class*="bg-[#faedcd]"]):hover {
        background-color: #fefae0;
    }
</style>