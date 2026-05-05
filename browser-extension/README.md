# Downloora Media Helper

Downloora Media Helper is a browser extension designed to detect public media URLs on the active page and easily send them to your Downloora dashboard.

## Why use this extension over tools like `yt-dlp`?

When extracting media from websites, command-line tools can sometimes struggle. This extension leverages your browser's native capabilities to provide a more reliable extraction experience:

- **Use a real browser-like session:** Runs directly within your actual browser environment instead of simulating one.
- **Detect media requests while the page plays:** Captures network requests seamlessly by inspecting what the browser is actually loading.
- **Use cookies from your browser:** Automatically accesses media that requires your active session cookies or authentication.
- **Follow embedded players:** Easily finds media streams hidden inside complex iframes and embedded video players.
- **Send the right Referer / headers:** Because it's your browser making the requests, the correct headers, tokens, and referers are naturally included.
- **Handle site-specific extractors that yt-dlp may not support:** If the browser can play it, the extension can likely detect it, bypassing the need for custom extraction logic for every new site.

## Installation

1. Open Chrome/Edge and navigate to `chrome://extensions/`
2. Enable **Developer mode** in the top right.
3. Click **Load unpacked** and select the directory containing this extension.
4. Pin the extension to your toolbar for easy access!

## Usage

1. Open a page containing video or audio.
2. Click the Downloora Media Helper extension icon.
3. The extension will scan the page for available media formats.
4. Click the download button to start the media in Downloora, or the bookmark button to save it to your wishlist.
