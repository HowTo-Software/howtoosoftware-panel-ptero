using HowToSoftware.Pterodactyl.Domain;

namespace HowToSoftware.Pterodactyl.Sample;

/// <summary>
/// A directory with no panel behind it, for building and testing the interface.
/// </summary>
/// <remarks>
/// <para>
/// <b>This is not production data and must never be mistaken for it.</b> Three things keep that
/// true: <see cref="IsLive"/> is false and the interface prints a banner because of it, the
/// registration helper refuses to install this when a panel is configured, and every name below
/// is visibly a sample rather than a plausible customer.
/// </para>
/// <para>
/// It exists because the panel is being built before an API key is available, and because a
/// dashboard that can only be looked at when the infrastructure is up is a dashboard nobody can
/// develop. It covers every state the interface has to render, including the awkward ones -
/// suspended, installing, failed, and a server whose daemon has not answered - which a healthy
/// live panel would rarely show all at once.
/// </para>
/// </remarks>
public sealed class SampleServerDirectory : IServerDirectory
{
    private const long Gib = 1024L * 1024 * 1024;

    /// <inheritdoc />
    public bool IsLive => false;

    private static IReadOnlyList<ServerSummary> Servers { get; } =
    [
        Build("sample-01", "SAMPLE / Survival", "Minecraft Java 1.21", "sample.invalid:25565",
            ServerState.Running, memoryGb: 8, diskGb: 30, cpuLimit: 400,
            memoryUsedGb: 5.0, diskUsedGb: 14.2, cpuUsed: 236, players: new PlayerCount(38, 80),
            node: "NODE-01", location: "US-01", uptimeHours: 292),

        Build("sample-02", "SAMPLE / Knox County", "Project Zomboid", "sample.invalid:16261",
            ServerState.Running, memoryGb: 16, diskGb: 40, cpuLimit: 700,
            memoryUsedGb: 11.5, diskUsedGb: 22.0, cpuUsed: 462, players: new PlayerCount(12, 32),
            node: "NODE-01", location: "US-01", uptimeHours: 154),

        Build("sample-03", "SAMPLE / Staging", "Minecraft Paper", "sample.invalid:25577",
            ServerState.Starting, memoryGb: 4, diskGb: 25, cpuLimit: 300,
            memoryUsedGb: 1.4, diskUsedGb: 3.1, cpuUsed: 88, players: null,
            node: "NODE-02", location: "US-01", uptimeHours: 0),

        Build("sample-04", "SAMPLE / Being installed", "Rust", "sample.invalid:28015",
            ServerState.Installing, memoryGb: 10, diskGb: 40, cpuLimit: 500,
            memoryUsedGb: 0, diskUsedGb: 6.4, cpuUsed: 0, players: null,
            node: "NODE-02", location: "US-01", uptimeHours: null),

        Build("sample-05", "SAMPLE / Unpaid", "Palworld", "sample.invalid:8211",
            ServerState.Suspended, memoryGb: 6, diskGb: 25, cpuLimit: 400,
            memoryUsedGb: 0, diskUsedGb: 18.9, cpuUsed: 0, players: null,
            node: "NODE-01", location: "US-01", uptimeHours: null),

        // The two states an operator has to be able to spot instantly.
        Build("sample-06", "SAMPLE / Install failed", "Terraria", "sample.invalid:7777",
            ServerState.Failed, memoryGb: 4, diskGb: 25, cpuLimit: 300,
            memoryUsedGb: 0, diskUsedGb: 1.2, cpuUsed: 0, players: null,
            node: "NODE-02", location: "US-01", uptimeHours: null),

        Build("sample-07", "SAMPLE / Daemon silent", "Minecraft Java 1.20", "sample.invalid:25580",
            ServerState.Unknown, memoryGb: 6, diskGb: 25, cpuLimit: 400,
            memoryUsedGb: null, diskUsedGb: null, cpuUsed: null, players: null,
            node: "NODE-02", location: "US-01", uptimeHours: null),
    ];

    /// <inheritdoc />
    public Task<PanelResult<IReadOnlyList<ServerSummary>>> ListServersAsync(
        CancellationToken cancellationToken = default) =>
        Task.FromResult(PanelResult<IReadOnlyList<ServerSummary>>.Ok(Servers));

