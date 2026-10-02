/* A tiny chiptune tracker: square-wave lead, triangle bass, a soft arpeggio
 * and noise drums, all synthesized with Web Audio so there is nothing to
 * download. Browsers only allow sound after a user gesture, so it starts on
 * the first click or tap. The toggle remembers its state in localStorage and
 * the song position carries across page loads so it feels continuous. */
(function () {
  'use strict';
  var KEY = 'th_music', POS = 'th_music_pos';
  var BPM = 118, STEPS_PER_BEAT = 4, STEP = 60 / BPM / STEPS_PER_BEAT;
  var BARS = 8, STEPS = BARS * 16;

  // Chords per bar (I V vi IV, two bars each): root midi + chord tones for the arp.
  var CHORDS = [
    { root: 36, arp: [60, 64, 67, 72] }, { root: 36, arp: [60, 64, 67, 72] },
    { root: 43, arp: [59, 62, 67, 71] }, { root: 43, arp: [59, 62, 67, 71] },
    { root: 45, arp: [57, 60, 64, 69] }, { root: 45, arp: [57, 60, 64, 69] },
    { root: 41, arp: [57, 60, 65, 69] }, { root: 41, arp: [57, 60, 65, 69] }
  ];
  // Lead melody as [midi or 0 for rest, length in 16th steps], 8 bars.
  var LEAD = [
    [76,2],[79,2],[76,2],[72,2],[74,2],[76,2],[79,3],[0,1],
    [81,2],[79,2],[76,2],[74,2],[72,6],[0,2],
    [74,2],[79,2],[83,2],[79,2],[81,2],[79,2],[74,3],[0,1],
    [71,2],[74,2],[79,2],[81,2],[79,6],[0,2],
    [81,2],[84,2],[83,2],[81,2],[76,3],[0,1],[79,2],[81,2],
    [84,2],[83,2],[81,2],[79,2],[76,6],[0,2],
    [77,2],[81,2],[84,2],[81,2],[79,2],[77,2],[76,2],[74,2],
    [72,2],[74,2],[76,2],[77,2],[79,6],[0,2]
  ];
  var leadAt = [];
  (function () { var t = 0; LEAD.forEach(function (n) { leadAt[t] = n; t += n[1]; }); })();

  var ctx = null, master, noiseBuf, timer = null, step = 0, nextTime = 0, playing = false;
  var wanted = (function () { try { return localStorage.getItem(KEY) !== 'off'; } catch (e) { return true; } })();

  function freq(m) { return 440 * Math.pow(2, (m - 69) / 12); }

  function init() {
    if (ctx) return true;
    var AC = window.AudioContext || window.webkitAudioContext;
    if (!AC) return false;
    ctx = new AC();
    master = ctx.createGain();
    master.gain.value = 0.16;
    var lp = ctx.createBiquadFilter();
    lp.type = 'lowpass'; lp.frequency.value = 5200;
    master.connect(lp); lp.connect(ctx.destination);
    noiseBuf = ctx.createBuffer(1, ctx.sampleRate * 0.5, ctx.sampleRate);
    var d = noiseBuf.getChannelData(0);
    for (var i = 0; i < d.length; i++) d[i] = Math.random() * 2 - 1;
    try { step = (parseInt(sessionStorage.getItem(POS), 10) || 0) % STEPS; } catch (e) {}
    return true;
  }

  function tone(type, midi, t, dur, vol, slideTo) {
    var o = ctx.createOscillator(), g = ctx.createGain();
    o.type = type;
    o.frequency.setValueAtTime(freq(midi), t);
    if (slideTo) o.frequency.exponentialRampToValueAtTime(slideTo, t + dur);
    g.gain.setValueAtTime(0.0001, t);
    g.gain.linearRampToValueAtTime(vol, t + 0.005);
    g.gain.setValueAtTime(vol, t + Math.max(0.01, dur - 0.03));
    g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
    o.connect(g); g.connect(master);
    o.start(t); o.stop(t + dur + 0.02);
  }
  function noise(t, dur, vol, hp) {
    var s = ctx.createBufferSource(), g = ctx.createGain(), f = ctx.createBiquadFilter();
    s.buffer = noiseBuf;
    f.type = 'highpass'; f.frequency.value = hp;
    g.gain.setValueAtTime(vol, t);
    g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
    s.connect(f); f.connect(g); g.connect(master);
    s.start(t); s.stop(t + dur + 0.02);
  }

  function schedule(s, t) {
    var bar = Math.floor(s / 16), inBar = s % 16, chord = CHORDS[bar];
    var n = leadAt[s];
    if (n && n[0]) tone('square', n[0], t, n[1] * STEP * 0.9, 0.22);
    if (inBar % 2 === 0) {
      var bassNote = [0, 12, 7, 12][(inBar / 2) % 4];
      tone('triangle', chord.root + bassNote, t, STEP * 1.6, 0.5);
    }
    tone('square', chord.arp[inBar % 4] + 12, t, STEP * 0.6, 0.045);
    if (inBar % 8 === 0) tone('triangle', 50, t, 0.12, 0.6, 35);   // kick
    if (inBar % 8 === 4) noise(t, 0.14, 0.25, 1200);               // snare
    if (inBar % 2 === 0) noise(t, 0.03, 0.08, 7000);               // hat
  }

  function tick() {
    while (nextTime < ctx.currentTime + 0.15) {
      schedule(step, nextTime);
      nextTime += STEP;
      step = (step + 1) % STEPS;
    }
  }

  function start() {
    if (!init() || playing) return;
    ctx.resume().then(function () {
      if (ctx.state !== 'running' || playing) return;
      playing = true;
      nextTime = ctx.currentTime + 0.05;
      timer = setInterval(tick, 40);
      render();
    });
  }
  function stop() {
    playing = false;
    clearInterval(timer); timer = null;
    if (ctx) ctx.suspend();
    render();
  }

  function render() {
    var btn = document.getElementById('music-toggle');
    if (!btn) return;
    btn.classList.toggle('on', wanted);
    btn.setAttribute('aria-pressed', wanted ? 'true' : 'false');
    btn.title = wanted ? (playing ? 'Music is playing. Click to mute.' : 'Music will start on your first tap.') : 'Music is off. Click to play.';
  }
  // Delegated, so the toggle keeps working after soft navigation swaps the header.
  document.addEventListener('click', function (e) {
    var btn = e.target.closest && e.target.closest('#music-toggle');
    if (!btn) return;
    wanted = !wanted;
    try { localStorage.setItem(KEY, wanted ? 'on' : 'off'); } catch (e2) {}
    if (wanted) start(); else stop();
    render();
  });
  window.THMusic = { render: render };
  // Autoplay policy: start on the first gesture anywhere if music is wanted.
  function firstGesture() {
    if (wanted) start();
    ['pointerdown', 'keydown', 'touchstart'].forEach(function (ev) { document.removeEventListener(ev, firstGesture, true); });
  }
  if (wanted) {
    ['pointerdown', 'keydown', 'touchstart'].forEach(function (ev) { document.addEventListener(ev, firstGesture, true); });
    if (init()) start(); // works if the browser already trusts this site
  }
  window.addEventListener('pagehide', function () {
    try { sessionStorage.setItem(POS, String(step)); } catch (e) {}
  });
  render();
})();
