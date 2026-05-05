const DEFAULT_SEEDR_URL = 'https://localhost:8443/dashboard';

const statusElement = document.querySelector('#status');
const listElement = document.querySelector('#media-list');
const seedrUrlInput = document.querySelector('#seedr-url');
const headerTitle = document.querySelector('#header-title');
const headerSubtitle = document.querySelector('#header-subtitle');
const headerThumbnail = document.querySelector('#header-thumbnail');
const closeBtn = document.querySelector('#close-btn');

closeBtn.addEventListener('click', () => window.close());

const isIgnoredMediaUrl = (value) => {
  try {
    const url = new URL(value);

    return url.hostname.endsWith('youtube.com') && url.pathname.startsWith('/s/search/audio/');
  } catch {
    return false;
  }
};

const supportedPageMedia = (tab) => {
  try {
    const url = new URL(tab.url);
    const title = tab.title?.replace(/\s+-\s+YouTube$/, '') ?? 'YouTube video';

    if (
      ['youtube.com', 'www.youtube.com', 'm.youtube.com'].includes(url.hostname) &&
      url.pathname === '/watch' &&
      url.searchParams.has('v')
    ) {
      return {
        url: `https://www.youtube.com/watch?v=${url.searchParams.get('v')}`,
        source: 'YouTube',
        kind: 'video',
        title,
        id: url.searchParams.get('v'),
      };
    }

    if (['youtu.be', 'www.youtu.be'].includes(url.hostname) && url.pathname.length > 1) {
      return {
        url: `https://youtu.be${url.pathname}`,
        source: 'YouTube',
        kind: 'video',
        title,
        id: url.pathname.substring(1),
      };
    }
  } catch {
    return null;
  }

  return null;
};

const mediaLabel = (item) => {
  if (item.title) {
    return item.title;
  }

  try {
    const url = new URL(item.url);
    const pathname = decodeURIComponent(url.pathname.split('/').filter(Boolean).pop() ?? url.hostname);

    return pathname || url.hostname;
  } catch {
    return item.url;
  }
};

const normalizeSeedrUrl = (value) => {
  try {
    const url = new URL(value || DEFAULT_SEEDR_URL);

    if (!/^https?:$/i.test(url.protocol)) {
      return DEFAULT_SEEDR_URL;
    }

    return url.toString();
  } catch {
    return DEFAULT_SEEDR_URL;
  }
};

const openInSeedr = async (mediaUrl) => {
  const seedrUrl = normalizeSeedrUrl(seedrUrlInput.value);
  await chrome.storage.sync.set({ seedrUrl });

  const target = new URL(seedrUrl);
  target.searchParams.set('url', mediaUrl);
  target.searchParams.set('source', 'browser-extension');
  target.searchParams.set('auto', '1');

  await chrome.tabs.create({ url: target.toString() });
};

const activeTab = async () => {
  const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });

  return tab;
};

const scanContentScript = async (tabId) => {
  try {
    const response = await chrome.tabs.sendMessage(tabId, {
      type: 'SEEDR_SCAN_PAGE_MEDIA',
    });

    return response?.items ?? [];
  } catch {
    return [];
  }
};

const getNetworkMedia = async (tabId) => {
  const response = await chrome.runtime.sendMessage({
    type: 'SEEDR_GET_TAB_MEDIA',
    tabId,
  });

  return response?.items ?? [];
};

const uniqueMedia = (items) => {
  const seen = new Set();

  const uniqueItems = items.filter((item) => {
    if (!item?.url || seen.has(item.url) || isIgnoredMediaUrl(item.url)) {
      return false;
    }

    seen.add(item.url);

    return true;
  });

  return uniqueItems.sort((first, second) => {
    const firstScore = first.source.includes('YouTube') ? 100 : first.kind === 'video' ? 50 : 0;
    const secondScore = second.source.includes('YouTube') ? 100 : second.kind === 'video' ? 50 : 0;

    return secondScore - firstScore;
  });
};

