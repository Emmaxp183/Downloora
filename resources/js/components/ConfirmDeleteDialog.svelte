<script lang="ts">
    import type { FormComponentProps } from '@inertiajs/core';
    import { Form } from '@inertiajs/svelte';
    import Button from '@/components/ui/button/Button.svelte';
    import {
        Dialog,
        DialogClose,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogTitle,
    } from '@/components/ui/dialog';

    type FormDefinition = Pick<FormComponentProps, 'action' | 'method'>;

    let {
        open = $bindable(false),
        title,
        description,
        confirmLabel = 'Delete',
        form,
    }: {
        open?: boolean;
        title: string;
        description: string;
        confirmLabel?: string;
        form: FormDefinition;
    } = $props();

    const closeOnSuccess = (): void => {
        open = false;
    };
</script>

<Dialog bind:open>
    <DialogContent>
        <Form
            {...form}
            options={{ preserveScroll: true }}
            onSuccess={closeOnSuccess}
        >
            {#snippet children({ processing })}
                <div class="space-y-3">
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </div>

                <DialogFooter class="mt-6 gap-2">
                    <DialogClose>
                        <Button variant="secondary" disabled={processing}>
                            Cancel
                        </Button>
                    </DialogClose>

                    <Button
                        type="submit"
                        variant="destructive"
                        disabled={processing}
                    >
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            {/snippet}
        </Form>
    </DialogContent>
</Dialog>
