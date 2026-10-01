import './bootstrap';
import Chart from 'chart.js/auto';

const modal = document.querySelector('[data-guest-modal]');
const purposeInput = document.querySelector('[data-purpose-input]');
const purposeOther = document.querySelector('[data-purpose-other]');
const purposeOtherInput = document.querySelector('[data-purpose-other-input]');
const dtsenUpdateWrap = document.querySelector('[data-dtsen-update]');
const dtsenUpdateInput = document.querySelector('[data-dtsen-update-input]');
const occupationSelect = document.querySelector('[data-occupation]');
const occupationOther = document.querySelector('[data-occupation-other]');
const occupationOtherInput = document.querySelector('[data-occupation-other-input]');
const purposeLabel = document.querySelector('[data-purpose-label]');
const purposeLabels = {
	PST: 'Pelayanan Statistik Terpadu (PST)',
	LPSE: 'Layanan Pengadaan Secara Elektronik (LPSE)',
	PPID: 'Pejabat Pengelola Informasi dan Dokumentasi (PPID)',
	KEGIATAN: 'Kegiatan lainnya',
};

function updatePurpose(purpose) {
	if (!purposeInput) return;
	purposeInput.value = purpose;
	if (purposeLabel) purposeLabel.textContent = purposeLabels[purpose] ?? purpose;
	const showOther = purpose === 'KEGIATAN';
	purposeOther?.classList.toggle('hidden', !showOther);
	if (purposeOtherInput) purposeOtherInput.required = showOther;
	dtsenUpdateWrap?.classList.toggle('hidden', purpose !== 'PST');
	if (purpose !== 'PST' && dtsenUpdateInput) dtsenUpdateInput.checked = false;
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

const visitsChartCanvas = document.querySelector('[data-visits-chart]');
if (visitsChartCanvas && window.adminVisitsChart) {
	const chartContext = visitsChartCanvas.getContext('2d');
	const fill = chartContext.createLinearGradient(0, 0, 0, 280);
	fill.addColorStop(0, 'rgba(31, 137, 193, .24)');
	fill.addColorStop(1, 'rgba(31, 137, 193, .015)');

	new Chart(chartContext, {
		type: 'bar',
		data: {
			labels: window.adminVisitsChart.labels,
			datasets: [{
				label: 'Pengunjung',
				data: window.adminVisitsChart.values,
				backgroundColor: fill,
				borderColor: '#2488b7',
				borderWidth: 1,
				borderRadius: 3,
				borderSkipped: false,
				maxBarThickness: 24,
			}],
		},
		options: {
			maintainAspectRatio: false,
			responsive: true,
			plugins: {
				legend: { display: false },
				tooltip: {
					backgroundColor: '#102d63',
					padding: 11,
					displayColors: false,
					callbacks: { label: (context) => `${context.parsed.y} pengunjung` },
				},
			},
			scales: {
				x: {
					grid: { display: false },
					border: { display: false },
					ticks: { color: '#768396', font: { family: 'DM Sans', size: 10 }, maxRotation: 0, autoSkip: true },
				},
				y: {
					beginAtZero: true,
					grace: '15%',
					border: { display: false },
					grid: { color: '#e8edf2', drawTicks: false },
					ticks: { color: '#8994a2', precision: 0, padding: 10, font: { family: 'DM Sans', size: 10 } },
				},
			},
		},
	});
}

const guestFilterForm = document.querySelector('[data-guest-filters]');
guestFilterForm?.querySelectorAll('select').forEach((select) => {
	select.addEventListener('change', () => guestFilterForm.requestSubmit());
});

const detailModal = document.querySelector('[data-guest-detail-modal]');
const guestEditModal = document.querySelector('[data-guest-edit-modal]');
const guestEditForm = document.querySelector('[data-guest-edit-form]');
const editOccupationSelect = guestEditForm?.querySelector('[data-edit-occupation]');
const editPurposeSelect = guestEditForm?.querySelector('[data-edit-purpose]');
const callCounterModal = document.querySelector('[data-call-counter-modal]');
const callCounterForm = document.querySelector('[data-counter-form]');
const callCounterSelect = document.querySelector('[data-counter-select]');
const toast = document.querySelector('[data-admin-toast]');
let pendingCallRow = null;
let editingGuestRow = null;
let busyCounterAssignments = callCounterSelect ? JSON.parse(callCounterSelect.dataset.counterOccupants ?? '{}') : {};
let toastTimer;
const spokenQueuePrefixes = {
	PST: 'pe es te',
	LPSE: 'el pe es e',
	PPID: 'pe pe i de',
	UMUM: 'umum',
};

function formatQueueNumberForSpeech(queueNo) {
	const match = queueNo.match(/^([A-Z]+)(\d+)$/);
	if (!match) return queueNo;

	const prefix = spokenQueuePrefixes[match[1]] ?? match[1].split('').join(' ');
	return `${prefix} ${match[2]}`;
}

function showAdminToast(message, isError = false) {
	if (!toast) return;
	toast.textContent = message;
	toast.classList.toggle('is-error', isError);
	toast.classList.add('is-visible');
	window.clearTimeout(toastTimer);
	toastTimer = window.setTimeout(() => toast.classList.remove('is-visible'), 3200);
}

function updateGuestEditVisibility() {
	if (!guestEditForm) return;
	const occupationOther = guestEditForm.querySelector('[data-edit-occupation-other-wrap]');
	const occupationOtherInput = guestEditForm.querySelector('[data-edit-occupation-other]');
	const purposeOther = guestEditForm.querySelector('[data-edit-purpose-other-wrap]');
	const purposeOtherInput = guestEditForm.querySelector('[data-edit-purpose-other]');
	const dtsenWrap = guestEditForm.querySelector('[data-edit-dtsen-wrap]');
	const dtsenInput = guestEditForm.querySelector('[data-edit-dtsen]');
	const showOccupationOther = editOccupationSelect?.value === 'LAINNYA';
	const showPurposeOther = editPurposeSelect?.value === 'KEGIATAN';
	const showDtsen = editPurposeSelect?.value === 'PST';

	occupationOther?.classList.toggle('hidden', !showOccupationOther);
	if (occupationOtherInput) occupationOtherInput.required = showOccupationOther;
	purposeOther?.classList.toggle('hidden', !showPurposeOther);
	if (purposeOtherInput) purposeOtherInput.required = showPurposeOther;
	dtsenWrap?.classList.toggle('hidden', !showDtsen);
	if (!showDtsen && dtsenInput) dtsenInput.checked = false;
}

function closeGuestEditModal() {
	if (!guestEditModal) return;
	guestEditModal.classList.add('hidden');
	guestEditModal.setAttribute('aria-hidden', 'true');
	document.body.style.overflow = '';
	editingGuestRow = null;
}

guestEditForm?.addEventListener('change', updateGuestEditVisibility);
guestEditModal?.querySelectorAll('[data-close-edit]').forEach((button) => {
	button.addEventListener('click', closeGuestEditModal);
});
guestEditModal?.addEventListener('click', (event) => {
	if (event.target === guestEditModal) closeGuestEditModal();
});
guestEditForm?.addEventListener('submit', async (event) => {
	event.preventDefault();
	if (!editingGuestRow) return;
	const submitButton = guestEditForm.querySelector('[type="submit"]');
	if (submitButton) submitButton.disabled = true;
	try {
		const response = await fetch(editingGuestRow.dataset.updateUrl, {
			method: 'PATCH',
			headers: {
				Accept: 'application/json',
				'Content-Type': 'application/json',
				'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
			},
			body: JSON.stringify(Object.fromEntries(new FormData(guestEditForm))),
		});
		const result = await response.json();
		if (!response.ok) {
			const validationMessage = Object.values(result.errors ?? {}).flat()[0];
			throw new Error(validationMessage ?? result.message ?? 'Data tamu tidak dapat diperbarui.');
		}
		closeGuestEditModal();
		showAdminToast(result.message);
		window.setTimeout(() => window.location.reload(), 700);
	} catch (error) {
		showAdminToast(error.message, true);
	} finally {
		if (submitButton) submitButton.disabled = false;
	}
});

function openCallCounterModal(row) {
	if (!callCounterModal || !callCounterSelect) return;
	pendingCallRow = row;
	refreshCounterOptions();
	callCounterModal.querySelector('[data-counter-queue]').textContent = `Nomor antrean ${row.dataset.queueNo}`;
	callCounterModal.classList.remove('hidden');
	callCounterModal.setAttribute('aria-hidden', 'false');
	document.body.style.overflow = 'hidden';
	callCounterSelect.focus();
}

function closeCallCounterModal() {
	if (!callCounterModal) return;
	callCounterModal.classList.add('hidden');
	callCounterModal.setAttribute('aria-hidden', 'true');
	document.body.style.overflow = '';
	pendingCallRow = null;
	refreshCounterOptions();
}

function refreshCounterOptions() {
	if (!callCounterSelect) return;
	const currentEntryId = Number(pendingCallRow?.dataset.entryId ?? 0);
	for (const option of callCounterSelect.options) {
		if (!option.value) {
			option.disabled = true;
			continue;
		}
		const occupantId = Number(busyCounterAssignments[option.value] ?? 0);
		const isBusy = occupantId !== 0 && occupantId !== currentEntryId;
		option.disabled = isBusy;
		option.textContent = `Loket ${option.value}${isBusy ? ' (Sibuk)' : ''}`;
	}
	if (callCounterSelect.selectedOptions[0]?.disabled) {
		const firstAvailable = [...callCounterSelect.options].find((option) => !option.disabled);
		if (firstAvailable) callCounterSelect.value = firstAvailable.value;
	}
}

document.querySelectorAll('[data-close-counter-modal]').forEach((button) => {
	button.addEventListener('click', closeCallCounterModal);
});
callCounterModal?.addEventListener('click', (event) => {
	if (event.target === callCounterModal) closeCallCounterModal();
});
callCounterForm?.addEventListener('submit', async (event) => {
	event.preventDefault();
	const row = pendingCallRow;
	const submitButton = callCounterForm.querySelector('[type="submit"]');
	if (!row || !callCounterSelect || !submitButton) return;
	submitButton.disabled = true;
	try {
		await updateGuestStatus(row, 'serving', true, Number(callCounterSelect.value));
		closeCallCounterModal();
	} catch (error) {
		showAdminToast(error.message, true);
	} finally {
		submitButton.disabled = false;
	}
});

function setRowStatus(row, status, label) {
	const select = row.querySelector('[data-status-select]');
	const pill = row.querySelector('.status-pill');
	if (select) select.value = status;
	if (pill) {
		pill.className = `status-pill status-pill--${status}`;
		pill.innerHTML = `<i></i>${label}`;
	}
	const callButton = row.querySelector('[data-call-guest]');
	if (status === 'completed') {
		if (callButton) {
			const spacer = document.createElement('span');
			spacer.className = 'icon-action action-spacer';
			spacer.setAttribute('aria-hidden', 'true');
			callButton.replaceWith(spacer);
		}
	} else if (!callButton) {
		const spacer = row.querySelector('.action-spacer');
		const button = document.createElement('button');
		button.className = 'icon-action call-action';
		button.type = 'button';
		button.dataset.callGuest = '';
		button.title = `Panggil ${row.dataset.queueNo}`;
		button.setAttribute('aria-label', `Panggil nomor ${row.dataset.queueNo}`);
		button.innerHTML = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>';
		if (spacer) spacer.replaceWith(button);
		else row.querySelector('[data-status-select]')?.before(button);
	}
}

async function updateGuestStatus(row, status, announce = false, counterNumber = null) {
	const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
	const response = await fetch(row.dataset.statusUrl, {
		method: 'PATCH',
		headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
		body: JSON.stringify({
			service_status: status,
			...(announce ? { call_announcement: true } : {}),
			...(counterNumber ? { counter_number: counterNumber } : {}),
		}),
	});
	const result = await response.json();
	if (!response.ok) {
		const validationMessage = Object.values(result.errors ?? {}).flat()[0];
		throw new Error(validationMessage ?? result.message ?? 'Status tidak dapat diperbarui.');
	}
	if (result.busy_counters) {
		busyCounterAssignments = result.busy_counters;
		refreshCounterOptions();
	}
	setRowStatus(row, result.service_status, result.status_label);
	showAdminToast(result.message);
	if (announce && 'speechSynthesis' in window) {
		window.speechSynthesis.cancel();
		const announcement = new SpeechSynthesisUtterance(`Nomor antrean ${formatQueueNumberForSpeech(result.queue_no)}, silakan menuju loket ${result.counter_number}.`);
		announcement.lang = 'id-ID';
		window.speechSynthesis.speak(announcement);
	}
}

document.querySelectorAll('[data-status-select]').forEach((select) => {
	select.addEventListener('change', async () => {
		const row = select.closest('[data-guest-row]');
		select.disabled = true;
		try {
			await updateGuestStatus(row, select.value);
		} catch (error) {
			showAdminToast(error.message, true);
			window.location.reload();
		} finally {
			select.disabled = false;
		}
	});
});

document.addEventListener('click', async (event) => {
	const deleteButton = event.target.closest('[data-delete-guest]');
	if (deleteButton) {
		const row = deleteButton.closest('[data-guest-row]');
		if (!row || !window.confirm(`Hapus data tamu ${row.dataset.queueNo}? Tindakan ini tidak dapat dibatalkan.`)) return;
		deleteButton.disabled = true;
		try {
			const response = await fetch(row.dataset.deleteUrl, {
				method: 'DELETE',
				headers: {
					Accept: 'application/json',
					'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
				},
			});
			const result = await response.json();
			if (!response.ok) throw new Error(result.message ?? 'Data tamu tidak dapat dihapus.');
			showAdminToast(result.message);
			window.setTimeout(() => window.location.reload(), 700);
		} catch (error) {
			showAdminToast(error.message, true);
			deleteButton.disabled = false;
		}
		return;
	}

	const editButton = event.target.closest('[data-edit-guest]');
	if (editButton) {
		const row = editButton.closest('[data-guest-row]');
		editButton.disabled = true;
		try {
			const response = await fetch(row.dataset.detailUrl, { headers: { Accept: 'application/json' } });
			if (!response.ok) throw new Error('Data tamu tidak dapat dimuat.');
			const guest = await response.json();
			editingGuestRow = row;
			guestEditForm.elements.namedItem('full_name').value = guest.full_name ?? '';
			guestEditForm.elements.namedItem('gender').value = guest.gender ?? '';
			guestEditForm.elements.namedItem('institution').value = guest.institution ?? '';
			guestEditForm.elements.namedItem('phone').value = guest.phone ?? '';
			guestEditForm.elements.namedItem('email').value = guest.email ?? '';
			guestEditForm.elements.namedItem('occupation').value = guest.occupation_code ?? '';
			guestEditForm.elements.namedItem('occupation_other').value = guest.occupation_other ?? '';
			guestEditForm.elements.namedItem('purpose').value = guest.purpose_code ?? '';
			guestEditForm.elements.namedItem('purpose_other').value = guest.purpose_other ?? '';
			guestEditForm.elements.namedItem('dtsen_update').checked = Boolean(guest.dtsen_update_value);
			guestEditModal.querySelector('[data-edit-queue]').textContent = `${guest.queue_no} · ${guest.created_at}`;
			updateGuestEditVisibility();
			guestEditModal.classList.remove('hidden');
			guestEditModal.setAttribute('aria-hidden', 'false');
			document.body.style.overflow = 'hidden';
			guestEditForm.elements.namedItem('full_name').focus();
		} catch (error) {
			showAdminToast(error.message, true);
		} finally {
			editButton.disabled = false;
		}
		return;
	}

	const callButton = event.target.closest('[data-call-guest]');
	if (callButton) {
		const row = callButton.closest('[data-guest-row]');
		openCallCounterModal(row);
		return;
	}

	const detailButton = event.target.closest('[data-open-detail]');
	if (detailButton) {
		const row = detailButton.closest('[data-guest-row]');
		detailButton.disabled = true;
		try {
			const response = await fetch(row.dataset.detailUrl, { headers: { Accept: 'application/json' } });
			if (!response.ok) throw new Error('Detail tamu tidak dapat dimuat.');
			const guest = await response.json();
			detailModal.querySelector('[data-detail-queue]').textContent = `${guest.queue_no} · ${guest.created_at}`;
			detailModal.querySelectorAll('[data-detail]').forEach((field) => {
				field.textContent = guest[field.dataset.detail] || '—';
			});
			detailModal.querySelector('[data-detail-dtsen-wrap]').classList.toggle('hidden', guest.service_code !== 'PST');
			detailModal.querySelector('[data-detail-other-wrap]').classList.toggle('hidden', !guest.purpose_other);
			detailModal.querySelector('[data-detail-occupation-wrap]').classList.toggle('hidden', !guest.occupation_other);
			detailModal.classList.remove('hidden');
			detailModal.setAttribute('aria-hidden', 'false');
			document.body.style.overflow = 'hidden';
			detailModal.querySelector('[data-close-detail]')?.focus();
		} catch (error) {
			showAdminToast(error.message, true);
		} finally {
			detailButton.disabled = false;
		}
	}

	if (event.target.closest('[data-close-detail]') || event.target === detailModal) {
		detailModal?.classList.add('hidden');
		detailModal?.setAttribute('aria-hidden', 'true');
		document.body.style.overflow = '';
	}
});

const queueScreen = document.querySelector('[data-queue-screen]');
if (queueScreen) {
	const counterList = queueScreen.querySelector('[data-counter-list]');
	const waitingList = queueScreen.querySelector('[data-waiting-list]');
	const currentCall = queueScreen.querySelector('[data-current-call]');
	const soundButton = queueScreen.querySelector('[data-queue-sound]');
	const clock = queueScreen.querySelector('[data-queue-clock]');
	const date = queueScreen.querySelector('[data-queue-date]');
	let audioContext;
	let soundEnabled = false;
	let initialLoad = true;
	let lastCallEventId = null;
	let pollInProgress = false;

	const formatQueueNumber = (number) => number ?? '--';
	const makeText = (tagName, className, text) => {
		const element = document.createElement(tagName);
		element.className = className;
		element.textContent = text;
		return element;
	};

	function updateClock() {
		const now = new Date();
		clock.textContent = `${new Intl.DateTimeFormat('id-ID', { timeZone: 'Asia/Jakarta', hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23' }).format(now)} WIB`;
		date.textContent = new Intl.DateTimeFormat('id-ID', { timeZone: 'Asia/Jakarta', weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(now);
	}

	function renderCurrentCall(call) {
		currentCall.replaceChildren();
		if (!call || call.service_status !== 'serving') {
			currentCall.append(
				makeText('span', 'current-queue-number is-empty', '--'),
				makeText('h2', '', 'Menunggu panggilan berikutnya'),
				makeText('p', '', 'Silakan menunggu, nomor antrean akan tampil di sini.'),
			);
			queueScreen.querySelector('[data-called-time]').textContent = '';
			return;
		}

		currentCall.append(
			makeText('span', 'current-queue-number', formatQueueNumber(call.queue_no)),
			makeText('h2', '', call.full_name),
			makeText('p', 'current-call-institution', call.institution),
			makeText('span', 'current-service-label', call.counter_number ? `LOKET ${call.counter_number}` : 'LOKET BELUM DITENTUKAN'),
		);
		queueScreen.querySelector('[data-called-time]').textContent = `DIPANGGIL ${call.called_at ?? ''}`;
	}

	function renderCounters(counters) {
		counterList.replaceChildren();
		counters.forEach((counter) => {
			const card = document.createElement('article');
			card.className = 'queue-service-card queue-service-card--counter';
			card.append(makeText('span', 'queue-service-code', String(counter.number)));
			const description = document.createElement('div');
			description.append(makeText('strong', '', `LOKET ${counter.number}`));
			description.append(makeText('small', '', counter.serving ? 'SEDANG MELAYANI' : 'TERSEDIA'));
			card.append(description);
			const queueInfo = document.createElement('span');
			queueInfo.className = 'queue-service-next';
			queueInfo.append(makeText('strong', '', counter.serving ? formatQueueNumber(counter.serving.queue_no) : '—'));
			queueInfo.append(makeText('small', '', counter.serving?.service_label ?? 'Kosong'));
			card.append(queueInfo);
			counterList.append(card);
		});
	}

	function renderWaiting(waiting) {
		waitingList.replaceChildren();
		queueScreen.querySelector('[data-waiting-count]').textContent = `${waiting.length} tamu menunggu dipanggil`;
		if (!waiting.length) {
			waitingList.append(makeText('span', 'waiting-empty', 'Belum ada antrean menunggu'));
			return;
		}
		waiting.slice(0, 16).forEach((entry) => {
			const chip = document.createElement('span');
			chip.className = `waiting-queue-chip waiting-queue-chip--${entry.service_code.toLowerCase()}`;
			chip.append(makeText('strong', '', formatQueueNumber(entry.queue_no)));
			chip.append(makeText('small', '', entry.service_label));
			waitingList.append(chip);
		});
	}

	function playCallSound(call) {
		if (!soundEnabled || !audioContext) return;
		const now = audioContext.currentTime;
		[880, 660].forEach((frequency, index) => {
			const oscillator = audioContext.createOscillator();
			const gain = audioContext.createGain();
			oscillator.frequency.value = frequency;
			oscillator.type = 'sine';
			gain.gain.setValueAtTime(0.0001, now + index * 0.2);
			gain.gain.exponentialRampToValueAtTime(0.22, now + index * 0.2 + 0.025);
			gain.gain.exponentialRampToValueAtTime(0.0001, now + index * 0.2 + 0.16);
			oscillator.connect(gain);
			gain.connect(audioContext.destination);
			oscillator.start(now + index * 0.2);
			oscillator.stop(now + index * 0.2 + 0.18);
		});
		if ('speechSynthesis' in window) {
			window.speechSynthesis.cancel();
			const announcement = new SpeechSynthesisUtterance(`Nomor antrean ${formatQueueNumberForSpeech(call.queue_no)}, silakan menuju loket ${call.counter_number}.`);
			announcement.lang = 'id-ID';
			window.speechSynthesis.speak(announcement);
		}
	}

	soundButton.addEventListener('click', async () => {
		soundEnabled = !soundEnabled;
		if (soundEnabled) {
			const AudioContextClass = window.AudioContext || window.webkitAudioContext;
			if (AudioContextClass) {
				audioContext = new AudioContextClass();
				await audioContext.resume();
			}
		}
		soundButton.setAttribute('aria-pressed', String(soundEnabled));
		soundButton.querySelector('[data-sound-label]').textContent = soundEnabled ? 'Suara aktif' : 'Aktifkan suara';
		soundButton.classList.toggle('is-enabled', soundEnabled);
	});

	async function refreshQueue() {
		if (pollInProgress) return;
		pollInProgress = true;
		try {
			const response = await fetch(queueScreen.dataset.dataUrl, { headers: { Accept: 'application/json' }, cache: 'no-store' });
			if (!response.ok) throw new Error('Koneksi layar antrean terputus');
			const data = await response.json();
			queueScreen.querySelector('[data-live-indicator]').classList.add('is-online');
			queueScreen.querySelector('[data-live-label]').textContent = `TERHUBUNG · ${data.updated_at} WIB`;
			renderCurrentCall(data.latest_call);
			renderCounters(data.counters ?? []);
			renderWaiting(data.waiting);
			if (data.latest_call?.service_status === 'serving' && !initialLoad && data.latest_call.event_id !== lastCallEventId) {
				playCallSound(data.latest_call);
				queueScreen.querySelector('[data-announcement]').textContent = `Nomor antrean ${formatQueueNumber(data.latest_call.queue_no)}, silakan menuju loket ${data.latest_call.counter_number}.`;
			}
			lastCallEventId = data.latest_call?.event_id ?? null;
			initialLoad = false;
		} catch {
			queueScreen.querySelector('[data-live-indicator]').classList.remove('is-online');
			queueScreen.querySelector('[data-live-label]').textContent = 'MENCOBA MENYAMBUNG';
		} finally {
			pollInProgress = false;
		}
	}

	updateClock();
	window.setInterval(updateClock, 1000);
	refreshQueue();
	window.setInterval(refreshQueue, 2500);
}

document.addEventListener('keydown', (event) => {
	if (event.key === 'Escape' && callCounterModal && !callCounterModal.classList.contains('hidden')) {
		closeCallCounterModal();
	}
	if (event.key === 'Escape' && detailModal && !detailModal.classList.contains('hidden')) {
		detailModal.classList.add('hidden');
		detailModal.setAttribute('aria-hidden', 'true');
		document.body.style.overflow = '';
	}
});
