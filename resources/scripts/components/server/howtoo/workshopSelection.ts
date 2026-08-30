import { WorkshopConfiguration, WorkshopItem } from '@/api/server/howtoo';

export const uniqueWorkshopValues = (values: string[]): string[] =>
    Array.from(new Set(values.map((value) => value.trim()).filter(Boolean)));

export const appendWorkshopResults = (current: WorkshopItem[], incoming: WorkshopItem[]): WorkshopItem[] =>
    Array.from(new Map([...current, ...incoming].map((item) => [item.workshopId, item])).values());

export const hasWorkshopChanges = (
    configuration: WorkshopConfiguration | undefined,
    workshopItems: string[],
    mods: string[]
): boolean =>
    !!configuration &&
    (JSON.stringify(workshopItems) !== JSON.stringify(configuration.workshopItems) ||
        JSON.stringify(mods) !== JSON.stringify(configuration.mods));

export const addWorkshopSelection = (
    workshopItems: string[],
    mods: string[],
    item: WorkshopItem
): { workshopItems: string[]; mods: string[] } => ({
    workshopItems: uniqueWorkshopValues([...workshopItems, item.workshopId]),
    mods: uniqueWorkshopValues([...mods, ...item.modIds]),
});

export const removeWorkshopSelection = (
    workshopItems: string[],
    mods: string[],
    workshopId: string,
    details: Map<string, WorkshopItem>
): { workshopItems: string[]; mods: string[] } => {
    const item = details.get(workshopId);
    const remainingItems = workshopItems.filter((id) => id !== workshopId);
    if (!item?.modIds.length) return { workshopItems: remainingItems, mods };

    const stillUsed = new Set(remainingItems.flatMap((id) => details.get(id)?.modIds || []));
    return {
        workshopItems: remainingItems,
        mods: mods.filter((id) => !item.modIds.includes(id) || stillUsed.has(id)),
    };
};