    /// <inheritdoc />
    public Task<PanelResult<ServerSummary>> GetServerAsync(
        string identifier,
        CancellationToken cancellationToken = default)
    {
        var server = Servers.FirstOrDefault(s =>
            string.Equals(s.Identifier, identifier, StringComparison.OrdinalIgnoreCase));

        return Task.FromResult(server is null
            ? PanelResult<ServerSummary>.Fail(PanelError.Create(
                PanelErrorKind.NotFound,
                "That server could not be found.",
                $"No sample server with identifier '{identifier}'."))
            : PanelResult<ServerSummary>.Ok(server));
    }

    /// <inheritdoc />
    public async Task<PanelResult<ServerResources>> GetResourcesAsync(
        string identifier,
        CancellationToken cancellationToken = default)
    {
        var server = await GetServerAsync(identifier, cancellationToken).ConfigureAwait(false);

        if (!server.IsSuccess)
        {
            return PanelResult<ServerResources>.Fail(server.Error!);
        }

        return server.Value!.Resources is { } resources
            ? PanelResult<ServerResources>.Ok(resources)
            : PanelResult<ServerResources>.Fail(PanelError.Create(
                PanelErrorKind.Upstream,
                "This server is not reporting right now.",
                $"Sample server '{identifier}' models a daemon that has not answered."));
    }

    /// <inheritdoc />
    public Task<PanelResult<AccountOverview>> GetOverviewAsync(
        CancellationToken cancellationToken = default)
    {
        var online = Servers.Count(s => s.State is ServerState.Running);

        // Summed from the servers rather than stated, so the header cannot disagree with the
        // cards under it.
        var reporting = Servers.Where(s => s.Players is not null).ToList();
        var players = reporting.Count == 0 ? (int?)null : reporting.Sum(s => s.Players!.Value.Connected);

        var memory = Servers.Sum(s => s.Resources?.MemoryBytes.Limit ?? 0);
        var disk = Servers.Sum(s => s.Resources?.DiskBytes.Limit ?? 0);

        return Task.FromResult(PanelResult<AccountOverview>.Ok(new AccountOverview(
            TotalServers: Servers.Count,
            OnlineServers: online,
            ConnectedPlayers: players,
            Memory: new Measure(memory, 96 * Gib),
            Disk: new Measure(disk, 500 * Gib),
            NeedsAttention: [.. Servers.Where(s => s.State.NeedsAttention())])));
    }

    private static ServerSummary Build(
        string identifier,
        string name,
        string game,
        string address,
        ServerState state,
        int memoryGb,
        int diskGb,
        int cpuLimit,
        double? memoryUsedGb,
        double? diskUsedGb,
        int? cpuUsed,
        PlayerCount? players,
        string node,
        string location,
        double? uptimeHours)
    {
        // A server whose daemon has not answered has no reading at all - not a reading of zero.
        var resources = memoryUsedGb is null || diskUsedGb is null || cpuUsed is null
            ? null
            : new ServerResources(
                new Measure((long)(memoryUsedGb.Value * Gib), memoryGb * Gib),
                new Measure((long)(diskUsedGb.Value * Gib), diskGb * Gib),
                new Measure(cpuUsed.Value, cpuLimit),
                NetworkRxBytes: 0,
                NetworkTxBytes: 0,
                UptimeMilliseconds: uptimeHours is null or 0
                    ? null
                    : (long)(uptimeHours.Value * 3_600_000));

        return new ServerSummary(
            identifier,
            // Derived from the identifier so a sample server keeps the same id across restarts.
            Uuid: DeterministicUuid(identifier),
            name,
            game,
            address,
            state,
            resources,
            players,
            node,
            location,
            // The HowToo server bot is offered above 5 GB. Read from the size rather than set
            // per row, for the same reason it is on the marketing site: a flag on the wrong row
            // advertises software the server is not offered.
            HasServerBot: memoryGb > 5);
    }

    private static Guid DeterministicUuid(string identifier)
    {
        var bytes = System.Security.Cryptography.MD5.HashData(
            System.Text.Encoding.UTF8.GetBytes("hts-sample:" + identifier));

        return new Guid(bytes);
    }
}

// =============================================================
// © 2026 HowToo Software. All rights reserved.
// =============================================================
