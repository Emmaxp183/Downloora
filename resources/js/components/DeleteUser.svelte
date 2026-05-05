<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import PasswordInput from '@/components/PasswordInput.svelte';
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogClose,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogTitle,
        DialogTrigger,
    } from '@/components/ui/dialog';
    import { Label } from '@/components/ui/label';
</script>

<div class="downloora-card space-y-6 bg-card p-5">
    <Heading
        variant="small"
        title="Delete account"
        description="Delete your account and all of its resources"
    />
    <div
        class="space-y-4 rounded-[1.25rem] border-2 border-[var(--downloora-danger)] bg-[var(--downloora-danger)]/10 p-4"
    >
        <div class="relative space-y-0.5 text-destructive">
            <p class="font-black">Warning</p>
            <p class="text-sm font-medium">
                Please proceed with caution, this cannot be undone.
            </p>
        </div>
        <Dialog>
            <DialogTrigger>
                <Button variant="destructive" data-test="delete-user-button"
                    >Delete account</Button
                >
            </DialogTrigger>
            <DialogContent>
                <Form
                    {...ProfileController.destroy.form()}
                    class="space-y-6"
                    options={{ preserveScroll: true }}
                >
                    {#snippet children({ errors, processing })}
                        <div class="space-y-3">
                            <DialogTitle
                                >Are you sure you want to delete your account?</DialogTitle
                            >
                            <DialogDescription>
                                Once your account is deleted, all of its
                                resources and data will also be permanently
                                deleted. Please enter your password to confirm
                                you would like to permanently delete your
                                account.
                            </DialogDescription>
                        </div>

                        <div class="grid gap-2">
                            <Label for="password" class="sr-only"
                                >Password</Label
                            >
                            <PasswordInput
                                id="password"
                                name="password"
                                placeholder="Password"
                            />
                            <InputError message={errors.password} />
                        </div>

                        <DialogFooter class="gap-2">
                            <DialogClose>
                                <Button variant="secondary">Cancel</Button>
                            </DialogClose>

                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={processing}
                                data-test="confirm-delete-user-button"
                            >
                                Delete account
                            </Button>
                        </DialogFooter>
                    {/snippet}
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</div>
