using HowToSoftware.Pterodactyl.Domain;

namespace HowToSoftware.Panel.Tests;

/// <summary>
/// One server, one state.
/// </summary>
/// <remarks>
/// Pterodactyl reports a server's condition through fields that can disagree, and every screen
/// that reduced them itself is a screen that can disagree with the next one. These tests pin the
/// precedence so that a dashboard card and a server page cannot say different things about the
/// same machine.
/// </remarks>
public class ServerStateTests
{
    [Theory]
    [InlineData("running", ServerState.Running)]
    [InlineData("starting", ServerState.Starting)]
    [InlineData("stopping", ServerState.Stopping)]
    [InlineData("offline", ServerState.Offline)]
    [InlineData("RUNNING", ServerState.Running)]
    [InlineData("  running  ", ServerState.Running)]
    public void TheDaemonStateMapsStraightThrough(string reported, ServerState expected)
    {
        Assert.Equal(expected, ServerStateResolver.FromDaemon(reported));
    }

    /// <summary>
    /// "We have not heard" is not "it is off".
    /// </summary>
    /// <remarks>
    /// The distinction matters to whoever is on call. A node that has dropped off the network
    /// renders as Unknown; a customer who stopped their own server renders as Offline. Mapping
    /// the first to the second hides an outage behind a normal-looking dashboard.
    /// </remarks>
    [Theory]
    [InlineData(null)]
    [InlineData("")]
    [InlineData("   ")]
    [InlineData("some-future-state")]
    public void AnAbsentOrUnrecognisedStateIsUnknown_NotOffline(string? reported)
    {
        Assert.Equal(ServerState.Unknown, ServerStateResolver.FromDaemon(reported));
    }

    /// <summary>
    /// Suspension outranks the process.
    /// </summary>
    /// <remarks>
    /// A suspended server can still report <c>running</c>, because suspension removes the
    /// customer's control rather than immediately killing the container. Showing ONLINE there
    /// would offer power buttons the panel will refuse.
    /// </remarks>
    [Fact]
    public void ASuspendedServerReadsAsSuspendedEvenWhileRunning()
    {
        Assert.Equal(
            ServerState.Suspended,
            ServerStateResolver.Resolve("running", isSuspended: true));
    }

    /// <summary>
    /// The full precedence, most severe first. Each row sets every flag up to its own.
    /// </summary>
    [Theory]
    [InlineData(true, true, true, true, true, ServerState.Suspended)]
    [InlineData(false, true, true, true, true, ServerState.Failed)]
    [InlineData(false, false, true, true, true, ServerState.Transferring)]
    [InlineData(false, false, false, true, true, ServerState.Restoring)]
    [InlineData(false, false, false, false, true, ServerState.Installing)]
    [InlineData(false, false, false, false, false, ServerState.Running)]
    public void ConditionsThatRemoveControlOutrankTheProcess(
        bool suspended,
        bool failed,
        bool transferring,
        bool restoring,
        bool installing,
        ServerState expected)
    {
        var state = ServerStateResolver.Resolve(
            "running",
            isSuspended: suspended,
            isInstalling: installing,
            isTransferring: transferring,
            isRestoring: restoring,
            installFailed: failed);

        Assert.Equal(expected, state);
    }

    /// <summary>
    /// A control that cannot work must not be offered.
    /// </summary>
    [Theory]
    [InlineData(ServerState.Running, true)]
    [InlineData(ServerState.Offline, true)]
    [InlineData(ServerState.Unknown, true)]
    [InlineData(ServerState.Suspended, false)]
    [InlineData(ServerState.Installing, false)]
    [InlineData(ServerState.Transferring, false)]
    [InlineData(ServerState.Restoring, false)]
    [InlineData(ServerState.Failed, false)]
    public void PowerControlsAreOfferedOnlyWhereASignalWouldBeAccepted(
        ServerState state,
        bool expected)
    {
        Assert.Equal(expected, state.AcceptsPowerSignals());
    }

    [Theory]
    [InlineData(ServerState.Failed)]
    [InlineData(ServerState.Unknown)]
    public void TheStatesAnOperatorMustNotMissAreFlagged(ServerState state)
    {
        Assert.True(state.NeedsAttention());
    }

    [Theory]
    [InlineData(ServerState.Running)]
    [InlineData(ServerState.Offline)]
    [InlineData(ServerState.Suspended)]
    public void OrdinaryStatesAreNotFlagged(ServerState state)
    {
        Assert.False(state.NeedsAttention());
    }
}

/// <summary>
/// Measures, and the two ways they are routinely got wrong.
/// </summary>
public class MeasureTests
{
    /// <summary>
    /// Pterodactyl writes "unlimited" as 0. A limit of zero and no limit at all are opposites,
    /// and reading one as the other renders an unlimited server as permanently full.
    /// </summary>
    [Fact]
    public void ZeroFromPterodactylMeansUnlimited_NotAlimitOfZero()
    {
        var measure = Measure.FromPterodactyl(used: 500, limit: 0);

        Assert.True(measure.IsUnlimited);
        Assert.Null(measure.Fraction);
    }

    [Fact]
    public void ARealLimitProducesAFraction()
    {
        var measure = Measure.FromPterodactyl(used: 512, limit: 1024);

        Assert.False(measure.IsUnlimited);
        Assert.Equal(0.5, measure.Fraction);
    }

    /// <summary>
    /// A container over its soft limit reports over 100%, and the figure is not clamped - that
    /// is precisely the situation an operator needs to see.
    /// </summary>
    [Fact]
    public void ConsumptionAboveTheLimitIsNotClamped()
    {
        var measure = new Measure(Used: 1200, Limit: 1000);

        Assert.Equal(1.2, measure.Fraction);
    }

    /// <summary>
    /// Node overallocation raises the ceiling; -1 removes it. Reading -1 as a percentage would
    /// produce a negative ceiling and a nonsensical bar.
    /// </summary>
    [Fact]
    public void NodeOverallocationRaisesTheCeiling()
    {
        var capacity = new NodeCapacity(
            AllocatedMemoryBytes: 150,
            TotalMemoryBytes: 100,
            AllocatedDiskBytes: 0,
            TotalDiskBytes: 100,
            MemoryOverallocatePercent: 100,
            DiskOverallocatePercent: 0);

        Assert.Equal(200, capacity.EffectiveMemoryBytes);
        Assert.Equal(0.75, capacity.Memory.Fraction);
    }

    [Fact]
    public void UnlimitedOverallocationLeavesNoCeiling()
    {
        var capacity = new NodeCapacity(0, 100, 0, 100, -1, -1);

        Assert.Equal(long.MaxValue, capacity.EffectiveMemoryBytes);
    }
}

// =============================================================
// (c) 2026 HowToo Software. All rights reserved.
// =============================================================
