namespace HowToSoftware.Pterodactyl.Domain;

/// <summary>
/// The panel's flags and the daemon's reported state, reduced to one answer.
/// </summary>
/// <remarks>
/// <para>
/// This exists because Pterodactyl reports a server's condition through two sources that do not
/// agree and are not ordered for you. The panel database knows whether a server is suspended,
/// installing, transferring or on a node in maintenance; the daemon knows whether the process is
/// running. A server can be suspended AND report <c>running</c>, because suspension stops the
/// customer from acting on it rather than immediately killing the container.
/// </para>
/// <para>
/// Precedence below is deliberate and is the whole point of the class: a condition that removes
/// the customer's control outranks whatever the process happens to be doing.
/// </para>
/// </remarks>
public static class ServerStateResolver
{
    /// <summary>
    /// Resolves the state to show.
    /// </summary>
    /// <param name="daemonState">
    /// <c>current_state</c> as the daemon reported it, or <see langword="null"/> when the daemon
    /// has not answered. Case-insensitive.
    /// </param>
    /// <param name="isSuspended">The panel's <c>is_suspended</c>.</param>
    /// <param name="isInstalling">The panel's <c>is_installing</c>.</param>
    /// <param name="isTransferring">The panel's <c>is_transferring</c>.</param>
    /// <param name="isRestoring">Whether a backup restore is in progress.</param>
    /// <param name="installFailed">Whether the last install ended in failure.</param>
    /// <returns>The single state the whole application will use.</returns>
    public static ServerState Resolve(
        string? daemonState,
        bool isSuspended = false,
        bool isInstalling = false,
        bool isTransferring = false,
        bool isRestoring = false,
        bool installFailed = false)
    {
        // Order matters. Each of these removes the customer's ability to act, whatever the
        // process is doing, so each outranks the daemon's opinion.
        if (isSuspended)
        {
            return ServerState.Suspended;
        }

        if (installFailed)
        {
            return ServerState.Failed;
        }

        if (isTransferring)
        {
            return ServerState.Transferring;
        }

        if (isRestoring)
        {
            return ServerState.Restoring;
        }

        if (isInstalling)
        {
            return ServerState.Installing;
        }

        return FromDaemon(daemonState);
    }

    /// <summary>
    /// Maps the daemon's <c>current_state</c> string on its own.
    /// </summary>
    /// <param name="daemonState">The reported state, or <see langword="null"/>.</param>
    /// <returns>The matching state, or <see cref="ServerState.Unknown"/>.</returns>
    /// <remarks>
    /// An unrecognised string becomes <see cref="ServerState.Unknown"/> rather than
    /// <see cref="ServerState.Offline"/>. A future daemon that reports a state we have not seen
    /// must not be rendered as "your server is off".
    /// </remarks>
    public static ServerState FromDaemon(string? daemonState) =>
        daemonState?.Trim().ToLowerInvariant() switch
        {
            "running" => ServerState.Running,
            "starting" => ServerState.Starting,
            "stopping" => ServerState.Stopping,
            "offline" => ServerState.Offline,
            _ => ServerState.Unknown,
        };
}

// =============================================================
// © 2026 HowToo Software. All rights reserved.
// =============================================================
