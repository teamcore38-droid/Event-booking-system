const input = document.querySelector('input[name="cover"]');
const preview = document.querySelector('[data-cover-preview]');
const fallback = input.form.elements.art;
const remove = input.form.elements.remove_cover;
let objectUrl;
function refreshPreview() {
    if (objectUrl) URL.revokeObjectURL(objectUrl);
    const file = input.files[0];
    objectUrl = file ? URL.createObjectURL(file) : null;
    const current = preview.dataset.uploaded && !remove?.checked ? preview.dataset.uploaded : null;
    preview.src = objectUrl || current || ('assets/' + fallback.value + '.jpg');
}
input.addEventListener('change', () => {
    if (input.files.length && remove) remove.checked = false;
    refreshPreview();
});
fallback.addEventListener('change', refreshPreview);
remove?.addEventListener('change', () => {
    if (remove.checked) input.value = '';
    refreshPreview();
});