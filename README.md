# CrossUI Studio Bridge Integration Guide

Welcome to the CrossUI Bridge Integration Guide. Integrating the "Open in Studio" button allows your readers and customers to seamlessly open your React/MUI components directly in CrossUI Studio without any local setup.

To accommodate different technical stacks and security requirements, we offer three distinct integration paths. **Crucially, all paths support Affiliate Tracking**, ensuring you receive your 30% first-year commission when users convert to a Pro subscription.

---

## Path 1: HTML Form Method (The Simplest)

**Best for**: Static sites, markdown-based blogs, CMS platforms with strict script policies, or ultra-fast integrations without adding external dependencies.

This approach relies on native browser form submission. It is extremely robust and works anywhere you can paste raw HTML.

### Implementation

Create a `<form>` targeting our secure Bridge endpoint. Use a hidden `textarea` to pass your React code, and an `input` for your `ref` code.

```html
<form action="https://studio.crossui.com/public/bridge.php" method="POST" target="_blank">
  <!-- 1. The code snippet to be loaded in Studio -->
  <textarea name="code" style="display:none;">
import React from 'react';
import { Button } from '@mui/material';

export default function Demo() {
  return <Button variant="contained">Hello CrossUI!</Button>;
}
  </textarea>

  <!-- 2. Affiliate Tracking Code -->
  <input type="hidden" name="ref" value="YOUR_REF_CODE">

  <!-- 3. Optional: Analytics context -->
  <input type="hidden" name="origin" value="my-react-tutorial-page">

  <!-- 4. The trigger button -->
  <button type="submit" class="open-studio-btn">Open in CrossUI Studio</button>
</form>
```

> [!TIP]
> **Tracking Commissions**: Replace `YOUR_REF_CODE` with your unique partner ID. Studio will automatically append this as a 60-day cookie for the user.

---

## Path 2: JS SDK Method (The Standard)

**Best for**: Documentation sites (e.g., Docusaurus, Nextra), Dev.to bloggers, and dynamic web apps looking for automated "Open in Studio" overlays across multiple code blocks.

The JS SDK automatically scans your page for code blocks (e.g., `<pre><code>`) and injects a sleek interactive button.

### Implementation

1. **Include the SDK**: Add this script near the end of your `<body>`.

```html
<script src="https://studio.crossui.com/public/CrossUI-Bridge-Client.js"></script>
```

2. **Initialize with your Ref Code**: Call the `init` method and pass your custom configuration, including the CSS selector for your code blocks and your affiliate code.

```javascript
CrossUIBridge.init({
  selector: 'pre code.language-jsx', // Target specific blocks
  ref: 'YOUR_REF_CODE',              // Your affiliate tracking code!
  origin: window.location.pathname   // Optional tracking info
});
```

When a user clicks the overlay button, the SDK dynamically captures the inner text of the code block and initiates a secure POST request, carrying your `ref` code safely to our servers.

---

## Path 3: Server-Side Bridge Method (The Most Controllable)

**Best for**: Template marketplaces, enterprise environments, or strict architectures where you don't want client browsers holding or sending raw code directly.

In this architecture, your frontend sends a payload to **your own server**. Your server validates the request, constructs the final HTML payload (including your `ref`), and returns an auto-submitting form to the client to render the redirect to Studio. This hides the actual integration logic and secures your templates.

### Backend Templates

Here are boilerplate templates for common backend languages. Notice how your `ref` code is securely injected server-side.

#### Node.js / Express (JavaScript)
```javascript
app.post('/api/launch-studio', (req, res) => {
  const reactCode = req.body.code;
  const partnerRef = "YOUR_REF_CODE"; // Secured on backend

  const html = `
    <html>
      <body onload="document.forms[0].submit()">
        <form action="https://studio.crossui.com/public/bridge.php" method="POST">
          <textarea name="code" style="display:none;">${reactCode}</textarea>
          <input type="hidden" name="ref" value="${partnerRef}">
        </form>
        <p>Launching CrossUI Studio...</p>
      </body>
    </html>
  `;
  res.send(html);
});
```

#### PHP
```php
<?php
// launch-studio.php
$reactCode = $_POST['code'] ?? '';
$partnerRef = 'YOUR_REF_CODE'; // Secured on backend
?>
<html>
  <body onload="document.forms[0].submit()">
    <form action="https://studio.crossui.com/public/bridge.php" method="POST">
      <textarea name="code" style="display:none;"><?php echo htmlspecialchars($reactCode); ?></textarea>
      <input type="hidden" name="ref" value="<?php echo htmlspecialchars($partnerRef); ?>">
    </form>
    <p>Launching CrossUI Studio...</p>
  </body>
</html>
```

#### C# / .NET Core
```csharp
[HttpPost("launch-studio")]
public IActionResult LaunchStudio([FromForm] string code)
{
    string partnerRef = "YOUR_REF_CODE"; // Secured on backend
    string encodedCode = System.Net.WebUtility.HtmlEncode(code);

    string html = $@"
        <html>
          <body onload='document.forms[0].submit()'>
            <form action='https://studio.crossui.com/public/bridge.php' method='POST'>
              <textarea name='code' style='display:none;'>{encodedCode}</textarea>
              <input type='hidden' name='ref' value='{partnerRef}' />
            </form>
            <p>Launching CrossUI Studio...</p>
          </body>
        </html>";

    return Content(html, "text/html");
}
```

#### Java (Spring Boot)
```java
@PostMapping("/launch-studio")
public ResponseEntity<String> launchStudio(@RequestParam String code) {
    String partnerRef = "YOUR_REF_CODE"; // Secured on backend
    String encodedCode = org.springframework.web.util.HtmlUtils.htmlEscape(code);

    String html = "<html>" +
        "<body onload=\"document.forms[0].submit()\">" +
        "<form action=\"https://studio.crossui.com/public/bridge.php\" method=\"POST\">" +
        "<textarea name=\"code\" style=\"display:none;\">" + encodedCode + "</textarea>" +
        "<input type=\"hidden\" name=\"ref\" value=\"" + partnerRef + "\">" +
        "</form>" +
        "<p>Launching CrossUI Studio...</p>" +
        "</body></html>";

    return ResponseEntity.ok().header("Content-Type", "text/html").body(html);
}
```

> [!IMPORTANT]
> **Why use the Server-Side method?** If you are selling premium MUI templates, you can authenticate the user's purchase session on your backend *before* injecting the premium code into the auto-submitting form. This ensures only verified buyers can open your proprietary templates in Studio, while you still reliably capture your 30% affiliate tracking via the backend-injected `ref`.
