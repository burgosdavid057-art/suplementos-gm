
(function () {
  
  function setupDropdowns() {
    document.querySelectorAll('[data-dropdown]').forEach((wrapper) => {
      const name = wrapper.dataset.dropdown;
      const btn = wrapper.querySelector('[data-dropdown-toggle]');
      const panel = document.querySelector(`[data-dropdown-panel="${name}"]`);
      const chevron = wrapper.querySelector('[data-chevron]');
      if (!btn || !panel) return;

      let open = false;
      const setOpen = (val) => {
        open = val;
        btn.setAttribute('aria-expanded', val ? 'true' : 'false');
        if (val) {
          panel.classList.remove('hidden');
          requestAnimationFrame(() => panel.dataset.open = 'true');
          if (chevron) chevron.style.transform = 'rotate(180deg)';
        } else {
          panel.dataset.open = 'false';
          if (chevron) chevron.style.transform = '';
          
          setTimeout(() => {
            if (!open) panel.classList.add('hidden');
          }, 180);
        }
      };

      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        setOpen(!open);
      });
      
      document.addEventListener('click', (e) => {
        if (open && !panel.contains(e.target) && !wrapper.contains(e.target)) {
          setOpen(false);
        }
      });
      
      document.addEventListener('keydown', (e) => {
        if (open && e.key === 'Escape') {
          setOpen(false);
          btn.focus();
        }
      });
    });
  }

  
  function setupMobileMenu() {
    const menu = document.querySelector('[data-mobile-menu]');
    if (!menu) return;
    const opens = document.querySelectorAll('[data-mobile-menu-open]');
    const closes = menu.querySelectorAll('[data-mobile-menu-close]');
    const overlay = menu.querySelector('[data-overlay]');
    const panel = menu.querySelector('[data-panel]');

    let isOpen = false;
    const setOpen = (val) => {
      isOpen = val;
      opens.forEach((b) => b.setAttribute('aria-expanded', val ? 'true' : 'false'));
      if (val) {
        menu.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        requestAnimationFrame(() => {
          if (overlay) overlay.style.opacity = '1';
          if (panel) panel.classList.remove('translate-x-full');
        });
      } else {
        if (overlay) overlay.style.opacity = '0';
        if (panel) panel.classList.add('translate-x-full');
        setTimeout(() => {
          if (!isOpen) {
            menu.classList.add('hidden');
            document.body.style.overflow = '';
          }
        }, 250);
      }
    };

    opens.forEach((b) => b.addEventListener('click', () => setOpen(true)));
    closes.forEach((b) => b.addEventListener('click', () => setOpen(false)));
    document.addEventListener('keydown', (e) => {
      if (isOpen && e.key === 'Escape') setOpen(false);
    });
  }

  
  function setupMobileSearch() {
    const toggle = document.querySelector('[data-mobile-search-toggle]');
    const panel = document.querySelector('[data-mobile-search]');
    if (!toggle || !panel) return;

    let open = false;
    toggle.addEventListener('click', () => {
      open = !open;
      panel.classList.toggle('hidden', !open);
      if (open) {
        const input = panel.querySelector('input[type="search"]');
        if (input) input.focus();
      }
    });
  }

  
  function setupHeaderScroll() {
    const header = document.querySelector('[data-header]');
    if (!header) return;
    let ticking = false;
    const update = () => {
      header.dataset.scrolled = window.scrollY > 8 ? 'true' : 'false';
      ticking = false;
    };
    window.addEventListener('scroll', () => {
      if (!ticking) {
        window.requestAnimationFrame(update);
        ticking = true;
      }
    }, { passive: true });
    update();
  }

  
  function setupCartBump() {
    const btn = document.getElementById('cart-button');
    if (!btn) return;
    document.addEventListener('cart:updated', () => {
      btn.style.transform = 'scale(1.06)';
      setTimeout(() => { btn.style.transform = ''; }, 180);
    });
  }

  function init() {
    setupDropdowns();
    setupMobileMenu();
    setupMobileSearch();
    setupHeaderScroll();
    setupCartBump();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
