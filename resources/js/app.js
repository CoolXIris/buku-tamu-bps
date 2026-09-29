import './bootstrap';
import Chart from 'chart.js/auto';

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
const toast = document.querySelector('[data-admin-toast]');
let toastTimer;

function showAdminToast(message, isError = false) {
	if (!toast) return;
	toast.textContent = message;
	toast.classList.toggle('is-error', isError);
	toast.classList.add('is-visible');
	window.clearTimeout(toastTimer);
	toastTimer = window.setTimeout(() => toast.classList.remove('is-visible'), 3200);
}

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

async function updateGuestStatus(row, status, announce = false) {
	const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
	const response = await fetch(row.dataset.statusUrl, {
		method: 'PATCH',
		headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
		body: JSON.stringify({
			service_status: status,
			...(announce ? { call_announcement: true } : {}),
		}),
	});
	const result = await response.json();
	if (!response.ok) throw new Error(result.message ?? 'Status tidak dapat diperbarui.');
	setRowStatus(row, result.service_status, result.status_label);
	showAdminToast(result.message);
	if (announce && 'speechSynthesis' in window) {
		window.speechSynthesis.cancel();
		const announcement = new SpeechSynthesisUtterance(`Nomor antrean ${result.queue_no.replace(/([A-Z]+)(\d+)/, '$1 $2')}, silakan menuju meja pelayanan.`);
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
	const callButton = event.target.closest('[data-call-guest]');
	if (callButton) {
		const row = callButton.closest('[data-guest-row]');
		callButton.disabled = true;
		try {
			await updateGuestStatus(row, 'serving', true);
		} catch (error) {
			showAdminToast(error.message, true);
		} finally {
			callButton.disabled = false;
		}
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
	const serviceList = queueScreen.querySelector('[data-service-list]');
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

	const formatQueueNumber = (number) => number?.replace(/^([A-Z]+)(\d+)$/, '$1-$2') ?? '--';
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
			makeText('span', 'current-service-label', `Loket: ${call.service_label} · ${call.service_name}`),
		);
		queueScreen.querySelector('[data-called-time]').textContent = `DIPANGGIL ${call.called_at ?? ''}`;
	}

	function renderServices(services) {
		serviceList.replaceChildren();
		services.forEach((service) => {
			const card = document.createElement('article');
			card.className = `queue-service-card queue-service-card--${service.code.toLowerCase()}`;
			card.append(makeText('span', 'queue-service-code', service.code === 'KEGIATAN' ? 'LAIN' : service.label));
			const description = document.createElement('div');
			description.append(makeText('strong', '', service.code === 'KEGIATAN' ? 'KEGIATAN LAINNYA' : service.label));
			description.append(makeText('small', '', service.name.toUpperCase()));
			card.append(description);
			const queueInfo = document.createElement('span');
			queueInfo.className = 'queue-service-next';
			queueInfo.append(makeText('strong', '', service.serving ? formatQueueNumber(service.serving.queue_no) : '—'));
			queueInfo.append(makeText('small', '', `${service.waiting_count} menunggu`));
			card.append(queueInfo);
			serviceList.append(card);
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
			const announcement = new SpeechSynthesisUtterance(`Nomor antrean ${call.queue_no.replace(/([A-Z]+)(\d+)/, '$1 $2')}, silakan menuju loket ${call.service_label}.`);
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
			renderServices(data.services);
			renderWaiting(data.waiting);
			if (data.latest_call?.service_status === 'serving' && !initialLoad && data.latest_call.event_id !== lastCallEventId) {
				playCallSound(data.latest_call);
				queueScreen.querySelector('[data-announcement]').textContent = `Nomor antrean ${formatQueueNumber(data.latest_call.queue_no)}, silakan menuju loket ${data.latest_call.service_label}.`;
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
	if (event.key === 'Escape' && detailModal && !detailModal.classList.contains('hidden')) {
		detailModal.classList.add('hidden');
		detailModal.setAttribute('aria-hidden', 'true');
		document.body.style.overflow = '';
	}
});
