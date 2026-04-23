<?php
/**
 * Bridge for CrossUI Studio
 * Captures POST code and writes it directly to client-side IndexedDB.
 */
// --- Professional CORS & Security ---
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Max-Age: 86400');
    exit;
}

// --- Basic Rate Limiting (File-based) ---
function checkRateLimit() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $tmpDir = sys_get_temp_dir();
    $limitFile = $tmpDir . '/cui_bridge_limit_' . md5($ip);
    
    $limit = 10; // 10 requests
    $window = 60; // per 60 seconds
    
    if (file_exists($limitFile)) {
        $data = json_decode(file_get_contents($limitFile), true);
        if ($data && (time() - $data['start']) < $window) {
            if ($data['count'] >= $limit) {
                http_response_code(429);
                die("Too many requests. Please try again later.");
            }
            $data['count']++;
        } else {
            $data = ['start' => time(), 'count' => 1];
        }
    } else {
        $data = ['start' => time(), 'count' => 1];
    }
    file_put_contents($limitFile, json_encode($data));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkRateLimit();
}
// ------------------------------------

$code = $_POST['code'] ?? '';
$origin = $_POST['origin'] ?? '';

// Basic Audit Log (Optional but recommended)
// LogManager::log("SNIPPET_IMPORT_TRIGGERED", ["origin" => $origin, "size" => strlen($code)]);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Importing Snippet...</title>
    <style>
        body {
            background: #0f172a;
            color: #94a3b8;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            overflow: hidden;
        }

        .loader {
            text-align: center;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid rgba(255, 255, 255, 0.1);
            border-top-color: #3b82f6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 16px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body>
    <div class="loader">
        <div class="spinner"></div>
        <div>Processing your snippet...</div>
    </div>

    <script>
        (async () => {
            const snippetCode = <?php echo json_encode($code); ?>;
            const SNIPPET_NAME = "[snippet].jsx";
            const PROJECT_ID = "playground";

            if (!snippetCode) {
                alert("No code provided.");
                window.location.href = "../../index.html";
                return;
            }

            try {
                const db = await new Promise((resolve, reject) => {
                    const request = indexedDB.open("CrossUI_VFS", 1);
                    request.onsuccess = (e) => resolve(e.target.result);
                    request.onerror = (e) => reject(e.target.error);
                });

                const transaction = db.transaction(["files"], "readwrite");
                const store = transaction.objectStore("files");

                const entry = {
                    projectId: PROJECT_ID,
                    name: SNIPPET_NAME,
                    content: snippetCode,
                    type: "file",
                    updatedAt: Date.now()
                };

                await new Promise((resolve, reject) => {
                    const req = store.put(entry);
                    req.onsuccess = resolve;
                    req.onerror = reject;
                });

                // Success - Redirect to Studio with Parameterized Hash
                window.location.href = "http://studio.crossui.com#!";

            } catch (err) {
                console.error("Failed to write to VFS:", err);
                alert("Failed to initialize snippet. Redirecting to Studio...");
                window.location.href = "http://studio.crossui.com";
            }
        })();
    </script>
</body>

</html>