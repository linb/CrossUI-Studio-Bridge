# CrossUI Studio Integration Guide (SDK)

Follow these steps to integrate the "Open in Studio" button into your forum, technical blog, or documentation library.

## 1. Include the Client SDK

Add the following script before the closing `</body>` tag of your HTML.

```html
<script src="https://your-domain.com/public/CrossUI-Bridge-Client.js"></script>
```

> [!IMPORTANT]
> Change `your-domain.com` to your actual CrossUI Studio deployment domain.

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

## 5. Custom Styling

You can override the `.crossui-open-btn` CSS class to match your site's branding:

```css
.crossui-open-btn {
    background: #111 !important; /* Force a different color */
    border-radius: 0;           /* Make it square */
}
```

## 6. Alternative Option: Manual Form Implementation

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
    <button type="submit">Open in CrossUI Studio</button>
</form>
```
 