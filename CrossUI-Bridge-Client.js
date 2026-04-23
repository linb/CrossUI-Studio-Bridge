/**
 * CrossUI Studio Bridge Client (Professional SDK)
 * Used for third-party forum/documentation integration: 
 * Sends code blocks to CrossUI Studio with one click.
 * 
 * Usage:
 * <script src="./CrossUI-Bridge-Client.js"></script>
 */

const CrossUIBridge = {
    // URL pointing to your backend bridge.php
    // Update this according to your actual deployment address
    BRIDGE_URL: 'https://studio.crossui.com/public/bridge.php',

    /**
     * Initialization: Scans the page for code blocks and adds the "Open in Studio" button.
     * @param {string} selector - CSS selector, e.g., 'pre code'
     */
    init(selector = 'pre code') {
        const codeBlocks = document.querySelectorAll(selector);
        codeBlocks.forEach((block) => {
            // Prevent multiple initializations
            if (block.parentElement.querySelector('.crossui-open-btn')) return;

            const btn = document.createElement('button');
            btn.innerHTML = '<span>🚀 Open in CrossUI Studio</span>';
            btn.className = 'crossui-open-btn';

            btn.onclick = () => {
                this.postToStudio(block.innerText, btn);
            };

            // Insert the button above the code block
            const container = block.parentElement;
            if (getComputedStyle(container).position === 'static') {
                container.style.position = 'relative';
            }
            container.insertBefore(btn, block);
        });
    },

    /**
     * Core Logic: Posts code via a hidden form.
     * @param {string} code - The JSX code content.
     * @param {HTMLElement} btn - The trigger button (for UI feedback).
     */
    postToStudio(code, btn) {
        if (!code) return;

        // UI Feedback: Loading state
        let originalContent = '';
        if (btn) {
            originalContent = btn.innerHTML;
            btn.innerHTML = '<span>⌛ Launching...</span>';
            btn.classList.add('is-launching');
            btn.disabled = true;
        }

        // Create a temporary hidden form
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = this.BRIDGE_URL;
        form.target = '_blank'; // Open Studio in a new tab

        // Inject code field
        const codeInput = document.createElement('input');
        codeInput.type = 'hidden';
        codeInput.name = 'code';
        codeInput.value = code;

        // Inject origin URL for audit logs
        const originInput = document.createElement('input');
        originInput.type = 'hidden';
        originInput.name = 'origin';
        originInput.value = window.location.href;

        form.appendChild(codeInput);
        form.appendChild(originInput);
        document.body.appendChild(form);

        // Execute submission
        form.submit();

        // Delay cleanup and status restoration
        setTimeout(() => {
            document.body.removeChild(form);
            if (btn) {
                btn.innerHTML = originalContent;
                btn.classList.remove('is-launching');
                btn.disabled = false;
            }
        }, 2000);
    }
};

/**
 * Injects required styles for the Bridge button.
 */
(function injectStyles() {
    if (document.getElementById('crossui-bridge-styles')) return;
    const style = document.createElement('style');
    style.id = 'crossui-bridge-styles';
    style.innerHTML = `
        .crossui-open-btn {
            position: absolute;
            right: 8px;
            top: 8px;
            z-index: 100;
            padding: 4px 10px;
            background: #4f46e5;
            color: #ffffff !important;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 4px;
            cursor: pointer;
            font-size: 11px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            font-weight: 500;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 4px;
            line-height: 1.4;
        }
        .crossui-open-btn:hover {
            background: #4338ca;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.15);
        }
        .crossui-open-btn:active {
            transform: translateY(0);
        }
        .crossui-open-btn.is-launching {
            background: #3730a3;
            cursor: wait;
            opacity: 0.9;
        }
        .crossui-open-btn span {
            pointer-events: none;
        }
    `;
    document.head.appendChild(style);
})();

// Auto-run initialization
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => CrossUIBridge.init());
} else {
    CrossUIBridge.init();
}

// Global export
window.CrossUIBridge = CrossUIBridge;
