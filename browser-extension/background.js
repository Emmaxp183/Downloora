const mediaByTab = new Map();
const MEDIA_URL_PATTERN =
  /\.(mp4|m4v|webm|mov|mp3|m4a|aac|wav|ogg|opus|flac|m3u8|mpd)(\?|#|$)/i;

const isHttpUrl = (url) => /^https?:\/\//i.test(url);

const isIgnoredMediaUrl = (url) => {
  try {
    const parsed = new URL(url);

    return (
      parsed.hostname.endsWith('youtube.com') &&
      parsed.pathname.startsWith('/s/search/audio/')
    );
  } catch {
    return false;
  }
};

const isMediaUrl = (url, resourceType) => {
  if (!isHttpUrl(url)) {
    return false;
  }

  if (isIgnoredMediaUrl(url)) {
    return false;
  }

  if (MEDIA_URL_PATTERN.test(url)) {
    return true;
  }

  return resourceType === 'media';
};

const mediaKind = (url) => {
  if (/\.(mp3|m4a|aac|wav|ogg|opus|flac)(\?|#|$)/i.test(url)) {
    return 'audio';
  }

  if (/\.(m3u8|mpd)(\?|#|$)/i.test(url)) {
    return 'playlist';
  }

  return 'video';
};

const rememberMedia = (tabId, item) => {
  if (tabId < 0) {
    return;
  }

  const current = mediaByTab.get(tabId) ?? [];

  if (current.some((existing) => existing.url === item.url)) {
    return;
  }

  mediaByTab.set(tabId, [item, ...current].slice(0, 40));
};

chrome.webRequest.onBeforeRequest.addListener(
  (details) => {
    if (!isMediaUrl(details.url, details.type)) {
      return;
    }

    rememberMedia(details.tabId, {
      url: details.url,
      kind: mediaKind(details.url),
      source: `Network ${details.type}`,
    });
  },
  {
    urls: ['http://*/*', 'https://*/*'],
    types: ['media', 'xmlhttprequest', 'other'],
  },
);

chrome.tabs.onRemoved.addListener((tabId) => {
  mediaByTab.delete(tabId);
});

chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (message?.type === 'DOWNLOORA_REMEMBER_MEDIA' && sender.tab?.id) {
    for (const item of message.items ?? []) {
      rememberMedia(sender.tab.id, item);
    }

    sendResponse({ ok: true });

    return;
  }

  if (message?.type === 'DOWNLOORA_GET_TAB_MEDIA') {
    sendResponse({
      items: (mediaByTab.get(message.tabId) ?? []).filter((item) => !isIgnoredMediaUrl(item.url)),
    });
  }
});
