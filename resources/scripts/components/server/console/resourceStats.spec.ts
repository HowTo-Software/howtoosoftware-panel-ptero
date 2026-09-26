import { parseResourceStats } from './resourceStats';

describe('parseResourceStats', () => {
    it('parses a normal Wings stats event', () => {
        expect(
            parseResourceStats(
                JSON.stringify({
                    memory_bytes: 1024,
                    cpu_absolute: 12.5,
                    disk_bytes: 2048,
                    network: { rx_bytes: 300, tx_bytes: 400 },
                    uptime: 5000,
                })
            )
        ).toEqual({ memory: 1024, cpu: 12.5, disk: 2048, rx: 300, tx: 400, uptime: 5000 });
    });

    it('keeps valid metrics when another metric is absent or invalid', () => {
        expect(parseResourceStats({ memory_bytes: 2048, cpu_absolute: '18.25', disk_bytes: null })).toEqual({
            memory: 2048,
            cpu: 18.25,
        });
    });

    it('parses the Panel resources response envelope', () => {
        expect(
            parseResourceStats({
                data: {
                    attributes: {
                        resources: {
                            memory_bytes: 4096,
                            cpu_absolute: 5,
                            disk_bytes: 8192,
                            network_rx_bytes: 2,
                            network_tx_bytes: 3,
                            uptime: 1000,
                        },
                    },
                },
            })
        ).toEqual({ memory: 4096, cpu: 5, disk: 8192, rx: 2, tx: 3, uptime: 1000 });
    });

    it('ignores malformed input instead of replacing existing counters', () => {
        expect(parseResourceStats('{not valid json')).toEqual({});
        expect(parseResourceStats({ cpu_absolute: 'unknown', memory_bytes: null })).toEqual({});
    });
});
