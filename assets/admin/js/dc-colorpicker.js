/**
 * Design Cart Migration
 * Code: design-cart-migration
 * Autor: Paweł Nosko — https://www.designcart.pl/pawel-nosko.html
 * Firma: Design Cart — https://www.designcart.pl/
 */

/**
 * DC Color Picker — samodzielny komponent vanilla JS
 *
 * HTML:
 *   <div class="dc-colorpicker" data-dc-colorpicker data-value="#1fa28c" data-name="color"></div>
 *
 * JS:
 *   DCColorPicker.initAll();
 *   // lub
 *   new DCColorPicker(element, { value: '#1fa28c', onChange: (c) => {} });
 */
(function (global) {
  'use strict';

  const DEFAULT_PRESETS = [
    '#1fa28c', '#1ca7ca', '#262c38',
    '#e5484d', '#f5a623', '#22a06b',
    '#6366f1', '#ec4899', '#ffffff', '#000000',
  ];

  /* ── Konwersje kolorów ── */

  function clamp(n, min, max) {
    return Math.min(max, Math.max(min, n));
  }

  function hexToRgb(hex) {
    const h = hex.replace('#', '');
    const full = h.length === 3
      ? h.split('').map(c => c + c).join('')
      : h.padStart(6, '0').slice(0, 6);
    const n = parseInt(full, 16);
    return { r: (n >> 16) & 255, g: (n >> 8) & 255, b: n & 255 };
  }

  function rgbToHex(r, g, b) {
    return '#' + [r, g, b].map(v => clamp(Math.round(v), 0, 255).toString(16).padStart(2, '0')).join('');
  }

  function rgbToHsv(r, g, b) {
    r /= 255; g /= 255; b /= 255;
    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    const d = max - min;
    let h = 0;
    const v = max;
    const s = max === 0 ? 0 : d / max;

    if (d !== 0) {
      switch (max) {
        case r: h = ((g - b) / d + (g < b ? 6 : 0)) / 6; break;
        case g: h = ((b - r) / d + 2) / 6; break;
        default: h = ((r - g) / d + 4) / 6;
      }
    }
    return { h: h * 360, s, v };
  }

  function hsvToRgb(h, s, v) {
    h = ((h % 360) + 360) % 360 / 60;
    const c = v * s;
    const x = c * (1 - Math.abs(h % 2 - 1));
    const m = v - c;
    let r = 0, g = 0, b = 0;

    if (h < 1) { r = c; g = x; }
    else if (h < 2) { r = x; g = c; }
    else if (h < 3) { g = c; b = x; }
    else if (h < 4) { g = x; b = c; }
    else if (h < 5) { r = x; b = c; }
    else { r = c; b = x; }

    return {
      r: (r + m) * 255,
      g: (g + m) * 255,
      b: (b + m) * 255,
    };
  }

  function parseColor(str) {
    if (!str) return null;
    str = str.trim();

    if (/^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(str)) {
      const rgb = hexToRgb(str);
      return { ...rgb, ...rgbToHsv(rgb.r, rgb.g, rgb.b), a: 1, hex: rgbToHex(rgb.r, rgb.g, rgb.b) };
    }

    const rgbMatch = str.match(/^rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)(?:\s*,\s*([\d.]+))?\s*\)$/i);
    if (rgbMatch) {
      const r = +rgbMatch[1], g = +rgbMatch[2], b = +rgbMatch[3];
      const a = rgbMatch[4] !== undefined ? +rgbMatch[4] : 1;
      const hsv = rgbToHsv(r, g, b);
      return { r, g, b, ...hsv, a, hex: rgbToHex(r, g, b) };
    }
    return null;
  }

  function colorToRgba(c) {
    return `rgba(${Math.round(c.r)}, ${Math.round(c.g)}, ${Math.round(c.b)}, ${c.a ?? 1})`;
  }

  function parseCssColor(str) {
    if (!str || str === 'transparent') return null;

    const hex = parseColor(str);
    if (hex) return hex.hex;

    const rgba = str.match(/rgba?\(\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)(?:\s*,\s*([\d.]+))?\s*\)/i);
    if (rgba) {
      const a = rgba[4] !== undefined ? parseFloat(rgba[4]) : 1;
      if (a <= 0) return null;
      return rgbToHex(+rgba[1], +rgba[2], +rgba[3]);
    }
    return null;
  }

  function sampleColorAtPoint(x, y, excludeEl) {
    for (const el of document.elementsFromPoint(x, y)) {
      if (excludeEl?.contains(el)) continue;
      if (el.closest?.('.dc-colorpicker__pick-screen')) continue;

      let node = el;
      while (node && node.nodeType === 1) {
        const cs = getComputedStyle(node);
        for (const prop of ['backgroundColor', 'color', 'borderTopColor']) {
          const hex = parseCssColor(cs[prop]);
          if (hex) return hex;
        }
        node = node.parentElement;
      }
    }
    return null;
  }

  function eyedropperApiAvailable() {
    return typeof global.EyeDropper === 'function' && global.isSecureContext;
  }

  /* ── Komponent ── */

  class DCColorPicker {
    constructor(root, options = {}) {
      if (!root || !(root instanceof HTMLElement)) {
        throw new Error('DCColorPicker: wymagany element DOM');
      }

      this.root = root;
      this.options = {
        value: root.dataset.value || '#1fa28c',
        name: root.dataset.name || '',
        label: root.dataset.label || 'Kolor',
        presets: DEFAULT_PRESETS,
        alpha: root.dataset.alpha === 'true',
        compact: root.hasAttribute('data-compact'),
        onChange: null,
        ...options,
      };

      if (root.dataset.presets) {
        this.options.presets = root.dataset.presets.split(',').map(s => s.trim());
      }

      this.isOpen = false;
      this.dragging = null;
      this._pagePickerActive = false;

      this._build();
      this._bind();
      this.setValue(this.options.value, { silent: true });
    }

    static initAll(selector = '[data-dc-colorpicker]') {
      return [...document.querySelectorAll(selector)].map(el => {
        if (el._dcColorPicker) return el._dcColorPicker;
        const instance = new DCColorPicker(el);
        el._dcColorPicker = instance;
        return instance;
      });
    }

    _build() {
      const { name, label, compact, alpha } = this.options;

      this.root.classList.toggle('dc-compact', compact);
      this.root.innerHTML = `
        ${name ? `<input type="hidden" class="dc-colorpicker__input" name="${name}">` : ''}
        <button type="button" class="dc-colorpicker__trigger" aria-haspopup="dialog" aria-expanded="false">
          <span class="dc-colorpicker__preview"></span>
          <span class="dc-colorpicker__label">${label}</span>
          <span class="dc-colorpicker__hex-display"></span>
        </button>
        <div class="dc-colorpicker__popover" role="dialog" aria-label="Wybór koloru" hidden>
          <div class="dc-colorpicker__sl" tabindex="0" aria-label="Nasycenie i jasność">
            <div class="dc-colorpicker__sl-bg"></div>
            <div class="dc-colorpicker__sl-white"></div>
            <div class="dc-colorpicker__sl-black"></div>
            <div class="dc-colorpicker__sl-cursor"></div>
          </div>
          <div class="dc-colorpicker__hue-wrap" aria-label="Odcień">
            <div class="dc-colorpicker__hue-thumb"></div>
          </div>
          <div class="dc-colorpicker__alpha-wrap" ${alpha ? '' : 'hidden'} aria-label="Przezroczystość">
            <div class="dc-colorpicker__alpha-gradient"></div>
            <div class="dc-colorpicker__alpha-thumb"></div>
          </div>
          <div class="dc-colorpicker__fields">
            <input type="text" class="dc-colorpicker__hex" spellcheck="false" maxlength="7" aria-label="Wartość HEX">
            <button type="button" class="dc-colorpicker__eyedropper" title="Pipeta (EyeDropper API)" aria-label="Pipeta">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 22l12-12m0 0l3 3m-3-3l4-4a2.5 2.5 0 013.5 0l1 1a2.5 2.5 0 010 3.5l-4 4m-3-3l-3 3"/></svg>
            </button>
          </div>
          <p class="dc-colorpicker__presets-label">Presety</p>
          <div class="dc-colorpicker__presets"></div>
        </div>
      `;

      this.els = {
        trigger: this.root.querySelector('.dc-colorpicker__trigger'),
        preview: this.root.querySelector('.dc-colorpicker__preview'),
        hexDisplay: this.root.querySelector('.dc-colorpicker__hex-display'),
        popover: this.root.querySelector('.dc-colorpicker__popover'),
        hidden: this.root.querySelector('.dc-colorpicker__input'),
        sl: this.root.querySelector('.dc-colorpicker__sl'),
        slBg: this.root.querySelector('.dc-colorpicker__sl-bg'),
        slCursor: this.root.querySelector('.dc-colorpicker__sl-cursor'),
        hueWrap: this.root.querySelector('.dc-colorpicker__hue-wrap'),
        hueThumb: this.root.querySelector('.dc-colorpicker__hue-thumb'),
        alphaWrap: this.root.querySelector('.dc-colorpicker__alpha-wrap'),
        alphaGradient: this.root.querySelector('.dc-colorpicker__alpha-gradient'),
        alphaThumb: this.root.querySelector('.dc-colorpicker__alpha-thumb'),
        hexInput: this.root.querySelector('.dc-colorpicker__hex'),
        eyedropper: this.root.querySelector('.dc-colorpicker__eyedropper'),
        presets: this.root.querySelector('.dc-colorpicker__presets'),
      };

      this.els.presets.innerHTML = this.options.presets.map(color =>
        `<button type="button" class="dc-colorpicker__preset" style="background:${color}" data-color="${color}" aria-label="${color}"></button>`
      ).join('');

      this.els.eyedropper.title = eyedropperApiAvailable()
        ? 'Pipeta — kliknij kolor na ekranie'
        : 'Pipeta — kliknij element na stronie';
    }

    _bind() {
      this.els.trigger.addEventListener('click', () => this.toggle());
      this.els.hexInput.addEventListener('change', () => this._fromHexInput());
      this.els.hexInput.addEventListener('keydown', e => { if (e.key === 'Enter') this._fromHexInput(); });

      this.els.sl.addEventListener('pointerdown', e => this._startDrag('sl', e));
      this.els.hueWrap.addEventListener('pointerdown', e => this._startDrag('hue', e));
      if (this.options.alpha) {
        this.els.alphaWrap.addEventListener('pointerdown', e => this._startDrag('alpha', e));
      }

      this.els.presets.addEventListener('click', e => {
        const btn = e.target.closest('.dc-colorpicker__preset');
        if (btn) this.setValue(btn.dataset.color);
      });

      this.els.eyedropper.addEventListener('click', e => this._eyedropper(e));

      this._onDocClick = e => {
        if (!this.isOpen) return;
        if (!this.root.contains(e.target)) this.close();
      };
      document.addEventListener('click', this._onDocClick);

      this._onKeydown = e => {
        if (e.key === 'Escape' && this.isOpen) this.close();
      };
      document.addEventListener('keydown', this._onKeydown);

      this._onPointerMove = e => this._onDrag(e);
      this._onPointerUp = () => this._stopDrag();
    }

    _startDrag(type, e) {
      this.dragging = type;
      this.root.setPointerCapture?.(e.pointerId);
      document.addEventListener('pointermove', this._onPointerMove);
      document.addEventListener('pointerup', this._onPointerUp);
      this._onDrag(e);
    }

    _stopDrag() {
      this.dragging = null;
      document.removeEventListener('pointermove', this._onPointerMove);
      document.removeEventListener('pointerup', this._onPointerUp);
    }

    _onDrag(e) {
      if (!this.dragging) return;

      if (this.dragging === 'sl') {
        const rect = this.els.sl.getBoundingClientRect();
        const s = clamp((e.clientX - rect.left) / rect.width, 0, 1);
        const v = clamp(1 - (e.clientY - rect.top) / rect.height, 0, 1);
        this.color.s = s;
        this.color.v = v;
        this._syncFromHsv();
      } else if (this.dragging === 'hue') {
        const rect = this.els.hueWrap.getBoundingClientRect();
        this.color.h = clamp((e.clientX - rect.left) / rect.width, 0, 1) * 360;
        this._syncFromHsv();
      } else if (this.dragging === 'alpha') {
        const rect = this.els.alphaWrap.getBoundingClientRect();
        this.color.a = clamp((e.clientX - rect.left) / rect.width, 0, 1);
        this._syncFromHsv();
      }
    }

    _fromHexInput() {
      const parsed = parseColor(this.els.hexInput.value);
      if (parsed) {
        this.els.hexInput.classList.remove('dc-invalid');
        this.setValue(parsed.hex);
      } else {
        this.els.hexInput.classList.add('dc-invalid');
      }
    }

    _eyedropper(e) {
      e.preventDefault();
      e.stopPropagation();

      if (eyedropperApiAvailable()) {
        this._eyedropperNative();
      } else {
        this._startPagePicker();
      }
    }

    _eyedropperNative() {
      const wasOpen = this.isOpen;
      this.close();

      const dropper = new global.EyeDropper();
      dropper.open()
        .then(result => {
          const hex = result?.sRGBHex;
          if (hex) this.setValue(hex);
          if (wasOpen) this.open();
        })
        .catch(err => {
          if (wasOpen) this.open();
          if (err?.name === 'AbortError') return;
          this._startPagePicker();
        });
    }

    _startPagePicker() {
      if (this._pagePickerActive) return;
      this._pagePickerActive = true;

      const wasOpen = this.isOpen;
      this.close();

      const overlay = document.createElement('div');
      overlay.className = 'dc-colorpicker__pick-screen';
      overlay.innerHTML = `
        <div class="dc-colorpicker__pick-hint">Kliknij element, aby pobrać kolor · Esc = anuluj</div>
        <div class="dc-colorpicker__pick-loupe" hidden></div>
      `;
      document.body.appendChild(overlay);

      const loupe = overlay.querySelector('.dc-colorpicker__pick-loupe');
      let picked = false;

      const cleanup = (reopen = wasOpen) => {
        if (!this._pagePickerActive) return;
        this._pagePickerActive = false;
        document.removeEventListener('pointermove', onMove, true);
        document.removeEventListener('click', onClick, true);
        document.removeEventListener('keydown', onKey, true);
        overlay.remove();
        document.body.classList.remove('dc-colorpicker-picking');
        if (reopen && !picked) this.open();
      };

      const onMove = ev => {
        const hex = sampleColorAtPoint(ev.clientX, ev.clientY, this.root);
        loupe.hidden = false;
        loupe.style.left = `${ev.clientX}px`;
        loupe.style.top = `${ev.clientY}px`;
        if (hex) {
          loupe.style.background = hex;
          loupe.style.setProperty('--loupe-color', hex);
        }
      };

      const onClick = ev => {
        ev.preventDefault();
        ev.stopPropagation();
        const hex = sampleColorAtPoint(ev.clientX, ev.clientY, this.root);
        picked = true;
        cleanup(false);
        if (hex) {
          this.setValue(hex);
          if (wasOpen) this.open();
        }
      };

      const onKey = ev => {
        if (ev.key === 'Escape') cleanup(true);
      };

      document.body.classList.add('dc-colorpicker-picking');
      document.addEventListener('pointermove', onMove, true);
      document.addEventListener('keydown', onKey, true);

      requestAnimationFrame(() => {
        document.addEventListener('click', onClick, true);
      });
    }

    _syncFromHsv() {
      const rgb = hsvToRgb(this.color.h, this.color.s, this.color.v);
      this.color.r = rgb.r;
      this.color.g = rgb.g;
      this.color.b = rgb.b;
      this.color.hex = rgbToHex(rgb.r, rgb.g, rgb.b);
      this._render({ emit: true });
    }

    _render({ emit = false } = {}) {
      const c = this.color;
      const bg = colorToRgba(c);

      this.root.style.setProperty('--cp-hue', c.h);
      this.els.preview.style.background = bg;
      this.els.hexDisplay.textContent = c.hex;
      this.els.hexInput.value = c.hex;
      this.els.slCursor.style.left = `${c.s * 100}%`;
      this.els.slCursor.style.top = `${(1 - c.v) * 100}%`;
      this.els.slCursor.style.background = bg;
      this.els.hueThumb.style.left = `${(c.h / 360) * 100}%`;

      if (this.options.alpha) {
        const opaque = rgbToHex(c.r, c.g, c.b);
        this.els.alphaGradient.style.background =
          `linear-gradient(to right, transparent, ${opaque})`;
        this.els.alphaThumb.style.left = `${(c.a ?? 1) * 100}%`;
      }

      if (this.els.hidden) {
        this.els.hidden.value = this.options.alpha ? colorToRgba(c) : c.hex;
      }

      this.els.presets.querySelectorAll('.dc-colorpicker__preset').forEach(btn => {
        btn.classList.toggle('dc-active', btn.dataset.color.toLowerCase() === c.hex.toLowerCase());
      });

      this.root.dataset.value = c.hex;

      if (emit) {
        const detail = this.getValue();
        if (typeof this.options.onChange === 'function') {
          this.options.onChange(detail, this);
        }
        this.root.dispatchEvent(new CustomEvent('dc:colorchange', { detail, bubbles: true }));
      }
    }

    getValue() {
      const c = this.color;
      return {
        hex: c.hex,
        rgb: { r: Math.round(c.r), g: Math.round(c.g), b: Math.round(c.b) },
        hsv: { h: c.h, s: c.s, v: c.v },
        alpha: c.a ?? 1,
        css: this.options.alpha ? colorToRgba(c) : c.hex,
      };
    }

    setValue(value, { silent = false } = {}) {
      const parsed = parseColor(value);
      if (!parsed) return this;

      this.color = {
        h: parsed.h,
        s: parsed.s,
        v: parsed.v,
        r: parsed.r,
        g: parsed.g,
        b: parsed.b,
        a: parsed.a ?? 1,
        hex: parsed.hex,
      };

      this._render({ emit: !silent });
      return this;
    }

    open() {
      this.isOpen = true;
      this.root.classList.add('dc-open');
      this.els.trigger.setAttribute('aria-expanded', 'true');
      this.els.popover.hidden = false;
      this._positionPopover();
    }

    close() {
      this.isOpen = false;
      this.root.classList.remove('dc-open', 'dc-popover-up');
      this.els.trigger.setAttribute('aria-expanded', 'false');
      this.els.popover.hidden = true;
    }

    toggle() {
      this.isOpen ? this.close() : this.open();
    }

    _positionPopover() {
      const rect = this.els.popover.getBoundingClientRect();
      const spaceBelow = window.innerHeight - this.root.getBoundingClientRect().bottom;
      this.root.classList.toggle('dc-popover-up', spaceBelow < rect.height + 16);
    }

    destroy() {
      this.close();
      document.removeEventListener('click', this._onDocClick);
      document.removeEventListener('keydown', this._onKeydown);
      delete this.root._dcColorPicker;
    }
  }

  global.DCColorPicker = DCColorPicker;

  function autoInit() {
    DCColorPicker.initAll('[data-dc-colorpicker]:not([data-manual])');
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', autoInit);
  } else {
    autoInit();
  }
})(typeof window !== 'undefined' ? window : globalThis);
