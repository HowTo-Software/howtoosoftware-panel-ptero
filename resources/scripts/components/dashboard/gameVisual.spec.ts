import { detectGameVisual } from '@/components/dashboard/gameVisual';

describe('game artwork detection', () => {
    it('prioritizes egg metadata over custom server names', () => {
        expect(detectGameVisual({ eggName: 'Project Zomboid', name: 'My survival server' })).toBe('zomboid');
    });

    it('recognizes supported game names and common egg labels', () => {
        expect(detectGameVisual({ eggName: 'Minecraft Java' })).toBe('minecraft');
        expect(detectGameVisual({ eggName: 'Bedrock Dedicated Server' })).toBe('minecraft');
        expect(detectGameVisual({ eggName: 'PocketMine-MP' })).toBe('minecraft');
        expect(detectGameVisual({ description: 'ARK: Survival Ascended' })).toBe('ark');
        expect(detectGameVisual({ name: 'Terraria community' })).toBe('terraria');
        expect(detectGameVisual({ name: 'Counter Strike 2 server' })).toBe('cs');
    });

    it('uses default artwork when the game cannot be identified', () => {
        expect(detectGameVisual({ name: 'Valheim server' })).toBe('default');
    });
});
