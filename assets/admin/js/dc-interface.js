/**
 * Design Cart Migration
 * Code: design-cart-migration
 * Autor: Paweł Nosko — https://www.designcart.pl/pawel-nosko.html
 * Firma: Design Cart — https://www.designcart.pl/
 */

/**
 * DC Interface — interakcje UI (tabs, accordion, file drop)
 */
(function (global) {
  'use strict';

  function qsAll(sel, root) {
    return [...(root || document).querySelectorAll(sel)];
  }

  function setActive(el, on) {
    if (!el) return;
    el.classList.toggle('dc-active', on);
    el.classList.toggle('is-active', on);
    if (el.matches('[role="tab"]')) {
      el.setAttribute('aria-selected', on ? 'true' : 'false');
    }
  }

  function clearActive(selector, root) {
    qsAll(selector, root).forEach(function (el) { setActive(el, false); });
  }

  function findPanelContainer(group) {
    if (!group) return null;

    var explicit = group.getAttribute('data-dc-tab-panels') || group.getAttribute('data-sf-tab-panels');
    if (explicit) {
      var scope = group.closest('.dc-form-card, .dc-card, .dc-interface, .dc-page') || document;
      return scope.querySelector(explicit) || document.querySelector(explicit);
    }

    var parent = group.parentElement;
    if (!parent) return null;

    var panelsWrap = parent.querySelector(':scope > .dc-tab-panels, :scope > .dc-form-card__body, :scope > .dc-card__body');
    if (panelsWrap) return panelsWrap;

    if (parent.querySelector(':scope > .dc-tab-panel')) return parent;

    var sibling = group.nextElementSibling;
    while (sibling) {
      if (sibling.matches('.dc-tab-panels, .dc-form-card__body, .dc-card__body') || sibling.querySelector(':scope > .dc-tab-panel')) {
        return sibling;
      }
      sibling = sibling.nextElementSibling;
    }

    return parent.querySelector(':scope > .dc-form-card__body, :scope > .dc-card__body') || parent;
  }

  function setDirectPanelsActive(container, activeId) {
    if (!container) return null;

    var panels = [...container.children].filter(function (el) {
      return el.classList.contains('dc-tab-panel');
    });

    panels.forEach(function (p) { setActive(p, false); });

    var panel = container.querySelector('#' + CSS.escape(activeId));
    if (panel && panel.classList.contains('dc-tab-panel')) {
      setActive(panel, true);
    }
    return panel;
  }

  /* ── Tabs ── */
  function initTabs(root) {
    qsAll('[data-dc-tab], [data-sf-tab]', root).forEach(function (btn) {
      if (btn._dcTabBound) return;
      btn._dcTabBound = true;

      btn.addEventListener('click', function () {
        var id = this.getAttribute('data-dc-tab') || this.getAttribute('data-sf-tab');
        if (!id) return;

        var group = this.closest('.dc-tabs, .dc-nav, .dc-pills') || this.parentElement;
        if (group) {
          clearActive('[data-dc-tab], [data-sf-tab]', group);
        }

        setActive(this, true);
        setDirectPanelsActive(findPanelContainer(group), id);
      });
    });
  }

  /* ── Language / nested tabs ── */
  function initLangTabs(root) {
    qsAll('[data-dc-lang], [data-sf-lang]', root).forEach(function (btn) {
      if (btn._dcLangBound) return;
      btn._dcLangBound = true;

      btn.addEventListener('click', function () {
        var id = this.getAttribute('data-dc-lang') || this.getAttribute('data-sf-lang');
        var section = this.closest('.dc-section, .dc-section-card, .dc-interface') || document;
        if (!id) return;

        var group = this.closest('.dc-pills, .dc-lang-pills, .dc-tabs') || this.parentElement;
        if (group) {
          clearActive('[data-dc-lang], [data-sf-lang]', group);
        }

        setActive(this, true);
        qsAll('.dc-lang-panel', section).forEach(function (p) { setActive(p, false); });

        var panel = section.querySelector('#' + CSS.escape(id));
        if (panel) setActive(panel, true);
      });
    });
  }

  /* ── Accordion ── */
  function initAccordion(root) {
    qsAll('[data-dc-accordion-trigger]', root).forEach(function (trigger) {
      if (trigger._dcAccBound) return;
      trigger._dcAccBound = true;

      trigger.addEventListener('click', function () {
        var item = this.closest('.dc-accordion__item');
        if (!item) return;

        var accordion = this.closest('.dc-accordion');
        var single = accordion && accordion.hasAttribute('data-dc-accordion-single');

        if (single) {
          qsAll('.dc-accordion__item.dc-open', accordion).forEach(function (open) {
            if (open !== item) {
              open.classList.remove('dc-open');
              var t = open.querySelector('[data-dc-accordion-trigger]');
              if (t) t.setAttribute('aria-expanded', 'false');
            }
          });
        }

        var isOpen = item.classList.toggle('dc-open');
        this.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      });
    });
  }

  /* ── File drop zones ── */
  function initFileDrop(root) {
    qsAll('.dc-drop', root).forEach(function (zone) {
      if (zone._dcDropBound) return;
      zone._dcDropBound = true;

      var input = zone.querySelector('.dc-drop__input');
      var list = zone.querySelector('.dc-drop__files');
      if (!input) return;

      ['dragenter', 'dragover'].forEach(function (evt) {
        zone.addEventListener(evt, function (e) {
          e.preventDefault();
          zone.classList.add('dc-dragover');
        });
      });

      ['dragleave', 'drop'].forEach(function (evt) {
        zone.addEventListener(evt, function (e) {
          e.preventDefault();
          zone.classList.remove('dc-dragover');
        });
      });

      zone.addEventListener('drop', function (e) {
        input.files = e.dataTransfer.files;
        renderFiles(input.files, list);
      });

      input.addEventListener('change', function () {
        renderFiles(input.files, list);
      });
    });
  }

  function renderFiles(files, list) {
    if (!list) return;
    list.innerHTML = '';
    if (!files.length) {
      list.hidden = true;
      return;
    }
    list.hidden = false;
    [...files].forEach(function (file) {
      var item = document.createElement('div');
      item.className = 'dc-drop__file';
      item.innerHTML =
        '<span class="dc-drop__file-icon" aria-hidden="true">📄</span>' +
        '<span class="dc-drop__file-name">' + file.name + '</span>' +
        '<span class="dc-drop__file-size">' + formatSize(file.size) + '</span>';
      list.appendChild(item);
    });
  }

  function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
  }

  function initAll(root) {
    root = root || document;
    initTabs(root);
    initLangTabs(root);
    initAccordion(root);
    initFileDrop(root);
    if (global.DCColorPicker) {
      global.DCColorPicker.initAll('[data-dc-colorpicker]:not([data-manual])');
    }
    if (global.DCDimension) {
      global.DCDimension.initAll('[data-dc-dimension]:not([data-manual])');
    }
  }

  global.DCInterface = {
    init: initAll,
    initTabs: initTabs,
    initAccordion: initAccordion,
    initFileDrop: initFileDrop,
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { initAll(); });
  } else {
    initAll();
  }
})(typeof window !== 'undefined' ? window : globalThis);
