<script module lang="ts">
    export const layout = {
        title: 'Create an account',
        description: 'Enter your details below to create your account',
    };
</script>

<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import PasswordInput from '@/components/PasswordInput.svelte';
    import TextLink from '@/components/TextLink.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import { login } from '@/routes';
    import { redirect as googleRedirect } from '@/routes/auth/google';
    import { store } from '@/routes/register';
</script>

<AppHead title="Register" />

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

    <div
        class="flex items-center gap-3 text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground"
    >
        <span class="h-px flex-1 bg-border"></span>
        <span>Email signup</span>
        <span class="h-px flex-1 bg-border"></span>
    </div>
</div>

<Form
    {...store.form()}
    resetOnSuccess={['password', 'password_confirmation']}
    class="flex flex-col gap-6"
>
    {#snippet children({ errors, processing })}
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    type="text"
                    required
                    autocomplete="name"
                    name="name"
                    placeholder="Full name"
                />
                <InputError message={errors.name} />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    required
                    autocomplete="email"
                    name="email"
                    placeholder="email@example.com"
                />
                <InputError message={errors.email} />
            </div>

            <div class="grid gap-2">
                <Label for="password">Password</Label>
                <PasswordInput
                    id="password"
                    required
                    autocomplete="new-password"
                    name="password"
                    placeholder="Password"
                />
                <InputError message={errors.password} />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirm password</Label>
                <PasswordInput
                    id="password_confirmation"
                    required
                    autocomplete="new-password"
                    name="password_confirmation"
                    placeholder="Confirm password"
                />
                <InputError message={errors.password_confirmation} />
            </div>

            <Button
                type="submit"
                class="mt-2 w-full"
                disabled={processing}
                data-test="register-user-button"
            >
                {#if processing}<Spinner />{/if}
                Create account
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            Already have an account?
            <TextLink href={login()} class="underline underline-offset-4">
                Log in
            </TextLink>
        </div>
    {/snippet}
</Form>
