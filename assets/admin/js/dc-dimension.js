/**
 * Design Cart Migration
 * Code: design-cart-migration
 * Autor: Paweł Nosko — https://www.designcart.pl/pawel-nosko.html
 * Firma: Design Cart — https://www.designcart.pl/
 */

/**
 * DC Dimension — range + input + px/%
 *
 * HTML:
 *   <div class="dc-dimension" data-dc-dimension
 *        data-name="width" data-unit-name="width_unit"
 *        data-value="38" data-unit="%" data-label="Szerokość"></div>
 */
(function (global) {
  'use strict';

  function clamp(n, min, max) {
    return Math.min(max, Math.max(min, n));
  }

  function parseNum(v, fallback) {
    var n = parseFloat(v);
    return Number.isFinite(n) ? n : fallback;
  }

  class DCDimension {
    constructor(root, options) {
      if (!root || !(root instanceof HTMLElement)) {
        throw new Error('DCDimension: wymagany element DOM');
      }

      this.root = root;
      this.options = {
        name: root.dataset.name || '',
        unitName: root.dataset.unitName || '',
        label: root.dataset.label || '',
        value: parseNum(root.dataset.value, 100),
        unit: root.dataset.unit === 'px' ? 'px' : '%',
        min: parseNum(root.dataset.min, 1),
        maxPx: parseNum(root.dataset.maxPx, 2000),
        maxPct: parseNum(root.dataset.maxPct, 100),
        step: parseNum(root.dataset.step, 1),
        fixedUnit: root.dataset.fixedUnit || '',
        onChange: null,
        ...options,
      };

      if (!this.options.unitName && this.options.name) {
        this.options.unitName = this.options.name + '_unit';
      }

      this._build();
      this._bind();
      this.setUnit(this.options.fixedUnit || this.options.unit, { silent: true });
      this.setValue(this.options.value, { silent: true });
    }

    static initAll(selector) {
      selector = selector || '[data-dc-dimension]:not([data-manual])';
      return [...document.querySelectorAll(selector)].map(function (el) {
        if (el._dcDimension) return el._dcDimension;
        var instance = new DCDimension(el);
        el._dcDimension = instance;
        return instance;
      });
    }

    _build() {
      var o = this.options;
      var fixed = !!o.fixedUnit;
      this.root.classList.toggle('dc-dimension--fixed', fixed);

      var unitsHtml = fixed
        ? ''
        : '<div class="dc-dimension__units" role="radiogroup" aria-label="Jednostka">' +
          '<label class="dc-dimension__unit-btn">' +
          '<input type="radio" class="dc-dimension__unit-radio" name="' + this._uid('u') + '" value="px">' +
          '<span class="dc-dimension__unit-label">px</span></label>' +
          '<label class="dc-dimension__unit-btn">' +
          '<input type="radio" class="dc-dimension__unit-radio" name="' + this._uid('u') + '" value="%">' +
          '<span class="dc-dimension__unit-label">%</span></label></div>';

      var numberWrapClass = fixed ? ' dc-dimension__number-wrap' : '';
      var suffixHtml = fixed ? '<span class="dc-dimension__suffix">' + o.fixedUnit + '</span>' : '';

      this.root.innerHTML =
        (o.label ? '<span class="dc-dimension__label">' + o.label + '</span>' : '') +
        (o.name ? '<input type="hidden" class="dc-dimension__hidden-value" name="' + o.name + '">' : '') +
        (!fixed && o.unitName ? '<input type="hidden" class="dc-dimension__hidden-unit" name="' + o.unitName + '">' : '') +
        '<div class="dc-dimension__control">' +
        '<input type="range" class="dc-dimension__range" aria-label="' + (o.label || 'Wymiar') + '">' +
        '<div class="dc-dimension__row">' +
        '<div class="' + numberWrapClass.trim() + '">' +
        '<input type="number" class="dc-dimension__number" inputmode="numeric" step="' + o.step + '">' +
        suffixHtml + '</div>' +
        unitsHtml +
        '</div></div>';

      this.els = {
        range: this.root.querySelector('.dc-dimension__range'),
        number: this.root.querySelector('.dc-dimension__number'),
        hiddenValue: this.root.querySelector('.dc-dimension__hidden-value'),
        hiddenUnit: this.root.querySelector('.dc-dimension__hidden-unit'),
        unitRadios: [...this.root.querySelectorAll('.dc-dimension__unit-radio')],
      };
    }

    _uid(prefix) {
      if (!this.root.id) {
        this.root.id = 'dc-dim-' + Math.random().toString(36).slice(2, 9);
      }
      return prefix + '-' + this.root.id;
    }

    _bind() {
      var self = this;

      this.els.range.addEventListener('input', function () {
        self.setValue(parseNum(self.els.range.value, self.value), { from: 'range' });
      });

      this.els.number.addEventListener('input', function () {
        if (self.els.number.value === '') return;
        self.setValue(parseNum(self.els.number.value, self.value), { from: 'number' });
      });

      this.els.number.addEventListener('change', function () {
        self.setValue(parseNum(self.els.number.value, self.value), { from: 'number' });
      });

      this.els.unitRadios.forEach(function (radio) {
        radio.addEventListener('change', function () {
          if (radio.checked) self.setUnit(radio.value);
        });
      });
    }

    _maxForUnit(unit) {
      return unit === 'px' ? this.options.maxPx : this.options.maxPct;
    }

    getValue() {
      return {
        value: this.value,
        unit: this.unit,
        css: this.unit === 'px' ? this.value + 'px' : this.value + '%',
      };
    }

    setValue(value, opts) {
      opts = opts || {};
      value = clamp(Math.round(parseNum(value, this.value)), this.options.min, this._maxForUnit(this.unit));

      this.value = value;
      this.els.range.value = value;
      this.els.number.value = value;
      if (this.els.hiddenValue) this.els.hiddenValue.value = value;

      this.root.dataset.value = String(value);

      if (!opts.silent) this._emit(opts.from || 'api');
      return this;
    }

    setUnit(unit, opts) {
      opts = opts || {};
      unit = unit === 'px' ? 'px' : '%';
      this.unit = unit;

      var max = this._maxForUnit(unit);
      this.els.range.min = this.options.min;
      this.els.range.max = max;
      this.els.range.step = this.options.step;
      this.els.number.min = this.options.min;
      this.els.number.max = max;
      this.els.number.step = this.options.step;

      this.els.unitRadios.forEach(function (r) {
        r.checked = r.value === unit;
      });

      if (this.els.hiddenUnit) this.els.hiddenUnit.value = unit;
      this.root.dataset.unit = unit;

      if (!opts.silent) {
        this.setValue(clamp(this.value, this.options.min, max), { silent: true });
        this._emit('unit');
      } else {
        this.value = clamp(this.value, this.options.min, max);
        this.els.range.value = this.value;
        this.els.number.value = this.value;
        if (this.els.hiddenValue) this.els.hiddenValue.value = this.value;
      }

      return this;
    }

    _emit(source) {
      var detail = Object.assign({ source: source }, this.getValue());
      if (typeof this.options.onChange === 'function') {
        this.options.onChange(detail, this);
      }
      this.root.dispatchEvent(new CustomEvent('dc:dimensionchange', { detail: detail, bubbles: true }));
    }

    destroy() {
      delete this.root._dcDimension;
    }
  }

  global.DCDimension = DCDimension;
})(typeof window !== 'undefined' ? window : globalThis);
