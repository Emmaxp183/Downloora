<script module lang="ts">
    export const layout = {
        title: 'Log in to your account',
        description: 'Enter your email and password below to log in',
    };
</script>

<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import PasswordInput from '@/components/PasswordInput.svelte';
    import TextLink from '@/components/TextLink.svelte';
    import { Button } from '@/components/ui/button';
    import { Checkbox } from '@/components/ui/checkbox';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import { register } from '@/routes';
    import { redirect as googleRedirect } from '@/routes/auth/google';
    import { store } from '@/routes/login';
    import { request } from '@/routes/password';

    let {
        status = '',
        canResetPassword,
        canRegister,
    }: {
        status?: string;
        canResetPassword: boolean;
        canRegister: boolean;
    } = $props();
</script>

<AppHead title="Log in" />

{#if status}
    <div class="mb-4 text-center text-sm font-medium text-green-600">
        {status}
    </div>
{/if}

<div class="flex flex-col gap-4">
    <a
        href={googleRedirect.url()}
        class="inline-flex min-h-12 w-full items-center justify-center gap-3 rounded-full border-2 border-foreground bg-card px-5 py-3 text-sm font-bold text-foreground shadow-[3px_3px_0_0_var(--foreground)] transition hover:translate-x-0.5 hover:translate-y-0.5 hover:bg-muted hover:shadow-[1px_1px_0_0_var(--foreground)]"
    >
        <span
            class="flex size-7 items-center justify-center rounded-full bg-white text-base font-black text-foreground"
            aria-hidden="true"
        >
            G
        </span>
        Continue with Google
    </a>

    <div class="flex items-center gap-3 text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">
        <span class="h-px flex-1 bg-border"></span>
        <span>Email login</span>
        <span class="h-px flex-1 bg-border"></span>
    </div>
</div>

<Form
    {...store.form()}
    resetOnSuccess={['password']}
    class="flex flex-col gap-6"
>
    {#snippet children({ errors, processing })}
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autocomplete="email"
                    placeholder="email@example.com"
                />
                <InputError message={errors.email} />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password">Password</Label>
                    {#if canResetPassword}
                        <TextLink href={request()} class="text-sm">
                            Forgot password?
                        </TextLink>
                    {/if}
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    placeholder="Password"
                />
                <InputError message={errors.password} />
            </div>

            <div class="flex items-center justify-between">
                <Label for="remember" class="flex items-center space-x-3">
                    <Checkbox id="remember" name="remember" />
                    <span>Remember me</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-4 w-full"
                disabled={processing}
                data-test="login-button"
            >
                {#if processing}<Spinner />{/if}
                Log in
            </Button>
        </div>

        {#if canRegister}
            <div class="text-center text-sm text-muted-foreground">
                Don't have an account?
                <TextLink href={register()}>Sign up</TextLink>
            </div>
        {/if}
    {/snippet}
</Form>
