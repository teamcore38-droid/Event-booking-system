const button = document.getElementById('camera');
const form = button.closest('form');
const message = document.getElementById('status');
button.addEventListener('click', async () => {
  if (!form.querySelector('select').reportValidity()) return;
  button.disabled = true;
  try {
    const scanner = new Html5Qrcode('reader');
    let captured = false;
    await scanner.start({ facingMode: 'environment' }, { fps: 8, qrbox: 200 }, async value => {
      if (captured) return;
      captured = true;
      form.elements.code.value = value;
      await scanner.stop();
      message.textContent = 'Code captured. Confirm admission below.';
      button.disabled = false;
    });
    message.textContent = 'Point the camera at the ticket QR code.';
  } catch {
    message.textContent = 'Camera unavailable. Enter the code printed below the QR image.';
    button.disabled = false;
  }
});