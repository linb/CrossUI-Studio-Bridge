/**
 * CrossUI Studio Bridge - Node.js (Express) Version
 *
 * Functions:
 * 1. CORS Management
 * 2. Rate Limiting (Anti-Spam)
 * 3. Atomic VFS Write (IndexedDB)
 * 4. Protocol-safe Redirection
 *
 * Synchronized with bridge.php - same headers, same rate-limit policy,
 * same VFS schema (composite key projectId+name), same HTML/CSS template.
 */
const express = require('express');
const router = express.Router();

// -- 1. Industrial Rate Limiting (in-memory sliding window) -------------------
// 15 snippets per 60 seconds per IP, matching PHP's file-based limiter.
const RATE_LIMIT = 15;
const RATE_WINDOW_MS = 60 * 1000;
const rateBuckets = new Map(); // ip -> { start: number, count: number }

function enforceRateLimit(req, res) {
    const ip = (req.ip || req.headers['x-forwarded-for'] || req.connection?.remoteAddress || 'unknown')
        .toString()
        .split(',')[0]
        .trim();
    const now = Date.now();
    const bucket = rateBuckets.get(ip);

    if (bucket && (now - bucket.start) < RATE_WINDOW_MS) {
        if (bucket.count >= RATE_LIMIT) {
            res.status(429).type('text/plain').send('Rate limit exceeded. Please wait a minute.');
            return false;
        }
        bucket.count++;
    } else {
        rateBuckets.set(ip, { start: now, count: 1 });
    }

    // Opportunistic cleanup so the Map doesn't grow unbounded.
    if (rateBuckets.size > 1024) {
        for (const [k, v] of rateBuckets) {
            if ((now - v.start) >= RATE_WINDOW_MS) rateBuckets.delete(k);
        }
    }

    return true;
}

// -- 2. Professional Headers & Security ---------------------------------------
function applySecurityHeaders(res) {
    res.set('Access-Control-Allow-Origin', '*');
    res.set('Access-Control-Allow-Methods', 'POST, OPTIONS');
    res.set('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With');
    res.set('X-Content-Type-Options', 'nosniff');
    res.set('Content-Type', 'text/html; charset=utf-8');
}

// -- 3. XSS-safe JSON injection -----------------------------------------------
// Mirrors PHP's json_encode(..., JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT).
// Escapes <, >, &, ', " and U+2028 / U+2029 so the payload is safe to embed
// inside an inline <script> block regardless of attacker-controlled content.
const LS = String.fromCharCode(0x2028);
const PS = String.fromCharCode(0x2029);
function safeJsonForScript(value) {
    return JSON.stringify(value)
        .replace(/</g, '\\u003c')
        .replace(/>/g, '\\u003e')
        .replace(/&/g, '\\u0026')
        .replace(/'/g, '\\u0027')
        .split(LS).join('\\u2028')
        .split(PS).join('\\u2029');
}

// -- 4. CORS Preflight --------------------------------------------------------
router.options('/bridge', (req, res) => {
    applySecurityHeaders(res);
    res.set('Access-Control-Max-Age', '86400');
    res.status(204).end();
});

// -- 5. Bridge Endpoint -------------------------------------------------------
router.post('/bridge', (req, res) => {
    applySecurityHeaders(res);

    if (!enforceRateLimit(req, res)) return;

    const code = (req.body && req.body.code) || '';
    const origin = (req.body && req.body.origin) || 'node_bridge';

    const snippetCodeJson = safeJsonForScript(code);
    const originJson = safeJsonForScript(origin);

    // Standardized HTML Template for Cross-Platform Consistency
    const html = `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Launching CrossUI Studio...</title>
    <style>
        body {
            background: #0f172a;
            color: white;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            overflow: hidden;
        }
        .loader { text-align: center; animation: fadeIn 0.5s ease-out; }
        .spinner {
            border: 3px solid rgba(255,255,255,0.05);
            border-top: 3px solid #6366f1;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 20px;
            box-shadow: 0 0 15px rgba(99, 102, 241, 0.2);
        }
        .text { font-size: 14px; font-weight: 500; letter-spacing: 0.5px; color: #94a3b8; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>
    <div class="loader">
        <div class="spinner"></div>
        <div class="text">Launching Studio Snippet...</div>
    </div>

    <script>
        /**
         * CrossUI VFS Injection Logic
         * Synchronized across PHP, Node.js, Java, and C# implementations.
         */
        (async () => {
            // Secure injection of server-side variables
            const snippetCode = ${snippetCodeJson};
            const origin = ${originJson};

            const STUDIO_URL = "https://studio.crossui.com/app#!";

            if (!snippetCode || snippetCode.trim() === "") {
                window.location.href = "https://studio.crossui.com/app";
                return;
            }

            try {
                const db = await new Promise((resolve, reject) => {
                    const request = indexedDB.open("CrossUI_VFS", 1);
                    request.onupgradeneeded = (e) => {
                        const db = e.target.result;
                        if (!db.objectStoreNames.contains("files")) {
                            db.createObjectStore("files", { keyPath: ["projectId", "name"] });
                        }
                    };
                    request.onsuccess = (e) => resolve(e.target.result);
                    request.onerror = (e) => reject(e.target.error);
                });

                const transaction = db.transaction(["files"], "readwrite");
                const store = transaction.objectStore("files");

                // Industrial Schema for Studio Compatibility
                const entry = {
                    type: "file",
                    encoding: "utf8",
                    projectId: "playground",
                    name: "[snippet].jsx",
                    content: snippetCode,
                    updatedAt: Date.now()
                };

                const req = store.put(entry);

                transaction.oncomplete = () => {
                    window.location.href = STUDIO_URL;
                };

                transaction.onerror = (e) => {
                    console.error("VFS Transaction failed:", e.target.error);
                    window.location.href = STUDIO_URL;
                };

            } catch (err) {
                console.error("Critical Bridge Error:", err);
                window.location.href = "https://studio.crossui.com/app";
            }
        })();
    </script>
</body>
</html>`;

    res.send(html);
});

module.exports = router;
