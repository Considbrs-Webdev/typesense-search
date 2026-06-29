export interface SortableList {
    destroy(): void;
}

export function createSortableList(
    container: HTMLElement,
    itemSelector: string,
    onMove: (fromIndex: number, toIndex: number) => void,
): SortableList {
    let dragIndex: number | null = null;

    function onDragStart(e: DragEvent): void {
        const item = (e.target as HTMLElement).closest<HTMLElement>(itemSelector);
        if (!item) return;
        dragIndex = Number(item.dataset.index);
        e.dataTransfer?.setData('text/plain', String(dragIndex));
    }

    function onDragOver(e: DragEvent): void {
        if ((e.target as HTMLElement).closest(itemSelector)) {
            e.preventDefault();
        }
    }

    function onDrop(e: DragEvent): void {
        const item = (e.target as HTMLElement).closest<HTMLElement>(itemSelector);
        if (!item || dragIndex === null) return;
        e.preventDefault();
        const toIndex = Number(item.dataset.index);
        if (dragIndex !== toIndex) {
            onMove(dragIndex, toIndex);
        }
        dragIndex = null;
    }

    container.addEventListener('dragstart', onDragStart);
    container.addEventListener('dragover', onDragOver);
    container.addEventListener('drop', onDrop);

    return {
        destroy() {
            container.removeEventListener('dragstart', onDragStart);
            container.removeEventListener('dragover', onDragOver);
            container.removeEventListener('drop', onDrop);
        },
    };
}
