<script lang="ts">
    import ChevronRight from 'lucide-svelte/icons/chevron-right';
    import Download from 'lucide-svelte/icons/download';
    import Folder from 'lucide-svelte/icons/folder';
    import X from 'lucide-svelte/icons/x';
    import { destroy as destroyMediaFolder } from '@/actions/App/Http/Controllers/MediaFolderAccessController';
    import { destroy as destroyTorrentFolder } from '@/actions/App/Http/Controllers/TorrentFolderAccessController';
    import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.svelte';
    import FileRow from '@/components/files/FileRow.svelte';

    type StoredFile = {
        id: number;
        name: string;
        original_path: string;
        mime_type: string | null;
        size_bytes: number;
        download_url: string;
        stream_url: string;
        cast_url: string | null;
        adaptive_stream_url: string | null;
        adaptive_stream_status: string | null;
        updated_at?: string | null;
    };

    type FileFolder = {
        id: string;
        torrent_id: number | null;
        media_import_id: number | null;
        name: string;
        download_url: string | null;
        size_bytes: number;
        updated_at?: string | null;
        files: StoredFile[];
    };

    type FileTreeFile = {
        kind: 'file';
        id: string;
        name: string;
        file: StoredFile;
    };

    type FileTreeFolder = {
        kind: 'folder';
        id: string;
        name: string;
        children: FileTreeNode[];
    };

    type FileTreeNode = FileTreeFile | FileTreeFolder;

    let { folder }: { folder: FileFolder } = $props();

    let expanded = $state(false);
    let expandedTreeFolders = $state<Record<string, boolean>>({});
    let deleteDialogOpen = $state(false);

    const collator = new Intl.Collator(undefined, {
        numeric: true,
        sensitivity: 'base',
    });

    const normalizePathSegments = (path: string): string[] =>
        path
            .replaceAll('\\', '/')
            .split('/')
            .map((segment) => segment.trim())
            .filter(Boolean);

    const fileSegments = (file: StoredFile, rootName: string): string[] => {
        const segments = normalizePathSegments(file.original_path || file.name);

        if (
            segments.length > 1 &&
            segments[0].localeCompare(rootName, undefined, {
                sensitivity: 'base',
            }) === 0
        ) {
            return segments.slice(1);
        }

        return segments.length > 0 ? segments : [file.name];
    };

    const sortNodes = (nodes: FileTreeNode[]): FileTreeNode[] =>
        nodes
            .map((node) =>
                node.kind === 'folder'
                    ? { ...node, children: sortNodes(node.children) }
                    : node,
            )
            .sort((left, right) => {
                if (left.kind !== right.kind) {
                    return left.kind === 'folder' ? -1 : 1;
                }

                return collator.compare(left.name, right.name);
            });

    const buildFileTree = (
        files: StoredFile[],
        rootName: string,
    ): FileTreeNode[] => {
        const root: FileTreeFolder = {
            kind: 'folder',
            id: 'root',
            name: rootName,
            children: [],
        };

        for (const file of files) {
            const segments = fileSegments(file, rootName);
            let currentFolder = root;

            for (const [index, segment] of segments.entries()) {
                const isFile = index === segments.length - 1;

                if (isFile) {
                    currentFolder.children.push({
                        kind: 'file',
                        id: `file-${file.id}`,
                        name: segment,
                        file,
                    });

                    continue;
                }

                const folderId = `${currentFolder.id}/${segment}`;
                let childFolder = currentFolder.children.find(
                    (node): node is FileTreeFolder =>
                        node.kind === 'folder' && node.id === folderId,
                );

                if (!childFolder) {
                    childFolder = {
                        kind: 'folder',
                        id: folderId,
                        name: segment,
                        children: [],
                    };

                    currentFolder.children.push(childFolder);
                }

                currentFolder = childFolder;
            }
        }

        return sortNodes(root.children);
    };

    const childFileCount = (node: FileTreeFolder): number =>
        node.children.reduce(
            (count, child) =>
                count + (child.kind === 'file' ? 1 : childFileCount(child)),
            0,
        );

    const isTreeFolderExpanded = (node: FileTreeFolder): boolean =>
        expandedTreeFolders[node.id] ?? false;

    const toggleTreeFolder = (node: FileTreeFolder): void => {
        expandedTreeFolders = {
            ...expandedTreeFolders,
            [node.id]: !isTreeFolderExpanded(node),
        };
    };

    const size = $derived(`${(folder.size_bytes / 1024 / 1024).toFixed(2)} MB`);
    const changed = $derived(
        folder.updated_at
            ? new Intl.DateTimeFormat(undefined, {
                  month: 'short',
                  day: 'numeric',
                  hour: 'numeric',
                  minute: '2-digit',
              }).format(new Date(folder.updated_at))
            : 'Unknown',
    );

    const count = $derived(
        folder.files.length === 1
            ? '1 file'
            : `${folder.files.length.toLocaleString()} files`,
    );

    const deleteForm = $derived(
        folder.torrent_id
            ? destroyTorrentFolder.form(folder.torrent_id)
            : folder.media_import_id
              ? destroyMediaFolder.form(folder.media_import_id)
              : null,
    );

    const fileTree = $derived(buildFileTree(folder.files, folder.name));
