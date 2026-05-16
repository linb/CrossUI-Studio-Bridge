/*
 * CrossUI Studio Bridge - Java (Spring Boot) Version
 *
 * Functions:
 *   1. CORS Management
 *   2. Rate Limiting (Anti-Spam)
 *   3. Atomic VFS Write (IndexedDB)
 *   4. Protocol-safe Redirection
 *
 * Synchronized with bridge.php / bridge.js - same headers, same rate-limit
 * policy (15 req / 60 s / IP), same VFS schema (composite key projectId+name),
 * same HTML/CSS template.
 *
 * Drop into a Spring Boot 3.x application. (For Spring Boot 2.x, replace the
 * `jakarta.servlet` import with `javax.servlet`.)
 */
package com.crossui.studio.bridge;

import com.fasterxml.jackson.databind.ObjectMapper;
import org.springframework.http.MediaType;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;

import jakarta.servlet.http.HttpServletRequest;
import java.util.concurrent.ConcurrentHashMap;

@RestController
public class BridgeController {

    // -- 1. Industrial Rate Limiting (in-memory sliding window) ---------------
    // 15 snippets per 60 seconds per IP, matching PHP's file-based limiter.
    private static final int  RATE_LIMIT     = 15;
    private static final long RATE_WINDOW_MS = 60_000L;

    private static final class Bucket {
        volatile long start;
        volatile int  count;
        Bucket(long start, int count) { this.start = start; this.count = count; }
    }
    private final ConcurrentHashMap<String, Bucket> rateBuckets = new ConcurrentHashMap<>();

    private static final ObjectMapper JSON = new ObjectMapper();

    private boolean enforceRateLimit(HttpServletRequest req) {
        String fwd = req.getHeader("X-Forwarded-For");
        String ip  = (fwd != null && !fwd.isEmpty()) ? fwd.split(",")[0].trim() : req.getRemoteAddr();
        if (ip == null || ip.isEmpty()) ip = "unknown";

        long now = System.currentTimeMillis();
        Bucket b  = rateBuckets.get(ip);
        if (b != null && (now - b.start) < RATE_WINDOW_MS) {
            synchronized (b) {
                if (b.count >= RATE_LIMIT) return false;
                b.count++;
            }
        } else {
            rateBuckets.put(ip, new Bucket(now, 1));
        }

        if (rateBuckets.size() > 1024) {
            rateBuckets.entrySet().removeIf(e -> (now - e.getValue().start) >= RATE_WINDOW_MS);
        }
        return true;
    }

    // -- 2. Professional Headers & Security -----------------------------------
    private static ResponseEntity.BodyBuilder withSecurityHeaders(ResponseEntity.BodyBuilder b) {
        return b.header("Access-Control-Allow-Origin",  "*")
                .header("Access-Control-Allow-Methods", "POST, OPTIONS")
                .header("Access-Control-Allow-Headers", "Content-Type, X-Requested-With")
                .header("X-Content-Type-Options",       "nosniff")
                .contentType(MediaType.parseMediaType("text/html; charset=utf-8"));
    }

    // -- 3. XSS-safe JSON injection -------------------------------------------
    // Mirrors PHP's json_encode(..., JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT).
    private static final char LS = 0x2028;
    private static final char PS = 0x2029;
    private String safeJsonForScript(Object value) {
        String raw;
        try { raw = JSON.writeValueAsString(value); }
        catch (Exception e) { raw = "\"\""; }
        StringBuilder sb = new StringBuilder(raw.length() + 16);
        for (int i = 0; i < raw.length(); i++) {
            char c = raw.charAt(i);
            if      (c == '<')  sb.append("\\u003c");
            else if (c == '>')  sb.append("\\u003e");
            else if (c == '&')  sb.append("\\u0026");
            else if (c == '\'') sb.append("\\u0027");
            else if (c == LS)   sb.append("\\u2028");
            else if (c == PS)   sb.append("\\u2029");
            else                sb.append(c);
        }
        return sb.toString();
    }

    // -- 4. CORS Preflight ----------------------------------------------------
    @RequestMapping(value = "/bridge", method = RequestMethod.OPTIONS)
    public ResponseEntity<Void> preflight() {
        return withSecurityHeaders(ResponseEntity.noContent())
                .header("Access-Control-Max-Age", "86400")
                .build();
    }

