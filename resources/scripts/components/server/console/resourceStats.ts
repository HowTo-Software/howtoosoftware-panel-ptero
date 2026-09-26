export type ResourceStatKey = 'memory' | 'cpu' | 'disk' | 'uptime' | 'rx' | 'tx';
export type ResourceStats = Record<ResourceStatKey, number>;
export type ResourceStatsPatch = Partial<ResourceStats>;

type JsonRecord = Record<string, unknown>;

const isRecord = (value: unknown): value is JsonRecord =>
    typeof value === 'object' && value !== null && !Array.isArray(value);

const firstFiniteNumber = (source: JsonRecord, keys: string[]): number | undefined => {
    for (const key of keys) {
        const value = source[key];
        if (value === null || value === undefined || (typeof value === 'string' && value.trim() === '')) continue;

        const parsed = typeof value === 'number' ? value : Number(value);
        if (Number.isFinite(parsed)) return parsed;
    }

    return undefined;
};

/** Extracts only valid resource counters from Wings websocket or Panel API payloads. */
export const parseResourceStats = (payload: unknown): ResourceStatsPatch => {
    let parsed = payload;
    if (typeof parsed === 'string') {
        try {
            parsed = JSON.parse(parsed);
        } catch {
            return {};
        }
    }

    if (!isRecord(parsed)) return {};

    const data = isRecord(parsed.data) ? parsed.data : undefined;
    const attributes =
        (data && isRecord(data.attributes) ? data.attributes : undefined) ||
        (isRecord(parsed.attributes) ? parsed.attributes : parsed);
    const source = isRecord(attributes.resources)
        ? attributes.resources
        : isRecord(attributes.utilization)
        ? attributes.utilization
        : attributes;
    const network = isRecord(source.network) ? source.network : isRecord(attributes.network) ? attributes.network : {};

    const values: ResourceStatsPatch = {};
    const memory = firstFiniteNumber(source, ['memory_bytes', 'memoryUsageInBytes', 'memory_usage_bytes']);
    const cpu = firstFiniteNumber(source, ['cpu_absolute', 'cpuUsagePercent', 'cpu_usage_percent']);
    const disk = firstFiniteNumber(source, ['disk_bytes', 'diskUsageInBytes', 'disk_usage_bytes']);
    const rx =
        firstFiniteNumber(source, ['network_rx_bytes', 'networkRxInBytes']) ??
        firstFiniteNumber(network, ['rx_bytes', 'rx']);
    const tx =
        firstFiniteNumber(source, ['network_tx_bytes', 'networkTxInBytes']) ??
        firstFiniteNumber(network, ['tx_bytes', 'tx']);
    const uptime = firstFiniteNumber(source, ['uptime']);

    if (memory !== undefined) values.memory = memory;
    if (cpu !== undefined) values.cpu = cpu;
    if (disk !== undefined) values.disk = disk;
    if (rx !== undefined) values.rx = rx;
    if (tx !== undefined) values.tx = tx;
    if (uptime !== undefined) values.uptime = uptime;

    return values;
};
