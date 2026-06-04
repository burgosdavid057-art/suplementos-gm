/*
 * Carrito 100% en cliente. Persistido en localStorage.
 * Estructura: [{ id, name, price, image, qty }]
 *
 * API global:
 *   Cart.add(item)        agrega 1 unidad (o sube qty si ya estaba)
 *   Cart.set(id, qty)     fija cantidad (0 = remueve)
 *   Cart.remove(id)
 *   Cart.clear()
 *   Cart.items()          → array
 *   Cart.count()          → suma de qty
 *   Cart.subtotal()       → suma de price*qty
 */
(function () {
  const KEY = "sgm_cart_v1";

  function read() {
    try {
      return JSON.parse(localStorage.getItem(KEY) || "[]") || [];
    } catch (e) {
      return [];
    }
  }
  function write(items) {
    localStorage.setItem(KEY, JSON.stringify(items));
    refreshUI();
    document.dispatchEvent(new CustomEvent("cart:updated", { detail: items }));
  }

  function refreshUI() {
    const count = read().reduce((n, i) => n + (i.qty || 0), 0);
    document.querySelectorAll("#cart-count").forEach((el) => {
      el.textContent = String(count);
      el.classList.toggle("opacity-0", count === 0);
    });
  }

  window.Cart = {
    items: read,
    count() { return read().reduce((n, i) => n + (i.qty || 0), 0); },
    subtotal() { return read().reduce((n, i) => n + (i.price * i.qty), 0); },
    add(item) {
      const items = read();
      const existing = items.find((i) => i.id === item.id);
      if (existing) {
        existing.qty = Math.min(99, (existing.qty || 0) + (item.qty || 1));
      } else {
        items.push({
          id: item.id,
          name: item.name,
          price: Number(item.price) || 0,
          image: item.image || "",
          qty: Math.max(1, item.qty || 1),
        });
      }
      write(items);
    },
    set(id, qty) {
      const items = read();
      const idx = items.findIndex((i) => i.id === id);
      if (idx < 0) return;
      if (qty <= 0) items.splice(idx, 1);
      else items[idx].qty = Math.min(99, qty);
      write(items);
    },
    remove(id) { this.set(id, 0); },
    clear() { write([]); },
  };

  document.addEventListener("DOMContentLoaded", refreshUI);

  // Bind para botones [data-add-to-cart]
  document.addEventListener("click", (e) => {
    const btn = e.target.closest("[data-add-to-cart]");
    if (!btn) return;
    e.preventDefault();
    const item = {
      id: btn.dataset.id,
      name: btn.dataset.name,
      price: Number(btn.dataset.price),
      image: btn.dataset.image || "",
      qty: Number(btn.dataset.qty || 1),
    };
    if (!item.id) return;
    Cart.add(item);

    // Feedback visual
    const original = btn.textContent;
    btn.textContent = "✓ Añadido";
    btn.classList.add("bg-brand-700");
    setTimeout(() => {
      btn.textContent = original;
      btn.classList.remove("bg-brand-700");
    }, 1200);
  });
})();