    // -- 5. Bridge Endpoint ---------------------------------------------------
    @PostMapping(value = "/bridge", consumes = MediaType.APPLICATION_FORM_URLENCODED_VALUE)
    public ResponseEntity<String> bridge(
            @RequestParam(name = "code",   required = false, defaultValue = "") String code,
            @RequestParam(name = "origin", required = false, defaultValue = "java_bridge") String origin,
            HttpServletRequest request) {

        if (!enforceRateLimit(request)) {
            return ResponseEntity.status(429)
                    .header("Access-Control-Allow-Origin", "*")
                    .contentType(MediaType.TEXT_PLAIN)
                    .body("Rate limit exceeded. Please wait a minute.");
        }

        String snippetCodeJson = safeJsonForScript(code);
        String originJson      = safeJsonForScript(origin);

        String html =
              "<!DOCTYPE html>\n" 
            + "<html lang=\"en\">\n" 
            + "<head>\n" 
            + "    <meta charset=\"UTF-8\">\n" 
            + "    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n" 
            + "    <title>Launching CrossUI Studio...</title>\n" 
            + "    <style>\n" 
            + "        body {\n" 
            + "            background: #0f172a;\n" 
            + "            color: white;\n" 
            + "            font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif;\n" 
            + "            display: flex;\n" 
            + "            flex-direction: column;\n" 
            + "            align-items: center;\n" 
            + "            justify-content: center;\n" 
            + "            height: 100vh;\n" 
            + "            margin: 0;\n" 
            + "            overflow: hidden;\n" 
            + "        }\n" 
            + "        .loader { text-align: center; animation: fadeIn 0.5s ease-out; }\n" 
            + "        .spinner {\n" 
            + "            border: 3px solid rgba(255,255,255,0.05);\n" 
            + "            border-top: 3px solid #6366f1;\n" 
            + "            border-radius: 50%;\n" 
            + "            width: 40px;\n" 
            + "            height: 40px;\n" 
            + "            animation: spin 0.8s linear infinite;\n" 
            + "            margin: 0 auto 20px;\n" 
            + "            box-shadow: 0 0 15px rgba(99, 102, 241, 0.2);\n" 
            + "        }\n" 
            + "        .text { font-size: 14px; font-weight: 500; letter-spacing: 0.5px; color: #94a3b8; }\n" 
            + "        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }\n" 
            + "        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }\n" 
            + "    </style>\n" 
            + "</head>\n" 
            + "<body>\n" 
            + "    <div class=\"loader\">\n" 
            + "        <div class=\"spinner\"></div>\n" 
            + "        <div class=\"text\">Launching Studio Snippet...</div>\n" 
            + "    </div>\n" 
            + "\n" 
            + "    <script>\n" 
            + "        /**\n" 
            + "         * CrossUI VFS Injection Logic\n" 
            + "         * Synchronized across PHP, Node.js, Java, and C# implementations.\n" 
            + "         */\n" 
            + "        (async () => {\n" 
            + "            // Secure injection of server-side variables\n" 
            + "            const snippetCode = \" + snippetCodeJson + \";\n" 
            + "            const origin = \" + originJson + \";\n" 
            + "\n" 
            + "            const STUDIO_URL = \"https://studio.crossui.com/app#!\";\n" 
            + "\n" 
            + "            if (!snippetCode || snippetCode.trim() === \"\") {\n" 
            + "                window.location.href = \"https://studio.crossui.com/app\";\n" 
            + "                return;\n" 
            + "            }\n" 
            + "\n" 
            + "            try {\n" 
            + "                const db = await new Promise((resolve, reject) => {\n" 
            + "                    const request = indexedDB.open(\"CrossUI_VFS\", 1);\n" 
            + "                    request.onupgradeneeded = (e) => {\n" 
            + "                        const db = e.target.result;\n" 
            + "                        if (!db.objectStoreNames.contains(\"files\")) {\n" 
            + "                            db.createObjectStore(\"files\", { keyPath: [\"projectId\", \"name\"] });\n" 
            + "                        }\n" 
            + "                    };\n" 
            + "                    request.onsuccess = (e) => resolve(e.target.result);\n" 
            + "                    request.onerror = (e) => reject(e.target.error);\n" 
            + "                });\n" 
            + "\n" 
            + "                const transaction = db.transaction([\"files\"], \"readwrite\");\n" 
            + "                const store = transaction.objectStore(\"files\");\n" 
            + "\n" 
            + "                // Industrial Schema for Studio Compatibility\n" 
            + "                const entry = {\n" 
            + "                    type: \"file\",\n" 
            + "                    encoding: \"utf8\",\n" 
            + "                    projectId: \"playground\",\n" 
            + "                    name: \"[snippet].jsx\",\n" 
            + "                    content: snippetCode,\n" 
            + "                    updatedAt: Date.now()\n" 
            + "                };\n" 
            + "\n" 
            + "                const req = store.put(entry);\n" 
            + "\n" 
            + "                transaction.oncomplete = () => {\n" 
            + "                    window.location.href = STUDIO_URL;\n" 
            + "                };\n" 
            + "\n" 
            + "                transaction.onerror = (e) => {\n" 
            + "                    console.error(\"VFS Transaction failed:\", e.target.error);\n" 
            + "                    window.location.href = STUDIO_URL;\n" 
            + "                };\n" 
            + "\n" 
            + "            } catch (err) {\n" 
            + "                console.error(\"Critical Bridge Error:\", err);\n" 
            + "                window.location.href = \"https://studio.crossui.com/app\";\n" 
            + "            }\n" 
            + "        })();\n" 
            + "    </script>\n" 
            + "</body>\n" 
            + "</html>\n";

        return withSecurityHeaders(ResponseEntity.ok()).body(html);
    }
}
