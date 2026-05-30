/**
 * Statflow Demo Site — main.js
 * Lightweight vanilla JS for interactive behaviours.
 * No framework, no build step.
 */

(function () {
  'use strict';

  /* ── Mobile navigation toggle ─────────────────────────────── */
  function initMobileNav() {
    var toggle = document.getElementById('nav-toggle');
    var nav    = document.getElementById('site-nav');
    var actions = document.getElementById('header-actions');

    if (!toggle) return;

    toggle.addEventListener('click', function () {
      var isOpen = nav.classList.toggle('is-open');
      if (actions) actions.classList.toggle('is-open', isOpen);
      toggle.setAttribute('aria-expanded', String(isOpen));
      toggle.setAttribute('aria-label', isOpen ? 'Close menu' : 'Open menu');
    });

    // Close on outside click
    document.addEventListener('click', function (e) {
      if (!toggle.contains(e.target) && !nav.contains(e.target)) {
        nav.classList.remove('is-open');
        if (actions) actions.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });
  }

  /* ── Contact form ─────────────────────────────────────────── */
  function initContactForm() {
    var form     = document.getElementById('contact-form');
    var feedback = document.getElementById('form-feedback');

    if (!form) return;

    form.addEventListener('submit', function (e) {
      e.preventDefault(); // Never actually sends — demo fixture

      // Simple inline validation
      var valid = true;
      var fields = form.querySelectorAll('[required]');
      fields.forEach(function (field) {
        field.style.borderColor = '';
        if (!field.value.trim()) {
          field.style.borderColor = 'var(--color-danger)';
          valid = false;
        }
      });

      if (!valid) return;

      // Simulate async submission
      var submitBtn = form.querySelector('[data-action="submit-contact"]');
      submitBtn.disabled = true;
      submitBtn.textContent = 'Sending…';

      setTimeout(function () {
        form.reset();
        submitBtn.disabled = false;
        submitBtn.textContent = 'Send message';
        if (feedback) {
          feedback.classList.add('is-visible');
          feedback.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
      }, 800);
    });

    // Clear validation state on input
    form.addEventListener('input', function (e) {
      if (e.target.style) e.target.style.borderColor = '';
    });
  }

  /* ── Smooth active nav link highlighting ─────────────────── */
  function initActiveNav() {
    var links = document.querySelectorAll('.site-nav__link');
    var path  = window.location.pathname.split('/').pop() || 'index.html';

    links.forEach(function (link) {
      var href = (link.getAttribute('href') || '').split('/').pop();
      if (href === path) {
        link.setAttribute('aria-current', 'page');
      }
    });
  }

  /* ── FAQ accordion (details/summary) ─────────────────────── */
  function initFaqAccordion() {
    var details = document.querySelectorAll('details.faq-item');
    details.forEach(function (det) {
      det.addEventListener('toggle', function () {
        if (det.open) {
          // Close siblings
          details.forEach(function (other) {
            if (other !== det && other.open) other.removeAttribute('open');
          });
        }
      });
    });
  }

  /* ── Pricing toggle (monthly/annual) ─────────────────────── */
  function initPricingToggle() {
    var toggle    = document.getElementById('billing-toggle');
    var pricesM   = document.querySelectorAll('[data-price-monthly]');
    var pricesA   = document.querySelectorAll('[data-price-annual]');
    var labelM    = document.getElementById('label-monthly');
    var labelA    = document.getElementById('label-annual');

    if (!toggle) return;

    toggle.addEventListener('change', function () {
      var isAnnual = toggle.checked;

      pricesM.forEach(function (el) {
        el.textContent = isAnnual
          ? el.getAttribute('data-price-annual')
          : el.getAttribute('data-price-monthly');
      });

      if (labelM) labelM.style.opacity = isAnnual ? '.5' : '1';
      if (labelA) labelA.style.opacity = isAnnual ? '1' : '.5';
    });
  }

  /* ── Scroll-reveal (lightweight, no IntersectionObserver polyfill needed) */
  function initScrollReveal() {
    if (!('IntersectionObserver' in window)) return;

    var items = document.querySelectorAll('[data-reveal]');
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-revealed');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12 });

    items.forEach(function (el) {
      el.style.opacity = '0';
      el.style.transform = 'translateY(20px)';
      el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
      observer.observe(el);
    });

    document.addEventListener('animationend', function () {}, { once: true });
  }

  // Apply revealed state
  document.addEventListener('animationend', function () {}, { passive: true });
  document.querySelectorAll('[data-reveal]');

  // Patch: apply reveal styles via class
  var style = document.createElement('style');
  style.textContent = '[data-reveal].is-revealed { opacity: 1 !important; transform: none !important; }';
  document.head.appendChild(style);

  /* ── Init ─────────────────────────────────────────────────── */
  document.addEventListener('DOMContentLoaded', function () {
    initMobileNav();
    initContactForm();
    initActiveNav();
    initFaqAccordion();
    initPricingToggle();
    initScrollReveal();
  });

})();
