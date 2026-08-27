namespace HowToSoftware.Pterodactyl.Domain;

/// <summary>
/// What went wrong, in the two registers it has to be told in.
/// </summary>
/// <param name="Kind">The class of failure, for code to branch on.</param>
/// <param name="UserMessage">
/// What the customer is shown. Says what happened and what they can do; never mentions an HTTP
/// status, a stack frame or an internal identifier.
/// </param>
/// <param name="TechnicalMessage">What goes in the log. Never shown in the interface.</param>
/// <param name="CorrelationId">
/// Ties the two together. The customer is given this and only this, so support can find the log
/// line without the customer having to describe the error.
/// </param>
/// <param name="UpstreamStatus">The HTTP status Pterodactyl returned, when there was one.</param>
/// <remarks>
/// <para>
/// Raw upstream errors do not reach the interface. A Pterodactyl 422 with a validation bag names
/// its own field names and its own rules, which are internal vocabulary; a 500 may carry a stack
/// trace. Both are useless to a customer and one of them is a disclosure.
/// </para>
/// <para>
/// The rule this type exists to enforce: <b>if it came from upstream, it is technical until
/// something deliberately translates it.</b>
/// </para>
/// </remarks>
public sealed record PanelError(
    PanelErrorKind Kind,
    string UserMessage,
    string TechnicalMessage,
    string CorrelationId,
    int? UpstreamStatus = null)
{
    /// <summary>Whether retrying the same request could plausibly succeed.</summary>
    public bool IsTransient => Kind is
        PanelErrorKind.Unreachable or PanelErrorKind.Timeout or
        PanelErrorKind.RateLimited or PanelErrorKind.Upstream;

    /// <summary>Creates an error with a fresh correlation id.</summary>
    /// <param name="kind">The class of failure.</param>
    /// <param name="userMessage">What the customer sees.</param>
    /// <param name="technicalMessage">What the log gets.</param>
    /// <param name="upstreamStatus">The upstream HTTP status, when there was one.</param>
    /// <returns>The error.</returns>
    public static PanelError Create(
        PanelErrorKind kind,
        string userMessage,
        string technicalMessage,
        int? upstreamStatus = null) =>
        new(kind, userMessage, technicalMessage, NewCorrelationId(), upstreamStatus);

    /// <summary>A short, readable, unambiguous id a customer can quote over the phone.</summary>
    /// <remarks>
    /// Crockford's alphabet: no I, L, O or U, so it cannot be misread as a digit and cannot
    /// accidentally spell anything.
    /// </remarks>
    private static string NewCorrelationId()
    {
        const string alphabet = "0123456789ABCDEFGHJKMNPQRSTVWXYZ";
        var bytes = new byte[8];
        System.Security.Cryptography.RandomNumberGenerator.Fill(bytes);

        return string.Create(8, bytes, static (span, source) =>
        {
            for (var i = 0; i < span.Length; i++)
            {
                span[i] = alphabet[source[i] % alphabet.Length];
            }
        });
    }
}

/// <summary>
/// The classes of failure the interface needs to tell apart.
/// </summary>
/// <remarks>
/// Deliberately not a mirror of HTTP status codes. A 403 from Pterodactyl and a 403 from our own
/// authorisation check mean very different things to a customer, and the interface has to say
/// different things about them.
/// </remarks>
public enum PanelErrorKind
{
    /// <summary>Nothing more specific applies.</summary>
    Unknown = 0,

    /// <summary>The panel could not be reached at all - DNS, TLS, connection refused.</summary>
    Unreachable,

    /// <summary>The panel did not answer in time.</summary>
    Timeout,

    /// <summary>Our credentials were rejected. A configuration fault, not the customer's.</summary>
    NotConfigured,

    /// <summary>The viewer is not signed in, or their session expired.</summary>
    NotAuthenticated,

    /// <summary>The viewer is signed in but may not do this.</summary>
    Forbidden,

    /// <summary>The thing referred to does not exist, or is not theirs to see.</summary>
    NotFound,

    /// <summary>The request was well-formed but the values were rejected.</summary>
    Validation,

    /// <summary>The action conflicts with the current state - already exists, wrong state.</summary>
    Conflict,

    /// <summary>Too many requests. Carries a retry hint where upstream supplied one.</summary>
    RateLimited,

    /// <summary>Pterodactyl or the daemon failed internally.</summary>
    Upstream,
}

/// <summary>
/// A result that is either a value or an error, without exceptions for expected failures.
/// </summary>
/// <typeparam name="T">The value type.</typeparam>
/// <remarks>
/// An unreachable panel, a rejected credential and a suspended server are all things that happen
/// in normal operation. Making each one an exception means the interesting cases travel the same
/// path as the bugs, and the compiler stops helping.
/// </remarks>
public readonly record struct PanelResult<T>
{
    private PanelResult(T? value, PanelError? error)
    {
        Value = value;
        Error = error;
    }

    /// <summary>The value, when the call succeeded.</summary>
    public T? Value { get; }

    /// <summary>The error, when it did not.</summary>
    public PanelError? Error { get; }

    /// <summary>Whether the call succeeded.</summary>
    public bool IsSuccess => Error is null;

    /// <summary>Wraps a value.</summary>
    /// <param name="value">The value.</param>
    /// <returns>A successful result.</returns>
    public static PanelResult<T> Ok(T value) => new(value, null);

    /// <summary>Wraps an error.</summary>
    /// <param name="error">The error.</param>
    /// <returns>A failed result.</returns>
    public static PanelResult<T> Fail(PanelError error) => new(default, error);
}

// =============================================================
// © 2026 HowToo Software. All rights reserved.
// =============================================================
