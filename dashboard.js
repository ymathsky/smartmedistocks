/**
 * SmartMediStocks — Dashboard JS Enhancements
 * Works with dashboard.css — no dependencies required.
 */
(function () {
  'use strict';

  /* ================================================================
     1. ANIMATED NUMBER COUNTER
     Targets every `.dash-card p.text-2xl` (KPI values).
     Reads the current text, animates from 0 → target with ease-out.
     Handles: plain integers, comma-formatted numbers, ₱ currency.
  ================================================================ */
  function easeOutCubic(t) { return 1 - Math.pow(1 - t, 3); }

  function animateCounter(el) {
    var raw    = el.textContent.trim();
    var hasPeso = raw.indexOf('₱') !== -1;
    // Strip everything except digits and dot
    var numStr = raw.replace(/[^0-9.]/g, '');
    var target = parseFloat(numStr);
    if (isNaN(target) || target === 0) return;

    var isDecimal = numStr.indexOf('.') !== -1;
    var decimals  = isDecimal ? 2 : 0;
    var duration  = 1100;
    var startTime = null;

    function tick(now) {
      if (!startTime) startTime = now;
      var elapsed  = Math.min((now - startTime) / duration, 1);
      var progress = easeOutCubic(elapsed);
      var current  = target * progress;

      var formatted = current.toLocaleString('en-PH', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
      });
      el.textContent = (hasPeso ? '₱' : '') + formatted;

      if (elapsed < 1) {
        requestAnimationFrame(tick);
      } else {
        // Restore exact original value to avoid floating-point drift
        el.textContent = raw;
      }
    }

    el.textContent = (hasPeso ? '₱' : '') + (isDecimal ? '0.00' : '0');
    requestAnimationFrame(tick);
  }

  /* ================================================================
     2. SCROLL-REVEAL via IntersectionObserver
     Adds .ds-reveal to all chart / table panels, then toggles
     .ds-visible when they scroll into the viewport.
     Falls back gracefully when IntersectionObserver is not available.
  ================================================================ */
  function initScrollReveal() {
    // Target white card panels (charts, alert boxes, tables)
    var panels = document.querySelectorAll(
      '.bg-white.rounded-2xl:not(.dash-card)'
    );
    if (!panels.length) return;

    if (!window.IntersectionObserver) {
      // Fallback: just make them visible immediately
      panels.forEach(function (p) { p.classList.add('ds-visible'); });
      return;
    }

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          // Small stagger based on sibling index inside its grid parent
          var siblings = entry.target.parentElement
            ? Array.prototype.slice.call(entry.target.parentElement.children)
            : [];
          var idx = siblings.indexOf(entry.target);
          entry.target.style.transitionDelay = (idx * 55) + 'ms';
          entry.target.classList.add('ds-visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });

    panels.forEach(function (p) {
      p.classList.add('ds-reveal');
      observer.observe(p);
    });
  }

  /* ================================================================
     3. ANIMATED STATUS DOT auto-inject
     Any badge span that contains "Action Needed" or a number > 0
     inside a .dash-card gets a pulsing dot prepended.
  ================================================================ */
  function injectStatusDots() {
    document.querySelectorAll('.dash-card span').forEach(function (badge) {
      var text = badge.textContent.trim();
      // Red dot for action-needed / critical alerts
      if (text === 'Action Needed' || text === 'Reorder') {
        var dot = document.createElement('span');
        dot.className = 'ds-dot ds-dot--red';
        dot.style.cssText = 'display:inline-block;width:7px;height:7px;margin-right:5px;vertical-align:middle;';
        badge.insertBefore(dot, badge.firstChild);
      }
      // Amber dot for monitor / warning
      if (text === 'Monitor') {
        var dotA = document.createElement('span');
        dotA.className = 'ds-dot ds-dot--amber';
        dotA.style.cssText = 'display:inline-block;width:7px;height:7px;margin-right:5px;vertical-align:middle;';
        badge.insertBefore(dotA, badge.firstChild);
      }
    });
  }

  /* ================================================================
     4. TOOLTIP shorthand
     Any element with data-tooltip attribute works via pure CSS already,
     but this ensures no tooltip clips outside the viewport by flipping
     it below when near the top of the page.
  ================================================================ */
  function fixTooltipFlip() {
    document.querySelectorAll('[data-tooltip]').forEach(function (el) {
      el.addEventListener('mouseenter', function () {
        var rect = el.getBoundingClientRect();
        if (rect.top < 80) {
          el.setAttribute('data-tooltip-pos', 'below');
          el.style.setProperty('--tt-top', 'auto');
          el.style.setProperty('--tt-bottom', 'auto');
        }
      });
    });
  }

  /* ================================================================
     5. SKELETON auto-remove
     Any element with .ds-skeleton-auto gets the skeleton class removed
     once the page fully loads (for PHP-rendered content that is already
     present but we want to show a brief shimmer on first paint).
  ================================================================ */
  function clearAutoSkeletons() {
    document.querySelectorAll('.ds-skeleton-auto').forEach(function (el) {
      setTimeout(function () {
        el.classList.remove('ds-skeleton', 'ds-skeleton-auto');
      }, 600);
    });
  }

  /* ================================================================
     6. ACTIVE NAV highlight
     Matches the current page URL to sidebar links and marks them active.
  ================================================================ */
  function highlightActiveNav() {
    var current = window.location.pathname.split('/').pop();
    document.querySelectorAll('a[href]').forEach(function (a) {
      if (a.getAttribute('href') === current) {
        a.classList.add('ds-nav-active');
        a.style.cssText += 'background:var(--ds-primary-light);color:var(--ds-primary);font-weight:700;';
      }
    });
  }

  /* ================================================================
     INIT
  ================================================================ */
  document.addEventListener('DOMContentLoaded', function () {
    // Counter animation — slight delay so the page renders first
    setTimeout(function () {
      document.querySelectorAll('.dash-card p.text-2xl').forEach(animateCounter);
    }, 120);

    initScrollReveal();
    injectStatusDots();
    fixTooltipFlip();
    clearAutoSkeletons();
    highlightActiveNav();
  });

})();
