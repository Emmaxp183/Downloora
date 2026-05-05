const MEDIA_URL_PATTERN =
  /\.(mp4|m4v|webm|mov|mp3|m4a|aac|wav|ogg|opus|flac|m3u8|mpd)(\?|#|$)/i;

const supportedPageUrl = () => {
  const url = new URL(window.location.href);

  if (
    ['youtube.com', 'www.youtube.com', 'm.youtube.com'].includes(url.hostname) &&
    url.pathname === '/watch' &&
    url.searchParams.has('v')
  ) {
    return {
      url: `https://www.youtube.com/watch?v=${url.searchParams.get('v')}`,
      source: 'YouTube page',
      kind: 'video',
      title: document.title.replace(/\s+-\s+YouTube$/, ''),
    };
  }

  if (['youtu.be', 'www.youtu.be'].includes(url.hostname) && url.pathname.length > 1) {
    return {
      url: `https://youtu.be${url.pathname}`,
      source: 'YouTube page',
      kind: 'video',
      title: document.title.replace(/\s+-\s+YouTube$/, ''),
    };
  }

  return null;
};

const absoluteUrl = (value) => {
  if (!value || value.startsWith('blob:') || value.startsWith('data:')) {
    return null;
  }

  try {
    const url = new URL(value, window.location.href);

    return /^https?:$/i.test(url.protocol) ? url.toString() : null;
  } catch {
    return null;
  }
};

const kindFrom = (url, fallback = 'file') => {
  if (/\.(mp3|m4a|aac|wav|ogg|opus|flac)(\?|#|$)/i.test(url)) {
    return 'audio';
  }

  if (/\.(m3u8|mpd)(\?|#|$)/i.test(url)) {
    return 'playlist';
  }

  if (/\.(mp4|m4v|webm|mov)(\?|#|$)/i.test(url)) {
    return 'video';
  }

  return fallback;
};

const addItem = (items, rawUrl, source, fallbackKind = 'file') => {
  const url = absoluteUrl(rawUrl);

  if (!url) {
    return;
  }

  if (!MEDIA_URL_PATTERN.test(url) && fallbackKind === 'file') {
    return;
  }

  if (items.some((item) => item.url === url)) {
    return;
  }

  items.push({
    url,
    source,
    kind: kindFrom(url, fallbackKind),
  });
};

const scanPageMedia = () => {
  const items = [];
  const pageItem = supportedPageUrl();

  if (pageItem !== null) {
    items.push(pageItem);
  }

  for (const element of document.querySelectorAll('video, audio')) {
    addItem(items, element.currentSrc || element.src, element.tagName.toLowerCase(), element.tagName.toLowerCase());
  }

  for (const source of document.querySelectorAll('source[src]')) {
    const parentKind = source.closest('audio') ? 'audio' : source.closest('video') ? 'video' : 'file';
    addItem(items, source.getAttribute('src'), 'source tag', parentKind);
  }

  for (const link of document.querySelectorAll('a[href]')) {
    addItem(items, link.getAttribute('href'), 'page link');
  }

  for (const meta of document.querySelectorAll(
    'meta[property="og:video"], meta[property="og:video:url"], meta[property="og:audio"], meta[name="twitter:player:stream"]',
  )) {
    addItem(items, meta.getAttribute('content'), 'page metadata');
  }

  return items;
};

chrome.runtime.onMessage.addListener((message, _sender, sendResponse) => {
  if (message?.type !== 'SEEDR_SCAN_PAGE_MEDIA') {
    return;
  }

  const items = scanPageMedia();
  chrome.runtime.sendMessage({ type: 'SEEDR_REMEMBER_MEDIA', items });
  sendResponse({ items });
});
