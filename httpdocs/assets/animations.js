
(function () {
  if (!window.gsap) {
    
    document.documentElement.classList.remove("js-anim");
    return;
  }

  if (window.ScrollTrigger) {
    gsap.registerPlugin(ScrollTrigger);
  }

  const SCROLL_DEFAULTS = {
    start: "top 88%",
    toggleActions: "play none none none",
  };

  function fadeUp(els, baseDelay) {
    if (!els.length) return;
    els.forEach((el) => {
      const delay = parseFloat(el.dataset.animDelay || baseDelay || 0) / 1000;
      gsap.fromTo(
        el,
        { y: 28, opacity: 0 },
        {
          y: 0,
          opacity: 1,
          duration: 0.8,
          ease: "power2.out",
          delay,
          scrollTrigger: { trigger: el, ...SCROLL_DEFAULTS },
        },
      );
    });
  }

  function fadeIn(els) {
    els.forEach((el) => {
      gsap.fromTo(
        el,
        { opacity: 0 },
        {
          opacity: 1,
          duration: 1,
          ease: "power2.out",
          scrollTrigger: { trigger: el, ...SCROLL_DEFAULTS },
        },
      );
    });
  }

  function scaleIn(els) {
    els.forEach((el) => {
      gsap.fromTo(
        el,
        { scale: 0.94, opacity: 0 },
        {
          scale: 1,
          opacity: 1,
          duration: 0.7,
          ease: "power2.out",
          scrollTrigger: { trigger: el, ...SCROLL_DEFAULTS },
        },
      );
    });
  }

  function stagger(containers) {
    containers.forEach((container) => {
      const items = container.children;
      if (!items.length) return;
      gsap.fromTo(
        items,
        { y: 24, opacity: 0 },
        {
          y: 0,
          opacity: 1,
          duration: 0.6,
          stagger: 0.07,
          ease: "power2.out",
          scrollTrigger: { trigger: container, ...SCROLL_DEFAULTS },
        },
      );
    });
  }

  
  function heroEntry() {
    const heroNodes = document.querySelectorAll('[data-anim-hero]');
    if (!heroNodes.length) return;
    const tl = gsap.timeline({ defaults: { ease: "power3.out" } });
    heroNodes.forEach((el, i) => {
      tl.fromTo(
        el,
        { y: 30, opacity: 0 },
        { y: 0, opacity: 1, duration: 0.9 },
        i === 0 ? 0 : "-=0.6",
      );
    });
  }

  
  function headerOnScroll() {
    const header = document.querySelector("[data-header]");
    if (!header) return;
    const update = () => {
      header.dataset.scrolled = window.scrollY > 8 ? "true" : "false";
    };
    update();
    window.addEventListener("scroll", update, { passive: true });
  }

  
  function gallopingHorse() {
    const band = document.querySelector("[data-galloping-band]");
    if (!band) return;
    const horse = band.querySelector("[data-galloping-horse]");
    const trail = band.querySelector("[data-galloping-trail]");
    if (!horse) return;

    let raf = 0;
    const update = () => {
      raf = 0;
      const rect = band.getBoundingClientRect();
      const vh = window.innerHeight;
      
      const total = vh + rect.height;
      const seen = vh - rect.top;
      const pct = Math.max(0, Math.min(1, seen / total));
      
      const left = -20 + pct * 135;
      horse.style.left = left + "%";
      if (trail) trail.style.left = Math.max(-30, left - 25) + "%";
    };
    const onScroll = () => {
      if (raf) return;
      raf = requestAnimationFrame(update);
    };
    update();
    window.addEventListener("scroll", onScroll, { passive: true });
    window.addEventListener("resize", onScroll);
  }

  function init() {
    fadeUp(document.querySelectorAll('[data-anim="fade-up"]'));
    fadeIn(document.querySelectorAll('[data-anim="fade-in"]'));
    scaleIn(document.querySelectorAll('[data-anim="scale-in"]'));
    stagger(document.querySelectorAll('[data-anim="stagger"]'));
    heroEntry();
    headerOnScroll();
    gallopingHorse();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