</script>

<div>
    <div
        class="downloora-row grid min-h-20 w-full grid-cols-[3.5rem_minmax(0,1fr)_7rem] items-center gap-4 px-4 sm:grid-cols-[3.5rem_minmax(0,1fr)_8rem_9rem_12rem]"
    >
        <button
            type="button"
            onclick={() => (expanded = !expanded)}
            class="flex size-11 shrink-0 items-center justify-center rounded-full border-2 border-foreground bg-[var(--downloora-lime)] text-[var(--downloora-ink)] shadow-[2px_2px_0_0_var(--foreground)]"
            aria-expanded={expanded}
            title={expanded ? 'Close folder' : 'Open folder'}
        >
            <Folder class="size-8 fill-current stroke-[1.5]" />
        </button>

        <button
            type="button"
            onclick={() => (expanded = !expanded)}
            class="min-w-0 text-left"
            aria-expanded={expanded}
        >
            <span class="block truncate text-base font-medium">
                {folder.name}
            </span>
            <span
                class="block truncate text-xs font-medium text-muted-foreground"
                >{count}</span
            >
        </button>

        <span class="flex items-center justify-end gap-2 sm:justify-center">
            {#if folder.download_url}
                <a
                    href={folder.download_url}
                    class="downloora-icon-button"
                    title="Download folder as zip"
                >
                    <Download class="size-4" />
                </a>
            {/if}

            {#if deleteForm}
                <button
                    type="button"
                    onclick={() => (deleteDialogOpen = true)}
                    class="downloora-icon-button downloora-danger"
                    title="Delete folder"
                >
                    <X class="size-4" />
                </button>
            {/if}

            <button
                type="button"
                onclick={() => (expanded = !expanded)}
                class="downloora-icon-button"
                aria-expanded={expanded}
                title={expanded ? 'Close folder' : 'Open folder'}
            >
                <ChevronRight
                    class={`size-5 transition-transform ${expanded ? 'rotate-90' : ''}`}
                />
            </button>
        </span>

        <span class="hidden text-base tabular-nums sm:block">{size}</span>

        <span class="hidden text-base text-muted-foreground sm:block">
            {changed}
        </span>
    </div>

    {#if expanded}
        <div class="mt-3 space-y-3 pl-3 sm:pl-6">
            {#each fileTree as node (node.id)}
                {@render treeNode(node, 0)}
            {/each}
        </div>
    {/if}
</div>

{#snippet treeNode(node: FileTreeNode, level: number)}
    {#if node.kind === 'folder'}
        <div class="space-y-3">
            <button
                type="button"
                onclick={() => toggleTreeFolder(node)}
                class="downloora-row flex min-h-16 w-full items-center gap-3 px-4 text-left"
                style={`margin-left: ${level * 1.25}rem`}
                aria-expanded={isTreeFolderExpanded(node)}
            >
                <span
                    class="flex size-10 shrink-0 items-center justify-center rounded-full border-2 border-foreground bg-[var(--downloora-paper)] text-[var(--downloora-ink)] shadow-[2px_2px_0_0_var(--foreground)]"
                >
                    <Folder class="size-6 fill-current stroke-[1.5]" />
                </span>

                <span class="min-w-0">
                    <span class="block truncate text-base font-medium">
                        {node.name}
                    </span>
                    <span
                        class="block truncate text-xs font-medium text-muted-foreground"
                    >
                        {childFileCount(node) === 1
                            ? '1 file'
                            : `${childFileCount(node).toLocaleString()} files`}
                    </span>
                </span>

                <ChevronRight
                    class={`ml-auto size-5 shrink-0 transition-transform ${isTreeFolderExpanded(node) ? 'rotate-90' : ''}`}
                />
            </button>

            {#if isTreeFolderExpanded(node)}
                <div class="space-y-3">
                    {#each node.children as child (child.id)}
                        {@render treeNode(child, level + 1)}
                    {/each}
                </div>
            {/if}
        </div>
    {:else}
        <div style={`margin-left: ${level * 1.25}rem`}>
            <FileRow file={node.file} />
        </div>
    {/if}
{/snippet}

{#if deleteForm}
    <ConfirmDeleteDialog
        bind:open={deleteDialogOpen}
        title="Delete this folder and all files inside?"
        description={`This will permanently delete ${folder.name} and every file inside it.`}
        confirmLabel="Delete folder"
        form={deleteForm}
    />
{/if}
