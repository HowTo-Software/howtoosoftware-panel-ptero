namespace HowToSoftware.Pterodactyl.Domain;

/// <summary>
/// A measured amount against the ceiling it is measured against.
/// </summary>
/// <param name="Used">What is currently consumed.</param>
/// <param name="Limit">
/// The ceiling, or <see langword="null"/> when the resource is unlimited - Pterodactyl uses 0
/// to mean unlimited, which is not a limit of zero and must never be rendered as one.
/// </param>
/// <remarks>
/// <para>
/// Usage and limit stay separate all the way to the component. Collapsing them into a single
/// percentage early is how a panel ends up unable to answer "11.2 GB of what?", and how an
/// unlimited resource ends up displayed as 100% full.
/// </para>
/// </remarks>
public readonly record struct Measure(long Used, long? Limit)
{
    /// <summary>Whether the resource has no ceiling.</summary>
    public bool IsUnlimited => Limit is null or 0;

    /// <summary>
    /// Consumption as a fraction of the limit, or <see langword="null"/> when there is none.
    /// </summary>
    /// <remarks>
    /// Not clamped. A container is allowed to exceed a soft limit, and a bar that silently
    /// stopped at 100% would hide exactly the situation an operator needs to see.
    /// </remarks>
    public double? Fraction => IsUnlimited ? null : (double)Used / Limit!.Value;

    /// <summary>Creates a measure from a limit where 0 means unlimited.</summary>
    /// <param name="used">Current consumption.</param>
    /// <param name="limit">The limit as Pterodactyl reports it, where 0 is unlimited.</param>
    /// <returns>The measure.</returns>
    public static Measure FromPterodactyl(long used, long limit) =>
        new(used, limit == 0 ? null : limit);
}

/// <summary>
/// What a running server is currently consuming.
/// </summary>
/// <param name="MemoryBytes">Memory in bytes, against the container limit.</param>
/// <param name="DiskBytes">Disk in bytes, against the container limit.</param>
/// <param name="CpuPercent">
/// CPU as a percentage of ONE logical thread, against the container's percentage limit.
/// </param>
/// <param name="NetworkRxBytes">Bytes received since the container started.</param>
/// <param name="NetworkTxBytes">Bytes sent since the container started.</param>
/// <param name="UptimeMilliseconds">How long the process has been up, or null when it is not.</param>
/// <remarks>
/// <para>
/// <b>CPU is a share, not a core count.</b> Pterodactyl's cpu limit is a percentage of one
/// thread: 100 is one thread's worth, 400 lets a container burst across four. It does not pin
/// cores. Any label this feeds must say "400% allocation" and never "4 dedicated cores".
/// </para>
/// <para>
/// Everything here is a live reading. It is not the same thing as what a plan allocated, and
/// the two must never be added together or shown in the same bar.
/// </para>
/// </remarks>
public sealed record ServerResources(
    Measure MemoryBytes,
    Measure DiskBytes,
    Measure CpuPercent,
    long NetworkRxBytes,
    long NetworkTxBytes,
    long? UptimeMilliseconds)
{
    /// <summary>A reading for a server that is not running.</summary>
    public static ServerResources Idle(long memoryLimitBytes, long diskLimitBytes, long cpuLimitPercent) =>
        new(new Measure(0, memoryLimitBytes == 0 ? null : memoryLimitBytes),
            new Measure(0, diskLimitBytes == 0 ? null : diskLimitBytes),
            new Measure(0, cpuLimitPercent == 0 ? null : cpuLimitPercent),
            0,
            0,
            null);
}

/// <summary>
/// What a node has committed versus what it is actually using.
/// </summary>
/// <param name="AllocatedMemoryBytes">Memory promised to servers on this node.</param>
/// <param name="TotalMemoryBytes">Memory the node has, before overallocation.</param>
/// <param name="AllocatedDiskBytes">Disk promised to servers on this node.</param>
/// <param name="TotalDiskBytes">Disk the node has, before overallocation.</param>
/// <param name="MemoryOverallocatePercent">How far memory may be oversubscribed, -1 for unlimited.</param>
/// <param name="DiskOverallocatePercent">How far disk may be oversubscribed, -1 for unlimited.</param>
/// <remarks>
/// <para>
/// <b>These are allocations, not measurements.</b> A node with 200 GB promised out of 256 GB is
/// not a node using 200 GB - most game servers sit well under their ceiling. Presenting an
/// allocation figure as consumption tells an operator the node is nearly full when it may be
/// half idle, and that is the mistake that leads to buying hardware nobody needed.
/// </para>
/// <para>
/// Real consumption, where it is obtainable at all, belongs in its own fields and its own bar.
/// </para>
/// </remarks>
public sealed record NodeCapacity(
    long AllocatedMemoryBytes,
    long TotalMemoryBytes,
    long AllocatedDiskBytes,
    long TotalDiskBytes,
    int MemoryOverallocatePercent,
    int DiskOverallocatePercent)
{
    /// <summary>Memory ceiling once overallocation is applied.</summary>
    public long EffectiveMemoryBytes => Effective(TotalMemoryBytes, MemoryOverallocatePercent);

    /// <summary>Disk ceiling once overallocation is applied.</summary>
    public long EffectiveDiskBytes => Effective(TotalDiskBytes, DiskOverallocatePercent);

    /// <summary>Memory allocated against the effective ceiling.</summary>
    public Measure Memory => new(AllocatedMemoryBytes, EffectiveMemoryBytes);

    /// <summary>Disk allocated against the effective ceiling.</summary>
    public Measure Disk => new(AllocatedDiskBytes, EffectiveDiskBytes);

    private static long Effective(long total, int overallocatePercent) =>
        overallocatePercent < 0 ? long.MaxValue : total + (total * overallocatePercent / 100);
}

// =============================================================
// © 2026 HowToo Software. All rights reserved.
// =============================================================
