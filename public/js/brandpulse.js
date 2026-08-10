(() => {
  'use strict';

  const PLUGIN_MARKER = '/plugins/brandpulse/';
  const CONTAINER_ID = 'brandpulse-header-counters';

  const currentScript = document.currentScript;
  const scriptUrl = currentScript?.src || '';
  const rootDoc = window.CFG_GLPI?.root_doc || '';
  const pluginBaseUrl = scriptUrl.includes(PLUGIN_MARKER)
    ? scriptUrl.substring(0, scriptUrl.indexOf('/public/js/brandpulse.js'))
    : rootDoc + '/plugins/brandpulse';
  const countersEndpoint = pluginBaseUrl + '/ajax/counters.php';
  const brandingEndpoint = pluginBaseUrl + '/ajax/branding.php';

  const t = (message) => (typeof window.__ === 'function' ? window.__(message, 'brandpulse') : message);
  let refreshTimer = null;

  const resolveAssetUrl = (value) => {
    if (!value) {
      return '';
    }

    if (/^(https?:)?\/\//.test(value) || value.startsWith('data:') || value.startsWith('/')) {
      return value;
    }

    return rootDoc + '/' + value.replace(/^\/+/, '');
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

  const findHeaderTarget = () => {
    const selectors = [
      'body > .page header.navbar .navbar-nav.flex-row.order-md-last',
      'body > .page header.navbar .navbar-nav.ms-auto',
      'header.navbar .navbar-nav.flex-row.order-md-last',
      'header.navbar .navbar-nav.ms-auto',
      '.navbar:not(#navbar-menu) .navbar-nav.flex-row.order-md-last',
      '.navbar:not(#navbar-menu) .navbar-nav.ms-auto',
      'header.navbar',
    ];

    for (const selector of selectors) {
      const target = document.querySelector(selector);
      if (target && !isSidebarElement(target)) {
        return target;
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

  const resolveIconUrl = (icon) => {
    if (!icon) {
      return pluginBaseUrl + '/public/icons/pulse/bell.svg';
    }

    if (icon.startsWith('pulse:')) {
      return pluginBaseUrl + '/public/icons/pulse/' + icon.substring(6) + '.svg';
    }

    if (icon.endsWith('.svg') || icon.startsWith('/') || icon.startsWith('http')) {
      return icon;
    }

    return null;
  };

  const setMaskIcon = (element, iconUrl) => {
    element.style.webkitMaskImage = cssUrl(iconUrl);
    element.style.maskImage = cssUrl(iconUrl);
  };

  const renderIcon = (counter) => {
    const icon = counter.icon || 'pulse:bell';
    const iconUrl = resolveIconUrl(icon);

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
        container.classList.remove('brandpulse-compact-search', 'is-expanded');
      }
      return;
    }

    const inputs = document.querySelectorAll([
      'header.navbar input[type="search"]',
      'header.navbar input[name="globalsearch"]',
      'header.navbar input[name="criteria"]',
      '.navbar:not(#navbar-menu) input[type="search"]',
      '.navbar:not(#navbar-menu) input[name="globalsearch"]',
      '.navbar:not(#navbar-menu) input[name="criteria"]',
    ].join(','));

    for (const input of inputs) {
      if (isSidebarElement(input)) {
        continue;
      }

      const container = input.closest('form, .input-group, .search, .navbar-search') || input.parentElement;
      if (!container || container.classList.contains('brandpulse-compact-search') || isSidebarElement(container)) {
        continue;
      }

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
      setMaskIcon(icon, pluginBaseUrl + '/public/icons/pulse/search.svg');
      trigger.append(icon);

      input.before(trigger);

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

  const renderCounter = (counter) => {
    const item = document.createElement('a');
    item.className = 'brandpulse-counter';
    item.href = counter.href || '#';
    item.title = counter.label || counter.key || '';
    item.setAttribute('aria-label', (counter.label || counter.key) + ': ' + counter.count);

    if (item.href.endsWith('#')) {
      item.addEventListener('click', (event) => event.preventDefault());
    }

    const badge = document.createElement('span');
    badge.className = 'brandpulse-badge';
    badge.textContent = String(counter.count ?? 0);
    badge.style.backgroundColor = counter.color || '#3b82f6';

    item.append(renderIcon(counter), badge);
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
    container.replaceChildren();
    container.hidden = false;
    for (const counter of payload.counters) {
      container.append(renderCounter(counter));
    }
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

    const menuLogoUrl = resolveAssetUrl(branding.menu_logo);
    if (menuLogoUrl) {
      for (const logo of document.querySelectorAll('aside .navbar-brand img, .navbar-vertical .navbar-brand img, #navbar-menu .navbar-brand img')) {
        logo.src = menuLogoUrl;
      }
    }

    const loginLogoUrl = resolveAssetUrl(branding.login_logo);
    if (loginLogoUrl) {
      const loginLogo = document.querySelector('.page-anonymous .navbar-brand img, .login-box img, form[action*="login"] img');
      if (loginLogo) {
        loginLogo.src = loginLogoUrl;
      }
    }

    const backgroundUrl = resolveAssetUrl(branding.login_background);
    if (backgroundUrl && document.querySelector('form[action*="login"], .page-anonymous, .login-box')) {
      document.body.classList.add('brandpulse-login-branded');
      document.body.style.setProperty('--brandpulse-login-background', 'url("' + backgroundUrl + '")');
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
      setupCompactSearch(payload.compact_search_enabled);
      render(payload);
      scheduleRefresh(payload);
    } catch (error) {
      window.console?.debug?.(t('BrandPulse counters unavailable'), error);
    }
  }

  const boot = () => {
    loadBranding();
    loadCounters();
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
  } else {
    boot();
  }
})();
