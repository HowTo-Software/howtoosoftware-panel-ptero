namespace HowToSoftware.Pterodactyl.Domain;

/// <summary>
/// The state of a game server, as this product talks about it.
/// </summary>
/// <remarks>
/// <para>
/// One vocabulary for the whole application. Pterodactyl describes a server through several
/// fields that can disagree - <c>current_state</c> from the daemon, and the panel's own
/// <c>is_suspended</c>, <c>is_installing</c>, <c>is_transferring</c> and
/// <c>is_node_under_maintenance</c> flags - and a UI that reads them ad hoc ends up showing
/// ONLINE on a dashboard card and OFFLINE on the server page for the same machine.
/// </para>
/// <para>
/// Resolution happens in exactly one place (<see cref="ServerStateResolver"/>). Components are
/// given the answer, never the inputs.
/// </para>
/// </remarks>
public enum ServerState
{
    /// <summary>
    /// The daemon has not reported and nothing overrides that.
    /// </summary>
    /// <remarks>
    /// Distinct from <see cref="Offline"/> on purpose. "We do not know" and "we know it is off"
    /// look identical to a naive mapping and are very different to a customer whose node has
    /// just dropped off the network.
    /// </remarks>
    Unknown = 0,

    /// <summary>Installing, or reinstalling. The server cannot be started.</summary>
    Installing,

    /// <summary>Booting.</summary>
    Starting,

    /// <summary>Up.</summary>
    Running,

    /// <summary>Shutting down.</summary>
    Stopping,

    /// <summary>Down, and known to be down.</summary>
    Offline,

    /// <summary>Suspended by an administrator or by billing. The customer cannot start it.</summary>
    Suspended,

    /// <summary>Being moved between nodes.</summary>
    Transferring,

    /// <summary>A backup is being restored over it.</summary>
    Restoring,

    /// <summary>The last installation failed. Needs attention.</summary>
    Failed,
}

/// <summary>
/// What a state means for the controls the customer is offered.
/// </summary>
public static class ServerStateExtensions
{
    /// <summary>Whether a power signal can be sent at all.</summary>
    /// <param name="state">The resolved state.</param>
    /// <returns><see langword="true"/> when power controls should be enabled.</returns>
    /// <remarks>
    /// A suspended or installing server rejects power signals at the panel, so offering the
    /// button only produces an error the customer cannot act on.
    /// </remarks>
    public static bool AcceptsPowerSignals(this ServerState state) => state switch
    {
        ServerState.Suspended or ServerState.Installing or ServerState.Transferring
            or ServerState.Restoring or ServerState.Failed => false,
        _ => true,
    };

    /// <summary>Whether the console can be attached.</summary>
    public static bool HasConsole(this ServerState state) =>
        state is not (ServerState.Suspended or ServerState.Transferring);

    /// <summary>Whether this state is one an operator should look at.</summary>
    public static bool NeedsAttention(this ServerState state) =>
        state is ServerState.Failed or ServerState.Unknown;

    /// <summary>Whether the server is doing something that will finish on its own.</summary>
    /// <remarks>Used to decide whether the UI should keep refreshing.</remarks>
    public static bool IsTransient(this ServerState state) => state switch
    {
        ServerState.Starting or ServerState.Stopping or ServerState.Installing
            or ServerState.Transferring or ServerState.Restoring => true,
        _ => false,
    };
}

// =============================================================
// © 2026 HowToo Software. All rights reserved.
// =============================================================
