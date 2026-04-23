package com.crossui.studio.bridge;

import org.springframework.stereotype.Controller;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.ResponseBody;
import com.fasterxml.jackson.databind.ObjectMapper;

/**
 * CrossUI Studio Bridge - Java (Spring Boot) Version
 * Use this controller to handle snippet injections in Java environments.
 */
@Controller
public class BridgeController {

    private final ObjectMapper objectMapper = new ObjectMapper();

    @PostMapping("/bridge")
    @ResponseBody
    public String handleBridge(
            @RequestParam(value = "code", defaultValue = "") String code,
            @RequestParam(value = "origin", defaultValue = "java_bridge") String origin) throws Exception {
        
        String jsonCode = objectMapper.writeValueAsString(code);
        String jsonOrigin = objectMapper.writeValueAsString(origin);

        return "<!DOCTYPE html>\n" +
                "<html lang=\"en\">\n" +
                "<head>\n" +
                "    <meta charset=\"UTF-8\">\n" +
                "    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n" +
                "    <title>Launching CrossUI Studio...</title>\n" +
                "    <style>\n" +
                "        body { background: #0f172a; color: white; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100vh; margin: 0; overflow: hidden; }\n" +
                "        .loader { text-align: center; animation: fadeIn 0.5s ease-out; }\n" +
                "        .spinner { border: 3px solid rgba(255,255,255,0.05); border-top: 3px solid #6366f1; border-radius: 50%; width: 40px; height: 40px; animation: spin 0.8s linear infinite; margin: 0 auto 20px; box-shadow: 0 0 15px rgba(99, 102, 241, 0.2); }\n" +
                "        .text { font-size: 14px; font-weight: 500; letter-spacing: 0.5px; color: #94a3b8; }\n" +
                "        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }\n" +
                "        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }\n" +
                "    </style>\n" +
                "</head>\n" +
                "<body>\n" +
                "    <div class=\"loader\">\n" +
                "        <div class=\"spinner\"></div>\n" +
                "        <div class=\"text\">Launching Studio Snippet...</div>\n" +
                "    </div>\n" +
                "\n" +
                "    <script>\n" +
                "        (async () => {\n" +
                "            const snippetCode = " + jsonCode + ";\n" +
                "            const origin = " + jsonOrigin + ";\n" +
                "            \n" +
                "            const SNIPPET_NAME = \"[snippet].jsx\";\n" +
                "            const PROJECT_ID = \"playground\";\n" +
                "            const STUDIO_URL = \"https://studio.crossui.com#!\";\n" +
                "\n" +
                "            if (!snippetCode || snippetCode.trim() === \"\") {\n" +
                "                window.location.href = \"https://studio.crossui.com\";\n" +
                "                return;\n" +
                "            }\n" +
                "\n" +
                "            try {\n" +
                "                const db = await new Promise((resolve, reject) => {\n" +
                "                    const request = indexedDB.open(\"CrossUI_VFS\", 1);\n" +
                "                    request.onupgradeneeded = (e) => {\n" +
                "                        const db = e.target.result;\n" +
                "                        if (!db.objectStoreNames.contains(\"files\")) {\n" +
                "                            db.createObjectStore(\"files\", { keyPath: \"id\" });\n" +
                "                        }\n" +
                "                    };\n" +
                "                    request.onsuccess = (e) => resolve(e.target.result);\n" +
                "                    request.onerror = (e) => reject(e.target.error);\n" +
                "                });\n" +
                "\n" +
                "                const transaction = db.transaction([\"files\"], \"readwrite\");\n" +
                "                const store = transaction.objectStore(\"files\");\n" +
                "\n" +
                "                const entry = {\n" +
                "                    id: \":virtual/\" + PROJECT_ID + \"/\" + SNIPPET_NAME,\n" +
                "                    path: SNIPPET_NAME,\n" +
                "                    content: snippetCode,\n" +
                "                    version: Date.now().toString(),\n" +
                "                    metadata: {\n" +
                "                        origin: origin,\n" +
                "                        timestamp: new Date().toISOString()\n" +
                "                    }\n" +
                "                };\n" +
                "\n" +
                "                store.put(entry);\n" +
                "                \n" +
                "                transaction.oncomplete = () => {\n" +
                "                    window.location.href = STUDIO_URL;\n" +
                "                };\n" +
                "                \n" +
                "                transaction.onerror = (e) => {\n" +
                "                    console.error(\"VFS Transaction failed:\", e.target.error);\n" +
                "                    window.location.href = STUDIO_URL;\n" +
                "                };\n" +
                "\n" +
                "            } catch (err) {\n" +
                "                console.error(\"Critical Bridge Error:\", err);\n" +
                "                window.location.href = \"https://studio.crossui.com\";\n" +
                "            }\n" +
                "        })();\n" +
                "    </script>\n" +
                "</body>\n" +
                "</html>";
    }
}
