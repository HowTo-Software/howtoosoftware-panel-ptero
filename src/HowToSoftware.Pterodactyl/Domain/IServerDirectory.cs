namespace HowToSoftware.Pterodactyl.Domain;

/// <summary>
/// Everything the dashboard needs about the servers a viewer can see.
/// </summary>
/// <remarks>
/// <para>
/// The seam between the interface and Pterodactyl. Components depend on this and on the domain
/// types it returns; nothing in <c>Components/</c> knows that Pterodactyl exists, which is what
/// lets the panel be built and tested before a key is available, and what will let the transport
/// underneath change without touching a screen.
/// </para>
/// <para>
/// Every method returns <see cref="PanelResult{T}"/> rather than throwing. An unreachable panel
/// is a normal Tuesday, and the dashboard has to render something sensible when it happens.
/// </para>
/// </remarks>
public interface IServerDirectory
{
    /// <summary>
    /// Whether this directory is backed by a real panel.
    /// </summary>
    /// <remarks>
    /// The interface uses this to say so on screen. A demonstration source that did not announce
    /// itself would be indistinguishable from a broken production one, and somebody would
    /// eventually make a decision from invented numbers.
    /// </remarks>
    bool IsLive { get; }

    /// <summary>
    /// The servers the viewer may see.
    /// </summary>
    /// <param name="cancellationToken">Cancels the request.</param>
    /// <returns>The servers, in a stable order.</returns>
    /// <remarks>
    /// Returns summaries only. Live resource readings for every server are a second call, because
    /// on an account with a hundred servers they are a hundred round trips to the daemon and the
    /// list has to render before they finish.
    /// </remarks>
    Task<PanelResult<IReadOnlyList<ServerSummary>>> ListServersAsync(
        CancellationToken cancellationToken = default);

    /// <summary>
    /// One server in full.
    /// </summary>
    /// <param name="identifier">The short identifier from the URL.</param>
    /// <param name="cancellationToken">Cancels the request.</param>
    /// <returns>The server, or an error.</returns>
    Task<PanelResult<ServerSummary>> GetServerAsync(
        string identifier,
        CancellationToken cancellationToken = default);

    /// <summary>
    /// Live consumption for one server.
    /// </summary>
    /// <param name="identifier">The short identifier.</param>
    /// <param name="cancellationToken">Cancels the request.</param>
    /// <returns>The reading, or an error.</returns>
    Task<PanelResult<ServerResources>> GetResourcesAsync(
        string identifier,
        CancellationToken cancellationToken = default);

    /// <summary>
    /// The figures across the top of the dashboard.
    /// </summary>
    /// <param name="cancellationToken">Cancels the request.</param>
    /// <returns>The overview, or an error.</returns>
    Task<PanelResult<AccountOverview>> GetOverviewAsync(
        CancellationToken cancellationToken = default);
}

/// <summary>
/// The power signals a server can be sent.
/// </summary>
/// <remarks>
/// Named for what they do rather than for the string Pterodactyl takes, so that a component
/// asking for <see cref="Kill"/> reads as the dangerous thing it is.
/// </remarks>
public enum PowerSignal
{
    /// <summary>Boot it.</summary>
    Start,

    /// <summary>Ask it to shut down, and let it save first.</summary>
    Stop,

    /// <summary>Stop, then start.</summary>
    Restart,

    /// <summary>
    /// Terminate the container immediately.
    /// </summary>
    /// <remarks>
    /// The process gets no chance to save. On a game server that means losing whatever has
    /// happened since the last write, which on some games is hours of a community's play. Every
    /// surface that offers this must make it visually distinct from <see cref="Stop"/> and must
    /// confirm.
    /// </remarks>
    Kill,
}

/// <summary>
/// Acting on a server.
/// </summary>
public interface IServerControl
{
    /// <summary>
    /// Sends a power signal.
    /// </summary>
    /// <param name="identifier">The short identifier.</param>
    /// <param name="signal">What to do.</param>
    /// <param name="cancellationToken">Cancels the request.</param>
    /// <returns>Success, or the reason it was refused.</returns>
    /// <remarks>
    /// Authorisation is Pterodactyl's, not ours: the call is made with the viewer's own
    /// credentials, so a viewer without the permission is refused by the panel even if our
    /// interface wrongly offered the button. Hiding a control is presentation; this is the
    /// enforcement.
    /// </remarks>
    Task<PanelResult<bool>> SendPowerAsync(
        string identifier,
        PowerSignal signal,
        CancellationToken cancellationToken = default);
}

// =============================================================
// © 2026 HowToo Software. All rights reserved.
// =============================================================
