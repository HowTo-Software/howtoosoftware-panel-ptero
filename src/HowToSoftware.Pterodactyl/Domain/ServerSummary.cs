using System.Collections.Generic;

namespace HowToSoftware.Pterodactyl.Domain;

/// <summary>
/// One server, as a dashboard card needs it.
/// </summary>
/// <param name="Identifier">
/// The short identifier Pterodactyl puts in URLs. This is what every Client API path takes.
/// </param>
/// <param name="Uuid">The full UUID. Used for correlation and admin lookups, not for routing.</param>
/// <param name="Name">The customer's name for the server.</param>
/// <param name="Game">What is running on it, e.g. "Minecraft Java 1.21". May be null.</param>
/// <param name="Address">The primary allocation as host:port, for the customer to connect to.</param>
/// <param name="State">The one resolved state.</param>
/// <param name="Resources">Live consumption, or null when the daemon has not reported.</param>
/// <param name="Players">Connected and maximum players, when the game exposes it.</param>
/// <param name="NodeName">Which node it is on.</param>
/// <param name="LocationCode">Where that node is, e.g. "US-01".</param>
/// <param name="HasServerBot">Whether the HowToo server bot is offered on this server.</param>
/// <remarks>
/// A view model in the sense that it is shaped for a screen, but a domain type in the sense
/// that it contains no Pterodactyl vocabulary. Components consume this; nothing in
/// <c>Components/</c> ever sees a Pterodactyl DTO.
/// </remarks>
public sealed record ServerSummary(
    string Identifier,
    Guid Uuid,
    string Name,
    string? Game,
    string? Address,
    ServerState State,
    ServerResources? Resources,
    PlayerCount? Players,
    string? NodeName,
    string? LocationCode,
    bool HasServerBot);

/// <summary>
/// Players on a server.
/// </summary>
/// <param name="Connected">How many are on now.</param>
/// <param name="Maximum">The configured cap, or null when the game does not report one.</param>
/// <remarks>
/// <b>Pterodactyl does not know this.</b> Player counts come from the game, not from the panel
/// or the daemon - they have to be read by querying the server directly, and not every game
/// answers. A null here means "we could not ask", and the UI must say that rather than print 0.
/// </remarks>
public readonly record struct PlayerCount(int Connected, int? Maximum);

/// <summary>
/// The account-wide figures across the top of the dashboard.
/// </summary>
/// <param name="TotalServers">Servers the viewer can see.</param>
/// <param name="OnlineServers">How many are running right now.</param>
/// <param name="ConnectedPlayers">
/// Players across every server that reports one, or null when none of them do.
/// </param>
/// <param name="Memory">Memory allocated across those servers, against what the account may use.</param>
/// <param name="Disk">Disk allocated, against what the account may use.</param>
/// <param name="NeedsAttention">Servers in a state an operator should look at.</param>
/// <remarks>
/// Memory and disk here are <b>allocations</b>, not live consumption - it is the sum of what the
/// servers were promised. The card says "allocated" for that reason.
/// </remarks>
public sealed record AccountOverview(
    int TotalServers,
    int OnlineServers,
    int? ConnectedPlayers,
    Measure Memory,
    Measure Disk,
    IReadOnlyList<ServerSummary> NeedsAttention);

// =============================================================
// © 2026 HowToo Software. All rights reserved.
// =============================================================
