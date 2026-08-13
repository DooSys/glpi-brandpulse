(() => {
  'use strict';

  const CONTAINER_ID = 'brandpulse-header-counters';

  const rootDoc = window.CFG_GLPI?.root_doc || '';
  const currentScriptUrl = document.currentScript?.src || '';
  const defaultPluginBaseUrl = rootDoc + '/plugins/brandpulse';
  const detectPluginBaseUrl = () => {
    const match = currentScriptUrl.match(/^(.*\/(?:plugins|marketplace)\/brandpulse)\/(?:public\/)?js\/brandpulse\.js(?:\?.*)?$/);
    return match?.[1] || defaultPluginBaseUrl;
  };
  const pluginBaseUrl = detectPluginBaseUrl();
  const countersEndpoint = pluginBaseUrl + '/ajax/counters.php';
  const brandingEndpoint = pluginBaseUrl + '/ajax/branding.php';
  const iconIndexEndpoint = pluginBaseUrl + '/ajax/icons.php';
  const iconStaticIndexEndpoint = pluginBaseUrl + '/icons/pulse/index.json';
  const iconBaseEndpoint = pluginBaseUrl + '/icons/pulse/';
  const iconAssetEndpoint = pluginBaseUrl + '/ajax/icon.php?file=';

  const t = (message) => (typeof window.__ === 'function' ? window.__(message, 'brandpulse') : message);
  const isHtmlDocument = () => document.contentType.toLowerCase().includes('html')
    && document.documentElement?.nodeName?.toLowerCase() === 'html';
  let refreshTimer = null;
  const pulseCacheKey = 'brandpulse:pulse-payload:v1';
  const pulseCacheMaxAge = 30 * 60 * 1000;

  const pulseCacheStorage = () => {
    try {
      return window.localStorage || window.sessionStorage || null;
    } catch (error) {
      return null;
    }
  };

  const readPulseCacheRecord = () => {
    try {
      return JSON.parse(pulseCacheStorage()?.getItem(pulseCacheKey) || 'null');
    } catch (error) {
      return null;
    }
  };

  const resolveAssetUrl = (value) => {
    if (!value) {
      return '';
    }

    if (/^(https?:)?\/\//.test(value) || value.startsWith('data:') || value.startsWith('/')) {
      return value;
    }

    return rootDoc + '/' + value.replace(/^\/+/, '');
  };

  const detectsDarkTheme = () => document.documentElement.dataset.glpiThemeDark === '1'
    || document.documentElement.matches('[data-bs-theme="dark"], .theme-dark, .dark')
    || document.body.matches('[data-bs-theme="dark"], .theme-dark, .dark')
    || window.matchMedia?.('(prefers-color-scheme: dark)')?.matches === true;

  const chooseThemeAsset = (branding, prefix, fallback = '') => {
    const light = branding?.[prefix + '_light'] || '';
    const dark = branding?.[prefix + '_dark'] || '';
    const neutral = branding?.[prefix + '_grey'] || '';

    if (detectsDarkTheme()) {
      return dark || neutral || light || fallback || '';
    }

    return light || neutral || dark || fallback || '';
  };

  const parseRgb = (value) => {
    const match = String(value || '').match(/rgba?\(([^)]+)\)/i);
    if (!match) {
      return null;
    }

    const parts = match[1].split(',').map((part) => Number.parseFloat(part.trim()));
    if (parts.length < 3 || parts.some((part, index) => index < 3 && Number.isNaN(part))) {
      return null;
    }

    return {
      r: parts[0],
      g: parts[1],
      b: parts[2],
      a: parts.length > 3 && !Number.isNaN(parts[3]) ? parts[3] : 1,
    };
  };

  const relativeLuminance = ({ r, g, b }) => {
    const normalize = (channel) => {
      const value = channel / 255;
      return value <= 0.03928 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
    };

    return 0.2126 * normalize(r) + 0.7152 * normalize(g) + 0.0722 * normalize(b);
  };

  const readableColorFor = (element) => {
    const header = element.closest('header, .navbar, .navbar-expand, .topbar, .page-header') || element.parentElement;
    if (header) {
      const headerColor = parseRgb(window.getComputedStyle(header).color);
      if (headerColor && headerColor.a > 0.35) {
        return 'rgb(' + [headerColor.r, headerColor.g, headerColor.b].join(', ') + ')';
      }
    }

    let current = element;
    while (current && current !== document.documentElement) {
      const color = parseRgb(window.getComputedStyle(current).backgroundColor);
      if (color && color.a > 0.2) {
        return relativeLuminance(color) < 0.46 ? '#ffffff' : '#1f2937';
      }
      current = current.parentElement;
    }

    return document.documentElement.matches('[data-bs-theme="dark"], .theme-dark') ? '#ffffff' : '#1f2937';
  };

  const setReadableIconColor = (element) => {
    if (element) {
      element.style.setProperty('--brandpulse-icon-color', readableColorFor(element));
    }
  };

  const cssUrl = (url) => 'url("' + String(url).replace(/["\\\n\r]/g, '') + '")';
  const isSidebarElement = (element) => Boolean(element?.closest?.('#navbar-menu, aside, .navbar-vertical'));
  const isVisibleElement = (element) => Boolean(element?.offsetParent || element?.getClientRects?.().length);

  const setCssUrlVariable = (name, url) => {
    if (url) {
      document.documentElement.style.setProperty(name, cssUrl(url), 'important');
    }
  };

  const findHeaderTarget = () => {
    const selectors = [
      'body > .page header[data-testid="main-header"] .user-menu',
      'header[data-testid="main-header"] .user-menu',
      'body > .page header.navbar .navbar-nav.flex-row.order-md-last.user-menu',
      'header.navbar .navbar-nav.flex-row.order-md-last.user-menu',
      '.navbar:not(#navbar-menu) .navbar-nav.flex-row.order-md-last',
      '.navbar:not(#navbar-menu) .navbar-nav.ms-auto',
      'header.navbar',
    ];

    for (const selector of selectors) {
      for (const target of document.querySelectorAll(selector)) {
        if (target && !isSidebarElement(target) && isVisibleElement(target)) {
          return target;
        }
      }
    }

    return null;
  };

  const ensureContainer = () => {
    const existing = document.getElementById(CONTAINER_ID);
    if (existing) {
      setReadableIconColor(existing);
      return existing;
    }

    const target = findHeaderTarget();
    if (!target) {
      return null;
    }

    const container = document.createElement('nav');
    container.id = CONTAINER_ID;
    container.className = 'brandpulse-counters';
    container.setAttribute('aria-label', t('GLPI BrandPulse counters'));
    setReadableIconColor(target);
    setReadableIconColor(container);
    target.prepend(container);

    return container;
  };

  const encodeIconPath = (path) => path.split('/').map((part) => encodeURIComponent(part)).join('/');

  const resolveIconUrl = (icon) => {
    if (!icon) {
      return iconAssetEndpoint + encodeURIComponent('Notifications/Bell.svg');
    }

    if (icon.startsWith('pulse:')) {
      const iconPath = icon.substring(6);
      const path = iconPath.endsWith('.svg') ? iconPath : iconPath + '.svg';
      return iconAssetEndpoint + encodeURIComponent(path);
    }

    if (/^(https?:)?\/\//.test(icon) || icon.startsWith('data:') || icon.startsWith('/')) {
      return icon;
    }

    if (icon.endsWith('.svg')) {
      return resolveAssetUrl(icon);
    }

    return null;
  };

  const setMaskIcon = (element, iconUrl) => {
    element.style.webkitMaskImage = cssUrl(iconUrl);
    element.style.maskImage = cssUrl(iconUrl);
  };

  const cachePulsePayload = (payload) => {
    try {
      if (!payload?.enabled || !Array.isArray(payload.counters)) {
        pulseCacheStorage()?.removeItem(pulseCacheKey);
        return;
      }

      pulseCacheStorage()?.setItem(pulseCacheKey, JSON.stringify({
        payload,
        stored_at: Date.now(),
      }));
    } catch (error) {
      window.console?.debug?.(t('BrandPulse counters unavailable'), error);
    }
  };

  const cachedPulsePayload = () => {
    try {
      const cached = readPulseCacheRecord();
      if (!cached?.payload?.enabled || !Array.isArray(cached.payload.counters)) {
        return null;
      }
      if (Date.now() - Number(cached.stored_at || 0) > pulseCacheMaxAge) {
        pulseCacheStorage()?.removeItem(pulseCacheKey);
        return null;
      }

      return cached.payload;
    } catch (error) {
      return null;
    }
  };

  const renderIcon = (counter) => {
    const icon = counter.icon || 'pulse:Notifications/Bell.svg';
    const iconUrl = counter.icon_url || resolveIconUrl(icon);

    if (iconUrl) {
      const element = document.createElement('span');
      element.className = 'brandpulse-icon';
      element.setAttribute('aria-hidden', 'true');
      setMaskIcon(element, iconUrl);
      return element;
    }

    const element = document.createElement('i');
    element.className = icon;
    element.setAttribute('aria-hidden', 'true');
    return element;
  };

  const setupCompactSearch = (enabled) => {
    document.body.classList.toggle('brandpulse-compact-search-enabled', Boolean(enabled));

    if (!enabled) {
      for (const container of document.querySelectorAll('.brandpulse-compact-search')) {
        container.querySelector('.brandpulse-search-trigger')?.remove();
        for (const nativeControl of container.querySelectorAll('.brandpulse-native-search-control')) {
          nativeControl.classList.remove('brandpulse-native-search-control');
        }
        container.classList.remove('brandpulse-compact-search', 'is-expanded');
        container.removeAttribute('data-brandpulse-compact-search');
      }
      return;
    }

    const inputs = document.querySelectorAll([
      'header[data-testid="main-header"] #global-search',
      '.secondary-bar #global-search',
      'header.navbar input[name="globalsearch"]',
      '.navbar:not(#navbar-menu) input[name="globalsearch"]',
    ].join(','));

    for (const input of inputs) {
      if (isSidebarElement(input) || input.type === 'hidden' || !isVisibleElement(input)) {
        continue;
      }

      const container = input.closest('form[role="search"]')
        || input.closest('form')
        || input.closest('.input-group, .search, .navbar-search')
        || input.parentElement;
      if (!container || container.dataset.brandpulseCompactSearch === '1' || isSidebarElement(container)) {
        continue;
      }

      container.dataset.brandpulseCompactSearch = '1';
      container.classList.add('brandpulse-compact-search');
      setReadableIconColor(container);

      const trigger = document.createElement('button');
      trigger.className = 'brandpulse-search-trigger';
      trigger.type = 'button';
      trigger.title = t('Search');
      trigger.setAttribute('aria-label', t('Search'));

      const icon = document.createElement('span');
      icon.className = 'brandpulse-search-trigger-icon';
      icon.setAttribute('aria-hidden', 'true');
      setMaskIcon(icon, iconAssetEndpoint + encodeURIComponent('Search/Magnifer.svg'));
      trigger.append(icon);

      input.before(trigger);
      const inputParent = input.parentElement;
      for (const nativeControl of inputParent.querySelectorAll('button, .input-group-text, .input-group-addon')) {
        if (nativeControl !== trigger && !nativeControl.contains(input)) {
          nativeControl.classList.add('brandpulse-native-search-control');
        }
      }

      const expand = () => {
        container.classList.add('is-expanded');
        input.focus();
      };

      const collapseIfEmpty = () => {
        if (!input.value) {
          container.classList.remove('is-expanded');
        }
      };

      trigger.addEventListener('click', expand);
      input.addEventListener('focus', () => container.classList.add('is-expanded'));
      input.addEventListener('blur', () => window.setTimeout(collapseIfEmpty, 150));
    }
  };

  const counterSignature = (counter, index) => [
    index,
    counter.key || '',
    counter.label || '',
    counter.icon || '',
    counter.icon_url || '',
  ].join('|');

  const updateCounterElement = (item, counter) => {
    const href = counter.href || '#';
    item.href = href;
    item.title = counter.label || counter.key || '';
    item.setAttribute('aria-label', (counter.label || counter.key) + ': ' + counter.count);

    const badge = item.querySelector('.brandpulse-badge');
    if (badge) {
      badge.textContent = String(counter.count ?? 0);
      badge.style.backgroundColor = counter.color || '#3b82f6';
    }
  };

  const renderCounter = (counter, index) => {
    const item = document.createElement('a');
    item.className = 'brandpulse-counter';
    item.dataset.brandpulseCounterSignature = counterSignature(counter, index);
    item.addEventListener('click', (event) => {
      if (item.getAttribute('href') === '#') {
        event.preventDefault();
      }
    });

    const badge = document.createElement('span');
    badge.className = 'brandpulse-badge';

    item.append(renderIcon(counter), badge);
    updateCounterElement(item, counter);

    return item;
  };

  const render = (payload) => {
    if (!payload.enabled || !Array.isArray(payload.counters)) {
      document.getElementById(CONTAINER_ID)?.remove();
      return;
    }

    const container = ensureContainer();
    if (!container) {
      return;
    }

    setReadableIconColor(container);
    const existingCounters = [...container.querySelectorAll('.brandpulse-counter')];
    const canUpdateCounters = existingCounters.length === payload.counters.length
      && payload.counters.every((counter, index) => (
        existingCounters[index]?.dataset.brandpulseCounterSignature === counterSignature(counter, index)
      ));

    if (canUpdateCounters) {
      payload.counters.forEach((counter, index) => updateCounterElement(existingCounters[index], counter));
      return;
    }

    container.replaceChildren();
    container.hidden = false;
    payload.counters.forEach((counter, index) => {
      container.append(renderCounter(counter, index));
    });
  };

  const hydrateCachedPulse = () => {
    const cachedPayload = cachedPulsePayload();
    if (!cachedPayload || !findHeaderTarget()) {
      return false;
    }

    setupCompactSearch(cachedPayload.compact_search_enabled);
    render(cachedPayload);

    return true;
  };

  const applyBranding = (payload) => {
    const branding = payload?.branding;
    if (!branding?.enabled) {
      return;
    }

    if (branding.title) {
      document.title = branding.title;
    }

    const faviconUrl = resolveAssetUrl(branding.favicon);
    if (faviconUrl) {
      let link = document.querySelector('link[rel="icon"]');
      if (!link) {
        link = document.createElement('link');
        link.rel = 'icon';
        document.head.append(link);
      }
      link.href = faviconUrl;
    }

    const sidebarExpandedLogoUrl = resolveAssetUrl(
      chooseThemeAsset(branding, 'logo_sidebar_expanded', branding.menu_logo)
    );
    const sidebarCollapsedLogoUrl = resolveAssetUrl(
      chooseThemeAsset(branding, 'logo_sidebar_collapsed', sidebarExpandedLogoUrl || branding.menu_logo)
    );
    const sidebarLightLogoUrl = resolveAssetUrl(branding.logo_sidebar_expanded_light || branding.menu_logo || sidebarExpandedLogoUrl);
    const sidebarDarkLogoUrl = resolveAssetUrl(branding.logo_sidebar_expanded_dark || sidebarLightLogoUrl);
    const sidebarReducedLightLogoUrl = resolveAssetUrl(branding.logo_sidebar_collapsed_light || sidebarLightLogoUrl);
    const sidebarReducedDarkLogoUrl = resolveAssetUrl(branding.logo_sidebar_collapsed_dark || sidebarReducedLightLogoUrl);
    const loginLightLogoUrl = resolveAssetUrl(branding.login_logo_light || branding.login_logo || sidebarLightLogoUrl);
    const loginDarkLogoUrl = resolveAssetUrl(branding.login_logo_dark || loginLightLogoUrl);

    setCssUrlVariable('--glpi-logo-light', sidebarLightLogoUrl);
    setCssUrlVariable('--glpi-logo-dark', sidebarDarkLogoUrl);
    setCssUrlVariable('--glpi-logo', detectsDarkTheme() ? sidebarDarkLogoUrl : sidebarLightLogoUrl);
    setCssUrlVariable('--glpi-logo-light-reduced', sidebarReducedLightLogoUrl);
    setCssUrlVariable('--glpi-logo-dark-reduced', sidebarReducedDarkLogoUrl);
    setCssUrlVariable('--glpi-logo-reduced', detectsDarkTheme() ? sidebarReducedDarkLogoUrl : sidebarReducedLightLogoUrl);
    setCssUrlVariable('--glpi-logo-light-login', loginLightLogoUrl);
    setCssUrlVariable('--glpi-logo-dark-login', loginDarkLogoUrl);

    const applyLogoUrl = (targets, url) => {
      if (!url) {
        return;
      }

      for (const target of document.querySelectorAll(targets.join(','))) {
        if (target instanceof HTMLImageElement) {
          target.src = url;
          target.srcset = '';
          continue;
        }

        target.style.backgroundImage = cssUrl(url);
        target.style.backgroundRepeat = 'no-repeat';
        target.style.backgroundPosition = 'center';
        target.style.backgroundSize = 'contain';
        target.style.content = cssUrl(url);
      }
    };

    applyLogoUrl([
      'body > .page header[data-testid="main-header"] .navbar-brand .glpi-logo',
      'body > .page aside.navbar .navbar-brand .glpi-logo',
      '.page header.navbar .navbar-brand .glpi-logo',
      '.page aside.navbar .navbar-brand .glpi-logo',
      'aside .navbar-brand img:not(.logo-sm)',
      '.navbar-vertical .navbar-brand img:not(.logo-sm)',
      '#navbar-menu .navbar-brand img:not(.logo-sm)',
      'aside .navbar-brand-image:not(.logo-sm)',
      '.navbar-vertical .navbar-brand-image:not(.logo-sm)',
      '#navbar-menu .navbar-brand-image:not(.logo-sm)',
    ], sidebarExpandedLogoUrl);

    applyLogoUrl([
      'body.navbar-collapsed aside.navbar .navbar-brand .glpi-logo',
      'body.navbar-collapsed .navbar-vertical .navbar-brand .glpi-logo',
      'aside .navbar-brand img.logo-sm',
      '.navbar-vertical .navbar-brand img.logo-sm',
      '#navbar-menu .navbar-brand img.logo-sm',
      '.navbar-brand .navbar-brand-image-small',
      '.navbar-brand img[data-logo-size="small"]',
      '.navbar-brand .logo-sm',
    ], sidebarCollapsedLogoUrl);

    const loginLogoUrl = resolveAssetUrl(chooseThemeAsset(branding, 'login_logo', branding.login_logo));
    if (loginLogoUrl) {
      for (const loginLogo of document.querySelectorAll('.page-anonymous .glpi-logo, .page-anonymous .navbar-brand img, .login-box img, form[action*="login"] img')) {
        if (loginLogo instanceof HTMLImageElement) {
          loginLogo.src = loginLogoUrl;
          loginLogo.srcset = '';
          continue;
        }

        loginLogo.style.setProperty('--logo', cssUrl(loginLogoUrl), 'important');
        loginLogo.style.content = cssUrl(loginLogoUrl);
        loginLogo.style.backgroundImage = cssUrl(loginLogoUrl);
        loginLogo.style.backgroundRepeat = 'no-repeat';
        loginLogo.style.backgroundPosition = 'center';
        loginLogo.style.backgroundSize = 'contain';
      }
    }

    const backgroundUrl = resolveAssetUrl(branding.login_background);
    if (backgroundUrl && document.querySelector('form[action*="login"], .page-anonymous, .login-box')) {
      document.body.classList.add('brandpulse-login-branded');
      document.body.style.setProperty('--brandpulse-login-background', cssUrl(backgroundUrl));
    }

    if (branding.login_alert_enabled && branding.login_alert_message) {
      const loginContainer = document.querySelector('form[action*="login"]')?.parentElement
        || document.querySelector('.login-box, .page-anonymous .container');
      if (loginContainer && !document.querySelector('.brandpulse-login-alert')) {
        const alert = document.createElement('div');
        alert.className = 'alert alert-' + (branding.login_alert_type || 'info') + ' brandpulse-login-alert';
        alert.textContent = branding.login_alert_message;
        loginContainer.prepend(alert);
      }
    }
  };



  const syncPulseTargetRow = (select) => {
    const row = select.closest('tr');
    if (!row) {
      return;
    }

    for (const target of row.querySelectorAll('[data-pulse-target]')) {
      target.hidden = target.dataset.pulseTarget !== select.value;
    }
  };

  const setupPulseTargets = () => {
    for (const select of document.querySelectorAll('[data-pulse-source]')) {
      syncPulseTargetRow(select);
    }

    document.addEventListener('change', (event) => {
      const select = event.target.closest('[data-pulse-source]');
      if (select) {
        syncPulseTargetRow(select);
      }
    });
  };

  const pulseRows = () => [...document.querySelectorAll('[data-pulse-rows] > [data-pulse-row]')];

  const renumberPulseRows = () => {
    pulseRows().forEach((row, index) => {
      for (const field of row.querySelectorAll('[name^="counters["]')) {
        field.name = field.name.replace(/counters\[[^\]]+\]/, 'counters[' + String(index) + ']');
      }
    });
  };

  const refreshPulseOrderControls = () => {
    const rows = pulseRows();
    const count = rows.length;
    document.querySelector('[data-pulse-row-count]')?.replaceChildren(String(count));

    rows.forEach((row, index) => {
      const up = row.querySelector('[data-pulse-move="up"]');
      const down = row.querySelector('[data-pulse-move="down"]');
      if (up) {
        up.disabled = index === 0;
      }
      if (down) {
        down.disabled = index === count - 1;
      }
    });
  };

  const nextPulseRowIndex = () => {
    let nextIndex = 0;
    for (const field of document.querySelectorAll('[name^="counters["]')) {
      const match = field.name.match(/^counters\[(\d+)\]/);
      if (match) {
        nextIndex = Math.max(nextIndex, Number.parseInt(match[1], 10) + 1);
      }
    }
    return nextIndex;
  };

  const clonePulseRowTemplate = () => {
    const template = document.querySelector('[data-pulse-row-template]');
    const tbody = document.querySelector('[data-pulse-rows]');
    if (!template || !tbody) {
      return;
    }

    const wrapper = document.createElement('tbody');
    wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(nextPulseRowIndex()));
    const row = wrapper.querySelector('[data-pulse-row]');
    if (!row) {
      return;
    }

    tbody.append(row);
    syncPulseTargetRow(row.querySelector('[data-pulse-source]'));
    refreshPulseOrderControls();
  };

  const setupPulseTableControls = () => {
    const table = document.querySelector('[data-pulse-table]');
    if (!table || table.dataset.ready) {
      return;
    }

    table.dataset.ready = '1';
    document.querySelector('[data-pulse-add]')?.addEventListener('click', clonePulseRowTemplate);

    table.addEventListener('click', (event) => {
      const button = event.target.closest('[data-pulse-move]');
      const row = button?.closest('[data-pulse-row]');
      if (!button || !row) {
        return;
      }

      if (button.dataset.pulseMove === 'up' && row.previousElementSibling) {
        row.previousElementSibling.before(row);
      }
      if (button.dataset.pulseMove === 'down' && row.nextElementSibling) {
        row.nextElementSibling.after(row);
      }

      refreshPulseOrderControls();
    });

    table.closest('form')?.addEventListener('submit', renumberPulseRows);
    refreshPulseOrderControls();
  };

  let iconIndexPromise = null;
  let activeIconField = null;
  let iconPage = 0;
  const iconsPerPage = 24;
  let iconSearchTimer = null;
  const iconFilters = {
    category: '',
    query: '',
  };

  const normalizeIcon = (icon) => {
    const normalized = {
      path: String(icon.path || icon.p || ''),
      label: String(icon.label || icon.l || ''),
      category: String(icon.category || icon.c || ''),
      search: String(icon.search || icon.s || ''),
    };
    normalized.search = (normalized.search || [normalized.path, normalized.label, normalized.category].join(' ')).toLowerCase();
    return normalized;
  };

  const loadIconIndex = async () => {
    if (!iconIndexPromise) {
      const fetchIconIndex = (url) => fetch(url, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      }).then((response) => (response.ok ? response.json() : null));

      iconIndexPromise = fetchIconIndex(iconIndexEndpoint)
        .then((index) => {
          const entries = Array.isArray(index?.icons) ? index.icons : (Array.isArray(index?.preferred) ? index.preferred : []);
          if (entries.length > 0) {
            return entries;
          }
          return fetchIconIndex(iconStaticIndexEndpoint).then((fallbackIndex) => (
            Array.isArray(fallbackIndex?.icons) ? fallbackIndex.icons : (Array.isArray(fallbackIndex?.preferred) ? fallbackIndex.preferred : [])
          ));
        })
        .then((entries) => entries.map(normalizeIcon).filter((icon) => icon.path !== ''))
        .catch(() => []);
    }

    return iconIndexPromise;
  };

  const iconMatches = (icon) => {
    if (iconFilters.category && icon.category !== iconFilters.category) {
      return false;
    }

    if (!iconFilters.query) {
      return true;
    }

    return iconFilters.query.split(/\s+/).every((word) => icon.search.includes(word));
  };

  const populateIconCategories = (modal, icons) => {
    const select = modal.querySelector('[data-icon-category]');
    if (!select || select.dataset.ready) {
      return;
    }

    const categories = [...new Set(icons.map((icon) => icon.category).filter(Boolean))]
      .sort((left, right) => left.localeCompare(right, undefined, { sensitivity: 'base' }));

    for (const category of categories) {
      const option = document.createElement('option');
      option.value = category;
      option.textContent = category;
      select.append(option);
    }

    select.dataset.ready = '1';
  };

  const updateIconField = (field, value, label) => {
    const input = field.querySelector('[data-icon-value]');
    const preview = field.querySelector('[data-icon-preview]');
    const textLabel = field.querySelector('[data-icon-label]');

    if (input) {
      input.value = value;
    }
    if (preview) {
      setMaskIcon(preview, resolveIconUrl(value));
    }
    if (textLabel) {
      textLabel.textContent = label;
    }
  };

  const renderIconModal = async () => {
    const modal = document.querySelector('[data-icon-modal]');
    if (!modal) {
      return;
    }

    const allIcons = await loadIconIndex();
    populateIconCategories(modal, allIcons);
    const results = modal.querySelector('[data-icon-results]');
    const pageLabel = modal.querySelector('[data-icon-page]');
    const prev = modal.querySelector('[data-icon-prev]');
    const next = modal.querySelector('[data-icon-next]');

    const filtered = allIcons.filter(iconMatches);
    const pageCount = Math.max(1, Math.ceil(filtered.length / iconsPerPage));
    iconPage = Math.min(iconPage, pageCount - 1);
    const pageIcons = filtered.slice(iconPage * iconsPerPage, (iconPage + 1) * iconsPerPage);

    results.replaceChildren();
    for (const icon of pageIcons) {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'brandpulse-icon-result';
      button.title = icon.category + ' / ' + icon.label;
      button.dataset.iconValue = 'pulse:' + icon.path;
      button.dataset.iconLabel = icon.category + ' / ' + icon.label;

      const preview = document.createElement('img');
      preview.className = 'brandpulse-icon-result-preview';
      preview.src = resolveIconUrl(button.dataset.iconValue);
      preview.alt = '';
      preview.loading = 'lazy';
      preview.decoding = 'async';
      preview.setAttribute('aria-hidden', 'true');
      preview.addEventListener('error', () => button.classList.add('brandpulse-icon-result-missing'), { once: true });

      const label = document.createElement('span');
      label.textContent = icon.label;

      button.append(preview, label);
      results.append(button);
    }

    if (pageLabel) {
      pageLabel.textContent = t('Page') + ' ' + String(iconPage + 1) + ' / ' + String(pageCount) + ' - ' + String(filtered.length) + ' ' + t('icons');
    }
    if (prev) {
      prev.disabled = iconPage <= 0;
    }
    if (next) {
      next.disabled = iconPage >= pageCount - 1;
    }
  };

  const openIconModal = async (field) => {
    const modal = document.querySelector('[data-icon-modal]');
    if (!modal) {
      return;
    }

    activeIconField = field;
    iconPage = 0;
    modal.hidden = false;
    document.body.classList.add('brandpulse-icon-modal-open');
    modal.querySelector('[data-icon-search]')?.focus();
    await renderIconModal();
  };

  const closeIconModal = () => {
    const modal = document.querySelector('[data-icon-modal]');
    if (modal) {
      modal.hidden = true;
    }
    activeIconField = null;
    document.body.classList.remove('brandpulse-icon-modal-open');
  };

  const setupIconPickerModal = () => {
    const modal = document.querySelector('[data-icon-modal]');
    if (!modal || modal.dataset.ready) {
      return;
    }

    modal.dataset.ready = '1';
    document.addEventListener('click', (event) => {
      const opener = event.target.closest('[data-icon-open]');
      if (opener) {
        const field = opener.closest('[data-icon-field]');
        if (field) {
          openIconModal(field);
        }
        return;
      }

      const selected = event.target.closest('.brandpulse-icon-result');
      if (selected && activeIconField) {
        updateIconField(activeIconField, selected.dataset.iconValue, selected.dataset.iconLabel);
        closeIconModal();
      }
    });

    modal.addEventListener('click', (event) => {
      if (event.target.closest('[data-icon-close]')) {
        closeIconModal();
      }
    });

    modal.querySelector('[data-icon-search]')?.addEventListener('input', (event) => {
      iconFilters.query = event.target.value.trim().toLowerCase();
      iconPage = 0;
      window.clearTimeout(iconSearchTimer);
      iconSearchTimer = window.setTimeout(renderIconModal, 140);
    });

    modal.querySelector('[data-icon-category]')?.addEventListener('change', (event) => {
      iconFilters.category = event.target.value;
      iconPage = 0;
      renderIconModal();
    });

    modal.querySelector('[data-icon-prev]')?.addEventListener('click', () => {
      iconPage = Math.max(0, iconPage - 1);
      renderIconModal();
    });

    modal.querySelector('[data-icon-next]')?.addEventListener('click', () => {
      iconPage += 1;
      renderIconModal();
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && !modal.hidden) {
        closeIconModal();
      }
    });
  };

  const scheduleRefresh = (payload) => {
    if (refreshTimer) {
      window.clearTimeout(refreshTimer);
    }

    const interval = Math.max(15, Number(payload.refresh_interval || 60)) * 1000;
    refreshTimer = window.setTimeout(loadCounters, interval);
  };

  async function loadBranding() {
    try {
      const response = await fetch(brandingEndpoint, {
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
        },
      });

      if (response.ok) {
        applyBranding(await response.json());
      }
    } catch (error) {
      window.console?.debug?.(t('BrandPulse branding unavailable'), error);
    }
  }

  async function loadCounters() {
    try {
      const response = await fetch(countersEndpoint, {
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
        },
      });

      if (!response.ok) {
        return;
      }

      const payload = await response.json();
      cachePulsePayload(payload);
      setupCompactSearch(payload.compact_search_enabled);
      render(payload);
      scheduleRefresh(payload);
    } catch (error) {
      window.console?.debug?.(t('BrandPulse counters unavailable'), error);
    }
  }

  const boot = () => {
    if (!isHtmlDocument()) {
      return;
    }

    setupPulseTargets();
    setupPulseTableControls();
    setupIconPickerModal();
    loadBranding();

    if (findHeaderTarget()) {
      hydrateCachedPulse();
      loadCounters();
    }
  };

  const observeHeaderForCachedPulse = () => {
    if (!cachedPulsePayload() || hydrateCachedPulse()) {
      return;
    }

    const observer = new MutationObserver(() => {
      if (hydrateCachedPulse()) {
        observer.disconnect();
      }
    });

    observer.observe(document.documentElement, {
      childList: true,
      subtree: true,
    });
    window.addEventListener('load', () => observer.disconnect(), { once: true });
  };

  observeHeaderForCachedPulse();

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
  } else {
    boot();
  }
})();
