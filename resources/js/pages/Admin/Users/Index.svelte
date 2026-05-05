<script module lang="ts">
    import { index as adminUsers } from '@/routes/admin/users';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Admin users',
                href: adminUsers(),
            },
        ],
    };
</script>

<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { updateQuota } from '@/actions/App/Http/Controllers/Admin/UserController';

    type User = {
        id: number;
        name: string;
        email: string;
        is_admin: boolean;
        email_verified_at: string | null;
        storage_quota_bytes: number;
        storage_used_bytes: number;
    };

    let { users }: { users: User[] } = $props();

    const mb = (bytes: number): number => Math.round(bytes / 1024 / 1024);
</script>

<AppHead title="Admin users" />

<div class="flex h-full flex-1 flex-col gap-5 overflow-x-auto">
    <div class="downloora-card bg-[var(--downloora-paper)] p-5">
        <h1 class="text-3xl font-black tracking-tight">Users</h1>
        <p class="text-sm font-medium text-muted-foreground">
            Manage quotas and account state.
        </p>
    </div>

    <div class="space-y-4">
        {#each users as user (user.id)}
            <div
                class="downloora-row grid gap-4 p-4 lg:grid-cols-[minmax(0,1fr)_22rem]"
            >
                <div class="min-w-0">
                    <p class="truncate text-base font-bold">
                        {user.name}{user.is_admin ? ' · admin' : ''}
                    </p>
                    <p
                        class="truncate text-xs font-medium text-muted-foreground"
                    >
                        {user.email}
                    </p>
                    <p class="mt-1 text-xs font-medium text-muted-foreground">
                        {mb(user.storage_used_bytes)} MB used of {mb(
                            user.storage_quota_bytes,
                        )} MB
                    </p>
                </div>

                <Form
                    {...updateQuota.form(user.id)}
                    class="flex flex-wrap items-center gap-2"
                >
                    {#snippet children({ errors, processing })}
                        <Input
                            name="storage_quota_mb"
                            type="number"
                            min="1"
                            value={mb(user.storage_quota_bytes)}
                            disabled={processing}
                            class="max-w-36"
                        />
                        <Button type="submit" disabled={processing}>Save</Button
                        >
                        {#if errors.storage_quota_mb}
                            <p class="text-xs text-destructive">
                                {errors.storage_quota_mb}
                            </p>
                        {/if}
                    {/snippet}
                </Form>
            </div>
        {/each}
    </div>
</div>
