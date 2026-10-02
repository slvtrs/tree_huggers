/* A tiny chiptune tracker: square-wave lead, triangle bass, a soft arpeggio
 * and noise drums, all synthesized with Web Audio so there is nothing to
 * download. Browsers only allow sound after a user gesture, so it starts on
 * the first click or tap. The toggle remembers its state in localStorage and
 * the song position carries across page loads so it feels continuous. */
(function () {
  'use strict';
  var KEY = 'th_music', POS = 'th_music_pos', SONG_KEY = 'th_song';
  var STEPS_PER_BEAT = 4, BARS = 8, STEPS = BARS * 16;

  // Each song: 8 bars of chords (bass root + 4 arpeggio tones) and a lead line
  // as [midi or 0 for rest, length in 16th steps] summing to 128 steps.
  function ch(root, arp) { return { root: root, arp: arp }; }
  var C = ch(36, [60, 64, 67, 72]), G = ch(43, [59, 62, 67, 71]), Am = ch(45, [57, 60, 64, 69]), F = ch(41, [57, 60, 65, 69]);
  var D = ch(38, [62, 66, 69, 74]), Bm = ch(47, [59, 62, 66, 71]), Gd = ch(43, [62, 67, 71, 74]), A = ch(45, [61, 64, 69, 73]);
  var Em = ch(40, [59, 64, 67, 71]);
  var SONGS = [
    { name: 'Canopy', bpm: 118, lead: 'square', drums: 1,
      chords: [C, C, G, G, Am, Am, F, F],
      notes: [
        [76,2],[79,2],[76,2],[72,2],[74,2],[76,2],[79,3],[0,1],
        [81,2],[79,2],[76,2],[74,2],[72,6],[0,2],
        [74,2],[79,2],[83,2],[79,2],[81,2],[79,2],[74,3],[0,1],
        [71,2],[74,2],[79,2],[81,2],[79,6],[0,2],
        [81,2],[84,2],[83,2],[81,2],[76,3],[0,1],[79,2],[81,2],
        [84,2],[83,2],[81,2],[79,2],[76,6],[0,2],
        [77,2],[81,2],[84,2],[81,2],[79,2],[77,2],[76,2],[74,2],
        [72,2],[74,2],[76,2],[77,2],[79,6],[0,2]
      ] },
    { name: 'Acorn Hop', bpm: 140, lead: 'square', drums: 1,
      chords: [Am, Am, F, F, C, C, G, G],
      notes: [
        [69,2],[72,2],[76,2],[81,2],[79,2],[76,2],[72,2],[0,2],
        [76,2],[74,2],[72,2],[71,2],[69,6],[0,2],
        [77,2],[81,2],[84,2],[81,2],[77,2],[76,2],[74,2],[0,2],
        [72,2],[74,2],[76,2],[77,2],[76,6],[0,2],
        [79,2],[76,2],[72,2],[76,2],[79,2],[81,2],[79,2],[0,2],
        [76,2],[79,2],[84,2],[83,2],[81,4],[79,2],[76,2],
        [74,2],[79,2],[83,2],[86,2],[83,2],[81,2],[79,2],[0,2],
        [78,2],[79,2],[81,2],[83,2],[81,6],[0,2]
      ] },
    { name: 'Old Growth', bpm: 92, lead: 'triangle', drums: 0.5,
      chords: [D, D, Bm, Bm, Gd, Gd, A, A],
      notes: [
        [74,4],[78,4],[81,8],
        [83,2],[81,2],[78,4],[76,8],
        [71,4],[74,4],[78,8],
        [81,4],[78,4],[74,8],
        [79,4],[83,4],[86,8],
        [85,2],[83,2],[81,4],[79,8],
        [81,4],[85,4],[76,4],[78,4],
        [79,2],[78,2],[76,4],[74,8]
      ] },
    { name: 'Sap Rising', bpm: 132, lead: 'square', drums: 1,
      chords: [G, G, Em, Em, C, C, D, D],
      notes: [
        [79,2],[83,2],[86,2],[83,2],[79,2],[81,2],[83,2],[0,2],
        [81,2],[79,2],[78,2],[79,2],[74,6],[0,2],
        [76,2],[79,2],[83,2],[88,2],[86,2],[83,2],[79,2],[0,2],
        [78,2],[79,2],[81,2],[83,2],[79,6],[0,2],
        [84,2],[83,2],[81,2],[79,2],[76,2],[79,2],[81,2],[0,2],
        [79,4],[76,4],[72,6],[0,2],
        [74,2],[78,2],[81,2],[86,2],[85,2],[81,2],[78,2],[0,2],
        [81,2],[83,2],[84,4],[86,6],[0,2]
      ] }
  ];
  SONGS.forEach(function (song) {
    song.leadAt = [];
    var t = 0;
    song.notes.forEach(function (n) { song.leadAt[t] = n; t += n[1]; });
    song.step = 60 / song.bpm / STEPS_PER_BEAT;
  });
  var songIndex = 0;
  try { songIndex = (parseInt(localStorage.getItem(SONG_KEY), 10) || 0) % SONGS.length; } catch (e) {}
  var song = SONGS[songIndex];

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
    try {
      var saved = (sessionStorage.getItem(POS) || '').split(':');
      if (parseInt(saved[0], 10) === songIndex) step = (parseInt(saved[1], 10) || 0) % STEPS;
    } catch (e) {}
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
    var bar = Math.floor(s / 16), inBar = s % 16, chord = song.chords[bar], STEP = song.step, dr = song.drums;
    var n = song.leadAt[s];
    if (n && n[0]) tone(song.lead, n[0], t, n[1] * STEP * 0.9, song.lead === 'triangle' ? 0.5 : 0.22);
    if (inBar % 2 === 0) {
      var bassNote = [0, 12, 7, 12][(inBar / 2) % 4];
      tone('triangle', chord.root + bassNote, t, STEP * 1.6, 0.5);
    }
    tone('square', chord.arp[inBar % 4] + 12, t, STEP * 0.6, 0.045);
    if (inBar % 8 === 0) tone('triangle', 50, t, 0.12, 0.6 * dr, 35);   // kick
    if (inBar % 8 === 4) noise(t, 0.14, 0.25 * dr, 1200);               // snare
    if (inBar % 2 === 0) noise(t, 0.03, 0.08 * dr, 7000);               // hat
  }

  function tick() {
    while (nextTime < ctx.currentTime + 0.15) {
      schedule(step, nextTime);
      nextTime += song.step;
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

  function setSong(i) {
    songIndex = ((i % SONGS.length) + SONGS.length) % SONGS.length;
    song = SONGS[songIndex];
    step = 0;
    try { localStorage.setItem(SONG_KEY, String(songIndex)); } catch (e) {}
    if (playing) nextTime = ctx.currentTime + 0.05;
    showName();
  }
  var nameTimer = null;
  function showName() {
    var el = document.getElementById('music-name');
    if (!el) return;
    el.textContent = '\u266A ' + song.name;
    el.classList.add('show');
    clearTimeout(nameTimer);
    nameTimer = setTimeout(function () { el.classList.remove('show'); }, 2200);
  }
  document.addEventListener('click', function (e) {
    var btn = e.target.closest && e.target.closest('#music-next');
    if (!btn) return;
    setSong(songIndex + 1);
    if (!wanted) {
      wanted = true;
      try { localStorage.setItem(KEY, 'on'); } catch (e2) {}
    }
    start();
    render();
  });

  function render() {
    var btn = document.getElementById('music-toggle');
    if (!btn) return;
    btn.classList.toggle('on', wanted);
    btn.setAttribute('aria-pressed', wanted ? 'true' : 'false');
    btn.title = (wanted ? (playing ? 'Playing: ' : 'Will play: ') : 'Muted: ') + song.name + '. Click to ' + (wanted ? 'mute.' : 'play.');
    var nx = document.getElementById('music-next');
    if (nx) nx.title = 'Next song (now: ' + song.name + ')';
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
    try { sessionStorage.setItem(POS, songIndex + ':' + step); } catch (e) {}
  });
  render();
})();
