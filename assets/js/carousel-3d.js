/**
 * 3D Product Carousel
 * Vanilla JS — drag / swipe, inertia, snap, depth cues, lightbox.
 *
 * Layout rule that drives everything: the card is sized FROM the scene box,
 * accounting for the perspective magnification of the front card, so the ring
 * can never overflow its container no matter how many cards there are.
 */
(function () {
    'use strict';

    /* ── Tunables ────────────────────────────────────────────── */
    var DAMPING = 0.94;   // inertia friction (0–1)
    var MIN_VELOCITY = 0.08;   // deg/frame below which inertia stops
    var DRAG_SENSITIVITY = 0.28;   // degrees per pixel dragged
    var CLICK_THRESHOLD = 6;      // px — below this a pointerup counts as a click
    var SNAP_STIFFNESS = 0.12;   // how hard snapping pulls toward the nearest card
    var SNAP_EPSILON = 0.05;   // deg — snap considered finished
    var RESUME_DELAY = 3500;   // ms of stillness before auto-spin resumes
    var SAFE_HEIGHT = 0.94;   // fraction of scene height the ring may occupy
    var SAFE_WIDTH = 0.98;   // fraction of section width the ring may occupy
    var GAP_FACTOR = 1.14;   // >1 pushes cards apart on the ring
    // Depth below which a card is showing us its back. cos(angle) flips sign at
    // exactly 0.5 on the 0–1 depth scale, so this is where mirrored faces begin.
    var FAR_THRESHOLD = 0.5;
    var MIN_FACES = 5;      // below this the ring is padded with repeats
    var MAX_CARD_WIDTH = 420;    // px, absolute ceiling
    var MIN_CARD_WIDTH = 120;    // px, absolute floor

    var reduceMotion = window.matchMedia &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function clamp(v, lo, hi) { return v < lo ? lo : (v > hi ? hi : v); }

    /* Parse "225/260" or "1.2" into a width/height ratio. */
    function parseAspect(raw, fallback) {
        if (!raw) return fallback;
        var s = String(raw).trim();
        var m = s.match(/^([\d.]+)\s*\/\s*([\d.]+)$/);
        if (m) {
            var w = parseFloat(m[1]), h = parseFloat(m[2]);
            if (w > 0 && h > 0) return w / h;
        }
        var n = parseFloat(s);
        return (n > 0) ? n : fallback;
    }

    function readNum(el, key, fallback) {
        var v = parseFloat(el.dataset[key]);
        return isNaN(v) ? fallback : v;
    }

    /* ── Carousel ────────────────────────────────────────────── */
    function Carousel3D(scene) {
        this.scene = scene;
        this.ring = scene.querySelector('.c3d-ring');
        if (!this.ring) return;

        this.cards = Array.prototype.slice.call(
            this.ring.querySelectorAll('.c3d-card')
        );
        if (!this.cards.length) return;

        this._fillRing();

        this.baseTilt = readNum(scene, 'tilt', -10);
        this.baseLean = readNum(scene, 'lean', 0);
        this.wobbleAmp = clamp(readNum(scene, 'wobble', 1.2), 0, 8);
        this.spinSpeed = readNum(scene, 'speed', 0.12);
        this.autoplay = scene.dataset.autoplay !== '0';
        this.snapEnabled = scene.dataset.snap !== '0';
        this.hideBack = scene.dataset.hideback === '1';
        this.spread = clamp(readNum(scene, 'spread', GAP_FACTOR), 1, 3);

        if (reduceMotion) {
            this.autoplay = false;
            this.wobbleAmp = 0;
        }

        this.rotation = 0;
        this.velocity = 0;
        this.tilt = this.baseTilt;
        this.lean = this.baseLean;
        this.dragging = false;
        this.moved = 0;
        this.pointerId = null;
        this.rafId = null;
        this.snapTarget = null;
        this.idleSince = 0;
        this.hovered = false;
        this.visible = true;
        this.lastDepth = [];

        if (this.hideBack) this.scene.classList.add('c3d-scene--hide-back');

        // Hang the instance off the element so it can be found again later —
        // to shut it down when a page builder replaces the markup, and for the
        // test harness to read its internals.
        scene._c3d = this;

        this._decorate();
        this._layout();
        this._bind();
        this._pruneBrokenMedia();
        this._start();
    }

    /**
     * With only two or three products a full 360° ring puts the neighbours
     * directly behind the viewer, so the showcase reads as a single card. Repeat
     * the set until the ring is populated — the same trick looping carousels use.
     * Repeats are hidden from assistive tech so the list is not announced twice.
     */
    Carousel3D.prototype._fillRing = function () {
        if (this.scene.dataset.fill === '0') return;

        var original = this.cards.length;
        if (original >= MIN_FACES) return;

        var copies = Math.ceil(MIN_FACES / original);
        for (var c = 1; c < copies; c++) {
            for (var i = 0; i < original; i++) {
                var clone = this.cards[i].cloneNode(true);
                clone.setAttribute('aria-hidden', 'true');
                clone.dataset.c3dRepeat = '1';
                this.ring.appendChild(clone);
            }
        }
        this.cards = Array.prototype.slice.call(
            this.ring.querySelectorAll('.c3d-card')
        );
    };

    /* Inject the veil overlays and the ground shadow. */
    Carousel3D.prototype._decorate = function () {
        this.cards.forEach(function (card) {
            if (!card.querySelector('.c3d-card__veil')) {
                var veil = document.createElement('span');
                veil.className = 'c3d-card__veil';
                veil.setAttribute('aria-hidden', 'true');
                card.appendChild(veil);
            }
        });

        var section = this.scene.closest('.c3d-section');
        if (section && !section.querySelector('.c3d-edge')) {
            ['left', 'right'].forEach(function (side) {
                var e = document.createElement('div');
                e.className = 'c3d-edge c3d-edge--' + side;
                e.setAttribute('aria-hidden', 'true');
                section.appendChild(e);
            });
        }

        if (this.scene.dataset.ground !== '0') {
            this.ground = this.scene.querySelector('.c3d-ground');
            if (!this.ground) {
                this.ground = document.createElement('div');
                this.ground.className = 'c3d-ground';
                this.ground.setAttribute('aria-hidden', 'true');
                this.scene.insertBefore(this.ground, this.scene.firstChild);
            }
        }
    };

    /**
     * Project the ring and return the screen-space bounding box.
     *
     * Closed-form budgets kept missing terms (the perspective magnification of
     * the front card, the tilt pushing it off centre, the lean lifting the side
     * cards), so instead every corner of every card is run through the same
     * matrix chain the browser applies and the extremes are measured directly.
     * Sampling is over one rotation step — the ring is n-fold symmetric — at the
     * extremes of the idle sway.
     */
    function projectRing(g) {
        var RAD = Math.PI / 180;
        var minX = 1e9, maxX = -1e9, minY = 1e9, maxY = -1e9;
        var hw = g.cardW / 2, hh = g.cardH / 2;
        var ROT_SAMPLES = 8;

        // Sway extremes plus the resting pose.
        var phases = [
            [g.tilt, g.lean],
            [g.tilt + g.wobble, g.lean + g.wobble * 0.45],
            [g.tilt + g.wobble, g.lean - g.wobble * 0.45],
            [g.tilt - g.wobble, g.lean + g.wobble * 0.45],
            [g.tilt - g.wobble, g.lean - g.wobble * 0.45]
        ];

        for (var ph = 0; ph < phases.length; ph++) {
            var th = phases[ph][0] * RAD, la = phases[ph][1] * RAD;
            var ct = Math.cos(th), st = Math.sin(th);
            var cl = Math.cos(la), sl = Math.sin(la);

            for (var s = 0; s < ROT_SAMPLES; s++) {
                var rot = (g.step * s / ROT_SAMPLES) * RAD;

                for (var i = 0; i < g.n; i++) {
                    var phi = rot + i * g.step * RAD;
                    var cp = Math.cos(phi), sp = Math.sin(phi);

                    for (var cx = -1; cx <= 1; cx += 2) {
                        for (var cy = -1; cy <= 1; cy += 2) {
                            var x = cx * hw, y = cy * hh;

                            // card: rotateY(phi) translateZ(radius)
                            var X = x * cp + g.radius * sp;
                            var Y = y;
                            var Z = -x * sp + g.radius * cp;

                            // ring: rotateZ(lean)
                            var X1 = X * cl - Y * sl;
                            var Y1 = X * sl + Y * cl;

                            // ring: rotateX(tilt)
                            var Y2 = Y1 * ct - Z * st;
                            var Z2 = Y1 * st + Z * ct;

                            // ring: translateY(dy)
                            Y2 += g.dy;

                            var denom = P_MIN_DENOM(g.P - Z2);
                            var k = g.P / denom;
                            var sx = X1 * k, sy = Y2 * k;

                            if (sx < minX) minX = sx;
                            if (sx > maxX) maxX = sx;
                            if (sy < minY) minY = sy;
                            if (sy > maxY) maxY = sy;
                        }
                    }
                }
            }
        }
        return { minX: minX, maxX: maxX, minY: minY, maxY: maxY };
    }

    function P_MIN_DENOM(d) { return d < 1 ? 1 : d; }

    /**
     * Size the cards from the box they have to live in.
     *
     * Card size, ring radius and perspective magnification are mutually
     * dependent, so this is a fixed-point loop: measure the projected extent,
     * scale the card by the ratio that would make it fit, repeat.
     */
    Carousel3D.prototype._layout = function () {
        var n = this.cards.length;
        var sceneH = this.scene.clientHeight;
        var sceneW = this.scene.clientWidth;
        if (!sceneH || !sceneW) return;

        var cs = getComputedStyle(this.scene);
        var P = parseFloat(cs.perspective);
        if (!P || isNaN(P)) P = 1200;

        var aspect = parseAspect(
            cs.getPropertyValue('--c3d-card-aspect'), 225 / 260
        );

        // Fraction of the available box the whole ring may occupy. Exposed as
        // CSS custom properties so media queries can retune it per breakpoint.
        var safeH = parseFloat(cs.getPropertyValue('--c3d-safe-height')) || SAFE_HEIGHT;
        var safeW = parseFloat(cs.getPropertyValue('--c3d-ring-width')) || SAFE_WIDTH;
        safeH = clamp(safeH, 0.4, 0.99);
        // Values above 1 deliberately let the ring run past the section edges,
        // where the edge fades take over — see the phone breakpoint.
        safeW = clamp(safeW, 0.2, 2.5);

        // Angular half-step; for n < 3 there is no real polygon, so fall back
        // to a radius proportional to the card width.
        var halfStep = Math.PI / Math.max(n, 3);
        var step = 360 / n;

        // The scene never clips, but the section does — so horizontal room is
        // measured against the real clipping boundary, which gives the ring the
        // container padding back instead of wasting it.
        var section = this.scene.closest('.c3d-section');
        var clipW = section ? section.clientWidth : sceneW;

        var targetY = (sceneH / 2) * safeH;
        var targetX = (clipW / 2) * safeW;

        var g = {
            n: n, step: step, P: P,
            tilt: this.baseTilt, lean: this.baseLean,
            wobble: this.wobbleAmp, dy: 0,
            cardW: 0, cardH: 0, radius: 0
        };

        var cardH = (sceneH * safeH) / 1.6; // seed
        var cardW, radius, box;
        var minH = MIN_CARD_WIDTH / aspect;
        var maxH = MAX_CARD_WIDTH / aspect;

        for (var pass = 0; pass < 12; pass++) {
            cardW = cardH * aspect;

            if (n < 3) {
                radius = cardW * 0.62 * this.spread;
            } else {
                radius = (cardW / 2) / Math.tan(halfStep) * this.spread;
            }
            // Never let the ring reach the camera.
            radius = Math.min(radius, P * 0.55);

            g.cardW = cardW; g.cardH = cardH; g.radius = radius;

            // Re-centre vertically: the tilt pushes the ring off centre, and
            // recovering that offset is worth a noticeably larger card.
            g.dy = 0;
            for (var c = 0; c < 3; c++) {
                box = projectRing(g);
                var midY = (box.minY + box.maxY) / 2;
                if (Math.abs(midY) < 0.5) break;
                g.dy -= midY * (1 - radius / P);
            }

            box = projectRing(g);
            var halfY = Math.max(-box.minY, box.maxY);
            var halfX = Math.max(-box.minX, box.maxX);

            var ratio = Math.min(targetY / halfY, targetX / halfX);
            var next = clamp(cardH * ratio, minH, maxH);

            var settled = Math.abs(next - cardH) < 0.4;
            cardH = next;
            if (settled) break;
        }

        cardW = cardH * aspect;
        g.cardW = cardW; g.cardH = cardH; g.radius = radius;
        box = projectRing(g);

        this.cardW = cardW;
        this.cardH = cardH;
        this.radius = radius;
        this.frontScale = P / (P - radius);
        this.step = step;
        this.originY = g.dy;
        this.debug = {
            sceneH: sceneH, sceneW: sceneW, clipW: clipW,
            safeH: safeH, safeW: safeW, aspect: aspect, P: P,
            targetY: targetY, targetX: targetX,
            halfY: Math.max(-box.minY, box.maxY),
            halfX: Math.max(-box.minX, box.maxX),
            minH: minH, maxH: maxH, passes: pass
        };

        // If the floor on card size forces the ring past the clip edge, fade
        // the edges so it reads as depth rather than as a crop.
        var bleeding = Math.max(-box.minX, box.maxX) > (clipW / 2) * 0.99;
        if (section) section.classList.toggle('is-bleeding', bleeding);

        this.ring.style.width = Math.round(cardW) + 'px';
        this.ring.style.height = Math.round(cardH) + 'px';

        var angleStep = this.step;
        this.cards.forEach(function (card, i) {
            card.style.width = Math.round(cardW) + 'px';
            card.style.height = Math.round(cardH) + 'px';
            card.style.transform =
                'rotateY(' + (i * angleStep) + 'deg) translateZ(' + Math.round(radius) + 'px)';
        });

        if (this.ground) {
            // Sit the contact shadow just under the projected bottom edge of
            // the front card, which box.maxY already gives us.
            this.ground.style.width = Math.round(cardW * this.frontScale * 1.25) + 'px';
            this.ground.style.height = Math.round(cardW * this.frontScale * 0.22) + 'px';
            this.ground.style.top =
                Math.round(sceneH / 2 + box.maxY * 0.94) + 'px';
        }

        this.lastDepth = new Array(n);
        this._apply();
    };

    /* Write the ring transform and the per-card depth cues. */
    Carousel3D.prototype._apply = function () {
        this.ring.style.transform =
            'translateY(' + (this.originY || 0).toFixed(2) + 'px) ' +
            'rotateX(' + this.tilt.toFixed(3) + 'deg) ' +
            'rotateZ(' + this.lean.toFixed(3) + 'deg) ' +
            'rotateY(' + this.rotation.toFixed(3) + 'deg)';

        var step = this.step;
        var rot = this.rotation;
        var moving = this.dragging || Math.abs(this.velocity) > 1.5;

        for (var i = 0; i < this.cards.length; i++) {
            var card = this.cards[i];
            var rad = (rot + i * step) * Math.PI / 180;
            var cos = Math.cos(rad);
            var t = (cos + 1) / 2;          // 0 = straight back, 1 = front
            var eased = t * t * (3 - 2 * t); // smoothstep

            card.style.setProperty('--c3d-depth', eased.toFixed(3));
            card.style.zIndex = String(Math.round(eased * 200));

            var far = eased < FAR_THRESHOLD;
            var prev = this.lastDepth[i];
            if (prev === undefined || prev !== far) {
                card.classList.toggle('is-far', far);
                this.lastDepth[i] = far;
            }

            var vid = card.querySelector('video');
            if (vid) {
                if (cos <= 0 || moving) {
                    if (!vid.paused) vid.pause();
                } else if (vid.paused) {
                    var p = vid.play();
                    if (p && p.catch) p.catch(function () { });
                }
            }
        }

        if (this.ground) {
            this.ground.style.opacity = String(
                clamp(0.55 - Math.abs(this.lean) * 0.02, 0, 1)
            );
        }
    };

    /* ── Interaction ─────────────────────────────────────────── */
    Carousel3D.prototype._bind = function () {
        var self = this;

        this._onDown = function (e) { self._down(e); };
        this._onMove = function (e) { self._move(e); };
        this._onUp = function (e) { self._up(e); };

        this.scene.addEventListener('pointerdown', this._onDown);
        this.scene.addEventListener('pointermove', this._onMove);
        this.scene.addEventListener('pointerup', this._onUp);
        this.scene.addEventListener('pointercancel', this._onUp);

        this.scene.addEventListener('mouseenter', function () { self.hovered = true; });
        this.scene.addEventListener('mouseleave', function () { self.hovered = false; });

        // Keyboard control.
        this.scene.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowLeft') {
                e.preventDefault(); self._nudge(1);
            } else if (e.key === 'ArrowRight') {
                e.preventDefault(); self._nudge(-1);
            } else if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault(); self._openFront();
            }
        });

        // Relayout on resize, debounced.
        var t = null;
        this._onResize = function () {
            clearTimeout(t);
            t = setTimeout(function () { self._layout(); }, 150);
        };
        window.addEventListener('resize', this._onResize);

        // Only animate while on screen.
        if ('IntersectionObserver' in window) {
            this._io = new IntersectionObserver(function (entries) {
                self.visible = entries[0].isIntersecting;
                if (self.visible) self._start(); else self._stop();
            }, { threshold: 0.01 });
            this._io.observe(this.scene);
        }

        this._onVis = function () {
            if (document.hidden) self._stop(); else if (self.visible) self._start();
        };
        document.addEventListener('visibilitychange', this._onVis);

        this.ring.querySelectorAll('img, video').forEach(function (m) {
            m.setAttribute('draggable', 'false');
        });
    };

    /**
     * Releases everything bound outside the scene element.
     *
     * Dropping the element is not enough: the resize listener sits on window and
     * the visibility listener on document, so both would outlive it. A page
     * builder re-renders a widget on every keystroke in its settings, which
     * would otherwise pile up a listener and an animation loop per keystroke.
     */
    Carousel3D.prototype.destroy = function () {
        this._stop();
        if (this._onResize) window.removeEventListener('resize', this._onResize);
        if (this._onVis) document.removeEventListener('visibilitychange', this._onVis);
        if (this._io) { this._io.disconnect(); this._io = null; }
        this.visible = false;
        delete this.scene.dataset.c3dReady;
        this.scene._c3d = null;
    };

    Carousel3D.prototype._down = function (e) {
        if (this.pointerId !== null) return;
        this.pointerId = e.pointerId;
        this.dragging = true;
        this.moved = 0;
        this.startX = this.lastX = e.clientX;
        this.startRot = this.rotation;
        this.lastTime = performance.now();
        this.velocity = 0;
        this.snapTarget = null;
        this.scene.classList.add('is-grabbing');
        if (this.scene.setPointerCapture) {
            try { this.scene.setPointerCapture(e.pointerId); } catch { /* best-effort */ }
        }
    };

    Carousel3D.prototype._move = function (e) {
        if (!this.dragging || e.pointerId !== this.pointerId) return;
        var dx = e.clientX - this.lastX;
        this.moved += Math.abs(dx);

        var now = performance.now();
        var dt = Math.max(now - this.lastTime, 1);
        this.velocity = (dx / dt) * 16 * DRAG_SENSITIVITY;

        this.rotation = this.startRot + (e.clientX - this.startX) * DRAG_SENSITIVITY;
        this._apply();

        this.lastX = e.clientX;
        this.lastTime = now;
    };

    Carousel3D.prototype._up = function (e) {
        if (!this.dragging || e.pointerId !== this.pointerId) return;
        this.dragging = false;
        this.pointerId = null;
        this.scene.classList.remove('is-grabbing');

        if (this.moved < CLICK_THRESHOLD) {
            var card = e.target.closest && e.target.closest('.c3d-card');
            if (card) { this.velocity = 0; this._openCard(card); return; }
        }
        this.idleSince = performance.now();
    };

    Carousel3D.prototype._nudge = function (dir) {
        this.velocity = 0;
        this.snapTarget = Math.round(this.rotation / this.step) * this.step + dir * this.step;
        this.idleSince = performance.now();
        this._start();
    };

    /* ── Animation loop ──────────────────────────────────────── */
    Carousel3D.prototype._start = function () {
        if (this.rafId || !this.visible) return;
        var self = this;
        var last = 0;
        var tick = function (now) {
            self.rafId = requestAnimationFrame(tick);
            if (self.dragging) { last = now; return; }

            // All motion is expressed per 60 Hz frame, then scaled by the real
            // elapsed time — otherwise inertia decays twice as fast on a 120 Hz
            // display and crawls in a throttled tab. Clamped so that returning
            // to a backgrounded tab does not jump the ring.
            var f = last ? clamp((now - last) / 16.667, 0, 4) : 1;
            last = now;

            var interacted = self.idleSince &&
                (now - self.idleSince) < RESUME_DELAY;

            if (Math.abs(self.velocity) > MIN_VELOCITY) {
                self.velocity *= Math.pow(DAMPING, f);
                self.rotation += self.velocity * f;
                if (Math.abs(self.velocity) <= MIN_VELOCITY && self.snapEnabled) {
                    self.snapTarget =
                        Math.round(self.rotation / self.step) * self.step;
                }
            } else if (self.snapTarget !== null) {
                var d = self.snapTarget - self.rotation;
                if (Math.abs(d) < SNAP_EPSILON) {
                    self.rotation = self.snapTarget;
                    self.snapTarget = null;
                } else {
                    self.rotation += d * (1 - Math.pow(1 - SNAP_STIFFNESS, f));
                }
            } else if (self.autoplay && !self.hovered && !interacted) {
                self.rotation += self.spinSpeed * f;
            }

            if (self.wobbleAmp > 0) {
                self.tilt = self.baseTilt +
                    Math.sin(now / 2600) * self.wobbleAmp;
                self.lean = self.baseLean +
                    Math.cos(now / 3400) * self.wobbleAmp * 0.45;
            }

            self._apply();
        };
        this.rafId = requestAnimationFrame(tick);
    };

    Carousel3D.prototype._stop = function () {
        if (this.rafId) { cancelAnimationFrame(this.rafId); this.rafId = null; }
    };

    /* Drop cards whose media failed to load, then relayout. */
    Carousel3D.prototype._pruneBrokenMedia = function () {
        var self = this;
        this.cards.slice().forEach(function (card) {
            var img = card.querySelector('img');
            if (!img) return;
            var fail = function () {
                var i = self.cards.indexOf(card);
                if (i > -1) self.cards.splice(i, 1);
                card.remove();
                if (self.cards.length) self._layout(); else self._stop();
            };
            if (img.complete) {
                if (img.naturalWidth === 0) fail();
            } else {
                img.addEventListener('error', fail, { once: true });
            }
        });
    };

    /* ── Lightbox ────────────────────────────────────────────── */
    Carousel3D.prototype._openFront = function () {
        var best = null, bestDepth = -1;
        var self = this;
        this.cards.forEach(function (card, i) {
            var rad = (self.rotation + i * self.step) * Math.PI / 180;
            var d = Math.cos(rad);
            if (d > bestDepth) { bestDepth = d; best = card; }
        });
        if (best) this._openCard(best);
    };

    Carousel3D.prototype._openCard = function (card) {
        var img = card.querySelector('img');
        var vid = card.querySelector('video');
        if (!img && !vid) return;

        var opener = document.activeElement;
        var scrollY = window.scrollY;
        var strings = window.C3D_I18N || {};

        var overlay = document.createElement('div');
        overlay.className = 'c3d-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');

        var media;
        if (vid) {
            media = document.createElement('video');
            media.src = vid.currentSrc || vid.src;
            media.autoplay = true; media.controls = true;
            media.loop = true; media.playsInline = true;
        } else {
            media = document.createElement('img');
            media.src = img.currentSrc || img.src;
            media.alt = img.alt || '';
        }
        media.className = 'c3d-overlay-media';

        var panel = document.createElement('div');
        panel.className = 'c3d-overlay-panel';
        panel.appendChild(media);

        var labelEl = card.querySelector('.c3d-card-label');
        if (labelEl && labelEl.textContent.trim()) {
            var cap = document.createElement('p');
            cap.className = 'c3d-overlay-label';
            cap.textContent = labelEl.textContent.trim();
            panel.appendChild(cap);
            overlay.setAttribute('aria-label', cap.textContent);
        }

        var link = card.querySelector('a');
        var href = link && link.getAttribute('href');
        if (href && href !== '#') {
            var btn = document.createElement('a');
            btn.href = link.href;
            btn.className = 'c3d-overlay-link';
            btn.textContent = strings.open || 'Open';
            btn.addEventListener('click', function (ev) { ev.stopPropagation(); });
            panel.appendChild(btn);
        }

        var closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'c3d-overlay-close';
        closeBtn.innerHTML = '&times;';
        closeBtn.setAttribute('aria-label', strings.close || 'Close');

        overlay.appendChild(closeBtn);
        overlay.appendChild(panel);
        document.body.appendChild(overlay);
        document.body.classList.add('c3d-scroll-lock');
        document.body.style.top = (-scrollY) + 'px';

        requestAnimationFrame(function () {
            overlay.classList.add('is-active');
        });
        closeBtn.focus();

        var closed = false;
        function close() {
            if (closed) return;
            closed = true;
            overlay.classList.remove('is-active');
            document.removeEventListener('keydown', onKey, true);
            document.body.classList.remove('c3d-scroll-lock');
            document.body.style.top = '';
            window.scrollTo(0, scrollY);
            var done = false;
            var finish = function () {
                if (done) return;
                done = true;
                overlay.remove();
                if (opener && opener.focus) opener.focus();
            };
            overlay.addEventListener('transitionend', finish, { once: true });
            setTimeout(finish, 500); // fallback if no transition fires
        }

        function onKey(ev) {
            if (ev.key === 'Escape') { ev.preventDefault(); close(); return; }
            if (ev.key !== 'Tab') return;
            // Focus trap.
            var f = overlay.querySelectorAll('button, a[href], video[controls]');
            if (!f.length) return;
            var first = f[0], last = f[f.length - 1];
            if (ev.shiftKey && document.activeElement === first) {
                ev.preventDefault(); last.focus();
            } else if (!ev.shiftKey && document.activeElement === last) {
                ev.preventDefault(); first.focus();
            }
        }

        overlay.addEventListener('click', function (ev) {
            if (ev.target === overlay) close();
        });
        closeBtn.addEventListener('click', function (ev) {
            ev.stopPropagation(); close();
        });
        document.addEventListener('keydown', onKey, true);
    };

    /* ── Boot ────────────────────────────────────────────────── */

    /**
     * @param {Document|Element} [root] Where to look for scenes. The block
     *   editor renders its canvas inside an iframe, so the editor script calls
     *   this with that iframe's document — this script only ever runs in the
     *   top frame.
     */
    function init(root) {
        // Guarded because init is also used directly as an event listener,
        // which would otherwise hand us an Event object as the root.
        var scope = (root && typeof root.querySelectorAll === 'function')
            ? root : document;
        scope.querySelectorAll('.c3d-scene').forEach(function (el) {
            if (el.dataset.c3dReady) return;
            el.dataset.c3dReady = '1';
            new Carousel3D(el);
        });
    }

    /**
     * Tears down every carousel inside root. Safe to call on markup that has
     * none, and safe to call twice.
     */
    function destroy(root) {
        var scope = (root && typeof root.querySelectorAll === 'function')
            ? root : document;
        scope.querySelectorAll('.c3d-scene').forEach(function (el) {
            if (el._c3d && el._c3d.destroy) el._c3d.destroy();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { init(); });
    } else {
        init();
    }

    window.C3D = { init: init, destroy: destroy };
})();
