(() => {
  'use strict';

  const PLUGIN_MARKER = '/plugins/brandpulse/';
  const CONTAINER_ID = 'brandpulse-header-counters';

  const currentScript = document.currentScript;
  const scriptUrl = currentScript?.src || '';
  const pluginBaseUrl = scriptUrl.includes(PLUGIN_MARKER)
    ? scriptUrl.substring(0, scriptUrl.indexOf('/public/js/brandpulse.js'))
    : `${window.CFG_GLPI?.root_doc || ''}/plugins/brandpulse`;
  const endpoint = `${pluginBaseUrl}/ajax/counters.php`;

  const t = (message) => (typeof window.__ === 'function' ? window.__(message, 'brandpulse') : message);
  let refreshTimer = null;

  const findHeaderTarget = () => {
    const selectors = [
      '#navbar-menu',
      'header .navbar-nav',
      'header nav',
      'header',
    ];

    for (const selector of selectors) {
      const target = document.querySelector(selector);
      if (target) {
        return target;
      }
    }

    return null;
  };

  const ensureContainer = () => {
    const existing = document.getElementById(CONTAINER_ID);
    if (existing) {
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
    target.append(container);

    return container;
  };

  const resolveIconUrl = (icon) => {
    if (!icon) {
      return `${pluginBaseUrl}/public/icons/pulse/bell.svg`;
    }

    if (icon.startsWith('pulse:')) {
      return `${pluginBaseUrl}/public/icons/pulse/${icon.substring(6)}.svg`;
    }

    if (icon.endsWith('.svg') || icon.startsWith('/') || icon.startsWith('http')) {
      return icon;
    }

    return null;
  };

  const renderIcon = (counter) => {
    const icon = counter.icon || 'pulse:bell';
    const iconUrl = resolveIconUrl(icon);

    if (iconUrl) {
      const image = document.createElement('img');
      image.className = 'brandpulse-icon';
      image.src = iconUrl;
      image.alt = '';
      image.loading = 'lazy';
      image.decoding = 'async';
      image.setAttribute('aria-hidden', 'true');
      return image;
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
      'header input[type="search"]',
      'header input[name="globalsearch"]',
      'header input[name="criteria"]',
      '#navbar-menu input[type="search"]',
    ].join(','));

    for (const input of inputs) {
      const container = input.closest('form, .input-group, .search, .navbar-search') || input.parentElement;
      if (!container || container.classList.contains('brandpulse-compact-search')) {
        continue;
      }

      container.classList.add('brandpulse-compact-search');

      const trigger = document.createElement('button');
      trigger.className = 'brandpulse-search-trigger';
      trigger.type = 'button';
      trigger.title = t('Search');
      trigger.setAttribute('aria-label', t('Search'));

      const icon = document.createElement('img');
      icon.src = `${pluginBaseUrl}/public/icons/pulse/search.svg`;
      icon.alt = '';
      icon.setAttribute('aria-hidden', 'true');
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
    item.setAttribute('aria-label', `${counter.label || counter.key}: ${counter.count}`);

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

    container.replaceChildren();
    container.hidden = false;
    for (const counter of payload.counters) {
      container.append(renderCounter(counter));
    }
  };

  const scheduleRefresh = (payload) => {
    if (refreshTimer) {
      window.clearTimeout(refreshTimer);
    }

    const interval = Math.max(15, Number(payload.refresh_interval || 60)) * 1000;
    refreshTimer = window.setTimeout(loadCounters, interval);
  };

  async function loadCounters() {
    try {
      const response = await fetch(endpoint, {
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

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadCounters, { once: true });
  } else {
    loadCounters();
  }
})();
