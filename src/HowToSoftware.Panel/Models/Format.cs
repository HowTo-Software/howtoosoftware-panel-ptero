using System.Globalization;

namespace HowToSoftware.Panel.Models;

/// <summary>
/// How figures are written on screen.
/// </summary>
/// <remarks>
/// Centralised so that a byte count reads the same on a dashboard card, a node capacity bar and
/// a server page. Two screens that format the same quantity differently make an operator check
/// whether they are looking at the same quantity.
/// </remarks>
public static class Format
{
    private const long Kib = 1024;
    private const long Mib = Kib * 1024;
    private const long Gib = Mib * 1024;
    private const long Tib = Gib * 1024;

    /// <summary>
    /// A byte count in binary units.
    /// </summary>
    /// <param name="bytes">The count, or null when nothing was measured.</param>
    /// <returns>The figure, or an em dash when there is nothing to state.</returns>
    /// <remarks>
    /// Binary units, because that is what the container limits are: Pterodactyl allocates in
    /// MiB, and a server given 4096 MiB has 4 GiB, not 4.29 GB. Showing decimal units next to a
    /// binary allocation makes the two disagree by 7% and invites a support ticket.
    /// </remarks>
    public static string Bytes(long? bytes) => bytes switch
    {
        null => "—",
        0 => "0 GB",
        >= Tib => Round((double)bytes.Value / Tib) + " TB",
        >= Gib => Round((double)bytes.Value / Gib) + " GB",
        >= Mib => Round((double)bytes.Value / Mib) + " MB",
        >= Kib => Round((double)bytes.Value / Kib) + " KB",
        _ => bytes.Value.ToString(CultureInfo.InvariantCulture) + " B",
    };

    /// <summary>
    /// A duration written the way an operator reads uptime.
    /// </summary>
    /// <param name="milliseconds">How long, or null when the server is not running.</param>
    /// <returns>Something like <c>12d 4h</c>, or an em dash.</returns>
    public static string Uptime(long? milliseconds)
    {
        if (milliseconds is null or <= 0)
        {
            return "—";
        }

        var span = TimeSpan.FromMilliseconds(milliseconds.Value);

        return span.TotalDays >= 1
            ? $"{(int)span.TotalDays}d {span.Hours}h"
            : span.TotalHours >= 1
                ? $"{(int)span.TotalHours}h {span.Minutes}m"
                : $"{span.Minutes}m";
    }

    // One decimal below ten, none above: 5.2 GB is worth the digit, 512.4 GB is noise.
    private static string Round(double value) =>
        value < 10
            ? value.ToString("0.#", CultureInfo.InvariantCulture)
            : value.ToString("0", CultureInfo.InvariantCulture);
}

// =============================================================
// © 2026 HowToo Software. All rights reserved.
// =============================================================
