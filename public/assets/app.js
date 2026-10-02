/* Tree Huggers page scripts. Everything hangs off which elements exist. */
(function () {
  'use strict';
  var cfgEl = document.getElementById('th-config');
  var CFG = cfgEl ? JSON.parse(cfgEl.textContent) : {};
  CFG.base = CFG.base || '';

  function initPage() {
  // ---- claim tokens: mirror the server's cookie into localStorage and back ----
    (function () {
      var LS = 'th_claims', body = document.body;
      var store = {};
      try { store = JSON.parse(localStorage.getItem(LS) || '{}') || {}; } catch (e) {}
      function save() { try { localStorage.setItem(LS, JSON.stringify(store)); } catch (e) {} }

      // The show page hands us the token for a tree hugged anonymously from this browser.
      var holder = document.querySelector('[data-claim-token]');
      if (holder) { store[holder.getAttribute('data-claim-id')] = holder.getAttribute('data-claim-token'); save(); }

      var known = [];
      try { known = JSON.parse(body.getAttribute('data-claim-ids') || '[]'); } catch (e) {}
      var missing = {};
      Object.keys(store).forEach(function (id) { if (known.indexOf(id) === -1) missing[id] = store[id]; });
      var csrf = (document.querySelector('meta[name=csrf]') || {}).content;
      if (Object.keys(missing).length && csrf && body.getAttribute('data-sync-url')) {
        fetch(body.getAttribute('data-sync-url'), {
          method: 'POST', credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
          body: JSON.stringify({ claims: missing })
        }).then(function (r) { return r.json(); }).then(function (res) {
          (res.drop || []).forEach(function (id) { delete store[id]; });
          save();
          if ((res.added > 0 || res.claimed > 0) && !sessionStorage.getItem('th_synced')) {
            sessionStorage.setItem('th_synced', '1');
            location.reload();
          }
        }).catch(function () {});
      }
    })();

    // ---- tree form: disabled "planting..." state while the photo uploads ----
    var treeForm = document.getElementById('tree-form');
    if (treeForm) {
      var submitBtn = treeForm.querySelector('button[type=submit]');
      var dots = null;
      function resetSubmit() {
        if (!submitBtn) return;
        clearInterval(dots);
        submitBtn.disabled = false;
        submitBtn.classList.remove('busy');
        if (submitBtn.dataset.label) submitBtn.innerHTML = submitBtn.dataset.label;
      }
      treeForm.addEventListener('submit', function () {
        if (!submitBtn || submitBtn.disabled) return;
        submitBtn.dataset.label = submitBtn.innerHTML;
        submitBtn.classList.add('busy');
        var n = 0;
        submitBtn.textContent = 'Planting';
        dots = setInterval(function () { n = (n + 1) % 4; submitBtn.textContent = 'Planting' + '...'.slice(0, n); }, 350);
        // disable after this tick so the button's value still posts with the form
        setTimeout(function () { submitBtn.disabled = true; }, 0);
      });
      // Back button / bfcache restore: re-enable so the form is usable again
      window.addEventListener('pageshow', function (e) { if (e.persisted) resetSubmit(); });
    }

    if (!window.L) return; // maps need Leaflet

    // ---- pixel tree sprite as a Leaflet icon ----
    var SPRITE = [
      '......OOOO......', '....OOGGGGOO....', '...OGGLLGGGGO...', '..OGGLLLGGGGGO..',
      '..OGGGLGGGGGGO..', '.OGGGGGGGGGGGGO.', '.OGGGGGGGDDGGGO.', '.OGGGGGGGGDGGGO.',
      '..OGGGGGGGGGGO..', '...OGGGGDDGGO...', '....OOGGGGOO....', '......OTTO......',
      '......OTTO......', '......OTTO......', '.....OTTTTO.....', '.....OOOOOO.....'
    ];
    var COLORS = { O: '#1b2a1c', G: '#3fa34d', L: '#8fe388', D: '#2a7a37', T: '#7a4a24' };
    var HILITE = { O: '#1b2a1c', G: '#f0b429', L: '#ffe08a', D: '#c98a12', T: '#7a4a24' };

    function spriteSvg(colors) {
      var s = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" shape-rendering="crispEdges">';
      SPRITE.forEach(function (row, y) {
        for (var x = 0; x < row.length; x++) {
          var c = colors[row[x]];
          if (c) s += '<rect x="' + x + '" y="' + y + '" width="1" height="1" fill="' + c + '"/>';
        }
      });
      return s + '</svg>';
    }
    function icon(colors, size) {
      size = size || 32;
      return L.icon({
        iconUrl: 'data:image/svg+xml,' + encodeURIComponent(spriteSvg(colors)),
        iconSize: [size, size], iconAnchor: [size / 2, size - 1], popupAnchor: [0, -size + 4],
        className: 'th-tree'
      });
    }
    var TREE_ICON = icon(COLORS), TREE_ICON_HI = icon(HILITE, 40);

    // ---- base map ----
    function makeMap(el, opts) {
      opts = opts || {};
      var palette = 'color';
      try { palette = localStorage.getItem('th_palette') || palette; } catch (e) {}
      var map = L.map(el, {
        center: opts.center || CFG.center || [20, 0],
        zoom: opts.zoom || CFG.zoom || 3,
        zoomSnap: 1, zoomControl: true, worldCopyJump: true,
        attributionControl: true
      });
      map.attributionControl.setPrefix('');
      var tiles = L.pixelLayer({
        tileUrl: CFG.tileUrl, attribution: CFG.attribution,
        pixelSize: CFG.pixelSize || 4, palette: palette
      }).addTo(map);
      if (opts.paletteControl !== false) map.addControl(new L.Control.Palette({ layer: tiles }));
      return map;
    }

    function esc(s) {
      return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
      });
    }
    function popupHtml(t) {
      var h = '<div class="popup"><b>' + esc(t.title) + '</b>';
      if (t.thumb) h += '<a href="' + esc(t.url) + '"><img src="' + esc(t.thumb) + '" alt=""></a>';
      if (t.species && t.species.length) h += '<div class="species">' + esc(t.species.join(', ')) + '</div>';
      h += '<div class="small">' + (t.user ? 'by ' + esc(t.user) : 'anonymous') + (t.when ? ', ' + esc(t.when) : '') + '</div>';
      h += '<a href="' + esc(t.url) + '">visit this tree &raquo;</a></div>';
      return h;
    }
    function addTrees(map, trees, fit) {
      var group = L.featureGroup();
      trees.forEach(function (t) {
        if (t.lat == null || t.lng == null) return;
        L.marker([t.lat, t.lng], { icon: TREE_ICON, title: t.title }).bindPopup(popupHtml(t), { maxWidth: 220 }).addTo(group);
      });
      group.addTo(map);
      if (fit && trees.length) {
        if (trees.length === 1) map.setView([trees[0].lat, trees[0].lng], 15);
        else map.fitBounds(group.getBounds().pad(0.15), { maxZoom: 15 });
      }
      return group;
    }

    // ---- home: all trees ----
    var home = document.querySelector('#map[data-trees-url]');
    if (home) {
      var map = makeMap(home);
      fetch(home.getAttribute('data-trees-url'), { headers: { Accept: 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (trees) {
          addTrees(map, trees, true);
          if (!trees.length && navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function (pos) {
              map.setView([pos.coords.latitude, pos.coords.longitude], 12);
            }, function () {}, { timeout: 5000 });
          }
        })
        .catch(function () {});
    }

    // ---- show page: one tree ----
    var one = document.querySelector('#map[data-tree]');
    if (one) {
      var t = JSON.parse(one.getAttribute('data-tree'));
      var m = makeMap(one, { center: [t.lat, t.lng], zoom: 16, paletteControl: false });
      L.marker([t.lat, t.lng], { icon: TREE_ICON_HI, title: t.title }).addTo(m);
    }

    // ---- profile: a user's trees ----
    var mine = document.querySelector('#map[data-trees]');
    if (mine) {
      addTrees(makeMap(mine), JSON.parse(mine.getAttribute('data-trees')), true);
    }

    // ---- form: location picker ----
    var picker = document.getElementById('locpicker');
    var latIn = document.getElementById('lat'), lngIn = document.getElementById('lng');
    var onLocationChange = [];
    function fireLocation() {
      var lat = parseFloat(latIn.value), lng = parseFloat(lngIn.value);
      if (isFinite(lat) && isFinite(lng)) onLocationChange.forEach(function (f) { f(lat, lng); });
    }
    if (picker && latIn && lngIn) {
      var has = picker.dataset.lat !== '' && picker.dataset.lng !== '';
      var pmap = makeMap(picker, {
        center: has ? [parseFloat(picker.dataset.lat), parseFloat(picker.dataset.lng)] : undefined,
        zoom: has ? 16 : undefined, paletteControl: false
      });
      var marker = null;
      function place(lat, lng, pan) {
        lat = Math.round(lat * 1e6) / 1e6; lng = Math.round(lng * 1e6) / 1e6;
        latIn.value = lat; lngIn.value = lng;
        if (!marker) {
          marker = L.marker([lat, lng], { icon: TREE_ICON_HI, draggable: true }).addTo(pmap);
          marker.on('dragend', function () { var p = marker.getLatLng(); place(p.lat, p.lng, false); });
        } else marker.setLatLng([lat, lng]);
        if (pan) pmap.setView([lat, lng], Math.max(pmap.getZoom(), 15));
        fireLocation();
      }
      if (has) place(parseFloat(picker.dataset.lat), parseFloat(picker.dataset.lng), false);
      pmap.on('click', function (e) { place(e.latlng.lat, e.latlng.lng, false); });
      [latIn, lngIn].forEach(function (inp) {
        inp.addEventListener('change', function () {
          var lat = parseFloat(latIn.value), lng = parseFloat(lngIn.value);
          if (isFinite(lat) && isFinite(lng) && Math.abs(lat) <= 90 && Math.abs(lng) <= 180) place(lat, lng, true);
        });
      });
      var useBtn = document.getElementById('use-location');
      if (useBtn) {
        if (!navigator.geolocation) useBtn.disabled = true;
        useBtn.addEventListener('click', function () {
          useBtn.disabled = true; useBtn.textContent = 'locating...';
          navigator.geolocation.getCurrentPosition(function (pos) {
            place(pos.coords.latitude, pos.coords.longitude, true);
            useBtn.disabled = false; useBtn.innerHTML = '&#9673; Use my location';
          }, function () {
            useBtn.disabled = false; useBtn.textContent = 'location unavailable';
          }, { enableHighAccuracy: true, timeout: 10000 });
        });
      }
      if (!has && navigator.geolocation && navigator.permissions) {
        // If the user already granted location, center the picker there (without placing a marker).
        navigator.permissions.query({ name: 'geolocation' }).then(function (p) {
          if (p.state === 'granted') navigator.geolocation.getCurrentPosition(function (pos) {
            pmap.setView([pos.coords.latitude, pos.coords.longitude], 13);
          });
        }).catch(function () {});
      }
    }

    // ---- form: species picker ----
    var widget = document.getElementById('species-widget');
    if (widget) {
      var hidden = document.getElementById('species-json');
      var chips = document.getElementById('species-chips');
      var q = document.getElementById('species-q');
      var results = document.getElementById('species-results');
      var nearby = document.getElementById('species-nearby');
      var max = parseInt(widget.dataset.max, 10) || 5;
      var selected = [];
      try { selected = JSON.parse(hidden.value) || []; } catch (e) {}

      function label(s) {
        return s.common_name && s.common_name.toLowerCase() !== s.name.toLowerCase()
          ? esc(s.common_name) + ' <span class="sci">(' + esc(s.name) + ')</span>' : '<span class="sci">' + esc(s.name) + '</span>';
      }
      function isSelected(id) { return selected.some(function (s) { return s.id === id; }); }
      function sync() {
        hidden.value = JSON.stringify(selected.map(function (s) { return { id: s.id, name: s.name, common_name: s.common_name || '' }; }));
        chips.innerHTML = selected.map(function (s, i) {
          return '<span class="chip">' + (s.photo ? '<img src="' + esc(s.photo) + '" alt="">' : '') + label(s) +
            '<span class="x" data-i="' + i + '" title="remove">&times;</span></span>';
        }).join('');
        q.disabled = selected.length >= max;
        q.placeholder = q.disabled ? 'max ' + max + ' tags' : 'search, e.g. red maple';
        Array.prototype.forEach.call(nearby.querySelectorAll('.chip[data-id]'), function (c) {
          c.classList.toggle('used', isSelected(parseInt(c.dataset.id, 10)));
        });
      }
      function add(s) {
        if (selected.length >= max || isSelected(s.id)) return;
        selected.push(s); sync();
      }
      chips.addEventListener('click', function (e) {
        if (e.target.classList.contains('x')) { selected.splice(parseInt(e.target.dataset.i, 10), 1); sync(); }
      });

      var timer = null, lastQ = '';
      q.addEventListener('input', function () {
        clearTimeout(timer);
        var term = q.value.trim();
        if (term.length < 2) { results.innerHTML = ''; return; }
        timer = setTimeout(function () {
          lastQ = term;
          fetch(widget.dataset.searchUrl + '?q=' + encodeURIComponent(term))
            .then(function (r) { return r.json(); })
            .then(function (list) {
              if (term !== lastQ) return;
              if (!list.length) { results.innerHTML = '<ul><li class="muted">nothing found</li></ul>'; return; }
              var ul = document.createElement('ul');
              list.forEach(function (s) {
                var li = document.createElement('li');
                li.innerHTML = (s.photo ? '<img src="' + esc(s.photo) + '" alt="">' : '<span style="width:28px"></span>') + '<span>' + label(s) + '</span>';
                li.addEventListener('click', function () { add(s); results.innerHTML = ''; q.value = ''; });
                ul.appendChild(li);
              });
              results.innerHTML = ''; results.appendChild(ul);
            }).catch(function () {});
        }, 280);
      });
      document.addEventListener('click', function (e) { if (!results.contains(e.target) && e.target !== q) results.innerHTML = ''; });
      q.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });

      var lastNearbyKey = null;
      function loadNearby(lat, lng) {
        var key = lat.toFixed(1) + ',' + lng.toFixed(1);
        if (key === lastNearbyKey) return;
        lastNearbyKey = key;
        nearby.innerHTML = '<span class="muted">looking up trees near here...</span>';
        fetch(widget.dataset.nearbyUrl + '?lat=' + lat + '&lng=' + lng)
          .then(function (r) { return r.json(); })
          .then(function (list) {
            if (!list.length || list.error) { nearby.innerHTML = '<span class="muted">No tree observations nearby yet.</span>'; return; }
            nearby.innerHTML = '<b>Trees commonly seen near here</b> (click to tag):<br>' + list.map(function (s) {
              return '<span class="chip" data-id="' + s.id + '">' + (s.photo ? '<img src="' + esc(s.photo) + '" alt="">' : '') + label(s) +
                ' <span class="n">&times;' + s.count + '</span></span>';
            }).join('');
            Array.prototype.forEach.call(nearby.querySelectorAll('.chip'), function (c, i) {
              c.addEventListener('click', function () { add(list[i]); });
            });
            sync();
          }).catch(function () { nearby.innerHTML = '<span class="muted">Could not reach iNaturalist right now.</span>'; });
      }
      onLocationChange.push(loadNearby);
      sync();
      fireLocation();
    }
  }

  initPage();

  // ---- soft navigation: fetch pages and swap the content so music never stops ----
  (function () {
    if (!window.fetch || !window.history || !window.DOMParser) return;
    var page = document.querySelector('.page');
    if (!page) return;

    function sameOrigin(a) {
      return a.origin === location.origin && a.pathname.indexOf(CFG.base + '/') === 0;
    }
    function isSoftLink(a) {
      if (!a || a.target || a.hasAttribute('download') || a.getAttribute('href') === null) return false;
      var href = a.getAttribute('href');
      if (href.charAt(0) === '#' || /^(mailto|tel|javascript):/.test(href)) return false;
      if (!sameOrigin(a)) return false;
      if (/\.(jpe?g|png|gif|webp|svg|json|css|js|pdf|zip)(\?|$)/i.test(a.pathname)) return false;
      if (a.pathname.indexOf(CFG.base + '/uploads/') === 0 || a.pathname.indexOf(CFG.base + '/api/') === 0) return false;
      return true;
    }

    function swap(doc, url, push) {
      var next = doc.querySelector('.page');
      if (!next) { location.href = url; return; }
      page.innerHTML = next.innerHTML;
      document.title = doc.title;
      ['data-claim-ids', 'data-sync-url'].forEach(function (k) {
        var v = doc.body.getAttribute(k);
        if (v !== null) document.body.setAttribute(k, v);
      });
      var csrf = doc.querySelector('meta[name=csrf]'), mine = document.querySelector('meta[name=csrf]');
      if (csrf && mine) mine.content = csrf.content;
      if (push) history.pushState({ soft: true }, '', url);
      window.scrollTo(0, 0);
      initPage();
      if (window.THMusic) window.THMusic.render();
    }

    var pending = 0;
    function go(url, push) {
      var id = ++pending;
      document.documentElement.classList.add('loading');
      fetch(url, { credentials: 'same-origin', headers: { 'X-Soft-Nav': '1' } })
        .then(function (r) {
          if (!r.ok && r.status !== 404) throw new Error('bad status');
          if ((r.headers.get('content-type') || '').indexOf('text/html') === -1) throw new Error('not html');
          return r.text().then(function (html) { return { html: html, url: r.url || url }; });
        })
        .then(function (res) {
          if (id !== pending) return;
          swap(new DOMParser().parseFromString(res.html, 'text/html'), res.url, push);
        })
        .catch(function () { location.href = url; })
        .then(function () { document.documentElement.classList.remove('loading'); });
    }

    document.addEventListener('click', function (e) {
      if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
      var a = e.target.closest && e.target.closest('a');
      if (!isSoftLink(a)) return;
      e.preventDefault();
      go(a.href, true);
    });
    window.addEventListener('popstate', function () { go(location.href, false); });
    history.replaceState({ soft: true }, '', location.href);
  })();
})();
