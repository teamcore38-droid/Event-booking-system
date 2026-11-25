const inventory = document.querySelector('[data-event-inventory]');
async function refreshInventory() {
  if (document.hidden) return;
  try {
    const response = await fetch('?p=availability&id=' + inventory.dataset.eventInventory, {cache:'no-store'});
    if (!response.ok) return;
    for (const category of await response.json()) {
      const panel = inventory.querySelector('[data-category="' + category.id + '"]');
      if (!panel) continue;
      panel.querySelector('[data-remaining]').textContent = category.remaining;
      const button = panel.querySelector('button');
      if (button && !category.remaining) {
        button.disabled = true;
        button.textContent = 'Booking closed';
      }
      const seats = panel.querySelector('select[name=seat]');
      if (seats) {
        const occupied = new Set(category.occupied.map(String));
        for (const option of seats.options) {
          if (occupied.has(option.value)) {
            if (option.selected) seats.value = '';
            option.disabled = true;
          }
        }
      }
    }
  } catch { /* The booking transaction also checks availability on submission. */ }
}
if (inventory) {
  setInterval(refreshInventory, 10000);
  document.addEventListener('visibilitychange', refreshInventory);
}