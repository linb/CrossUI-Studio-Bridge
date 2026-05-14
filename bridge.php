/**
 * CrossUI Studio Bridge - PHP Version
 *
 * Functions:
 * 1. CORS Management
 * 2. Rate Limiting (Anti-Spam)
 * 3. Atomic VFS Write (IndexedDB)
 * 4. Protocol-safe Redirection
 */

// 1. Professional Headers & Security
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('X-Content-Type-Options: nosniff');
header('Content-Type: text/html; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Max-Age: 86400');
    exit;
}

// 2. Industrial Rate Limiting (File-based fallback)
function enforceRateLimit() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $tmpDir = sys_get_temp_dir();
    $limitFile = $tmpDir . '/cui_bridge_limit_' . md5($ip);

    $limit = 15;   // Max 15 snippets
    $window = 60;  // Per 60 seconds

    $data = file_exists($limitFile) ? json_decode(file_get_contents($limitFile), true) : null;

    if ($data && (time() - $data['start']) < $window) {
        if ($data['count'] >= $limit) {
            http_response_code(429);
            die("Rate limit exceeded. Please wait a minute.");
        }
        $data['count']++;
    } else {
        $data = ['start' => time(), 'count' => 1];
    }
    file_put_contents($limitFile, json_encode($data));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforceRateLimit();
}

// 3. Payload Acquisition
$code = $_POST['code'] ?? '';
$origin = $_POST['origin'] ?? 'php_bridge';
?>
<!DOCTYPE html>
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
            const snippetCode = <?php echo json_encode($code, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
            const origin = <?php echo json_encode($origin, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

            const SNIPPET_NAME = "[snippet].jsx";
            const PROJECT_ID = "playground";
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
                            db.createObjectStore("files", { keyPath: "id" });
                        }
                    };
                    request.onsuccess = (e) => resolve(e.target.result);
                    request.onerror = (e) => reject(e.target.error);
                });

                const transaction = db.transaction(["files"], "readwrite");
                const store = transaction.objectStore("files");

                // Industrial Schema for Studio Compatibility
                const entry = {
                    id: ":virtual/" + PROJECT_ID + "/" + SNIPPET_NAME,
                    path: SNIPPET_NAME,
                    content: snippetCode,
                    version: Date.now().toString(),
                    metadata: {
                        origin: origin,
                        timestamp: new Date().toISOString()
                    }
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
</html>