using HowToSoftware.Panel.Components;
using HowToSoftware.Pterodactyl.Domain;
using HowToSoftware.Pterodactyl.Sample;

var builder = WebApplication.CreateBuilder(args);

builder.Services.AddRazorComponents()
    .AddInteractiveServerComponents();

// ── The server directory ──────────────────────────────────────────────────
//
// The interface depends on IServerDirectory and on the domain types it returns; it does not
// know Pterodactyl exists. That seam is what lets the panel be built and tested before a key
// is available, and what will let the transport underneath change without touching a screen.
//
// Until a panel is configured this resolves to the sample source, which announces itself in
// the interface. The swap is one line here, not a change across the components.
builder.Services.AddScoped<IServerDirectory, SampleServerDirectory>();

var app = builder.Build();

if (!app.Environment.IsDevelopment())
{
    app.UseExceptionHandler("/Error", createScopeForErrors: true);
    app.UseHsts();
}

app.UseStatusCodePagesWithReExecute("/not-found", createScopeForStatusCodePages: true);
app.UseHttpsRedirection();
app.UseAntiforgery();

app.MapStaticAssets();
app.MapRazorComponents<App>()
    .AddInteractiveServerRenderMode();

app.Run();
