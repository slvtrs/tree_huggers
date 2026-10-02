/* PixelLayer: a Leaflet GridLayer that fetches ordinary raster tiles, shrinks
 * them, snaps every pixel to a small palette (with optional ordered dithering),
 * and blows them back up with no smoothing. Real geography, 8-bit look. */
(function (L) {
  'use strict';
  if (!L) return;

  var PALETTES = {
    color: {
      mode: 'nearest',
      colors: ['#f8f0d8', '#ffffff', '#60a8e8', '#78c850', '#388040', '#f8d870', '#f08080',
               '#c8b8a8', '#a0a0a0', '#505050', '#202020', '#e8c8a8', '#a8d8f8', '#c0f0a0',
               '#f8f8a0', '#e0b0d0', '#e8e4dc', '#d0ccc4']
    },
    gameboy: { mode: 'luma', colors: ['#0f380f', '#306230', '#8bac0f', '#9bbc0f'] },
    mono:    { mode: 'luma', colors: ['#111111', '#555555', '#aaaaaa', '#f4f4f4'] }
  };

  var BAYER = [[0, 8, 2, 10], [12, 4, 14, 6], [3, 11, 1, 9], [15, 7, 13, 5]];

  function hexToRgb(h) {
    var n = parseInt(h.slice(1), 16);
    return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
  }

  var PixelLayer = L.GridLayer.extend({
    options: {
      tileUrl: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
      pixelSize: 4,
      palette: 'color',
      dither: true,
      ditherStrength: 12,
      maxZoom: 19,
      maxNativeZoom: 19
    },

    initialize: function (opts) {
      L.GridLayer.prototype.initialize.call(this, opts);
      this._setPaletteCache();
    },

    setPalette: function (name) {
      if (!PALETTES[name]) return;
      this.options.palette = name;
      this._setPaletteCache();
      this.redraw();
    },

    _setPaletteCache: function () {
      var p = PALETTES[this.options.palette] || PALETTES.color;
      this._mode = p.mode;
      this._rgb = p.colors.map(hexToRgb);
    },

    createTile: function (coords, done) {
      var tile = document.createElement('canvas');
      var size = this.getTileSize();
      tile.width = size.x;
      tile.height = size.y;
      tile.className = 'leaflet-tile';
      var img = new Image();
      var self = this;
      img.crossOrigin = 'anonymous';
      img.onload = function () {
        try { self._pixelate(img, tile); done(null, tile); }
        catch (e) { tile.getContext('2d').drawImage(img, 0, 0, tile.width, tile.height); done(null, tile); }
      };
      img.onerror = function (e) { done(e, tile); };
      img.src = L.Util.template(this.options.tileUrl, L.extend({ x: coords.x, y: coords.y, z: coords.z, s: 'a' }, this.options));
      return tile;
    },

    _pixelate: function (img, tile) {
      var ps = Math.max(1, this.options.pixelSize | 0);
      var w = Math.ceil(tile.width / ps), h = Math.ceil(tile.height / ps);
      var small = document.createElement('canvas');
      small.width = w; small.height = h;
      var sctx = small.getContext('2d', { willReadFrequently: true });
      sctx.imageSmoothingEnabled = true;
      sctx.drawImage(img, 0, 0, w, h);
      var image = sctx.getImageData(0, 0, w, h);
      this._quantize(image.data, w, h);
      sctx.putImageData(image, 0, 0);
      var ctx = tile.getContext('2d');
      ctx.imageSmoothingEnabled = false;
      ctx.drawImage(small, 0, 0, w * ps, h * ps);
    },

    _quantize: function (d, w, h) {
      var pal = this._rgb, n = pal.length, luma = this._mode === 'luma';
      var dither = this.options.dither, strength = this.options.ditherStrength;
      for (var y = 0; y < h; y++) {
        for (var x = 0; x < w; x++) {
          var i = (y * w + x) * 4;
          var r = d[i], g = d[i + 1], b = d[i + 2];
          if (dither) {
            var t = (BAYER[y & 3][x & 3] / 16 - 0.5) * strength;
            r += t; g += t; b += t;
          }
          var best;
          if (luma) {
            var l = 0.299 * r + 0.587 * g + 0.114 * b;
            best = Math.min(n - 1, Math.max(0, Math.floor(l / (256 / n))));
          } else {
            var bd = 1e9; best = 0;
            for (var k = 0; k < n; k++) {
              var c = pal[k], dr = r - c[0], dg = g - c[1], db = b - c[2];
              var dist = dr * dr * 0.3 + dg * dg * 0.59 + db * db * 0.11;
              if (dist < bd) { bd = dist; best = k; }
            }
          }
          var out = pal[best];
          d[i] = out[0]; d[i + 1] = out[1]; d[i + 2] = out[2]; d[i + 3] = 255;
        }
      }
    }
  });

  // Little control to switch palettes; remembers the choice.
  var PaletteControl = L.Control.extend({
    options: { position: 'topright', layer: null, storageKey: 'th_palette' },
    onAdd: function () {
      var div = L.DomUtil.create('div', 'th-palette leaflet-bar');
      var layer = this.options.layer, self = this;
      L.DomEvent.disableClickPropagation(div);
      Object.keys(PALETTES).forEach(function (name) {
        var b = L.DomUtil.create('button', name === layer.options.palette ? 'on' : '', div);
        b.type = 'button';
        b.textContent = name.toUpperCase();
        b.title = 'Palette: ' + name;
        L.DomEvent.on(b, 'click', function () {
          layer.setPalette(name);
          try { localStorage.setItem(self.options.storageKey, name); } catch (e) {}
          Array.prototype.forEach.call(div.children, function (c) { c.className = c === b ? 'on' : ''; });
        });
      });
      return div;
    }
  });

  L.PixelLayer = PixelLayer;
  L.pixelLayer = function (opts) { return new PixelLayer(opts); };
  L.PixelLayer.PALETTES = PALETTES;
  L.Control.Palette = PaletteControl;
})(window.L);
