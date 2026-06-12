# CrossUI Studio Integration Guide (SDK)

Follow these steps to integrate the "Open in Studio" button into your forum, technical blog, or documentation library.

## 1. Include the Client SDK

Add the following script before the closing `</body>` tag of your HTML.

```html
<script src="https://studio.crossui.com/public/CrossUI-Bridge-Client.js"></script>
```

> [!IMPORTANT]
> Self-hosting Studio? Replace `studio.crossui.com` with your own deployment domain.

## 2. Automatic Initialization

By default, the script scans for `pre code` tags and adds an overlay button.

If you need custom control over which blocks to enhance (e.g., if you use Prism.js or Highlight.js), you can call the `init` method manually:

```javascript
// Example: Targeting only JSX blocks
CrossUIBridge.init('pre code.language-jsx');
```

## 3. How It Works

1. **Scan**: `CrossUIBridge.init()` traverses the DOM for specific code containers.
2. **Mount**: It injects an absolute-positioned `🚀 Open in Studio` button into the container.
3. **Submit**: When clicked, it generates a secure `POST` request to `bridge.php`.
4. **UX**: Provides instant visual feedback (`⌛ Launching...`) while the bridge page initializes.

## 4. Configuration for Administrators

Ensure your `bridge.php` server-side script is accessible and configured for your allowed origins.

### Security Features
- **Rate Limiting**: Throttles frequent submissions based on Client IP (10/min).
- **Audit Logs**: Records the `origin` URL for traffic analysis.
- **CORS Handling**: Supports AJAX and Form POST across origins.

## 5. Partner Attribution (Earn Commission)

If you are a CrossUI partner (see https://studio.crossui.com/partners), attach
your partner ref ID so readers who later upgrade to Pro are credited to you:

```javascript
// JS SDK: pass your ref ID once at init
CrossUIBridge.init('pre code', { partner: 'your-ref-id' });
```

Or, with the manual form (see Section 7), add one hidden field:

```html
<input type="hidden" name="partner" value="your-ref-id">
```

How it works: the bridge carries your ref into the Studio session (last-click,
60-day window). When the visitor creates an account, attribution locks to that
account permanently — later upgrades from any device or browser are still
credited to you. Settled commissions arrive as itemized monthly transfers in
your own Stripe dashboard.

> [!NOTE]
> No partner ID? The button works exactly the same — attribution is optional
> and adds zero friction for your readers.

## 6. Custom Styling

You can override the `.crossui-open-btn` CSS class to match your site's branding:

```css
.crossui-open-btn {
    background: #111 !important; /* Force a different color */
    border-radius: 0;           /* Make it square */
}
```

## 7. Alternative Option: Manual Form Implementation

If you prefer not to use the JavaScript SDK, you can implement the bridge using a standard HTML form. This is useful for static environments or platforms with strict script policies.

```html
<form action="https://studio.crossui.com/public/bridge.php" method="POST" target="_blank">
    <!-- Place your code snippet here -->
    <textarea name="code" style="display:none;">
import React from 'react';
export default function Demo() {
    return <div>Hello CrossUI!</div>;
}
    </textarea>
    <input type="hidden" name="origin" value="forum_post_xyz">
    <!-- Optional: partner attribution (see Section 5) -->
    <input type="hidden" name="partner" value="your-ref-id">
    <button type="submit">Open in CrossUI Studio</button>
</form>
```
