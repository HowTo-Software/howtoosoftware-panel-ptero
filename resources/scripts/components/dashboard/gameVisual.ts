import { Server } from '@/api/server/getServer';

export type GameVisualKey = 'minecraft' | 'zomboid' | 'ark' | 'terraria' | 'cs' | 'default';

export const GAME_VISUALS: Record<GameVisualKey, { label: string; icon: string; background: string }> = {
    minecraft: {
        label: 'Minecraft',
        icon: '/themes/howtoo/games/Mine1.png',
        background: '/themes/howtoo/games/Mine2.png',
    },
    zomboid: {
        label: 'Project Zomboid',
        icon: '/themes/howtoo/games/Zomboid1.png',
        background: '/themes/howtoo/games/Zomboid2.png',
    },
    ark: {
        label: 'ARK',
        icon: '/themes/howtoo/games/Ark1.png',
        background: '/themes/howtoo/games/Ark2.png',
    },
    terraria: {
        label: 'Terraria',
        icon: '/themes/howtoo/games/Terraria1.png',
        background: '/themes/howtoo/games/Terraria2.png',
    },
    cs: {
        label: 'Counter-Strike 2',
        icon: '/themes/howtoo/games/CS1.png',
        background: '/themes/howtoo/games/CS2.png',
    },
    default: {
        label: 'Game server',
        icon: '/themes/howtoo/games/Default1.png',
        background: '/themes/howtoo/games/Default2.png',
    },
};

type GameMetadata = Partial<Pick<Server, 'eggName' | 'name' | 'description' | 'dockerImage'>>;

const normalize = (value: string): string =>
    value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase();

const detectIn = (value: string): GameVisualKey | undefined => {
    const text = normalize(value);
    if (/project[\s-]*zomboid|\bzomboid\b/.test(text)) return 'zomboid';
    if (/counter[\s-]*strike\s*2|\bcs2\b/.test(text)) return 'cs';
    if (/\bminecraft\b|\bpaper\b|\bspigot\b|\bpurpur\b|\bfabric\b/.test(text)) return 'minecraft';
    if (/\bark\b|ark[:\s-]+survival|survival evolved|survival ascended/.test(text)) return 'ark';
    if (/\bterraria\b/.test(text)) return 'terraria';

    return undefined;
};

export const detectGameVisual = (server: GameMetadata): GameVisualKey => {
    const egg = server.eggName ? detectIn(server.eggName) : undefined;
    if (egg) return egg;

    const metadataFallback = detectIn(`${server.description || ''} ${server.name || ''}`);
    if (metadataFallback) return metadataFallback;

    return (server.dockerImage && detectIn(server.dockerImage)) || 'default';
};

export const resolveGameVisuals = (server: GameMetadata) => {
    const key = detectGameVisual(server);
    return { key, ...GAME_VISUALS[key] };
};