const renderHeader = (tab, items) => {
  const pageMedia = supportedPageMedia(tab);

  if (pageMedia && pageMedia.title) {
    headerTitle.textContent = pageMedia.title;
    headerSubtitle.textContent = 'YouTube · ready';

    if (pageMedia.id) {
      headerThumbnail.src = `https://img.youtube.com/vi/${pageMedia.id}/hqdefault.jpg`;
    }
  } else if (items.length > 0 && items[0].title) {
    headerTitle.textContent = items[0].title;
    headerSubtitle.textContent = `${items[0].source} · ready`;
    headerThumbnail.src = tab.favIconUrl || 'https://via.placeholder.com/64/111/111';
  } else {
    headerTitle.textContent = tab.title || 'Detected Media';
    headerSubtitle.textContent = 'Ready';
    headerThumbnail.src = tab.favIconUrl || 'https://via.placeholder.com/64/111/111';
  }
};

const renderMedia = (items) => {
  listElement.replaceChildren();

  if (items.length === 0) {
    statusElement.hidden = false;
    statusElement.textContent = 'No public media URLs found yet. Start playback on the page, then try again.';

    return;
  }

  statusElement.hidden = true;

  for (const item of items) {
    const card = document.createElement('article');
    card.className = 'media-card';

    const icon = document.createElement('div');
    icon.className = 'media-icon';

    if (item.kind === 'audio') {
      icon.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"></path><circle cx="6" cy="18" r="3"></circle><circle cx="18" cy="16" r="3"></circle></svg>';
    } else {
      icon.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="2.18" ry="2.18"></rect><line x1="7" y1="2" x2="7" y2="22"></line><line x1="17" y1="2" x2="17" y2="22"></line><line x1="2" y1="12" x2="22" y2="12"></line><line x1="2" y1="7" x2="7" y2="7"></line><line x1="2" y1="17" x2="7" y2="17"></line><line x1="17" y1="17" x2="22" y2="17"></line><line x1="17" y1="7" x2="22" y2="7"></line></svg>';
    }

    const body = document.createElement('div');
    body.className = 'media-body';
    const title = document.createElement('p');
    const meta = document.createElement('p');

    title.className = 'media-title';
    title.textContent = mediaLabel(item);

    meta.className = 'media-meta';

    const kindText = item.kind || 'media';
    const sourceText = item.source || 'detected media';

    let format = 'MP4';

    if (item.url.includes('.m3u8')) {
      format = 'HLS Stream';
    }

    if (item.url.includes('.mp3')) {
      format = 'MP3';
    }

    if (item.url.includes('.m4a')) {
      format = 'M4A';
    }

    if (item.url.includes('.webm')) {
      format = 'WEBM';
    }

    if (!title.textContent.includes('·')) {
      title.textContent = `Auto • ${format}`;
    }

    meta.textContent = `${kindText} • ${sourceText}`;

    body.append(title, meta);

    const button = document.createElement('button');
    button.className = 'save-button';
    button.type = 'button';
    button.title = 'Send to Seedr';
    button.setAttribute('aria-label', `Send ${mediaLabel(item)} to Seedr`);
    button.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg> Save';
    button.addEventListener('click', () => openInSeedr(item.url));

    card.append(icon, body, button);
    listElement.append(card);
  }
};

const scan = async () => {
  statusElement.hidden = false;
  statusElement.textContent = 'Scanning this tab...';
  listElement.replaceChildren();

  const tab = await activeTab();

  if (!tab?.id || !/^https?:\/\//i.test(tab.url ?? '')) {
    statusElement.textContent = 'Open a normal website tab to scan for public media.';
    renderHeader(tab || {}, []);

    return;
  }

  const pageMedia = supportedPageMedia(tab);
  const items = uniqueMedia([
    ...(pageMedia === null ? [] : [pageMedia]),
    ...(await scanContentScript(tab.id)),
    ...(await getNetworkMedia(tab.id)),
  ]);

  renderHeader(tab, items);
  renderMedia(items);
};

const boot = async () => {
  const { seedrUrl } = await chrome.storage.sync.get({
    seedrUrl: DEFAULT_SEEDR_URL,
  });

  seedrUrlInput.value = seedrUrl;
  
  seedrUrlInput.addEventListener('change', () => {
    chrome.storage.sync.set({
      seedrUrl: normalizeSeedrUrl(seedrUrlInput.value),
    });
  });

  await scan();
};

boot();
