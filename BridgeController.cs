/*
 * CrossUI Studio Bridge - C# (ASP.NET Core) Version
 *
 * Synchronized with bridge.php / bridge.js / BridgeController.java - same
 * headers, same rate-limit policy (15 req / 60 s / IP), same VFS schema
 * (composite key projectId+name), same HTML/CSS template.
 *
 * Drop into an ASP.NET Core 6+ Web API or MVC project.
 */
using System;
using System.Collections.Concurrent;
using System.Text;
using System.Text.Json;
using Microsoft.AspNetCore.Http;
using Microsoft.AspNetCore.Mvc;

namespace CrossUI.Studio.Bridge.Controllers
{
    [ApiController]
    [Route("[controller]")]
    public class BridgeController : ControllerBase
    {
        // -- 1. Industrial Rate Limiting (in-memory sliding window) -----------
        private const int  RATE_LIMIT     = 15;
        private const long RATE_WINDOW_MS = 60_000L;

        private sealed class Bucket
        {
            public long Start;
            public int  Count;
            public Bucket(long start, int count) { Start = start; Count = count; }
        }
        private static readonly ConcurrentDictionary<string, Bucket> RateBuckets = new();

        private static bool EnforceRateLimit(HttpContext ctx)
        {
            string fwd = ctx.Request.Headers["X-Forwarded-For"].ToString();
            string ip  = !string.IsNullOrEmpty(fwd)
                ? fwd.Split(',')[0].Trim()
                : (ctx.Connection.RemoteIpAddress?.ToString() ?? "unknown");
            if (string.IsNullOrEmpty(ip)) ip = "unknown";

            long now = DateTimeOffset.UtcNow.ToUnixTimeMilliseconds();
            if (RateBuckets.TryGetValue(ip, out var b) && (now - b.Start) < RATE_WINDOW_MS)
            {
                lock (b)
                {
                    if (b.Count >= RATE_LIMIT) return false;
                    b.Count++;
                }
            }
            else
            {
                RateBuckets[ip] = new Bucket(now, 1);
            }

            if (RateBuckets.Count > 1024)
            {
                foreach (var kv in RateBuckets)
                {
                    if ((now - kv.Value.Start) >= RATE_WINDOW_MS)
                        RateBuckets.TryRemove(kv.Key, out _);
                }
            }
            return true;
        }

        // -- 2. Professional Headers & Security -------------------------------
        private void ApplySecurityHeaders()
        {
            Response.Headers["Access-Control-Allow-Origin"]  = "*";
            Response.Headers["Access-Control-Allow-Methods"] = "POST, OPTIONS";
            Response.Headers["Access-Control-Allow-Headers"] = "Content-Type, X-Requested-With";
            Response.Headers["X-Content-Type-Options"]       = "nosniff";
        }

        // -- 3. XSS-safe JSON injection ---------------------------------------
        private static string SafeJsonForScript(string value)
        {
            string raw = JsonSerializer.Serialize(value ?? "");
            var sb = new StringBuilder(raw.Length + 16);
            foreach (char c in raw)
            {
                switch (c)
                {
                    case '<':       sb.Append("\\u003c"); break;
                    case '>':       sb.Append("\\u003e"); break;
                    case '&':       sb.Append("\\u0026"); break;
                    case '\'':     sb.Append("\\u0027"); break;
                    case '\u2028': sb.Append("\\u2028"); break;
                    case '\u2029': sb.Append("\\u2029"); break;
                    default:        sb.Append(c);           break;
                }
            }
            return sb.ToString();
        }

        // -- 4. CORS Preflight ------------------------------------------------
        [HttpOptions]
        public IActionResult Preflight()
        {
            ApplySecurityHeaders();
            Response.Headers["Access-Control-Max-Age"] = "86400";
            return NoContent();
        }

        // -- 5. Bridge Endpoint -----------------------------------------------
        [HttpPost]
        public IActionResult Post([FromForm] string? code, [FromForm] string? origin)
        {
            ApplySecurityHeaders();

            if (!EnforceRateLimit(HttpContext))
            {
                return StatusCode(429, "Rate limit exceeded. Please wait a minute.");
            }

            string snippetCodeJson = SafeJsonForScript(code ?? "");
            string originJson      = SafeJsonForScript(string.IsNullOrEmpty(origin) ? "csharp_bridge" : origin);

            string html = $@"<!DOCTYPE html>
<html lang=""en"">
<head>
    <meta charset=""UTF-8"">
    <meta name=""viewport"" content=""width=device-width, initial-scale=1.0"">
    <title>Launching CrossUI Studio...</title>
    <style>
        body {{
            background: #0f172a;
            color: white;
            font-family: -apple-system, BlinkMacSystemFont, ""Segoe UI"", Roboto, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            overflow: hidden;
        }}
        .loader {{ text-align: center; animation: fadeIn 0.5s ease-out; }}
        .spinner {{
            border: 3px solid rgba(255,255,255,0.05);
            border-top: 3px solid #6366f1;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 20px;
            box-shadow: 0 0 15px rgba(99, 102, 241, 0.2);
        }}
        .text {{ font-size: 14px; font-weight: 500; letter-spacing: 0.5px; color: #94a3b8; }}
        @keyframes spin {{ 0% {{ transform: rotate(0deg); }} 100% {{ transform: rotate(360deg); }} }}
        @keyframes fadeIn {{ from {{ opacity: 0; transform: translateY(10px); }} to {{ opacity: 1; transform: translateY(0); }} }}
    </style>
</head>
<body>
    <div class=""loader"">
        <div class=""spinner""></div>
        <div class=""text"">Launching Studio Snippet...</div>
    </div>

    <script>
        /**
         * CrossUI VFS Injection Logic
         * Synchronized across PHP, Node.js, Java, and C# implementations.
         */
        (async () => {{
            // Secure injection of server-side variables
            const snippetCode = {snippetCodeJson};
            const origin = {originJson};

            const STUDIO_URL = ""https://studio.crossui.com/app#!"";

            if (!snippetCode || snippetCode.trim() === """") {{
                window.location.href = ""https://studio.crossui.com/app"";
                return;
            }}

            try {{
                const db = await new Promise((resolve, reject) => {{
                    const request = indexedDB.open(""CrossUI_VFS"", 1);
                    request.onupgradeneeded = (e) => {{
                        const db = e.target.result;
                        if (!db.objectStoreNames.contains(""files"")) {{
                            db.createObjectStore(""files"", {{ keyPath: [""projectId"", ""name""] }});
                        }}
                    }};
                    request.onsuccess = (e) => resolve(e.target.result);
                    request.onerror = (e) => reject(e.target.error);
                }});

                const transaction = db.transaction([""files""], ""readwrite"");
                const store = transaction.objectStore(""files"");

                // Industrial Schema for Studio Compatibility
                const entry = {{
                    type: ""file"",
                    encoding: ""utf8"",
                    projectId: ""playground"",
                    name: ""[snippet].jsx"",
                    content: snippetCode,
                    updatedAt: Date.now()
                }};

                const req = store.put(entry);

                transaction.oncomplete = () => {{
                    window.location.href = STUDIO_URL;
                }};

                transaction.onerror = (e) => {{
                    console.error(""VFS Transaction failed:"", e.target.error);
                    window.location.href = STUDIO_URL;
                }};

            }} catch (err) {{
                console.error(""Critical Bridge Error:"", err);
                window.location.href = ""https://studio.crossui.com/app"";
            }}
        }})();
    </script>
</body>
</html>";

            return Content(html, "text/html; charset=utf-8");
        }
    }
}
