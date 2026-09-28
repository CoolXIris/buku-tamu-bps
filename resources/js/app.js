import './bootstrap';

const modal = document.querySelector('[data-guest-modal]');
const purposeInput = document.querySelector('[data-purpose-input]');
const purposeOther = document.querySelector('[data-purpose-other]');
const purposeOtherInput = document.querySelector('[data-purpose-other-input]');
const occupationSelect = document.querySelector('[data-occupation]');
const occupationOther = document.querySelector('[data-occupation-other]');
const occupationOtherInput = document.querySelector('[data-occupation-other-input]');

function updatePurpose(purpose) {
	if (!purposeInput) return;
	purposeInput.value = purpose;
	const showOther = purpose === 'KEGIATAN';
	purposeOther?.classList.toggle('hidden', !showOther);
	if (purposeOtherInput) purposeOtherInput.required = showOther;
}

function openModal(purpose = purposeInput?.value ?? 'PST') {
	if (!modal) return;
	updatePurpose(purpose);
	modal.classList.remove('hidden');
	modal.setAttribute('aria-hidden', 'false');
	document.body.style.overflow = 'hidden';
	modal.querySelector('input[name="full_name"]')?.focus();
}

function closeModal() {
	if (!modal) return;
	modal.classList.add('hidden');
	modal.setAttribute('aria-hidden', 'true');
	document.body.style.overflow = '';
}

document.querySelectorAll('[data-open-form]').forEach((button) => {
	button.addEventListener('click', () => openModal(button.dataset.purpose));
});
document.querySelectorAll('[data-close-form]').forEach((button) => button.addEventListener('click', closeModal));
modal?.addEventListener('click', (event) => {
	if (event.target === modal) closeModal();
});
document.addEventListener('keydown', (event) => {
	if (event.key === 'Escape' && modal && !modal.classList.contains('hidden')) closeModal();
});
document.querySelectorAll('[data-visitor-tab]').forEach((tab) => {
	tab.addEventListener('click', () => {
		const selected = tab.dataset.visitorTab;
		document.querySelectorAll('[data-visitor-tab]').forEach((item) => {
			const isSelected = item === tab;
			item.classList.toggle('is-active', isSelected);
			item.setAttribute('aria-selected', String(isSelected));
		});
		document.querySelectorAll('[data-service-panel]').forEach((panel) => {
			panel.classList.toggle('hidden', panel.dataset.servicePanel !== selected);
		});
	});
});

purposeInput?.addEventListener('change', () => updatePurpose(purposeInput.value));
occupationSelect?.addEventListener('change', () => {
	const showOther = occupationSelect.value === 'LAINNYA';
	occupationOther?.classList.toggle('hidden', !showOther);
	if (occupationOtherInput) occupationOtherInput.required = showOther;
});
occupationSelect?.dispatchEvent(new Event('change'));
if (purposeInput) updatePurpose(purposeInput.value);
if (modal && modal.getAttribute('aria-hidden') === 'false') {
	document.body.style.overflow = 'hidden';
}
