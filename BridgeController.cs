using Microsoft.AspNetCore.Mvc;
using System.Text.Json;

namespace CrossUI.Studio.Bridge.Controllers
{
    /**
     * CrossUI Studio Bridge - C# (ASP.NET Core) Version
     * Add this controller to your Web API or MVC project.
     */
    [ApiController]
    [Route("[controller]")]
    public class BridgeController : ControllerBase
    {
        [HttpPost]
        public IActionResult Post([FromForm] string code, [FromForm] string origin = "csharp_bridge")
        {
            // Serialize to JSON string literals
            string jsonCode = JsonSerializer.Serialize(code ?? "");
            string jsonOrigin = JsonSerializer.Serialize(origin ?? "");

            string html = $@"
<!DOCTYPE html>
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
        (async () => {{
            // Data passed from ASP.NET Core
            const snippetCode = {jsonCode};
            const origin = {jsonOrigin};
            
            const SNIPPET_NAME = ""[snippet].jsx"";
            const PROJECT_ID = ""playground"";
            const STUDIO_URL = ""https://studio.crossui.com#!"";

            if (!snippetCode || snippetCode.trim() === """") {{
                window.location.href = ""https://studio.crossui.com"";
                return;
            }}

            try {{
                const db = await new Promise((resolve, reject) => {{
                    const request = indexedDB.open(""CrossUI_VFS"", 1);
                    request.onupgradeneeded = (e) => {{
                        const db = e.target.result;
                        if (!db.objectStoreNames.contains(""files"")) {{
                            db.createObjectStore(""files"", {{ keyPath: ""id"" }});
                        }
                    }};
                    request.onsuccess = (e) => resolve(e.target.result);
                    request.onerror = (e) => reject(e.target.error);
                }});

                const transaction = db.transaction([""files""], ""readwrite"");
                const store = transaction.objectStore(""files"");

                const entry = {{
                    id: "":virtual/"" + PROJECT_ID + ""/"" + SNIPPET_NAME,
                    path: SNIPPET_NAME,
                    content: snippetCode,
                    version: Date.now().toString(),
                    metadata: {{
                        origin: origin,
                        timestamp: new Date().toISOString()
                    }}
                }};

                store.put(entry);
                
                transaction.oncomplete = () => {{
                    window.location.href = STUDIO_URL;
                }};
                
                transaction.onerror = (e) => {{
                    console.error(""VFS Transaction failed:"", e.target.error);
                    window.location.href = STUDIO_URL;
                }};

            } catch (err) {{
                console.error(""Critical Bridge Error:"", err);
                window.location.href = ""https://studio.crossui.com"";
            }}
        }})();
    </script>
</body>
</html>";

            return Content(html, "text/html");
        }
    }
}
